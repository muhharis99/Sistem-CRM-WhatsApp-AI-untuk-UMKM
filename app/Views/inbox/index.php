<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WhatsApp Inbox - CRM UMKM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html,body{height:100%} body{overflow:hidden}.app{height:100vh;display:grid;grid-template-columns:320px 1fr 300px}.sidebar,.customer-pane,.chat{height:100vh}.sidebar,.customer-pane{overflow:auto;border-right:1px solid var(--bs-border-color)}.chat{display:flex;flex-direction:column}.conversation{cursor:pointer}.conversation.active{background:var(--bs-secondary-bg)}.messages{flex:1;overflow:auto;background:var(--bs-tertiary-bg);padding:20px}.bubble{max-width:75%;padding:10px 14px;border-radius:16px;margin-bottom:10px}.incoming{background:var(--bs-body-bg)}.outgoing{background:#d9fdd3;margin-left:auto}.composer{border-top:1px solid var(--bs-border-color);padding:12px}.muted{font-size:.8rem;opacity:.7}
    </style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="p-3 border-bottom sticky-top bg-body">
            <div class="d-flex justify-content-between align-items-center"><strong>Inbox</strong><button id="refresh" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i></button></div>
            <input id="search" class="form-control form-control-sm mt-3" placeholder="Cari customer..."><select id="status" class="form-select form-select-sm mt-2"><option value="">Semua status</option><option>OPEN</option><option>PENDING</option><option>RESOLVED</option></select>
        </div>
        <div id="conversationList"></div>
    </aside>
    <main class="chat">
        <div id="chatHeader" class="p-3 border-bottom"><strong>Pilih percakapan</strong></div>
        <div id="messages" class="messages d-flex align-items-center justify-content-center text-secondary">Pilih percakapan untuk melihat pesan.</div>
        <div class="composer bg-body">
            <div class="input-group"><input id="messageInput" class="form-control" placeholder="Ketik balasan..." disabled><button id="send" class="btn btn-success" disabled>Kirim</button></div>
            <div class="muted mt-1">Pengiriman WhatsApp akan diteruskan ke gateway Baileys pada endpoint outgoing message.</div>
        </div>
    </main>
    <aside class="customer-pane p-3">
        <h6>Customer</h6><div id="customerInfo" class="text-secondary">Belum ada customer dipilih.</div>
    </aside>
</div>
<script src="https://cdn.socket.io/4.8.1/socket.io.min.js"></script>
<script>
const state={conversations:[],conversation:null};
const $=id=>document.getElementById(id);
async function api(url,options={}){const r=await fetch(url,{credentials:'same-origin',headers:{'Content-Type':'application/json',...(options.headers||{})},...options}); if(!r.ok) throw new Error((await r.json()).message||'Request gagal'); return r.json();}
async function loadConversations(){const q=new URLSearchParams(); if($('status').value) q.set('status',$('status').value); const res=await api('/api/inbox/conversations?'+q.toString()); state.conversations=res.data||[]; renderConversations();}
function renderConversations(){const s=$('search').value.toLowerCase(); $('conversationList').innerHTML=state.conversations.filter(c=>(c.customer_name||c.customer_phone||'').toLowerCase().includes(s)).map(c=>`<div class="conversation p-3 border-bottom ${state.conversation&&state.conversation.id===c.id?'active':''}" data-id="${c.id}"><div class="d-flex justify-content-between"><strong>${escapeHtml(c.customer_name||c.customer_phone)}</strong>${c.unread_count?`<span class="badge text-bg-success">${c.unread_count}</span>`:''}</div><div class="muted">${escapeHtml(c.customer_phone||'')}</div><div class="small text-truncate">Status: ${c.status}</div></div>`).join('')||'<div class="p-3 text-secondary">Belum ada conversation.</div>'; document.querySelectorAll('.conversation').forEach(x=>x.onclick=()=>openConversation(Number(x.dataset.id)))}
async function openConversation(id){const res=await api('/api/inbox/conversations/'+id+'/messages'); state.conversation=res.conversation; $('chatHeader').innerHTML=`<strong>${escapeHtml(state.conversation.customer_name||state.conversation.customer_phone||'Customer')}</strong><div class="muted">${escapeHtml(state.conversation.customer_phone||'')}</div>`; $('customerInfo').innerHTML=`<div><strong>${escapeHtml(state.conversation.customer_name||'-')}</strong></div><div>${escapeHtml(state.conversation.customer_phone||'-')}</div><div class="mt-2">Status: <b>${state.conversation.status}</b></div><div>AI: <b>${state.conversation.ai_enabled?'ON':'OFF'}</b></div>`; renderMessages(res.data||[]); $('messageInput').disabled=false; $('send').disabled=false; await api('/api/inbox/conversations/'+id+'/read',{method:'POST'}); loadConversations();}
function renderMessages(rows){$('messages').innerHTML=rows.length?rows.map(m=>`<div class="bubble ${m.direction==='INCOMING'?'incoming':'outgoing'}"><div>${escapeHtml(m.body||('['+m.message_type+']'))}</div><div class="muted mt-1">${new Date(m.timestamp.replace(' ','T')).toLocaleString()}</div></div>`).join(''):'<div class="text-secondary">Belum ada pesan.</div>'; $('messages').scrollTop=$('messages').scrollHeight;}
function escapeHtml(v){return String(v).replace(/[&<>'"]/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[x]))}
$('refresh').onclick=loadConversations;$('status').onchange=loadConversations;$('search').oninput=renderConversations;
const socket=typeof io!=='undefined'?io():null; if(socket){socket.on('connect',()=>socket.emit('tenant.join',window.TENANT_ID||null)); socket.on('whatsapp.message',d=>{if(state.conversation&&d.conversation_id===state.conversation.id)openConversation(state.conversation.id); else loadConversations();});}
loadConversations().catch(e=>{$('conversationList').innerHTML='<div class="p-3 text-danger">'+escapeHtml(e.message)+'</div>'});
</script>
</body>
</html>
