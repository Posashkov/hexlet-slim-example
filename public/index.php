<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;

session_start();

$users = [];
if (($usersFromJson = file_get_contents(__DIR__ . '/../users.json')) !== false) {
    $users = json_decode($usersFromJson, true);
}


$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates/');
});
$container->set('flash', function() {
    return new \Slim\Flash\Messages();
});


$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
   $response->getBody()->write('Welcome to Slim!');
   return $response; 
})->setName('index');

$app->get('/users', function ($request, $response, $args) use ($users, $router) {
    $term = htmlspecialchars($request->getQueryParam('term'));
    $filteredUsers = array_filter($users, fn ($user) => str_contains(strtolower($user['nickname']), strtolower($term)));
    
    $filteredUsers = array_map(function ($user) use ($router) {
        $user['route'] = $router->urlFor('users.show', ['id' => $user['id']]);
        return $user;
    }, $filteredUsers);
    

    $messages = $this->get('flash')->getMessages();
    
    $params = [
        'users' => $filteredUsers,
        'term' => $term,
        'routeFormUserSearch' => $router->urlFor('users.index'),
        'flash' => $messages,    
    ];
    
    
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users.index');

$app->post('/users', function ($request, $response) use ($users, $router) {
    $parsedUser = $request->getParsedBodyParam('user');
    $user = [
        'nickname' => htmlspecialchars($parsedUser['nickname']),
        'email' => htmlspecialchars($parsedUser['email']),
        'id' => rand(1, 1000000),
    ];

    $errors = (new Validator)->validate($user);

    if (count($errors) === 0) {
        $users[] = $user;
        file_put_contents(__DIR__ . '/../users.json', json_encode($users));

        $this->get('flash')->addMessage('success', 'User was added successfully');

        return $response->withRedirect($router->urlFor('users.index'), 302);        
    }
        
    $params = [
        'errors' => $errors,
        'user' => $user,
        'routeFormCreateUser' => $router->urlFor('users.index'),
    ];
    return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
})->setName('users.store');

$app->get('/users/{id:[0-9]+}', function ($request, $response, $args) use ($users, $router) {
    $id = htmlspecialchars($args['id']);
    
    $user = array_filter($users, fn ($user) => $user['id'] == $id);
    
    if (!$user) {
        $response->getBody()->write('User not found');
        return $response->withStatus(404);
    }
   
    $params = [
        'user' => reset($user),
        'routeUserIndex' => $router->urlFor('users.index'),
    ];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('users.show');

$app->get('/users/new', function ($request, $response) use ($router) {
    $params = [
        'user' => [],
        'error' => [],
        'routeFormCreateUser' => $router->urlFor('users.index'),
    ];
    
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('users.create');

$app->run();
