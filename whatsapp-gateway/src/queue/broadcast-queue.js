import { Queue } from 'bullmq';
import { QUEUE_PREFIX, redis } from './connection.js';

export const broadcastQueue = new Queue('broadcast', {
  connection: redis,
  prefix: QUEUE_PREFIX,
  defaultJobOptions: {
    attempts: 5,
    backoff: { type: 'exponential', delay: 5000 },
    removeOnComplete: { age: 86400, count: 10000 },
    removeOnFail: { age: 604800, count: 10000 },
  },
});

export async function enqueueCampaign({ tenant_id, campaign_id, device_id, recipient_ids, rate_limit_per_minute }) {
  const jobs = [];
  for (const recipient_id of recipient_ids) {
    jobs.push({
      name: `campaign:${campaign_id}:recipient:${recipient_id}`,
      data: { tenant_id, campaign_id, device_id, recipient_id, rate_limit_per_minute },
      opts: { jobId: `campaign:${campaign_id}:recipient:${recipient_id}` },
    });
  }
  if (jobs.length) await broadcastQueue.addBulk(jobs);
  return { queued: jobs.length };
}
