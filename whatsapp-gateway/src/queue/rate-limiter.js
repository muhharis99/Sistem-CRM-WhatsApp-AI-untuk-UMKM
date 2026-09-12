import { redis } from './connection.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export async function acquireCampaignSlot(campaignId, perMinute) {
  const limit = Math.max(1, Number(perMinute) || 1);
  while (true) {
    const bucket = Math.floor(Date.now() / 60000);
    const key = `crmwa:rate:${campaignId}:${bucket}`;
    const count = await redis.incr(key);
    if (count === 1) await redis.expire(key, 70);
    if (count <= limit) return;
    await redis.decr(key);
    const wait = 61000 - (Date.now() % 60000);
    await sleep(Math.min(Math.max(wait, 250), 61000));
  }
}
