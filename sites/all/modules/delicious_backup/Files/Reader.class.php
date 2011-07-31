<?php
class DeliciousBackupReader {

  const DIR_HTML = '';
  
  const EXTERNAL_HTML_FILENAME = 'html';
  
  const ERROR_LOG = 1;
  const ERROR_THROW = 2;
  const ERROR_LOG_AND_THROW = 4;
  const ERROR_WATCHDOG = 8;
  
  var $getimages = true;
  var $obj = null;

  function __construct($node, $cache = false) {
    $this->obj = new DeliciousBackupReaderObj();
    
    if(!$this->obj->node = (is_numeric($node)) ? node_load($node) : $node)
      $this->Exception('node unknown');

    $obj = db_query("SELECT nid,bid,hash,href FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $this->obj->node->nid))->fetchObject();
    if(!$obj) $this->Exception('database error');
    
    
    $this->obj->nid = $obj->nid;
    $this->obj->bid = $obj->bid;
    $this->obj->hash = $obj->hash;
    $this->obj->href = $obj->href;
    $this->obj->url = $obj->href;
    
    if ($cache == true) {
      if (!$this->obj->html = @file_get_contents($this->GetDirectory(self::DIR_HTML, self::EXTERNAL_HTML_FILENAME))) {
        $this->log('no html found download external html');
        $this->GetExternalHTML();
        //$this->Exception('Error getting hash file ' . $this->obj->bid .' - ' . $this->obj->href, self::ERROR_LOG_AND_THROW);
      }

      if (isset($this->obj->node->body[$this->obj->node->language])) $this->obj->content = $this->obj->node->body[$this->obj->node->language][0]['value'];

    }
    
    $this->obj->html = $this->obj->html;
    $this->obj->content = $this->obj->content;

  }

  private function GetNode() {
    if ($this->obj->node == null)
      $this->obj->node = node_load($this->nid);

    return $this->obj->node;
  }
  
  private function CallinternalHook($hook) {
    //module_invoke_all('delicious_backup_pre_filter', $this);    
    $path = drupal_get_path('module', 'delicious_backup') . '/filters';
    require_once $path . '/DmBase.class.php' ;

    foreach(file_scan_directory($path, '/.*\.class.php$/') as $inc) {
      $class = str_replace('.class.php', '', $inc->filename);
      if ($class == 'DmBase') continue;
              
      require_once $inc->uri;

      $filter = new $class($this->obj);
      $filter->{$hook}();
      $this->obj = $filter->obj;
      
    }
  }  
  
  function UpdateNode() {

    try {
      $obj = $this->obj;

      if (is_numeric($obj)) $obj = db_query("SELECT nid,bid,hash,href FROM {delicious_bookmarks_backup} WHERE bid = :bid", array(':bid' => $obj))->fetchObject();

      $file = $this->GetDirectory(self::DIR_HTML, self::EXTERNAL_HTML_FILENAME);

      // validate file
      if (!file_exists($file))
        $this->Exception('no file content');

      if ($this->FileIsBinary($file))
        $this->Exception('file is binary'); //@TODO: PDF download or other?

      if (!$this->obj->html = file_get_contents($file))
        $this->Exception('error reading hash file');

      if (strlen($this->obj->html) == 0)
        $this->Exception('zero content');

      if ($obj->nid == 0)
        $this->Exception('no node nid found');

      if (isset($node->nid))
        $this->Exception('not a valid node');

      $this->log('got content html');      

      $this->obj->content = DeliciousBackup::Readability($this->obj->html);
      $this->log('filter with Readability');      
      
      $this->log('invoke: delicious_backup_pre_filter');
      $this->CallinternalHook('PreFilter');

      $this->FilterContent();
      $this->CallinternalHook('PostFilter');
      
      $this->obj->node->body[$this->obj->node->language][0]['value'] = $this->obj->content;
      $this->obj->node->body[$this->obj->node->language][0]['format'] = 'full_html';

      node_save($this->obj->node);
      $this->log('node updated');      

      return true;

    } catch (Exception $e) {
      $this->Exception('error on bid: ' . $obj->bid, self::ERROR_WATCHDOG);
      return false;
    }

  }

  protected function HTTPDownloadAutoPath($url, $filename = '', $overwrite = false) {
    
    if ($filename == '') $filename = md5($url);
    
    $subpath = isset($this->OverwriteHTTPDir) ? $this->OverwriteHTTPDir : strtolower(get_class($this));
    $path = DELICIOUS_BACKUP_ROOT_DIR . $this->obj->hash . '/' . $subpath;
    
    return $this->HTTPDownload($url, $path . '/' . $filename, $overwrite);
  }
  
  protected function HTTPDownload($url, $outputfile, $overwrite = false) {

    $opts = array(
        'timeout' => 10,
    );

    if ($overwrite == false AND file_exists($outputfile)) {
      $test = new stdClass();
      $test->code = 200;
      $test->data = file_get_contents($outputfile);
      $test->cached = true;
      return $test;
    }

    $res = drupal_http_request($url, $opts);

    if ($res->code != 200)
      $this->Exception('error getting file: ' . $url);

    // create fiel directory set rights; drush uses other user!
    file_prepare_directory($dest_path = dirname($outputfile), FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    drupal_chmod($dest_path);
    
    file_put_contents($outputfile, $res->data);
    drupal_chmod($outputfile);

    return $res;
  }

  protected function TransliterateFilename($filename) {

    $filename = urldecode($filename);    
    
    /*if (function_exists('transliteration_clean_filename')) {
      return transliteration_clean_filename($filename);
    }*/
    
    $filename = urldecode($filename);
    $filename = str_replace(' ', '_', $filename);
    // Remove remaining unsafe characters.
    $filename = preg_replace('![^0-9A-Za-z_.-]!', '', $filename);
    // Force lowercase to prevent issues on case-insensitive file systems.
    $filename = strtolower($filename);
    
    return $filename;
  }
  
  protected function GetDirectory($type, $filename = '') {
    
    // we dont want double slahes
    if ($filename != '') $filename = '/' . $filename;
    if ($type != '') $type= '/' . $type;

    return DELICIOUS_BACKUP_ROOT_DIR . $this->obj->hash . $type . $filename;
  }  
 
  protected function CreateDOM($html) {
    return filter_dom_load($html);
  }

  protected function IsImgUrl($url) {
    return preg_match('@\.(png|jpg|gif|jpeg)$@i', $url);
  }

  function GetBaseUrl() {

    $xpath = new DOMXPath($this->CreateDOM($this->obj->html));

    //http://www.compago.it/php/phpckbk-CHP-13-SECT-17.html

    // Compute the Base URL for relative links
    // Check if there is a <base href=""/> in the page
    $nodeList = $xpath->query('//base/@href');
    if ($nodeList->length == 1)
      return $nodeList->item(0)->nodeValue;


    // return fallback (mostly complete url for save if in main app)
    return $this->obj->url;
  }

  protected function myinnerHTML($doc){
    return filter_dom_serialize($doc);

    //http://svn.beerpla.net/repos/public/PHP/SmartDOMDocument/trunk/SmartDOMDocument.class.php
		return preg_replace(array("/^\<\!DOCTYPE.*?<html><body>/si", "!</body></html>$!si"), '', $doc->saveHTML());
  }

  private function FileIsBinary($file) {
    if (file_exists($file)) {
      if (!is_file($file)) return 0;

      $fh  = fopen($file, "r");
      $blk = fread($fh, 512);
      fclose($fh);
      clearstatcache();

      return (
        //0 or substr_count($blk, "^ -~", "^\r\n")/512 > 0.3
          substr_count($blk, "\x00") > 0
      );
    }
    return 0;
  }

  private function FilterContent() {

    require_once drupal_get_path('module', 'delicious_backup') . '/Files/htmLawed.php';

    $config = array('safe'=>1, 'elements'=>'div, br, img, h1, h2, h3, h4 ,h5, a, em, b, strong, cite, code, ol, ul, li, dl, dt, dd, p, div, span, code, blockquote, pre', 'deny_attribute'=>'id, style, class');

    $this->log('filtering content with htmLawed');

    $this->obj->content = preg_replace('/<!--(.*)-->/Uis', '', $this->obj->content);
    #$this->obj->content = preg_replace('/<p>&nbsp;<\/p>/Uis', '', $this->obj->content);
    $this->obj->content = _filter_autop(htmLawed($this->obj->content, $config));

  }

  public function log($str) {
    $this->obj->logArray[] = $str;
  }

  public function GetLog() {
    return implode("\r\n", $this->obj->logArray);
  }

  public function GetContent() {
    return $this->obj->content;
  }


  public function GetExternalHTML() {
    
        
    $filename = $this->GetDirectory(self::DIR_HTML, self::EXTERNAL_HTML_FILENAME);
    try {

      // simple check to not download binary links here depens on url
      if (preg_match('/\.(pdf|mp4|png|gif|jpeg|jpg|mp3|flv|doc|docx)$/i', $this->obj->href))
        $this->Exception('binary file?');

      // get only headers of file; get_headers do redirect and provide an array so filter it tricky
      $head = @get_headers($this->obj->href, 1);
      if (isset($head['Content-Type']) AND !preg_match('@(text|html)@i', is_array($head['Content-Type']) ? end($head['Content-Type']) : $head['Content-Type']))
        $this->Exception('url response: is not html');

      if (isset($head['Content-Length']) AND (is_array($head['Content-Length']) ? end($head['Content-Length']) : $head['Content-Length'])  > 1024 * 1014 * 5)
        $this->Exception('url response: file to large');


      $this->HTTPDownload($this->obj->href, $filename);

      module_invoke_all('delicious_backup_updated', $this->obj->bid);

      db_update('delicious_bookmarks_backup')->fields(array('response_code' => 200, 'content_fetched' => time(), 'content_updated' => time(), 'queued' => 0))->condition('bid', $this->obj->bid)->execute();
      watchdog('delicious_backup', 'OK getting html content %id - %url ', array('%id' => $this->obj->bid, '%url' => $this->obj->href));
    } catch (Exception $e) {
      db_update('delicious_bookmarks_backup')
              ->fields(array(
                  'content_fetched' => time(),
                  'response_code' => 0,
                  'queued' => 0,
              ))
              ->expression('response_errors', 'response_errors + 1')
              ->condition('bid', $this->obj->bid)->execute();

      watchdog('delicious_backup', 'Error getting html content: %msg - %id - %url ', array('%msg' => $e->getMessage(), '%id' => $this->obj->bid, '%url' => $this->obj->href), WATCHDOG_ALERT);
    }
  }

  
  protected function Exception($msg, $severity = self::ERROR_LOG_AND_THROW, Exception $e = null) {

    if($e) 
      watchdog_exception($msg, $e);
    
    if ($severity & self::ERROR_LOG OR $severity & self::ERROR_LOG_AND_THROW)
      $this->log($msg);
    
    if ($severity & self::ERROR_WATCHDOG)
      watchdog('delicious_backup', $msg);
    
    if ($severity & self::ERROR_THROW OR $severity & self::ERROR_LOG_AND_THROW)
      throw new Exception($msg);
    
  }

}

  class DeliciousBackupReaderObj {
    
    var $nid = 0;
    var $bid = 0;
    var $hash = '';
    var $href = '';
    var $url = '';
    
    var $content = '';
    var $html = '';
    var $node = null;
    var $logArray = array();
    
    function __construct() {

    }
    
  }


?>