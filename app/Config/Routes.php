<?php

namespace Config;

use CodeIgniter\Routing\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('/health', 'Health::index');
$routes->post('/api/auth/login', 'Auth::login');
$routes->post('/api/auth/logout', 'Auth::logout');
$routes->get('/api/me', 'Auth::me');
$routes->get('/api/whatsapp/devices', 'WhatsAppDevices::index');
$routes->post('/api/whatsapp/devices', 'WhatsAppDevices::create');
$routes->post('/api/webhooks/whatsapp', 'WhatsAppWebhook::receive');
$routes->get('/api/customers', 'Customers::index');
$routes->get('/api/customers/(:num)', 'Customers::show/$1');
$routes->post('/api/customers', 'Customers::create');
$routes->put('/api/customers/(:num)', 'Customers::update/$1');
$routes->get('/api/tags', 'Tags::index');
$routes->post('/api/tags', 'Tags::create');
$routes->post('/api/customers/(:num)/tags/(:num)', 'Tags::attach/$1/$2');
$routes->delete('/api/customers/(:num)/tags/(:num)', 'Tags::detach/$1/$2');
$routes->get('/api/pipelines', 'Pipelines::index');
$routes->post('/api/pipelines', 'Pipelines::create');
$routes->get('/api/deals', 'Pipelines::deals');
$routes->post('/api/deals', 'Pipelines::createDeal');
$routes->get('/api/inbox/conversations', 'Inbox::conversations');
$routes->get('/api/inbox/conversations/(:num)/messages', 'Inbox::messages/$1');
$routes->post('/api/inbox/conversations/(:num)/read', 'Inbox::markRead/$1');
$routes->patch('/api/inbox/conversations/(:num)', 'Inbox::updateConversation/$1');
$routes->post('/api/inbox/conversations/(:num)/send', 'Inbox::send/$1');
$routes->get('/api/ai/knowledge', 'AI::knowledge');
$routes->post('/api/ai/knowledge', 'AI::knowledge');
$routes->post('/api/ai/conversations/(:num)/suggest', 'AI::suggest/$1');
$routes->post('/api/ai/conversations/(:num)/analyze', 'AI::analyze/$1');
$routes->patch('/api/ai/conversations/(:num)/handoff', 'AI::toggleHandoff/$1');
$routes->get('/api/templates', 'Templates::index');
$routes->post('/api/templates', 'Templates::create');
$routes->put('/api/templates/(:num)', 'Templates::update/$1');
$routes->delete('/api/templates/(:num)', 'Templates::delete/$1');
$routes->get('/api/campaigns', 'Campaigns::index');
$routes->post('/api/campaigns', 'Campaigns::create');
$routes->post('/api/campaigns/(:num)/preview', 'Campaigns::preview/$1');
$routes->post('/api/campaigns/(:num)/enqueue', 'CampaignQueue::enqueue/$1');
$routes->get('/api/campaigns/(:num)/queue-status', 'CampaignQueue::show/$1');
$routes->post('/api/internal/campaign-recipients/(:num)/claim', 'CampaignQueue::claim/$1');
$routes->post('/api/internal/campaign-recipients/(:num)/complete', 'CampaignQueue::complete/$1');
$routes->get('/api/scheduled-messages', 'ScheduledMessages::index');
$routes->post('/api/scheduled-messages', 'ScheduledMessages::create');
$routes->get('/api/automations', 'Automations::index');
$routes->post('/api/automations', 'Automations::create');
$routes->patch('/api/automations/(:num)/toggle', 'Automations::toggle/$1');
$routes->get('/inbox', 'InboxView::index');
