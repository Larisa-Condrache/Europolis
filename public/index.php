<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Support/board.php';

/* ─────────────────────────────────────────
   STATIC DOCS (optional)
───────────────────────────────────────── */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_starts_with($uri, '/docs/') && file_exists(__DIR__ . $uri)) {
    $ext = pathinfo($uri, PATHINFO_EXTENSION);

    $mime = match ($ext) {
        'html' => 'text/html',
        'yaml', 'yml' => 'text/yaml',
        'css' => 'text/css',
        'js' => 'application/javascript',
        default => 'text/plain'
    };

    header("Content-Type: $mime");
    readfile(__DIR__ . $uri);
    exit;
}

/* ─────────────────────────────────────────
   CONTAINER
───────────────────────────────────────── */
$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    PDO::class => function () {
        $host = '127.0.0.1';
        $db   = 'europolis';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    },
]);

$container = $containerBuilder->build();

/* ─────────────────────────────────────────
   SLIM APP
───────────────────────────────────────── */
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

/* ─────────────────────────────────────────
   CORS preflight
───────────────────────────────────────── */
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

/* ─────────────────────────────────────────
   CORS middleware
───────────────────────────────────────── */
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {

    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();

        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Origin, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withStatus(200);
    }

    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Origin, Authorization, X-Requested-With')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

/* ─────────────────────────────────────────
   ERROR HANDLER
───────────────────────────────────────── */
$app->addErrorMiddleware(true, true, true);

/* ─────────────────────────────────────────
   ROUTES
───────────────────────────────────────── */
(require __DIR__ . '/../src/Routes/games.php')($app);
(require __DIR__ . '/../src/Routes/players.php')($app);
(require __DIR__ . '/../src/Routes/turns.php')($app);
(require __DIR__ . '/../src/Routes/properties.php')($app);
(require __DIR__ . '/../src/Routes/trades.php')($app);
(require __DIR__ . '/../src/Routes/chat.php')($app);

/* ───────────────────────────────────────── */
$app->run();
