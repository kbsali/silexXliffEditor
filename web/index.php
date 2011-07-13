<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__.'/../silex.phar';
require_once __DIR__.'/../helpers.php';

use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();

$app->register(new Silex\Extension\TwigExtension(), array(
    'twig.path'       => __DIR__.'/../views',
    'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));

$dir = __DIR__.'/../../xliff-editor-test/';

$app->get('/', function() use($app, $dir) {
    $basedir = helper::getBaseDir($dir);
    $files = helper::rglob('*', $basedir);
    $fileNames = helper::getFileNames($files, $basedir);
    return $app->redirect('/edit/'.$fileNames[0]);
});
$app->get('/permission/{fileName}', function($fileName) use($app, $dir) {
    $basedir = helper::getBaseDir($dir);
    $f = $basedir.'/'.$fileName;
    helper::fixPerms($f);
    $msg = helper::fixIds($f) ? 'Impossible to change file\'s permissions' : 'File\'s permissions successfully updated';
    return $app->redirect('/edit/'.$fileName);
});
$app->get('/reindex/{fileName}', function($fileName) use($app, $dir) {
    $basedir = helper::getBaseDir($dir);
    $f = $basedir.'/'.$fileName;
    $msg = helper::fixIds($f) ? 'IDs re-indexed' : 'ERROR writing to file';
    //return new Response($msg);
    return $app->redirect('/edit/'.$fileName);
});
$app->post('/update/{fileName}/{id}', function($fileName, $id) use($app, $dir) {
    $request = $app['request'];

    $basedir = helper::getBaseDir($dir);
    $f = $basedir.'/'.$fileName;
    try {
        $oXml = xliff::parse($f);
    } catch (Exception $e) {
        throw new Exception('Problem parsing xml : ' . $e->getMessage());
    }
    $data = $request->get('data');
    if(empty($data)) {
        return new Response('A');
    }
    $oXml = xliff::updateId($oXml, $id, $data);
    $msg = file_put_contents($f, i18n::saveXml($oXml)) ? 'File saved ok' : 'ERROR writing to file';
    return new Response($msg);
    //var_export(get_class_methods(get_class($app)));
});
$app->get('/delete/{fileName}/{id}', function($fileName, $id) use($app, $dir) {
    $basedir = helper::getBaseDir($dir);
    $f = $basedir.'/'.$fileName;
    try {
        $oXml = xliff::parse($f);
    } catch (Exception $e) {
        throw new Exception('Problem parsing xml : ' . $e->getMessage());
    }
    $oXml = xliff::removeId($oXml, $id);
    $msg = file_put_contents($f, i18n::saveXml($oXml)) ? 'File saved ok' : 'ERROR writing to file';
    return $app->redirect('/edit/'.$fileName);
});
$app->get('/edit/{fileName}', function($fileName) use($app, $dir) {
    $request = $app['request'];

    $basedir = helper::getBaseDir($dir);
    $f = $basedir.'/'.$fileName;

    $files = helper::rglob('*.xml', $basedir);
    $fileNames = helper::getFileNames($files, $basedir);

    try {
        $oXml = xliff::parse($f);
        $x = '/xliff/file/body/trans-unit';

        if(!is_null($request->get('empty'))) {
            $x.= "/target[. = '']";
        }

        $dups = array();
        if(!is_null($request->get('duplicate'))) {
            $dups = xliff::getDuplicates($oXml);
            foreach($dups as $id => $bla) {
                $xpath[] = '@id="' . $id . '"';
            }
            $x.= '[' . join(' or ', $xpath) . ']';
        }

        $langSource = $oXml->file['source-language'];
        $langTarget = $oXml->file['target-language'];
        foreach($oXml->xpath($x) as $ts) {
            $ts->id = $ts['id'];
            $arr[] = $ts;
        }
    } catch (Exception $e) {
        throw new Exception('Problem parsing xml : ' . $e->getMessage());
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

        'noDuplicate' => !is_null($request->get('duplicate')) && empty($dups),

        'selectFiles' => html::dropdown('f', $fileNames, $fileName),
        'selectShowEmpty' => html::dropdown('empty', array(0 => 'No', 1 => 'Yes'), !is_null($request->get('empty'))),
        'selectShowDuplicate' => html::dropdown('duplicate', array(0 => 'No', 1 => 'Yes'), !is_null($request->get('duplicate')) && !empty($dups)),
    ));
});
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