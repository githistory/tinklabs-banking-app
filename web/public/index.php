<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../app/vendor/autoload.php';
require '../app/src/Account.php';

$container = new \Slim\Container();
$container['account'] = function ($container) {
  $db = new PDO('mysql:host=mysqldb;dbname=account', 'root', 'root');
  return new App\Acme\Account($db);
};
$app = new \Slim\App($container);

// open account
$app->post('/account', function (Request $request, Response $response) {
  $json = $request->getBody();
  $data = json_decode($json, true);

  try {
    $result = (object)[
      'success' => true,
      'message' => $this->account->openAccount($data['name'], $data['hkid'])
    ];
  } catch (Exception $e) {
    $result = (object)[
      'success' => false,
      'message' => $e->getMessage()
    ];
  }

  $response->getBody()->write(json_encode($result));
  return $response;
});

$app->delete('/account/{id}', function (Request $request, Response $response, $args) {
  $id = $args['id'];

  try {
    $result = (object)[
      'success' => true,
      'message' => $this->account->closeAccount($id)
    ];
  } catch (Exception $e) {
    $result = (object)[
      'success' => false,
      'message' => $e->getMessage()
    ];
  }

  $response->getBody()->write(json_encode($result));
  return $response;
});

$app->get('/account/{id}/balance', function (Request $request, Response $response, $args) {
  $id = $args['id'];

  try {
    $result = (object)[
      'success' => true,
      'message' => $this->account->getBalance($id)
    ];
  } catch (Exception $e) {
    $result = (object)[
      'success' => false,
      'message' => $e->getMessage()
    ];
  }

  $response->getBody()->write(json_encode($result));
  return $response;
});

$app->put('/account/{id}/withdraw/{amount}', function (Request $request, Response $response, $args) {
  $id = $args['id'];
  $amount = $args['amount'];

  try {
    $result = (object)[
      'success' => true,
      'message' => $this->account->withdraw($id, $amount)
    ];
  } catch (Exception $e) {
    $result = (object)[
      'success' => false,
      'message' => $e->getMessage()
    ];
  }

  $response->getBody()->write(json_encode($result));
  return $response;
});

$app->put('/account/{id}/deposit/{amount}', function (Request $request, Response $response, $args) {
  $id = $args['id'];
  $amount = $args['amount'];

  try {
    $result = (object)[
      'success' => true,
      'message' => $this->account->deposit($id, $amount)
    ];
  } catch (Exception $e) {
    $result = (object)[
      'success' => false,
      'message' => $e->getMessage()
    ];
  }

  $response->getBody()->write(json_encode($result));
  return $response;
});

$app->put('/account/transfer/{from}/{to}/{amount}', function (Request $request, Response $response, $args) {
  $from = $args['from'];
  $to = $args['to'];
  $amount = $args['amount'];

  try {
    $result = (object)[
      'success' => true,
      'message' => $this->account->transfer($from, $to, $amount)
    ];
  } catch (Exception $e) {
    $result = (object)[
      'success' => false,
      'message' => $e->getMessage()
    ];
  }

  $response->getBody()->write(json_encode($result));
  return $response;
});

$app->run();
