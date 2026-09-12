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
$routes->get('/api/inbox/conversations', 'Inbox::conversations');
$routes->get('/api/inbox/conversations/(:num)/messages', 'Inbox::messages/$1');
$routes->post('/api/inbox/conversations/(:num)/read', 'Inbox::markRead/$1');
$routes->patch('/api/inbox/conversations/(:num)', 'Inbox::updateConversation/$1');
$routes->post('/api/inbox/conversations/(:num)/send', 'Inbox::send/$1');
$routes->get('/inbox', 'InboxView::index');
