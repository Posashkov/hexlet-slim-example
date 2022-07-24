<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

session_start();

if (!isset($_SESSION['isAuthorized'])) {
    $_SESSION['isAuthorized'] = false;
}

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates/');
});
$container->set('flash', function() {
    return new \Slim\Flash\Messages();
});


$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
   $response->getBody()->write('Welcome to Slim!');
   return $response; 
})->setName('index');


$app->get('/users', function ($request, $response, $args) use ($router) {
    $term = htmlspecialchars($request->getQueryParam('term'));
    
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    
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
        'routeAddNewUser' => $router->urlFor('users.create'),
        'flash' => $messages,
        'isAuthorized' => $_SESSION['isAuthorized'],
        'routeFormLogin' => $router->urlFor('session.store'),
    ];
    
    
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users.index');


$app->post('/users', function ($request, $response) use ($router) {
    $parsedUser = $request->getParsedBodyParam('user');
    $user = [
        'nickname' => htmlspecialchars($parsedUser['nickname']),
        'email' => htmlspecialchars($parsedUser['email']),
        'id' => rand(1, 1000000),
    ];

    $errors = (new Validator)->validate($user);

    if (count($errors) === 0) {
        $users = json_decode($request->getCookieParam('users', json_encode([])), true);
        $users[] = $user;
        $encodedUsers = json_encode($users);

        $this->get('flash')->addMessage('success', 'User was added successfully');

        return $response
            ->withHeader('Set-Cookie', "users={$encodedUsers}")
            ->withRedirect($router->urlFor('users.index'), 302);        
    }
        
    $params = [
        'errors' => $errors,
        'user' => $user,
        'routeFormCreateUser' => $router->urlFor('users.index'),
    ];
    return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
})->setName('users.store');


$app->get('/users/{id:[0-9]+}', function ($request, $response, $args) use ($router) {
    if (!$_SESSION['isAuthorized']) {
        $this->get('flash')->addMessage('error', 'Access denied. Please log in.');

        return $response->withRedirect($router->urlFor('users.index'), 302);
    }

    $id = htmlspecialchars($args['id']);
    
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
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
    if (!$_SESSION['isAuthorized']) {
        $this->get('flash')->addMessage('error', 'Access denied. Please log in.');

        return $response->withRedirect($router->urlFor('users.index'), 302);
    }

    $params = [
        'user' => [],
        'error' => [],
        'routeFormCreateUser' => $router->urlFor('users.index'),
    ];
    
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('users.create');


$app->get('/users/{id:[0-9]+}/edit', function ($request, $response, $args) use ($router) {
    if (!$_SESSION['isAuthorized']) {
        $this->get('flash')->addMessage('error', 'Access denied. Please log in.');

        return $response->withRedirect($router->urlFor('users.index'), 302);
    }

    $id = htmlspecialchars($args['id']);

    $users = json_decode($request->getCookieParam('users', json_encode([])), true);    
    $user = array_filter($users, fn ($user) => $user['id'] == $id);
    
    if (!$user) {
        $response->getBody()->write('User not found');
        return $response->withStatus(404);
    }
    
    $flash = $this->get('flash')->getMessages();
    
    $params = [
        'user' => reset($user),
        'errors' => [],
        'flash' => $flash,
        'routeFormEditUser' => $router->urlFor('users.update', ['id' => $id]),
        'routeUserIndex' => $router->urlFor('users.index'),
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('users.edit');


$app->patch('/users/{id:[0-9]+}', function ($request, $response, $args) use ($router) {
    $id = htmlspecialchars($args['id']);
   
    $parsedData = $request->getParsedBodyParam('user');
    $parsedUser = [
        'nickname' => htmlspecialchars($parsedData['nickname']),
        'email' => htmlspecialchars($parsedData['email']),
        'id' => $id,
    ];

    $errors = (new Validator)->validate($parsedUser);
    
    if (count($errors) === 0) {
        $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    
        $updatedUsers = array_map(function ($user) use ($parsedUser) {
            if ($user['id'] == $parsedUser['id']) {            
                $user['nickname'] = $parsedUser['nickname'];
                $user['email'] = $parsedUser['email'];
            }
            return $user;            
        }, $users);
        
        $encodedUsers = json_encode($updatedUsers);

        $this->get('flash')->addMessage('success', 'User was updated successfully');

        return $response
            ->withHeader('Set-Cookie', "users={$encodedUsers}")
            ->withRedirect($router->urlFor('users.edit', ['id' => $id]), 302);
    }
    
    $params = [
        'errors' => $errors,
        'user' => $parsedUser,
        'flash' => [],
        'routeFormEditUser' => $router->urlFor('users.update', ['id' => $id]),
    ];
    
    return $this->get('renderer')->render($response->withStatus(422), 'users/edit.phtml', $params);
})->setName('users.update');


$app->delete('/users/{id:[0-9]+}', function ($request, $response, $args) use ($router) {
    $id = htmlspecialchars($args['id']);
    
    $users = json_decode($request->getCookieParam('users', json_encode([])), true);
    
    $updatedUsers = array_filter($users, fn ($user) => $user['id'] != $id);
    
    $encodedUsers = json_encode($updatedUsers);

    $this->get('flash')->addMessage('success', 'User has been deleted');

    return $response
        ->withHeader('Set-Cookie', "users={$encodedUsers}")
        ->withRedirect($router->urlFor('users.index'), 302);    
})->setName('users.delete');


$app->post('/session', function ($request, $response) use ($router) {
    $login = htmlspecialchars($request->getParsedBodyParam('login'));
    
    if ($login === 'login@localhost') {
        $_SESSION['isAuthorized'] = true;
        $this->get('flash')->addMessage('success', 'User was successfully authorized');
    } else {
        $this->get('flash')->addMessage('error', 'Autorisation error');
    }
    
    return $response->withRedirect($router->urlFor('users.index'), 302);
})->setName('session.store');


$app->delete('/session', function ($request, $response) use ($router) {
    $_SESSION = [];
    session_destroy();

    return $response->withRedirect($router->urlFor('users.index'), 302);
})->setName('session.delete');

$app->run();
