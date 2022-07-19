<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];


$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates/');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
   $response->getBody()->write('Welcome to Slim!');
   return $response; 
});

$app->get('/users', function ($request, $response, $args) use ($users) {
    $term = htmlspecialchars($request->getQueryParam('term'));
    $filteredUsers = array_filter($users, fn ($user) => str_contains($user, $term));
    $params['users'] = $filteredUsers;
    $params['term'] = $term;
    
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->post('/users', function ($request, $response) {
//    return $response->write('POST /users');
    return $response->withStatus(302);
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => htmlspecialchars($args['id']), 'nickname' => 'user-' . htmlspecialchars($args['id'])];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->run();
