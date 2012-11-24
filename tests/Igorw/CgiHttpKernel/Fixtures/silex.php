<?php

require __DIR__.'/../../../../vendor/autoload.php';

$app = new Silex\Application();

$app['exception_handler']->disable();

$app->get('/foo', function () {
    return 'bar';
});

$app->post('/baz', function () {
    return 'qux';
});

$app->run();
