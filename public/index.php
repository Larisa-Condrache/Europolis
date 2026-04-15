<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Servește fișiere statice din /docs
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (str_starts_with($uri, '/docs/') && file_exists(__DIR__ . $uri)) {
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $mime = match($ext) {
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

$containerBuilder = new ContainerBuilder();
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

(require __DIR__ . '/../src/Routes/games.php')($app);
(require __DIR__ . '/../src/Routes/players.php')($app);
(require __DIR__ . '/../src/Routes/turns.php')($app);
(require __DIR__ . '/../src/Routes/trades.php')($app);

$app->run();