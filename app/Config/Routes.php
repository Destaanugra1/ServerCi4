<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Router\RouteCollection;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

// --------------------------------------------------------------------
// Router Setup
// --------------------------------------------------------------------
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
// The Auto Routing (Legacy) is dangerous. Use Auto Routing (Improved) if you need it.
// $routes->setAutoRoute(true);

// --------------------------------------------------------------------
// Route Definitions
// --------------------------------------------------------------------

// Auth & User
$routes->group('user', function ($routes) {
    $routes->get('listuser', 'Home::listUsers');
    $routes->get('testemail', 'Home::testEmail');
    $routes->get('(:num)', 'Home::getuserByid/$1');
    $routes->get('(:segment)', 'Home::getuserByid/$1');
    $routes->put('update/(:num)', 'Home::editProfile/$1');
});
$routes->post('register', 'Home::add');
$routes->post('login', 'Home::login');
$routes->get('home/verify/(:segment)', 'Home::verify/$1');

// Product
$routes->group('product', function ($routes) {
    $routes->get('/', 'Products::list');
    $routes->post('create', 'Products::create');
    $routes->get('(:any)', 'Products::getProductByid/$1');
    $routes->post('update/(:num)', 'Products::update/$1');
    $routes->delete('delete/(:num)', 'Products::deleteData/$1');
    $routes->post('update-stock/(:num)', 'Products::updateStock/$1');
});

// Category
$routes->group('category', function ($routes) {
    $routes->get('/', 'CategoryController::list');
});

// API: Cart, Checkout & Midtrans, Payment
$routes->group('api', ['namespace' => 'App\Controllers'], function ($routes) {
    // Keranjang (Cart)
    $routes->get('cart/user/(:num)', 'CartController::getByUser/$1');
    $routes->post('cart', 'CartController::create');
    $routes->put('cart/(:num)', 'CartController::update/$1');
    $routes->delete('cart/(:num)', 'CartController::delete/$1');
    $routes->post('checkout', 'CheckoutController::create');
    $routes->get('admin/dashboard-stats', 'Home::getDashboardStats');
});
// Checkout → generate Midtrans Snap token
// POST  /api/checkout             { cart_id }

// Midtrans Notification Hook
// POST  /api/midtrans/notification
$routes->post('midtrans/notification', 'MidtransNotificationController::handleNotification');

// PaymentController (jika masih dipakai)
$routes->group('api', function ($routes) {
    $routes->post('payment/create', 'PaymentController::createTransaction');
    $routes->post('payment/notification', 'PaymentController::handleNotification');
    $routes->post('payment/confirm', 'PaymentController::confirm');
    $routes->get('payment/test', 'PaymentController::testMidtransConnection');
    $routes->post('payment/insert', 'PaymentController::testinsert');
    $routes->get('history/(:num)', 'HistoryPembelianController::getByUser/$1');
    $routes->get('history', 'HistoryPembelianController::index');
    $routes->put('history/(:num)/shipping', 'HistoryPembelianController::updateShippingStatus/$1');
    $routes->put('history/(:num)/tracking', 'HistoryPembelianController::updateTracking/$1');
    $routes->get('statistics/weekly', 'StatisticsController::getWeeklyRevenue');
});

$routes->get('categories', 'CategoryController::index');
$routes->post('categories', 'CategoryController::create');
$routes->put('categories/(:num)', 'CategoryController::update/$1');
$routes->delete('categories/(:num)', 'CategoryController::delete/$1');

$routes->get('products/total', 'Products::getTotalProducts');

$routes->get('history-pembelian/shipping/(:num)', 'HistoryPembelianController::getByUserWithShipping/$1');

// $routes->post('api/cart/add', 'CartController::addToCart');
// // Seharusnya ada route seperti ini di Routes.php
// $routes->delete('api/cart/item/(:num)', 'CartController::deleteItem/$1');

// $routes->post('api/cart/cleanup-duplicates', 'CartController::cleanupDuplicates');

// --------------------------------------------------------------------
// Additional Routing
// --------------------------------------------------------------------
// Load environment-specific routes if available
if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
