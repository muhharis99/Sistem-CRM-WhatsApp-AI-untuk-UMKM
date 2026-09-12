export function normalizePhone(input, defaultCountryCode = '62') {
  let value = String(input || '').trim().replace(/[\s().-]/g, '');
  if (value.startsWith('+')) value = value.slice(1);
  if (value.startsWith('00')) value = value.slice(2);
  if (value.startsWith('0')) value = defaultCountryCode + value.slice(1);
  if (!/^\d{8,15}$/.test(value)) throw new Error('Invalid phone number');
  return value;
}

export function jidToPhone(jid) {
  const local = String(jid || '').split('@')[0];
  if (!/^\d+$/.test(local)) return null;
  return local;
}
