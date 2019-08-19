<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

require_once __DIR__ . '/CatchAll.php';

return function (App $app) {
    /** @var \Slim\Container $container */
    $container = $app->getContainer();

    $catchAll = new CatchAll($container);
    $app->any('/{section}/{primary}/{secondary}/{tertiary}/{quaternary}/[{quinary}]', $catchAll);
    $app->any('/{section}/{primary}/{secondary}/{tertiary}/[{quaternary}]', $catchAll);
    $app->any('/{section}/{primary}/{secondary}/[{tertiary}]', $catchAll);
    $app->any('/{section}/{primary}/[{secondary}]', $catchAll);
    $app->any('/{section}/[{primary}]', $catchAll);
    $app->any('/[{section}]', $catchAll);
    $app->any('', $catchAll);
};
