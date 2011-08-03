<?php
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

function protect($app) {
    if($app['request']->get('require_authentication')) {
        if(null === $user = $app['session']->get('user')) {
            throw new AccessDeniedHttpException('require auth...');
        }
    }
}
$app->before(function() use ($app) {
    if(!file_exists(__DIR__.'/app.yml')) {
        throw new Exception('No app.yml found (cp '.__DIR__.'/app.yml.sample '.__DIR__.'/app.yml ?)');
    }
    $arr = Yaml::parse(__DIR__.'/app.yml');
    $app['users'] = $arr['users'];
    $app['base_dir'] = $arr['base_dir'];
});

$app->get('/logout', function() use($app) {
    $app['session']->invalidate();
    return $app->redirect('/login');
});

$app->get('/login', function() use($app) {
    $username = $app['request']->server->get('PHP_AUTH_USER', false);
    $password = $app['request']->server->get('PHP_AUTH_PW');

    if(isset($app['users'][$username]) && $app['users'][$username]['password'] === $password) {
        $app['session']->set('user', array('username' => $username, 'group' => $app['users'][$username]['group']));
        return $app->redirect('/');
    }
    $response = new Response();
    $response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'site_login'));
    $response->setStatusCode(401, 'Please sign in.');
    return $response;
});

$app->get('/', function() use($app) {
    protect($app);

    $files = helper::getXliffFiles($app['base_dir'][0]);
    return $app->redirect('/edit/'.key($files));
})->value('require_authentication', true);

$app->get('/permission/{fileName}', function($fileName) use($app) {
    protect($app);

    $f = $app['base_dir'][0].'/'.helper::decodeFileName($fileName);
    helper::fixPerms($f);
    $msg = helper::fixIds($f) ? 'Impossible to change file\'s permissions' : 'File\'s permissions successfully updated';
    return $app->redirect('/edit/'.$fileName);
})->value('require_authentication', true);

$app->get('/reindex/{fileName}', function($fileName) use($app) {
    protect($app);

    $f = $app['base_dir'][0].'/'.helper::decodeFileName($fileName);
    $msg = helper::fixIds($f) ? 'IDs re-indexed' : 'ERROR writing to file';
    return $app->redirect('/edit/'.$fileName);
})->value('require_authentication', true);

$app->post('/update/{what}/{fileName}/{id}', function($what, $fileName, $id) use($app) {
    protect($app);
    $request = $app['request'];

    $f = $app['base_dir'][0].'/'.helper::decodeFileName($fileName);
    try {
        $oXml = xliff::parse($f);
    } catch (Exception $e) {
        throw new Exception('Problem parsing xml : ' . $e->getMessage());
    }
    $data = $request->get('data');
    if(empty($data)) {
        return new Response('A');
    }
    if($what == 'translation') {
        $oXml = xliff::updateTranslationId($oXml, $id, $data);
    } elseif($what == 'comment') {
        $oXml = xliff::updateCommentId($oXml, $id, $data);
    }
    $msg = file_put_contents($f, i18n::saveXml($oXml)) ? 'File saved ok' : 'ERROR writing to file';
    return new Response($msg);
})->value('require_authentication', true);

$app->get('/delete/{fileName}/{id}', function($fileName, $id) use($app) {
    protect($app);
    $user = $app['session']->get('user');
    if('god' != $user['group']) {
        throw new Exception('You are not allowed to delete elements!');
    }
    $f = $app['base_dir'][0].'/'.helper::decodeFileName($fileName);
    try {
        $oXml = xliff::parse($f);
    } catch (Exception $e) {
        throw new Exception('Problem parsing xml : ' . $e->getMessage());
    }
    $oXml = xliff::removeId($oXml, $id);
    $msg = file_put_contents($f, i18n::saveXml($oXml)) ? 'File saved ok' : 'ERROR writing to file';
    return $app->redirect('/edit/'.$fileName);
})->value('require_authentication', true);

$app->get('/edit/{fileName}', function($fileName) use($app) {
    protect($app);
    $user = $app['session']->get('user');
    $request = $app['request'];

    $files = helper::getXliffFiles($app['base_dir'][0]);
    $f = $app['base_dir'][0].'/'.helper::decodeFileName($fileName);

    try {
        $oXml = xliff::parse($f);
        $x = '/xliff/file/body/trans-unit';
        if( 1 == $request->get('empty', 0) ) {
            $x.= "/target[. = '']";
        }

        $dups = array();
        if( 1 == $request->get('duplicate', 0) ) {
            $dups = xliff::getDuplicates($oXml);
            $xpath = array();
            foreach($dups as $id => $bla) {
                $xpath[] = '@id="' . $id . '"';
            }
            $x.= '[' . join(' or ', $xpath) . ']';
        }

        $langSource = $oXml->file['source-language'];
        $langTarget = $oXml->file['target-language'];

        $aXml = $oXml->xpath($x);
        $arr = array();
        if(false !== $aXml) {
            foreach($aXml as $ts) {
                $ts->id = $ts['id'];
                $ts->comment = $ts['comment'];
                $arr[] = $ts;
            }
        }
    } catch (Exception $e) {
        throw new Exception('Problem parsing xml : ' . $e->getMessage());
    }
    return $app['twig']->render('file.twig', array(
        'oXml' => $arr,
        'isWritable' => is_writable($f),

        'canDelete' => 'god' === $user['group'],

        'baseDir' => $app['base_dir'][0],
        'fileName' => helper::decodeFileName($fileName),

        'langSource' => (string)$oXml->file['source-language'],
        'langTarget' => (string)$oXml->file['target-language'],

        'dateExtract' => (string)$oXml->file['date'],
        'dateSave' => filemtime($f),

        'noDuplicate' => 1 == $request->get('duplicate', 0) && empty($arr),
        'noEmpty' => 1 == $request->get('empty', 0) && empty($arr),

        'selectFiles' => html::dropdown('f', helper::getFileBaseNames($files), $fileName),
        'selectShowEmpty' => html::dropdown('empty', array(0 => 'No', 1 => 'Yes'), $request->get('empty', 0) ),
        'selectShowDuplicate' => html::dropdown('duplicate', array(0 => 'No', 1 => 'Yes'), $request->get('duplicate', 0) ),
    ));
})->value('require_authentication', true);

$app->error(function (\Exception $e) use ($app) {
    if ($e instanceof NotFoundHttpException) {
        return new Response('The requested page could not be found.', 404);
    }
    if ($e instanceof AccessDeniedHttpException) {
        return $app->redirect('/login');
    }
    $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
    return new Response('We are sorry, but something went terribly wrong: ' . $e->getMessage(), $code);
});