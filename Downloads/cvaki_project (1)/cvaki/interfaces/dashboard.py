"""
CVAKI Doctor — Web Dashboard
Real-time diagnostics, API key management, and chat interface.
Runs on a free localhost port (default 7799).
"""

import json
import asyncio
import threading
from datetime import datetime
from typing import Optional

try:
    from fastapi import FastAPI, WebSocket, WebSocketDisconnect, HTTPException
    from fastapi.responses import HTMLResponse, JSONResponse
    from fastapi.staticfiles import StaticFiles
    from fastapi.middleware.cors import CORSMiddleware
    import uvicorn
    FASTAPI_AVAILABLE = True
except ImportError:
    FASTAPI_AVAILABLE = False

from ..core.ai_engine import CVAKIEngine
from ..core.memory import MemoryStore


DASHBOARD_HTML = """<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>𝗖𝗩♞𝗞𝗜 Doctor</title>
<style>
  :root {
    --bg: #0d0d0d;
    --panel: #141414;
    --border: #1e1e1e;
    --orange: #ff6b00;
    --teal: #00bfa5;
    --white: #e8e8e8;
    --dim: #666;
    --danger: #e74c3c;
    --success: #27ae60;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: var(--bg); color: var(--white); font-family: 'Courier New', monospace; height: 100vh; display: flex; flex-direction: column; }

  /* Header */
  header { background: var(--panel); border-bottom: 1px solid var(--border); padding: 12px 20px; display: flex; align-items: center; gap: 16px; }
  .logo { color: var(--orange); font-size: 22px; font-weight: bold; letter-spacing: 2px; }
  .sub { color: var(--teal); font-size: 12px; }
  .status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success); animation: pulse 2s infinite; margin-left: auto; }
  .status-label { color: var(--dim); font-size: 12px; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

  /* Layout */
  .main { display: flex; flex: 1; overflow: hidden; }
  .sidebar { width: 240px; background: var(--panel); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 12px; gap: 8px; overflow-y: auto; }
  .content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

  /* Tabs */
  .tabs { display: flex; border-bottom: 1px solid var(--border); }
  .tab { padding: 10px 18px; cursor: pointer; color: var(--dim); font-size: 13px; border-bottom: 2px solid transparent; transition: .2s; }
  .tab.active { color: var(--orange); border-bottom-color: var(--orange); }
  .tab:hover { color: var(--white); }
  .tab-content { flex: 1; overflow-y: auto; padding: 16px; display: none; }
  .tab-content.active { display: flex; flex-direction: column; gap: 12px; }

  /* Sidebar items */
  .section-title { color: var(--dim); font-size: 10px; text-transform: uppercase; letter-spacing: 2px; padding: 4px 0; }
  .stat-card { background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 10px; }
  .stat-value { color: var(--orange); font-size: 20px; font-weight: bold; }
  .stat-label { color: var(--dim); font-size: 11px; }

  /* Chat */
  .chat-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; padding-bottom: 8px; }
  .msg { max-width: 85%; padding: 10px 14px; border-radius: 8px; font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
  .msg.user { background: #1a1f2e; border: 1px solid #2a3050; align-self: flex-end; color: var(--white); }
  .msg.ai { background: #0f1a18; border: 1px solid #0e3330; align-self: flex-start; color: var(--teal); }
  .msg-meta { font-size: 10px; color: var(--dim); margin-bottom: 2px; }
  .chat-input-row { display: flex; gap: 8px; padding-top: 8px; border-top: 1px solid var(--border); }
  .chat-input { flex: 1; background: var(--panel); border: 1px solid var(--border); border-radius: 6px; padding: 10px 14px; color: var(--white); font-family: 'Courier New', monospace; font-size: 13px; outline: none; }
  .chat-input:focus { border-color: var(--orange); }
  .send-btn { background: var(--orange); color: #000; border: none; border-radius: 6px; padding: 10px 18px; cursor: pointer; font-weight: bold; font-size: 13px; transition: .2s; }
  .send-btn:hover { background: #ff8c00; }

  /* Config / Diagnostics */
  .config-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--border); }
  .config-key { color: var(--teal); font-size: 12px; min-width: 160px; }
  .config-val { color: var(--white); font-size: 12px; flex: 1; }
  .input-field { background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 6px 10px; color: var(--white); font-family: 'Courier New', monospace; font-size: 12px; width: 100%; outline: none; }
  .input-field:focus { border-color: var(--orange); }
  .btn { padding: 6px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 12px; font-family: 'Courier New', monospace; }
  .btn-orange { background: var(--orange); color: #000; }
  .btn-teal { background: var(--teal); color: #000; }
  .btn-danger { background: var(--danger); color: #fff; }
  .card { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 14px; }
  .card h3 { color: var(--orange); font-size: 13px; margin-bottom: 10px; }
  pre { background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 10px; font-size: 12px; color: var(--teal); overflow-x: auto; white-space: pre-wrap; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; }
  .badge-ok { background: #0e3330; color: var(--teal); }
  .badge-warn { background: #2d1f00; color: var(--orange); }
</style>
</head>
<body>

<header>
  <div class="logo">𝗖𝗩♞𝗞𝗜</div>
  <div class="sub">BRAIN GODS DOCTOR · DASHBOARD</div>
  <span class="status-label">System Online</span>
  <div class="status-dot"></div>
</header>

<div class="main">
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="section-title">System</div>
    <div class="stat-card"><div class="stat-value" id="uptime-val">--</div><div class="stat-label">Uptime</div></div>
    <div class="stat-card"><div class="stat-value" id="msg-val">0</div><div class="stat-label">Messages</div></div>
    <div class="stat-card"><div class="stat-value" id="model-val" style="font-size:12px">llama-3.3</div><div class="stat-label">Active Model</div></div>

    <div class="section-title" style="margin-top:8px">Quick Actions</div>
    <button class="btn btn-orange" style="width:100%" onclick="sendCmd('/reset')">Reset Context</button>
    <button class="btn btn-teal" style="width:100%" onclick="switchTab('diagnostics')">Run Diagnostics</button>
    <button class="btn" style="width:100%;background:var(--border);color:var(--white)" onclick="switchTab('config')">API Settings</button>
  </div>

  <!-- Content area -->
  <div class="content">
    <div class="tabs">
      <div class="tab active" onclick="switchTab('chat')">💬 Chat</div>
      <div class="tab" onclick="switchTab('diagnostics')">🔬 Doctor</div>
      <div class="tab" onclick="switchTab('config')">⚙️ Config</div>
      <div class="tab" onclick="switchTab('log')">📋 Log</div>
    </div>

    <!-- Chat Tab -->
    <div class="tab-content active" id="tab-chat">
      <div class="chat-container" id="chat-messages"></div>
      <div class="chat-input-row">
        <input class="chat-input" id="chat-input" placeholder="Talk to 𝗖𝗩♞𝗞𝗜..." onkeydown="handleKey(event)"/>
        <button class="send-btn" onclick="sendMessage()">Send ❯</button>
      </div>
    </div>

    <!-- Diagnostics Tab -->
    <div class="tab-content" id="tab-diagnostics">
      <div class="card">
        <h3>🔬 System Diagnostics</h3>
        <div id="diag-content" style="color:var(--dim);font-size:12px">Click Run to start diagnostics...</div>
        <br><button class="btn btn-teal" onclick="runDiagnostics()">Run Diagnostics</button>
      </div>
      <div class="card">
        <h3>📊 API Status</h3>
        <div class="config-row"><span class="config-key">Groq API</span><span id="api-status" class="badge badge-ok">Connected</span></div>
        <div class="config-row"><span class="config-key">WhatsApp</span><span id="wa-status" class="badge badge-warn">Pending</span></div>
        <div class="config-row"><span class="config-key">Background Service</span><span class="badge badge-ok">Running</span></div>
      </div>
    </div>

    <!-- Config Tab -->
    <div class="tab-content" id="tab-config">
      <div class="card">
        <h3>🔑 API Key Management</h3>
        <div class="config-row">
          <span class="config-key">Groq API Key</span>
          <input class="input-field" id="api-key-input" type="password" placeholder="gsk_..."/>
          <button class="btn btn-orange" onclick="updateApiKey()">Update</button>
        </div>
        <div style="font-size:11px;color:var(--dim);margin-top:4px">Get your free key at console.groq.com</div>
      </div>
      <div class="card">
        <h3>📱 WhatsApp</h3>
        <div class="config-row">
          <span class="config-key">Your Number</span>
          <input class="input-field" id="wa-number-input" placeholder="+919876543210"/>
          <button class="btn btn-teal" onclick="updateWA()">Save</button>
        </div>
        <div style="margin-top:12px">
          <button class="btn btn-orange" onclick="startWABridge()">Start WhatsApp Bridge (QR)</button>
          <div id="qr-container" style="margin-top:10px;display:none">
            <pre id="qr-code">Loading QR code...</pre>
          </div>
        </div>
      </div>
    </div>

    <!-- Log Tab -->
    <div class="tab-content" id="tab-log">
      <div class="card">
        <h3>📋 Message History</h3>
        <div id="log-content"><pre style="color:var(--dim)">Loading...</pre></div>
        <br><button class="btn btn-danger" onclick="clearLog()">Clear History</button>
      </div>
    </div>
  </div>
</div>

<script>
let ws = null;
let msgCount = 0;
let startTime = Date.now();

function connectWS() {
  ws = new WebSocket(`ws://${location.host}/ws`);
  ws.onmessage = (e) => {
    const data = JSON.parse(e.data);
    if (data.type === 'chat') appendMsg('ai', data.content);
    if (data.type === 'qr') showQR(data.content);
    if (data.type === 'status') updateStatus(data);
  };
  ws.onclose = () => setTimeout(connectWS, 2000);
}

function appendMsg(role, text, meta = '') {
  const c = document.getElementById('chat-messages');
  const d = document.createElement('div');
  d.innerHTML = `<div class="msg-meta">${role === 'ai' ? '𝗖𝗩♞𝗞𝗜' : 'You'} · ${new Date().toLocaleTimeString()}</div>
    <div class="msg ${role}">${escHtml(text)}</div>`;
  c.appendChild(d);
  c.scrollTop = c.scrollHeight;
  msgCount++;
  document.getElementById('msg-val').textContent = msgCount;
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function sendMessage() {
  const inp = document.getElementById('chat-input');
  const msg = inp.value.trim();
  if (!msg) return;
  appendMsg('user', msg);
  inp.value = '';
  try {
    const res = await fetch('/api/chat', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:msg})});
    const data = await res.json();
    appendMsg('ai', data.response);
  } catch(e) {
    appendMsg('ai', '[Error connecting to CVAKI]');
  }
}

function sendCmd(cmd) {
  document.getElementById('chat-input').value = cmd;
  sendMessage();
}

function handleKey(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } }

function switchTab(name) {
  document.querySelectorAll('.tab,.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelector(`.tab[onclick*="${name}"]`).classList.add('active');
  document.getElementById(`tab-${name}`).classList.add('active');
  if (name === 'log') loadLog();
}

async function updateApiKey() {
  const key = document.getElementById('api-key-input').value.trim();
  if (!key) return;
  await fetch('/api/config', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:'groq_api_key',value:key})});
  document.getElementById('api-key-input').value = '';
  alert('API key updated!');
}

async function updateWA() {
  const num = document.getElementById('wa-number-input').value.trim();
  await fetch('/api/config', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({key:'whatsapp_number',value:num})});
  alert('WhatsApp number saved!');
}

async function runDiagnostics() {
  document.getElementById('diag-content').textContent = 'Running diagnostics...';
  try {
    const res = await fetch('/api/diagnostics');
    const data = await res.json();
    document.getElementById('diag-content').innerHTML = `<pre>${escHtml(JSON.stringify(data, null, 2))}</pre>`;
  } catch(e) {
    document.getElementById('diag-content').textContent = 'Error running diagnostics';
  }
}

function startWABridge() {
  document.getElementById('qr-container').style.display = 'block';
  document.getElementById('qr-code').textContent = 'Starting WhatsApp bridge...\\nRun: node whatsapp/bridge.js\\nThen scan the QR code with your WhatsApp.';
}

function showQR(qr) {
  document.getElementById('qr-container').style.display = 'block';
  document.getElementById('qr-code').textContent = qr;
}

async function loadLog() {
  const res = await fetch('/api/history');
  const data = await res.json();
  const html = data.history.map(m =>
    `<div class="config-row">
      <span class="config-key" style="color:${m.role==='user'?'var(--white)':'var(--teal)'}">${m.role}</span>
      <span class="config-val">${escHtml(m.content.substring(0,120))}${m.content.length>120?'...':''}</span>
      <span style="color:var(--dim);font-size:10px">${m.time||''}</span>
    </div>`
  ).join('');
  document.getElementById('log-content').innerHTML = html || '<div style="color:var(--dim)">No messages yet.</div>';
}

async function clearLog() {
  await fetch('/api/history', {method:'DELETE'});
  loadLog();
}

// Uptime counter
setInterval(() => {
  const s = Math.floor((Date.now()-startTime)/1000);
  document.getElementById('uptime-val').textContent = s>3600?`${Math.floor(s/3600)}h`:s>60?`${Math.floor(s/60)}m`:`${s}s`;
}, 1000);

connectWS();
</script>
</body>
</html>
"""


