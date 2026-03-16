/**
 * CVAKI WhatsApp Bridge
 * Uses whatsapp-web.js to connect to WhatsApp Web via QR code.
 * Forwards messages to the Python CVAKI core and sends back responses.
 */

const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Config
const CVAKI_DIR = path.join(os.homedir(), '.cvaki');
const RESPONSE_FILE = path.join(CVAKI_DIR, 'wa_response.txt');
const MY_NUMBER_FILE = path.join(CVAKI_DIR, 'cvaki.db'); // Read via Python, bridge uses config file
const CONFIG_FILE = path.join(CVAKI_DIR, 'wa_config.json');

// Ensure CVAKI dir exists
if (!fs.existsSync(CVAKI_DIR)) {
    fs.mkdirSync(CVAKI_DIR, { recursive: true });
}

// Load config
let config = { myNumber: '', respondToAll: false };
if (fs.existsSync(CONFIG_FILE)) {
    try { config = { ...config, ...JSON.parse(fs.readFileSync(CONFIG_FILE, 'utf8')) }; } catch {}
}

// If myNumber not set, prompt for it
if (!config.myNumber) {
    const readline = require('readline');
    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    rl.question('Enter your WhatsApp number (e.g. +919876543210): ', (num) => {
        config.myNumber = num.trim().replace(/\s+/g, '').replace(/^\+/, '');
        fs.writeFileSync(CONFIG_FILE, JSON.stringify(config, null, 2));
        rl.close();
        startClient();
    });
} else {
    startClient();
}


function startClient() {
    console.log('\x1b[33m[CVAKI WA]\x1b[0m Initializing WhatsApp bridge...');
    console.log(`\x1b[36m[CVAKI WA]\x1b[0m Your number: ${config.myNumber}`);

    const client = new Client({
        authStrategy: new LocalAuth({ dataPath: path.join(CVAKI_DIR, 'wa_session') }),
        puppeteer: {
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
        }
    });

    // QR Code display
    client.on('qr', (qr) => {
        console.log('\x1b[33m[CVAKI WA]\x1b[0m Scan this QR code with your WhatsApp:');
        qrcode.generate(qr, { small: true });

        // Also save QR for web dashboard
        const qrFile = path.join(CVAKI_DIR, 'wa_qr.txt');
        fs.writeFileSync(qrFile, qr);
    });

    client.on('authenticated', () => {
        console.log('\x1b[32m[CVAKI WA]\x1b[0m WhatsApp authenticated!');
        const qrFile = path.join(CVAKI_DIR, 'wa_qr.txt');
        if (fs.existsSync(qrFile)) fs.unlinkSync(qrFile);
    });

    client.on('ready', () => {
        console.log('\x1b[32m[CVAKI WA]\x1b[0m ✓ WhatsApp bridge ready!');
        console.log('\x1b[36m[CVAKI WA]\x1b[0m Message yourself to talk to 𝗖𝗩♞𝗞𝗜');
    });

    client.on('disconnected', (reason) => {
        console.log(`\x1b[31m[CVAKI WA]\x1b[0m Disconnected: ${reason}. Reconnecting...`);
        setTimeout(startClient, 5000);
    });

    // Message handler
    client.on('message', async (msg) => {
        try {
            const chat = await msg.getChat();
            const sender = msg.from.replace('@c.us', '').replace(/\D/g, '');
            const myNum = config.myNumber.replace(/\D/g, '');
            const isFromMe = sender === myNum || msg.fromMe;

            // Only respond to messages from yourself
            if (!isFromMe && !config.respondToAll) return;

            const text = msg.body.trim();
            if (!text) return;

            console.log(`\x1b[36m[CVAKI WA]\x1b[0m Received: "${text.substring(0, 60)}..."`);

            // Show typing indicator
            await chat.sendStateTyping();

            // Forward to Python CVAKI via HTTP
            let response = '';
            try {
                const res = await fetch('http://localhost:7799/api/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: text }),
                });
                const data = await res.json();
                response = data.response || 'I processed your message.';
            } catch (err) {
                // Fallback: check response file (set by Python background service)
                await new Promise(r => setTimeout(r, 3000));
                if (fs.existsSync(RESPONSE_FILE)) {
                    response = fs.readFileSync(RESPONSE_FILE, 'utf8').trim();
                    fs.unlinkSync(RESPONSE_FILE);
                } else {
                    response = '⚠️ CVAKI core is offline. Start main.py first.';
                }
            }

            // Clear typing
            await chat.clearState();

            // Send response with CVAKI branding
            const formattedResponse = `𝗖𝗩♞𝗞𝗜\n─────────────\n${response}`;
            await msg.reply(formattedResponse);

            console.log(`\x1b[32m[CVAKI WA]\x1b[0m Replied: "${response.substring(0, 60)}..."`);

        } catch (err) {
            console.error(`\x1b[31m[CVAKI WA]\x1b[0m Error handling message:`, err.message);
        }
    });

    client.initialize();
}


// Graceful shutdown
process.on('SIGINT', () => {
    console.log('\n\x1b[33m[CVAKI WA]\x1b[0m Shutting down WhatsApp bridge...');
    process.exit(0);
});
