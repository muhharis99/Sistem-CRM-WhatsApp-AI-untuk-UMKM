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
