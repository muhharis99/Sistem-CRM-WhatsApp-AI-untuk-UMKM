import 'dotenv/config';
import fs from 'node:fs';
import path from 'node:path';
import { EventEmitter } from 'node:events';
import makeWASocket, { DisconnectReason, useMultiFileAuthState } from '@whiskeysockets/baileys';

export class SessionManager extends EventEmitter {
  constructor() {
    super();
    this.sessions = new Map();
    this.root = path.resolve(process.env.SESSION_ROOT || './sessions');
    fs.mkdirSync(this.root, { recursive: true });
  }

  async connect(device) {
    const existing = this.sessions.get(String(device.id));
    if (existing?.socket) return existing.socket;

    const key = `${device.tenant_id}/${device.id}`;
    const authDir = path.join(this.root, key);
    fs.mkdirSync(authDir, { recursive: true });
    const { state, saveCreds } = await useMultiFileAuthState(authDir);

    const entry = { deviceId: String(device.id), tenantId: String(device.tenant_id), socket: null, stopped: false, reconnectTimer: null, reconnectAttempt: 0, qr: null };
    const socket = makeWASocket({ auth: state, printQRInTerminal: false });
    entry.socket = socket;
    this.sessions.set(String(device.id), entry);

    socket.ev.on('creds.update', saveCreds);
    socket.ev.on('connection.update', (update) => this.handleConnectionUpdate(entry, update));
    socket.ev.on('messages.upsert', ({ messages, type }) => this.emit('messages.upsert', { device, messages, type }));
    return socket;
  }

  async handleConnectionUpdate(entry, update) {
    if (update.qr) {
      entry.qr = update.qr;
      entry.reconnectAttempt = 0;
      this.emit('qr', { deviceId: entry.deviceId, tenantId: entry.tenantId, qr: update.qr });
    }
    if (update.connection === 'open') {
      entry.reconnectAttempt = 0;
      entry.qr = null;
      this.emit('connected', { deviceId: entry.deviceId, tenantId: entry.tenantId });
    }
    if (update.connection === 'close') {
      const statusCode = update.lastDisconnect?.error?.output?.statusCode;
      const loggedOut = statusCode === DisconnectReason.loggedOut;
      this.emit(loggedOut ? 'logged_out' : 'disconnected', { deviceId: entry.deviceId, tenantId: entry.tenantId, statusCode });
      entry.socket = null;
      if (!entry.stopped && !loggedOut) this.scheduleReconnect(entry, { statusCode });
    }
  }

  scheduleReconnect(entry, meta = {}) {
    if (entry.reconnectTimer) return;
    const delay = Math.min(30000, 1000 * (2 ** entry.reconnectAttempt++));
    entry.reconnectTimer = setTimeout(async () => {
      entry.reconnectTimer = null;
      try {
        await this.connect({ id: entry.deviceId, tenant_id: entry.tenantId });
      } catch (error) {
        this.emit('error', { deviceId: entry.deviceId, ...meta, error });
        this.scheduleReconnect(entry, meta);
      }
    }, delay);
  }

  get(deviceId) { return this.sessions.get(String(deviceId)) || null; }

  getQr(deviceId) { return this.get(deviceId)?.qr || null; }

  async disconnect(deviceId) {
    const entry = this.get(deviceId);
    if (!entry) return;
    entry.stopped = true;
    if (entry.reconnectTimer) clearTimeout(entry.reconnectTimer);
    entry.socket?.ws?.close();
    this.sessions.delete(String(deviceId));
    this.emit('disconnected', { deviceId: String(deviceId), tenantId: entry.tenantId });
  }

  async logout(deviceId) {
    const entry = this.get(deviceId);
    if (!entry?.socket) return;
    entry.stopped = true;
    await entry.socket.logout();
    this.sessions.delete(String(deviceId));
  }

  async sendText(deviceId, jid, text, quoted = undefined) {
    const entry = this.get(deviceId);
    if (!entry?.socket) throw new Error('WhatsApp device is not connected');
    return entry.socket.sendMessage(jid, { text }, quoted ? { quoted } : undefined);
  }

  async shutdown() {
    for (const [deviceId] of this.sessions) await this.disconnect(deviceId);
  }
}
