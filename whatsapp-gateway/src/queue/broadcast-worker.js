import 'dotenv/config';
import axios from 'axios';
import { Worker } from 'bullmq';
import { QUEUE_PREFIX, redis } from './connection.js';
import { acquireCampaignSlot } from './rate-limiter.js';

const CRM_BASE_URL = (process.env.CRM_BASE_URL || 'http://127.0.0.1:8080').replace(/\/$/, '');
const QUEUE_INTERNAL_SECRET = process.env.QUEUE_INTERNAL_SECRET || '';
const GATEWAY_SECRET = process.env.WHATSAPP_GATEWAY_SECRET || '';
const CONCURRENCY = Math.max(1, Number(process.env.WORKER_CONCURRENCY || 5));
const MAX_ATTEMPTS = 5;

function normalizeJid(phone) {
  let value = String(phone || '').replace(/\D/g, '');
  if (value.startsWith('0')) value = `62${value.slice(1)}`;
  if (value.startsWith('8')) value = `62${value}`;
  if (!value) throw new Error('Nomor WhatsApp kosong atau tidak valid.');
  return `${value}@s.whatsapp.net`;
}

async function crm(path, data) {
  const response = await axios.post(`${CRM_BASE_URL}${path}`, data, {
    timeout: 20000,
    headers: { 'Content-Type': 'application/json', 'X-Queue-Internal-Secret': QUEUE_INTERNAL_SECRET },
    validateStatus: () => true,
  });
  if (response.status >= 400 || !response.data?.success) throw new Error(response.data?.messages?.error || response.data?.message || `CRM HTTP ${response.status}`);
  return response.data.data;
}

async function sendText(deviceId, remoteJid, text, tenantId) {
  const response = await axios.post(`${CRM_BASE_URL.replace(/:\d+$/, `:${process.env.PORT || 3000`)}/api/whatsapp/messages/send`, {
    tenant_id: tenantId, device_id: deviceId, remote_jid: remoteJid, text,
  }, { timeout: 20000, headers: { 'Content-Type': 'application/json', 'X-Gateway-Secret': GATEWAY_SECRET }, validateStatus: () => true });
  if (response.status >= 400 || !response.data?.success) throw new Error(response.data?.message || `Gateway HTTP ${response.status}`);
  return response.data.message;
}

const worker = new Worker('broadcast', async (job) => {
  const { tenant_id, recipient_id, campaign_id, rate_limit_per_minute } = job.data;
  const claim = await crm(`/api/internal/campaign-recipients/${recipient_id}/claim`, { tenant_id, campaign_id });
  if (!claim?.claimed) {
    if (claim?.reason === 'OPTED_OUT' || claim?.reason === 'ALREADY_PROCESSED') return claim;
    throw new Error(`Recipient tidak dapat di-claim: ${claim?.reason || 'UNKNOWN'}`);
  }
  const recipient = claim.recipient;
  await acquireCampaignSlot(campaign_id, rate_limit_per_minute);
  try {
    const result = await sendText(recipient.device_id, normalizeJid(recipient.phone), recipient.rendered_body, tenant_id);
    await crm(`/api/internal/campaign-recipients/${recipient_id}/complete`, {
      tenant_id, status: 'SENT', message_id: result?.key?.id || result?.key?.id || null, attempts: recipient.attempts,
    });
    return { success: true, recipient_id, message_id: result?.key?.id || null };
  } catch (error) {
    const retryable = job.attemptsMade + 1 < MAX_ATTEMPTS;
    await crm(`/api/internal/campaign-recipients/${recipient_id}/complete`, {
      tenant_id, status: retryable ? 'PENDING' : 'FAILED', error_message: error.message, attempts: recipient.attempts,
    }).catch(() => null);
    if (retryable) throw error;
    return { success: false, recipient_id, status: 'FAILED', error: error.message };
  }
}, { connection: redis, prefix: QUEUE_PREFIX, concurrency: CONCURRENCY });

worker.on('completed', (job) => console.log(`[broadcast] completed ${job.name}`));
worker.on('failed', (job, error) => console.error(`[broadcast] failed ${job?.name}: ${error.message}`));
worker.on('error', (error) => console.error('[broadcast] worker error', error));

console.log(`Broadcast worker running with concurrency=${CONCURRENCY}`);
