import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import http from 'node:http';
import crypto from 'node:crypto';
import QRCode from 'qrcode';
import { Server as SocketIOServer } from 'socket.io';
import { SessionManager } from './services/session-manager.js';

const app = express();
const server = http.createServer(app);
const io = new SocketIOServer(server, { cors: { origin: true, credentials: true } });
const sessions = new SessionManager();
const PORT = Number(process.env.PORT || 3000);
const HOST = process.env.HOST || '127.0.0.1';
const CRM_BASE_URL = process.env.CRM_BASE_URL || 'http://127.0.0.1:8080';
const GATEWAY_SECRET = process.env.WHATSAPP_GATEWAY_SECRET || '';

app.use(cors());
app.use(express.json({ limit: '2mb' }));

function secureEqual(a, b) {
  const aa = Buffer.from(a || ''); const bb = Buffer.from(b || '');
  return aa.length === bb.length && crypto.timingSafeEqual(aa, bb);
}
function authenticate(req, res, next) {
  if (!GATEWAY_SECRET || !secureEqual(req.get('X-Gateway-Secret'), GATEWAY_SECRET)) return res.status(401).json({ success: false, message: 'Unauthorized' });
  next();
}

async function webhook(payload) {
  const secret = process.env.WHATSAPP_WEBHOOK_SECRET || '';
  const body = JSON.stringify(payload);
  const signature = crypto.createHmac('sha256', secret).update(body).digest('hex');
  const response = await fetch(`${CRM_BASE_URL}/api/webhooks/whatsapp`, {
    method: 'POST', headers: { 'content-type': 'application/json', 'X-WhatsApp-Gateway-Secret': secret, 'X-WhatsApp-Signature': signature }, body,
  });
  if (!response.ok) throw new Error(`CRM webhook failed: ${response.status}`);
}

const relay = (event, data) => {
  io.emit(`whatsapp.${event}`, data);
  webhook({ event, ...data }).catch(console.error);
};
sessions.on('qr', (data) => relay('qr', data));
sessions.on('connected', (data) => relay('connected', data));
sessions.on('disconnected', (data) => relay('disconnected', data));
sessions.on('logged_out', (data) => relay('logged_out', data));
sessions.on('messages.upsert', async ({ device, messages, type }) => {
  const event = type === 'notify' ? 'message.received' : 'message.history';
  for (const message of messages) {
    const data = { tenant_id: Number(device.tenant_id), device_id: Number(device.id), message };
    io.to(`tenant:${device.tenant_id}`).emit('whatsapp.message', { event, ...data });
    webhook({ event, ...data }).catch(console.error);
  }
});

app.get('/health', (_req, res) => res.json({ success: true, service: 'whatsapp-gateway', sessions: sessions.sessions.size }));
app.get('/api/whatsapp/devices/:id/qr', authenticate, async (req, res) => {
  const qr = sessions.getQr(req.params.id); if (!qr) return res.status(404).json({ success: false, message: 'QR not available' });
  res.json({ success: true, qr, dataUrl: await QRCode.toDataURL(qr) });
});
app.post('/api/whatsapp/devices/:id/connect', authenticate, async (req, res) => { try { await sessions.connect({ id: req.params.id, tenant_id: req.body.tenant_id }); res.json({ success: true }); } catch (e) { res.status(500).json({ success: false, message: e.message }); } });
app.post('/api/whatsapp/devices/:id/disconnect', authenticate, async (req, res) => { await sessions.disconnect(req.params.id); res.json({ success: true }); });
app.post('/api/whatsapp/devices/:id/logout', authenticate, async (req, res) => { await sessions.logout(req.params.id); res.json({ success: true }); });
app.post('/api/whatsapp/messages/send', authenticate, async (req, res) => { try { const result = await sessions.sendText(req.body.device_id, req.body.remote_jid, req.body.text); res.json({ success: true, message: result }); } catch (e) { res.status(422).json({ success: false, message: e.message }); } });

io.on('connection', (socket) => socket.on('tenant.join', (tenantId) => socket.join(`tenant:${tenantId}`)));
const shutdown = async () => { await sessions.shutdown(); server.close(() => process.exit(0)); };
process.on('SIGTERM', shutdown); process.on('SIGINT', shutdown);
server.listen(PORT, HOST, () => console.log(`WhatsApp gateway listening on http://${HOST}:${PORT}`));
