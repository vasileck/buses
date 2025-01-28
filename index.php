<?php
require_once 'Database.php';
require_once 'Router.php';
require_once 'BusController.php';
require_once 'ResponseHelper.php';

$db = new Database([
    'host'     => 'localhost', 
    'port'     => 'port',        
    'dbname'   => 'bus_schedule',
    'user'     => 'postgres',
    'password' => 'password',  
]);

$controller = new BusController($db);

$router = new Router();

$router->get('find-bus', function() use ($controller) {
    $controller->findBus();
});

$router->post('route', function() use ($controller) {
    $controller->createOrUpdateRoute();
});

$router->delete('route', function() use ($controller) {
    $controller->deleteRoute();
});

$router->run();