def create_app(engine: CVAKIEngine, memory: MemoryStore) -> "FastAPI":
    if not FASTAPI_AVAILABLE:
        raise ImportError("FastAPI not installed. Run: pip install fastapi uvicorn")

    app = FastAPI(title="CVAKI Doctor", version="1.0.0")
    app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])

    connected_ws = []

    @app.get("/", response_class=HTMLResponse)
    async def dashboard():
        return DASHBOARD_HTML

    @app.post("/api/chat")
    async def chat_endpoint(body: dict):
        message = body.get("message", "")
        if not message:
            raise HTTPException(400, "No message provided")
        response = engine.chat(message, stream=False)
        memory.log_message("dashboard", "user", message)
        memory.log_message("dashboard", "assistant", response)
        return {"response": response, "timestamp": datetime.utcnow().isoformat()}

    @app.get("/api/diagnostics")
    async def diagnostics():
        info = engine.shell.get_system_info()
        return {
            "status": "ok",
            "system": info,
            "api_key_set": memory.has_api_key(),
            "whatsapp_number": memory.get("whatsapp_number", "not set"),
            "model": engine.model,
            "history_count": len(memory.get_history("dashboard", limit=1000)),
            "timestamp": datetime.utcnow().isoformat()
        }

    @app.post("/api/config")
    async def set_config(body: dict):
        key = body.get("key", "")
        value = body.get("value", "")
        if not key:
            raise HTTPException(400, "Key required")
        memory.set(key, value)
        if key == "groq_api_key":
            import groq
            engine.client = groq.Groq(api_key=value)
        return {"status": "ok"}

    @app.get("/api/history")
    async def get_history(channel: str = "dashboard", limit: int = 100):
        return {"history": memory.get_history(channel, limit)}

    @app.delete("/api/history")
    async def clear_history():
        memory.clear_history()
        return {"status": "ok"}

    @app.websocket("/ws")
    async def websocket_endpoint(ws: WebSocket):
        await ws.accept()
        connected_ws.append(ws)
        try:
            while True:
                await ws.receive_text()
        except WebSocketDisconnect:
            connected_ws.remove(ws)

    return app


def start_dashboard(engine: CVAKIEngine, memory: MemoryStore, port: int = 7799):
    """Start the dashboard in blocking mode (call from thread)."""
    if not FASTAPI_AVAILABLE:
        print(f"\033[38;2;255;107;0m[CVAKI]\033[0m Dashboard unavailable — install: pip install fastapi uvicorn")
        return

    app = create_app(engine, memory)

    # Find free port
    import socket
    while port < 7900:
        try:
            s = socket.socket()
            s.bind(('', port))
            s.close()
            break
        except OSError:
            port += 1

    uvicorn.run(app, host="127.0.0.1", port=port, log_level="error")
