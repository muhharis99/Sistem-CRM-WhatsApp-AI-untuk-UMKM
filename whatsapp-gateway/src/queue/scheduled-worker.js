import 'dotenv/config';
import axios from 'axios';

const CRM_BASE_URL=(process.env.CRM_BASE_URL||'http://127.0.0.1:6060').replace(/\/$/,'');
const GATEWAY_URL=(process.env.GATEWAY_URL||'http://127.0.0.1:3000').replace(/\/$/,'');
const SECRET=process.env.QUEUE_INTERNAL_SECRET||'';
const GATEWAY_SECRET=process.env.WHATSAPP_GATEWAY_SECRET||'';
const POLL_MS=Math.max(1000,Number(process.env.SCHEDULED_POLL_MS||5000));

const headers={'Content-Type':'application/json','X-Queue-Internal-Secret':SECRET};
const gatewayHeaders={'Content-Type':'application/json','X-Gateway-Secret':GATEWAY_SECRET};
const sleep=(ms)=>new Promise(r=>setTimeout(r,ms));
function jid(phone){let v=String(phone||'').replace(/\D/g,'');if(v.startsWith('0'))v=`62${v.slice(1)}`;if(v.startsWith('8'))v=`62${v}`;if(!v)throw new Error('Nomor WhatsApp tidak valid');return `${v}@s.whatsapp.net`;}
async function postCrm(path,data){const r=await axios.post(`${CRM_BASE_URL}${path}`,data,{timeout:20000,headers,validateStatus:()=>true});if(r.status>=400||!r.data?.success)throw new Error(r.data?.message||`CRM HTTP ${r.status}`);return r.data.data;}
async function send(item){const r=await axios.post(`${GATEWAY_URL}/api/whatsapp/messages/send`,{tenant_id:item.tenant_id,device_id:item.device_id,remote_jid:jid(item.phone),text:item.body},{timeout:20000,headers:gatewayHeaders,validateStatus:()=>true});if(r.status>=400||!r.data?.success)throw new Error(r.data?.message||`Gateway HTTP ${r.status}`);return r.data.message;}

async function tick(){
  const items=await postCrm('/api/internal/scheduled-messages/claim',{limit:25});
  for(const item of items||[]){
    try{const result=await send(item);await postCrm(`/api/internal/scheduled-messages/${item.id}/complete`,{status:'SENT',message_id:result?.key?.id||null,attempts:item.attempts});}
    catch(error){const retry=(item.attempts||0)<(item.max_attempts||3);await postCrm(`/api/internal/scheduled-messages/${item.id}/complete`,{status:retry?'PENDING':'FAILED',error_message:error.message,attempts:item.attempts}).catch(()=>null);}
  }
}

console.log('Scheduled message worker running');
while(true){try{await tick();}catch(error){console.error('[scheduled] tick failed',error.message);}await sleep(POLL_MS);}
