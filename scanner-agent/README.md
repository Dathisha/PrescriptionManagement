# Prescription Scanner Agent

A lightweight local service that bridges the Prescription Management web application to Windows-connected physical scanners via the **Windows Image Acquisition (WIA)** API.

---

## How It Works

```
Physical Prescription
      │
      ▼
 Connected Scanner (USB/Network)
      │  WIA Driver
      ▼
 scan-wia.ps1  ◄── PowerShell WIA COM automation
      │
      ▼
 server.js  (Node.js HTTP, localhost:7854 only)
      │  base64 PNG
      ▼
 Angular Web App  ──► Preview ──► Confirm ──► Laravel API ──► MySQL
```

---

## Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| Windows | 10 / 11 | WIA is Windows-only |
| Node.js | 16+ | [Download from nodejs.org](https://nodejs.org) |
| Scanner | Any WIA-compatible device | Most USB/network scanners qualify |

---

## Setup (One-Time)

### 1. Install Node.js
Download and install from [https://nodejs.org](https://nodejs.org) (LTS version).

### 2. Connect Scanner
Connect your scanner via USB or ensure it is on the same network. Install the manufacturer's driver if prompted by Windows.

### 3. Verify Scanner in Windows
Open **Windows Fax and Scan** (`Start → Windows Fax and Scan`) and confirm your scanner appears and can scan a test page.

### 4. Place the `scanner-agent` Folder
The `scanner-agent` folder can stay in its current location inside the project, or be copied to a convenient location on the hospital computer (e.g. `C:\PrescriptionScanner\`).

---

## Starting the Agent

### Option A — Double-click (Recommended for staff)
Double-click **`start-agent.bat`**

This will:
- Check that Node.js is installed
- Install npm packages on first run (one-time, needs internet)
- Start the agent

### Option B — Command line
```cmd
cd scanner-agent
npm install
node server.js
```

The agent window must remain open while scanning. Minimise it — do **not** close it.

---

## Endpoints (localhost only)

| Method | URL | Description |
|---|---|---|
| GET | `http://localhost:7854/status` | Health check |
| GET | `http://localhost:7854/scanners` | List connected WIA scanners |
| POST | `http://localhost:7854/scan` | Trigger scan |

### POST /scan — Request body
```json
{ "deviceId": "optional-wia-device-id" }
```
If `deviceId` is omitted, the first available scanner is used.

### POST /scan — Response
```json
{
  "success": true,
  "mimeType": "image/png",
  "base64": "<base64-encoded-PNG>",
  "dataUrl": "data:image/png;base64,...",
  "filename": "scan_2026-09-10T04-00-00-000Z.png",
  "sizeBytes": 245760
}
```

---

## Troubleshooting

### "No scanner found"
- Ensure the scanner is connected and powered on.
- Open **Windows Fax and Scan** and verify the scanner is visible.
- Restart the scanner and try again.
- Some scanners require the manufacturer's WIA driver (not just the generic one).

### "PowerShell execution policy" error
Run this once in PowerShell as Administrator:
```powershell
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
```

### Scanner shows a dialog box
Some scanner drivers open their own scanning software. This is controlled by the scanner manufacturer. If this happens, use the driver software to perform the scan and save the file, then use the **"Select Scanned Document"** file-picker option in the web app instead.

### Port 7854 already in use
Edit `server.js` line 8 and change `const PORT = 7854;` to any free port. Update `scannerAgentUrl` in the Angular `environment.ts` file to match.

---

## Security

- The agent **only** listens on `127.0.0.1` (loopback). It is **not** accessible from any other computer on the network.
- No patient data passes through the agent. The agent only receives a device ID and returns a raw image.
- All patient association and storage is handled securely by the Laravel backend.
- Do not change `HOST = '127.0.0.1'` to `0.0.0.0` — doing so would expose the agent to the network.

---

## Deployment Notes

This agent is **not deployed to Hostinger** or any server. It runs only on the hospital computers where scanning is needed. The web application and backend are deployed to Hostinger independently.
