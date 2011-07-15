<?php
use Symfony\Component\HttpFoundation\Response;

$dir = __DIR__.'/../tests/xliff';


$app->get('/login', function() use($app, $dir) {
    $username = $app['request']->server->get('PHP_AUTH_USER', false);
    $password = $app['request']->server->get('PHP_AUTH_PW');

    if('admin' === $username && 'password' === $password) {
        $app['session']->set('user', array('username' => $username));
        return $app->redirect('/');
    }
    $response = new Response();
    $response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'site_login'));
    $response->setStatusCode(401, 'Please sign in.');
    return $response;
});

$app->get('/', function() use($app, $dir) {
    if(null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $basedir = helper::getBaseDir($dir);
    $files = helper::rglob('*.{xliff,xml}', $basedir, GLOB_BRACE);
    $fileNames = helper::getFileNames($files, $basedir);
    return $app->redirect('/edit'.$fileNames[0]);
});

$app->get('/permission/{fileName}', function($fileName) use($app, $dir) {
    if(null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $basedir = helper::getBaseDir($dir);
    $f = $basedir.'/'.$fileName;
    helper::fixPerms($f);
    $msg = helper::fixIds($f) ? 'Impossible to change file\'s permissions' : 'File\'s permissions successfully updated';
    return $app->redirect('/edit/'.$fileName);
});

$app->get('/reindex/{fileName}', function($fileName) use($app, $dir) {
    if(null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $basedir = helper::getBaseDir($dir);
    $f = $basedir.'/'.$fileName;
    $msg = helper::fixIds($f) ? 'IDs re-indexed' : 'ERROR writing to file';
    //return new Response($msg);
    return $app->redirect('/edit/'.$fileName);
});

$app->post('/update/{fileName}/{id}', function($fileName, $id) use($app, $dir) {
    if(null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

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
    if(null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

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
    if(null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $request = $app['request'];

    $basedir = helper::getBaseDir($dir);
    $f = $basedir.'/'.$fileName;

    $files = helper::rglob('*.{xliff,xml}', $basedir, GLOB_BRACE);
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