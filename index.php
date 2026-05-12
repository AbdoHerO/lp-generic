<?php
require __DIR__ . '/config/helpers.php';
require __DIR__ . '/src/Router.php';

$router = new Router();

require __DIR__ . '/src/Controllers/HomeController.php';
require __DIR__ . '/src/Controllers/ProductController.php';
require __DIR__ . '/src/Controllers/LeadController.php';
require __DIR__ . '/src/Controllers/PageController.php';

$router->get('/',                        ['HomeController', 'index']);
$router->get('/search',                  ['HomeController', 'search']);
$router->get('/category/{slug}',         ['HomeController', 'category']);

$router->get('/thank-you',               ['LeadController', 'thankYou']);
$router->post('/lead/submit',            ['LeadController', 'submit']);

$router->get('/page/privacy',            ['PageController', 'privacy']);
$router->get('/page/terms',              ['PageController', 'terms']);
$router->get('/page/refund',             ['PageController', 'refund']);

// Product slug catch-all (must be last)
$router->get('/{slug}',                  ['ProductController', 'show']);

$router->dispatch();
