<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;

$users = [];
if (($usersFromJson = file_get_contents(__DIR__ . '/../users.json')) !== false) {
    $users = json_decode($usersFromJson, true);
}


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
    $filteredUsers = array_filter($users, fn ($user) => str_contains(strtolower($user['nickname']), strtolower($term)));
    $params['users'] = $filteredUsers;
    $params['term'] = $term;
    
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->post('/users', function ($request, $response) use ($users) {
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

        return $response->withRedirect('/users', 302);        
    }
    
    $params = [
        'errors' => $errors,
        'user' => $user,
    ];
    return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
});

$app->get('/users/{id:[0-9]+}', function ($request, $response, $args) {
    $params = ['id' => htmlspecialchars($args['id']), 'nickname' => 'user-' . htmlspecialchars($args['id'])];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
        'error' => [],
    ];
    
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->run();
