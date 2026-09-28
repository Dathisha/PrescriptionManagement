/**
 * Prescription Scanner Agent — server.js
 *
 * A lightweight local HTTP server (localhost:7854 only) that bridges the
 * Angular web app to Windows WIA/TWAIN scanner drivers via PowerShell.
 *
 * Endpoints:
 *   GET  /status   — Health check
 *   GET  /scanners — List connected WIA scanners
 *   POST /scan     — Trigger a scan; returns scanned image as base64
 */

'use strict';

const express = require('express');
const cors    = require('cors');
const { spawn } = require('child_process');
const path    = require('path');
const fs      = require('fs');
const os      = require('os');

const app  = express();
const PORT = 7854;
const HOST = '127.0.0.1'; // Bind to loopback only — never expose to network

// ── CORS: allow only localhost origins ────────────────────────────
app.use(cors({
  origin: (origin, callback) => {
    // Allow same-origin requests (no origin header) or any localhost/127.0.0.1
    if (!origin ||
        origin.startsWith('http://localhost') ||
        origin.startsWith('http://127.0.0.1')) {
      callback(null, true);
    } else {
      callback(new Error(`CORS: origin not allowed — ${origin}`));
    }
  },
  methods: ['GET', 'POST', 'OPTIONS'],
  allowedHeaders: ['Content-Type'],
}));

app.use(express.json());

const PS_SCRIPT = path.join(__dirname, 'scan-wia.ps1');

// ── PowerShell runner ─────────────────────────────────────────────
/**
 * Execute scan-wia.ps1 with the given named parameters.
 * @param {Object} params  e.g. { Action: 'scan', DeviceId: '...', OutputPath: '...' }
 * @returns {Promise<string>} stdout of the script
 */
function runPowerShell(params = {}) {
  return new Promise((resolve, reject) => {
    // Build -Key Value argument pairs
    const args = [
      '-NoProfile',
      '-NonInteractive',
      '-ExecutionPolicy', 'Bypass',
      '-File', PS_SCRIPT,
    ];
    for (const [key, value] of Object.entries(params)) {
      args.push(`-${key}`, value);
    }

    const ps = spawn('powershell.exe', args, { windowsHide: true });

    let stdout = '';
    let stderr = '';
    ps.stdout.on('data', (d) => { stdout += d.toString(); });
    ps.stderr.on('data', (d) => { stderr += d.toString(); });

    ps.on('close', (code) => {
      if (code === 0) {
        resolve(stdout.trim());
      } else {
        // Extract the meaningful error from stderr (strip PowerShell noise)
        const msg = stderr
          .split('\n')
          .filter(l => l.trim() && !l.startsWith('    '))
          .map(l => l.replace(/^\s*\+ .+/, '').trim())
          .filter(Boolean)
          .join(' ')
          .replace(/Write-Error\s*:?\s*/i, '')
          .trim();
        reject(new Error(msg || `PowerShell exited with code ${code}`));
      }
    });

    ps.on('error', (err) => {
      reject(new Error(`Failed to start PowerShell: ${err.message}`));
    });
  });
}

// ── Routes ────────────────────────────────────────────────────────

/** GET /status — Health check for the Angular app to ping */
app.get('/status', (_req, res) => {
  res.json({
    status  : 'ok',
    version : '1.0.0',
    agent   : 'Prescription Scanner Agent',
    platform: process.platform,
    time    : new Date().toISOString(),
  });
});

/** GET /scanners — List all WIA scanners visible to Windows */
app.get('/scanners', async (_req, res) => {
  try {
    const raw = await runPowerShell({ Action: 'list' });

    let scanners = [];
    if (raw && raw !== '[]' && raw !== 'null') {
      try {
        const parsed = JSON.parse(raw);
        // ConvertTo-Json returns an object (not array) when there is only 1 item
        scanners = Array.isArray(parsed) ? parsed : [parsed];
      } catch {
        scanners = [];
      }
    }

    res.json({ scanners });
  } catch (err) {
    console.error('[/scanners]', err.message);
    res.status(500).json({ error: err.message, scanners: [] });
  }
});

/** POST /scan — Trigger scan and return the image as base64 */
app.post('/scan', async (req, res) => {
  // Generous timeout — scanning can take up to 60 s on slow hardware
  res.setTimeout(90_000);

  const deviceId  = (req.body && req.body.deviceId) ? String(req.body.deviceId).trim() : '';
  const tmpFile   = path.join(os.tmpdir(), `rx_scan_${Date.now()}.png`);

  try {
    const params = { Action: 'scan', OutputPath: tmpFile };
    if (deviceId) params.DeviceId = deviceId;

    await runPowerShell(params);

    if (!fs.existsSync(tmpFile)) {
      return res.status(500).json({ error: 'Scan completed but output file was not created.' });
    }

    const stat = fs.statSync(tmpFile);
    if (stat.size === 0) {
      fs.unlinkSync(tmpFile);
      return res.status(500).json({ error: 'Scanner returned an empty file. Please try again.' });
    }

    const imageBuffer = fs.readFileSync(tmpFile);
    const base64      = imageBuffer.toString('base64');
    const mimeType    = 'image/png';
    const filename    = `scan_${new Date().toISOString().replace(/[:.]/g, '-')}.png`;

    fs.unlinkSync(tmpFile); // Clean up temp file

    res.json({
      success : true,
      mimeType,
      base64,
      dataUrl  : `data:${mimeType};base64,${base64}`,
      filename,
      sizeBytes: stat.size,
    });

  } catch (err) {
    console.error('[/scan]', err.message);
    if (fs.existsSync(tmpFile)) {
      try { fs.unlinkSync(tmpFile); } catch {}
    }
    res.status(500).json({ error: err.message });
  }
});

// ── Start server ──────────────────────────────────────────────────
app.listen(PORT, HOST, () => {
  console.log('\n╔═══════════════════════════════════════════════╗');
  console.log('║    Prescription Management — Scanner Agent    ║');
  console.log('╚═══════════════════════════════════════════════╝');
  console.log(`\n  Listening on: http://${HOST}:${PORT}`);
  console.log('  Endpoints:');
  console.log(`    GET  http://${HOST}:${PORT}/status   — health check`);
  console.log(`    GET  http://${HOST}:${PORT}/scanners — list scanners`);
  console.log(`    POST http://${HOST}:${PORT}/scan     — trigger scan`);
  console.log('\n  Keep this window open while scanning prescriptions.');
  console.log('  Press Ctrl+C to stop.\n');
});
