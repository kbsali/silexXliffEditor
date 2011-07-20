<?php
function isLoggedIn($app) {
    if(null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }
    return $user;
}
class xliff {
  public static function parse($f) {
    if(!file_exists($f)) {
      throw new Exception($f . ' does not exist');
    }
    try {
      libxml_use_internal_errors(true);
      // SimpleXML has issues with Namespaces...
      $oXml = simplexml_load_string( str_replace('xmlns=', 'ns=', file_get_contents($f)) );
      if(!$oXml) {
        $errs = libxml_get_errors();
        $strErr = '';
        foreach($errs as $err) {
          $strErr .= trim($err->message) . PHP_EOL .
            ' Line: ' . $err->line . PHP_EOL .
            ' Column: ' . $err->column . PHP_EOL . PHP_EOL;
        }
        throw new Exception('Error in file ' . $f . ' : ' . $strErr);
      }
    } catch (Exception $e) {
      throw new Exception('Problem parsing xml : ' . $e->getMessage());
    }
    return $oXml;
  }
  /**
   * Removes an xliff translation node given its id
   * @param SimpleXMLElement $oXml
   * @param int $id
   * @return SimpleXMLElement $oXml
   */
  public static function removeId(SimpleXMLElement $oXml, $id) {
    $o = $oXml->xpath('/xliff/file/body/trans-unit[@id="'.$id.'"]');
    $dom = dom_import_simplexml($o[0]);
    $dom->parentNode->removeChild($dom);
    return $oXml;
  }
  /**
   * Updates target translation node given its id + its new value
   * @param type $oXml
   * @param int $id
   * @param string $data
   * @return SimpleXML $oXml
   */
  public static function updateId(SimpleXMLElement $oXml, $id, $data) {
    $o = $oXml->xpath('/xliff/file/body/trans-unit[@id="'.$id.'"]');
    $o[0]->target = str_replace('\\', '', trim($data));
    $dom = dom_import_simplexml($o[0]);
    return $oXml;
  }
  /**
   * Get list of duplicate ids
   * @param SimpleXMLElement $oXml
   * @return array
   */
  public static function getDuplicates(SimpleXMLElement $oXml) {
    $dups = array();
    $ret = array();
    foreach($oXml->file->body->{'trans-unit'} as $ts) {
      $dups[ (string)$ts->source ][] = (int)$ts['id'];
    }
    foreach($dups as $dup) {
      if(count($dup) > 1) {
        foreach($dup as $id)
        $ret[$id] = 1;
      }
    }
    return $ret;
  }
}
class i18n {
  /**
   * Saves given xml object (formatted)
   * @param simplexml $oXml
   * @return string xml formatted string (ready to be saved to file)
   */
  public static function saveXml($oXml) {
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($oXml->asXML());
    return $dom->saveXML();
  }
  /**
   * Returns an array from the given xliff string
   * array(
   *   'id' => array('source', 'target')
   * )
   * @param $xml valid xliff xml string
   * @return array
   */
  public static function xliff2arr($xml = null) {
    if(is_null($xml)) {
      return array();
    }
    try {
      libxml_use_internal_errors(true);
      $o = simplexml_load_string($xml);
    } catch (Exception $e) {
      die('Problem parsing xml : ' . $e->getMessage());
    }
    if(!$o) {
      $errs = libxml_get_errors();
      $strErr = '';
      foreach($errs as $err) {
        $strErr .= trim($err->message) . PHP_EOL .
          ' Line: ' . $err->line . PHP_EOL .
          ' Column: ' . $err->column . PHP_EOL . PHP_EOL;
      }
      die('Error in file ' . $f . ' : ' . $strErr);
    }
    $ret = array();
    foreach($o->file->body->{'trans-unit'} as $ts) {
      $ret[ (int)$ts['id'] ] = array(
        'source' => (string)$ts->source,
        'target' => (string)$ts->target
      );
      //$source = !empty($ts->source) ? $ts->source : '&nbsp';
      //$target = !empty($ts->target) ? $ts->target : '&nbsp';
    }
    return $ret;
  }
}
class html {
  /**
   * Returns html for a select drop down. If selected element is defined, preselects it!
   * @param string $name - form element name + html id
   * @param array $options - array of options for the drop down
   * @param string $selected - selected element
   * @param string $nullLabel - if defined, it is added to the list of options
   * @return string
   */
  public static function dropdown($name, array $options, $selected = null, $nullLabel = null) {
    if(!is_null($nullLabel)) {
      $options = array(0 => $nullLabel) + $options;
    }
    $ret = '<select class="ui-corner-all" name="' . $name . '" id="' . $name . '">' . PHP_EOL;
    foreach ($options as $key => $option) {
      $select = $selected == $key ? ' selected="selected"' : '';
      $ret.= '<option value="' . $key . '"' . $select . '>' . $option . '</option>' . PHP_EOL;
    }
    $ret.= '</select>' . PHP_EOL;
    return $ret;
  }
  /**
   * Returns an html unordered list given an array of options. If selected is defined,
   * adds "selected" class to  element and does NOT make a link of this element.
   * @param array $options
   * @param string $selected
   */
  public static function fileList(array $options, $selected = null) {
    $ret = '<ul>' . PHP_EOL;
    foreach($fileNames as $k => $v) {
      if($idx == $k) {
        $ret.= '<li class="selected">' . $v . '</li>' . PHP_EOL;
      } else {
        $ret.= '<li><a href="?f=' . $k . '">' . $v . '</a></li>' . PHP_EOL;
      }
    }
    $ret = '</ul>' . PHP_EOL;
  }
}
class helper {
  /**
   * Recursively browses the given $path filtering element by $pattern
   * @param string $pattern
   * @param string $path
   * @param int $flags
   * @return array
   */
  public static function rglob($pattern, $path = '', $flags = 0) {
    if (!$path && ($dir = dirname($pattern)) != '.') {
      if ($dir == '\\' || $dir == '/') {
        $dir = '';
      }
      return self::rglob(basename($pattern), $flags, $dir . '/');
    }
    $paths = glob($path . '*', GLOB_ONLYDIR | GLOB_NOSORT);
    $files = glob($path . $pattern, $flags);
    if(is_array($paths) && is_array($files)) {
      foreach ($paths as $p) {
        $files = array_merge($files, self::rglob($pattern, $p . '/', $flags));
      }
    }
    return is_array($files) ? $files : array();
  }
  /**
   * Returns best possible "basedir" (if NOT defined in xliff.ini takes local var)
   * @return string
   */
  public static function getBaseDir($dir = null) {
    if(!is_null($dir)) {
      return $dir;
    }
    $ret = '/PATH/TO/XLIFF/DIRECTORY';
    if(!file_exists('xliff.ini')) {
      return $ret;
    }
    $ini = parse_ini_file('xliff.ini');
    if(!isset($ini['basedir'])) {
      return $ret;
    }
    return $ini['basedir'];
  }
  /**
   * Returns an array of file names removing previous defined basedir
   * - it also append "(ro)" to read-only files
   * @param array $arr
   * @param string $basedir
   * @param boolean $verbose - if true will append (ro) to read-only files
   * @return array
   */
  public static function getFileNames(Array $arr = array(), $basedir, $verbose = false) {
    $ret = array();
    foreach($arr as $f) {
      if(true === $verbose && !is_writable($f)) {
        $f.= ' (ro)';
      }
      $ret[str_replace($basedir . '/', '', $f)] = str_replace($basedir, '', $f);
    }
    return $ret;
  }
  /**
   * Tries to make given $f file writable
   * - $idx if used for linking back to the file
   * @todo remove $idx (ajax call?)
   * @param string $f
   * @return boolean
   */
  function fixPerms($f) {
    return @chmod($f, 0666);
  }
  /**
   * Updates translation node ids from 1 to n
   * - $idx if used for linking back to the file
   * @todo remove $idx (ajax call?)
   * @param string $f
   * @return boolean
   */
  function fixIds($f) {
    try {
      $oXml = simplexml_load_string(file_get_contents($f));
    } catch (Exception $e) {
      return false; //die('Problem parsing xml : ' . $e->getMessage().PHP_EOL);
    }
    $i = 0;
    foreach($oXml->file->body->{'trans-unit'} as $ts) {
      $i++;
      $ts['id'] = $i;
    }
    return @file_put_contents($f, i18n::saveXml($oXml));


    if(!@file_put_contents($f, i18n::saveXml($oXml))) {
      die('ERROR writing to file. <a href="?f=' . $idx . '">back</a>');
    } else {
      die('IDs re-indexed. <a href="?f=' . $idx . '">back</a>');
    }
  }
}
function dump() {
    $args = func_get_args();
    $backtrace = debug_backtrace();
    $code = file($backtrace[0]['file']);

    echo '<pre style="background: #eee;border: 1px solid #aaa;clear: both;overflow: auto;padding: 10px;text-align: left;margin-bottom: 5px">';
    echo '<b>' . $backtrace[0]['file'] . '</b>::<b>' . $backtrace[0]['line'] . '</b><br/><br/>';
    echo '<b>' . htmlspecialchars(trim($code[$backtrace[0]['line'] - 1])) . '</b>' . PHP_EOL;
    echo PHP_EOL;

    ob_start();
    foreach($args as $arg){
        var_dump($arg);
    }
    $str = ob_get_contents();
    ob_end_clean();

    $str = preg_replace('/=>(\s+)/', ' => ', $str);
    $str = preg_replace('/ => NULL/', ' &rarr; <b style="color: #000">NULL</b>', $str);
    $str = preg_replace('/}\n(\s+)\[/', "}\n\n" . '$1[', $str);

    $str = preg_replace('/ (float|int)\((\-?[\d\.]+)\)/', ' <span style="color: #888">$1</span> <b style="color: brown">$2</b>', $str);

    $str = preg_replace('/array\((\d+)\) {\s+}\n/', '<span style="color: #888">array&bull;$1</span> <b style="color: brown">[]</b>', $str);
    $str = preg_replace('/ string\((\d+)\) \"(.*)\"/', ' <span style="color: #888">str&bull;$1</span> <b style="color: brown">\'$2\'</b>', $str);

    $str = preg_replace('/\[\"(.+)\"\] => /', '<span style="color: purple">\'$1\'</span> &rarr; ', $str);

    $str = preg_replace('/object\((\S+)\)#(\d+) \((\d+)\) {/', '<span style="color: #888">obj&bull;$2</span> <b style="color: #0C9136">$1[$3]</b> {', $str);

    $str = str_replace("bool(false)", '<span style="color: #888">bool&bull;</span><span style="color: red">false</span>', $str);
    $str = str_replace("bool(true)", '<span style="color: #888">bool&bull;</span><span style="color: green">true</span>', $str);

    echo $str;

    echo 'Sizes: ';
    foreach($args as $k => $arg) {
        if($k > 0) {
            echo ', ';
        }
        echo count($arg);
    }
    echo '</pre>';
}