# 𝗖𝗩♞𝗞𝗜 — Brain Gods AI

```
    ██████╗██╗   ██╗ █████╗ ██╗  ██╗██╗
   ██╔════╝██║   ██║██╔══██╗██║ ██╔╝██║
   ██║     ██║   ██║███████║█████╔╝ ██║
   ██║     ╚██╗ ██╔╝██╔══██║██╔═██╗ ██║
   ╚██████╗ ╚████╔╝ ██║  ██║██║  ██╗██║
    ╚═════╝  ╚═══╝  ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝
              Brain Gods AI v1.0
```

An AI-controlled system shell powered by **Groq** (free & fast).  
Talk to your computer in plain English — CVAKI executes commands, finds files,
analyzes projects, controls your keyboard/mouse, and talks to you on WhatsApp.

---

## ✨ Features

| Feature | Description |
|---|---|
| **AI Shell** | Natural language → shell commands, auto-executes |
| **File Intelligence** | Search, read, and understand files across your system |
| **Project Analysis** | Load any codebase, auto-understand, find errors (retries to 99.8% confidence) |
| **WhatsApp** | Message yourself → CVAKI replies (QR code auth) |
| **CVAKI Doctor** | Live web dashboard: chat, config, diagnostics |
| **ML Brain** | PyTorch model controls keyboard/mouse for UI automation |
| **Background Service** | Always-on daemon, message queue, auto-healing |
| **Custom PS Prompt** | Fox-branded PowerShell prompt with orange/teal theme |

---

## 🚀 Quick Start

### Step 1 — Get a Free Groq API Key
> https://console.groq.com (free, instant, very fast)

### Step 2 — Install

**Windows (PowerShell as Admin):**
```powershell
git clone https://github.com/cvaki/cvaki.git
cd cvaki
.\setup.bat
```

**Linux / macOS:**
```bash
git clone https://github.com/cvaki/cvaki.git
cd cvaki
chmod +x setup.sh && ./setup.sh
```

### Step 3 — Run
```powershell
cvaki --setup     # First time: enter your Groq API key
cvaki             # Start the AI shell
```

---

## 💻 Terminal Interface

When you start CVAKI, your PowerShell prompt transforms:

```
π=[22:14:05]=(~/myproject)ꬹ[username]_{localhost:7799}
|_____[𝗖𝗩♞𝗞𝗜.ai]==>  ▌
```

Just type naturally:

```
|_____[𝗖𝗩♞𝗞𝗜.ai]==>  find my resume and show what's in it
|_____[𝗖𝗩♞𝗞𝗜.ai]==>  look at my project in ~/myapp and fix any import errors
|_____[𝗖𝗩♞𝗞𝗜.ai]==>  install pandas and create a CSV reader script
|_____[𝗖𝗩♞𝗞𝗜.ai]==>  what Python files are in my Downloads?
```

### Slash Commands
```
/help              Show all commands
/analyze [path]    Deep-analyze a project
/find <name>       Search files system-wide
/run <cmd>         Execute shell command directly
/tree [path]       Show directory tree
/cd <path>         Change directory
/sysinfo           System information
/key <apikey>      Update Groq API key live
/reset             Clear conversation context
/exit              Exit CVAKI
```

---

## 🌐 CVAKI Doctor (Web Dashboard)

Opens automatically at **http://localhost:7799**

- 💬 **Chat tab** — Talk to CVAKI from browser
- 🔬 **Doctor tab** — Live system diagnostics
- ⚙️ **Config tab** — Change API key, WhatsApp number, start QR bridge
- 📋 **Log tab** — Full message history

---

## 📱 WhatsApp Integration

1. Make sure CVAKI's Python core is running
2. Install Node.js (https://nodejs.org)
3. Run the bridge:
   ```bash
   cd whatsapp
   npm install
   node bridge.js
   ```
4. Scan the QR code with your WhatsApp
5. Message **yourself** — CVAKI reads and replies

```
You → yourself:  "Find all PDF files on my computer"
𝗖𝗩♞𝗞𝗜 replies: "Found 12 PDF files:
                  • ~/Documents/Resume.pdf (245KB)
                  • ~/Downloads/Invoice.pdf (89KB)
                  ..."
```

---

## 🤖 ML Brain (Hardware Control)

Uses PyTorch + pyautogui to control keyboard and mouse.

```
|_____[𝗖𝗩♞𝗞𝗜.ai]==>  click the Start button and open Notepad
|_____[𝗖𝗩♞𝗞𝗜.ai]==>  take a screenshot and describe what you see
|_____[𝗖𝗩♞𝗞𝗜.ai]==>  type "Hello World" in the active window
```

**Safety**: Move mouse to any corner to abort all automation (pyautogui failsafe).

---

## 📂 Project Structure

```
cvaki/
├── main.py                     # Entry point
├── requirements.txt            # Python dependencies
├── setup.bat                   # Windows setup
├── setup.sh                    # Linux/macOS setup
│
├── core/
│   ├── ai_engine.py            # Groq AI + tool dispatch
│   ├── shell_agent.py          # Command execution (safe)
│   ├── file_agent.py           # File search / read / write
│   ├── project_analyst.py      # Codebase deep analysis
│   └── memory.py               # SQLite persistence
│
├── interfaces/
│   ├── terminal.py             # Interactive PowerShell UI
│   └── dashboard.py            # FastAPI web dashboard
│
├── services/
│   └── background.py           # Always-on daemon
│
├── whatsapp/
│   ├── bridge.js               # Node.js WhatsApp bridge
│   └── package.json
│
├── ml_brain/
│   └── brain.py                # PyTorch hardware controller
│
└── powershell/
    └── Microsoft.PowerShell_profile.ps1  # PS profile
```

---

## ⚙️ Configuration

All config lives in `~/.cvaki/cvaki.db` (SQLite).

| Key | Description |
|---|---|
| `groq_api_key` | Your Groq API key |
| `whatsapp_number` | Your WhatsApp number (e.g. +919876543210) |

Change live via:
- Terminal: `/key <new_key>`
- Dashboard: Settings tab
- Code: `memory.set("groq_api_key", "new_key")`

---

## 🔒 Safety

- **Dangerous commands** are blocked automatically (`rm -rf`, `format`, `shutdown`, etc.)
- **pyautogui failsafe**: move mouse to any screen corner to abort
- **All AI tool calls** are logged to `~/.cvaki/cvaki.db`
- CVAKI never sends your files anywhere — Groq only receives the text you type

---

## 🧰 Requirements

**Python 3.10+**  
**Node.js 18+** (WhatsApp bridge only)

Key Python packages:
- `groq` — AI backbone
- `fastapi` + `uvicorn` — Web dashboard
- `torch` — ML brain
- `pyautogui` — Hardware control
- `rich` — Terminal UI

---

## 🤝 Credits

Built by **CVAKI Brain Gods**  
Powered by **Groq** (ultra-fast LLaMA inference)  
Logo: the Fox — sharp, fast, intelligent

---

*"The fox knows many things, but the hedgehog knows one big thing.  
CVAKI knows everything."*
