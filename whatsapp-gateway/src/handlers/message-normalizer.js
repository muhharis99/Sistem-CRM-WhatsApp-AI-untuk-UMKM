import { jidToPhone } from '../utils/phone.js';

export function normalizeBaileysMessage(message) {
  const key = message?.key || {};
  const content = message?.message || {};
  const remoteJid = key.remoteJid || '';
  const messageType = content.conversation ? 'text'
    : content.extendedTextMessage ? 'text'
    : content.imageMessage ? 'image'
    : content.videoMessage ? 'video'
    : content.audioMessage ? 'audio'
    : content.documentMessage ? 'document'
    : content.stickerMessage ? 'sticker'
    : content.locationMessage ? 'location'
    : content.contactMessage ? 'contact'
    : content.reactionMessage ? 'reaction'
    : content.pollCreationMessage ? 'poll'
    : 'unknown';
  const body = content.conversation
    || content.extendedTextMessage?.text
    || content.imageMessage?.caption
    || content.videoMessage?.caption
    || content.documentMessage?.caption
    || null;
  return {
    message_id: key.id || null,
    remote_jid: remoteJid,
    chat_type: remoteJid.endsWith('@g.us') ? 'GROUP' : 'INDIVIDUAL',
    phone: jidToPhone(remoteJid),
    push_name: message?.pushName || null,
    direction: key.fromMe ? 'OUTGOING' : 'INCOMING',
    message_type: messageType,
    body,
    timestamp: message?.messageTimestamp ? new Date(Number(message.messageTimestamp) * 1000).toISOString() : new Date().toISOString(),
    quoted_message_id: content.extendedTextMessage?.contextInfo?.stanzaId || null,
  };
}
