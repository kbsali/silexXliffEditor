<?php
$app = require __DIR__.'/../src/bootstrap.php';

require __DIR__.'/../src/app.php';

/*
$app->error(function(\Exception $e) {
    if ($e instanceof NotFoundHttpException) {
        return new Response('La página que buscas no está aquí.', 404);
    }
    $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
    return new Response('Algo ha fallado en nuestra sala de máquinas.', $code);
});
*/
try {
    $app->run();
} catch(Exception $e) {
    die($e->getMessage());
}