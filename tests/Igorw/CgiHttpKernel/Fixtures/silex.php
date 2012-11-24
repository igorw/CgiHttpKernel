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

$app->put('/put-target', function () {
    return 'putted';
});

$app->delete('/delete-target', function () {
    return 'deleted';
});

$app->run();
