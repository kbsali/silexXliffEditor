<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__.'/silex.phar';
require_once __DIR__.'/helpers.php';

$app = new Silex\Application();

$app->register(new Silex\Extension\TwigExtension(), array(
    'twig.path'       => __DIR__.'/views',
    'twig.class_path' => __DIR__.'/vendor/twig/lib',
));


$app->get('/', function () use ($app) {
    $basedir = helper::getBaseDir(__DIR__.'/../xliff-editor-test/');
    $files = helper::rglob('*.xml', $basedir);
    $fileNames = helper::getFileNames($files, $basedir);
    return var_export($files);
});
$app->get('/permission/{fileName}', function ($fileName) use ($app) {
    $basedir = helper::getBaseDir(__DIR__.'/../xliff-editor-test/');
    $f = $basedir.'/'.$fileName;
    helper::fixPerms($f);
    $msg = helper::fixIds($f) ? 'Impossible to change file\'s permissions' : 'File\'s permissions successfully updated';
    //return new Response($msg);
    header('Location:/edit/'.$fileName);
});
$app->get('/reindex/{fileName}', function ($fileName) use ($app) {
    $basedir = helper::getBaseDir(__DIR__.'/../xliff-editor-test/');
    $f = $basedir.'/'.$fileName;
    $msg = helper::fixIds($f) ? 'IDs re-indexed' : 'ERROR writing to file';
    //return new Response($msg);
    header('Location:/edit/'.$fileName);
});
$app->post('/update/{fileName}/{id}', function ($fileName, $id) use ($app) {
    $basedir = helper::getBaseDir(__DIR__.'/../xliff-editor-test/');
    $f = $basedir.'/'.$fileName;
    try {
        $oXml = xliff::parse($f);
    } catch (Exception $e) {
        throw Exception('Problem parsing xml : ' . $e->getMessage());
    }
    //$data = 'asdf';
    if(empty($data)) {
        return new Response('');
    }
    $oXml = xliff::updateId($oXml, $id, $data);
    $msg = file_put_contents($f, i18n::saveXml($oXml)) ? 'ERROR writing to file' : 'File saved ok';
    //return new Response($msg);
    //var_export(get_class_methods(get_class($app)));
});
$app->get('/delete/{fileName}/{id}', function ($fileName, $id) use ($app) {
    $basedir = helper::getBaseDir(__DIR__.'/../xliff-editor-test/');
    $f = $basedir.'/'.$fileName;
    try {
        $oXml = xliff::parse($f);
    } catch (Exception $e) {
        throw Exception('Problem parsing xml : ' . $e->getMessage());
    }
    $oXml = xliff::removeId($oXml, $id);
    $msg = file_put_contents($f, i18n::saveXml($oXml)) ? 'ERROR writing to file' : 'File saved ok';
    //return new Response($msg);
    header('Location:/edit/'.$fileName);
});
$app->get('/edit/{fileName}', function ($fileName) use ($app) {
    $basedir = helper::getBaseDir(__DIR__.'/../xliff-editor-test/');
    $f = $basedir.'/'.$fileName;
    try {
      $oXml = xliff::parse($f);
      $x = '/xliff/file/body/trans-unit';
      $langSource = $oXml->file['source-language'];
      $langTarget = $oXml->file['target-language'];
      foreach($oXml->xpath($x) as $ts) {
        $ts->id = $ts['id'];
        $arr[] = $ts;
      }
    } catch (Exception $e) {
      throw Exception('Problem parsing xml : ' . $e->getMessage());
    }
    return $app['twig']->render('file.twig', array(
        'oXml' => $arr,
        'isWritable' => is_writable($f),
        'baseDir' => $basedir,
        'fileName' => $fileName,
        'langSource' => (string)$oXml->file['source-language'],
        'langTarget' => (string)$oXml->file['target-language'],
        'dateExtract' => (string)$oXml->file['date'],
        'dateSave' => filemtime($f),
    ));
});
/*
$app->error(function (\Exception $e) {
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