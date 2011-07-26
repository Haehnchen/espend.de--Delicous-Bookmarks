<?php
/*
 * http://www.rainer-grundel.de/wissensdb/typo3/module/artikel/article/rssxml_newsfeeds_erstellen.html
 */

class Reader {

  const FIELD_ATTACH_IMAGE = 'delicious_bookmark_image';
  const DIR_IMAGES = 'images';
  const DIR_HTML = '';
  
  const EXTERNAL_HTML_FILENAME = 'html';
  
  const ERROR_LOG = 1;
  const ERROR_THROW = 2;
  const ERROR_LOG_AND_THROW = 4;
  const ERROR_WATCHDOG = 8;
  
  
  var $html = '';
  var $url = '';
  var $content = '';
  var $node = null;
  var $nid = null;
  var $getimages = true;
  var $obj = null;

  var $logArray = array();

  function __construct($node, $cache = false) {

    $this->node = (is_numeric($node)) ? node_load($node) : $node;
    if(!$this->node)
      $this->Exception('node unknown');

    $this->obj = db_query("SELECT nid,bid,hash,href FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $this->node->nid))->fetchObject();
    if(!$this->obj)
      $this->Exception('database error');            
    
    $this->url = $this->obj->href;

    if ($cache == true) {
      if (!$this->html = file_get_contents($this->GetDirectory(self::DIR_HTML, self::EXTERNAL_HTML_FILENAME))) {
        watchdog('delicious_backup', 'Error getting hash file %id - %url ', array('%id' => $this->obj->bid, '%url' => $this->obj->href));
      }

      $this->content = $this->node->body[$this->node->language][0]['value'];

    }

  }

  private function GetNode() {
    if ($this->node == null)
      $this->node = node_load($this->nid);

    return $this->node;
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

      if (!$this->html = file_get_contents($file))
        $this->Exception('error reading hash file');

      if (strlen($this->html) == 0)
        $this->Exception('zero content');

      if ($obj->nid == 0)
        $this->Exception('no node nid found');

      if (isset($node->nid))
        $this->Exception('not a valid node');

      $this->log('got content html');      
      
      $this->content = DeliciousBackup::Readability($this->html);
      $this->log('filter with Readability');      

      // download image but only when set in var
      $this->ImagesDownload();

      $this->FilterContent();

      $this->node->body[$this->node->language][0]['value'] = $this->content;
      $this->node->body[$this->node->language][0]['format'] = 'full_html';

      node_save($this->node);
      $this->log('node updated');      

      return true;

    } catch (Exception $e) {
      $this->Exception('error on bid: ' . $obj->bid, self::ERROR_WATCHDOG);
      return false;
    }

  }

  function ReplaceImages() {

      if (!$field = field_get_items('node', $this->node, self::FIELD_ATTACH_IMAGE, $this->node->language)) {
        return;
      }
      
      $this->log('replacing ' . count($field) . ' images');

      $imgs = array();
      foreach($field as $img) {
        $imgs[$img['filename']] = array(
          'width' => '200',
          'src' => file_create_url($img['uri']),
         );

        if ($img['alt']) $imgs[$img['filename']]['alt'] = $img['alt'];
        if ($img['title']) $imgs[$img['filename']]['title'] = $img['title'];

      };
      
      $this->ReplaceExternalImages($imgs);
  }

  private function HTTPDownload($url, $outputfile, $overwrite = false) {

    $opts = array(
        'timeout' => 10,
    );

    if ($overwrite == false AND file_exists($outputfile))
      return true;

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

  private function TransliterateFilename($filename) {

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
  
  private function GetDirectory($type, $filename = '') {
    
    // we dont want double slahes
    if ($filename != '') $filename = '/' . $filename;
    if ($type != '') $type= '/' . $type;

    return DELICIOUS_BACKUP_ROOT_DIR . $this->obj->hash . $type . $filename;
  }  
  
  function ImagesDownload() {

    // nothing todo here
    if ($this->getimages == false OR !count($imgs = $this->GetImagesInfo($this->content)) > 0) return;

    foreach($imgs as $img) {
      try {
        
        $img_path = $this->GetDirectory(self::DIR_IMAGES, $this->TransliterateFilename(basename($img['absolute_url'])));

        // check if image already attached to this node
        if (!DeliciousBackup::ImageIsAttached(self::FIELD_ATTACH_IMAGE, $this->node, $img_path)) {
          
          // download image and attach to node
          $this->HTTPDownload($img['absolute_url'], $img_path);

          if (!DeliciousBackup::FileIsImage($img_path)) {
            if (file_exists($img_path)) unlink($img_path);
            $this->Exception('error getting image: ' . t('Only JPEG, PNG and GIF images are allowed.') . ' : '. $img['absolute_url']);
          }

          // create a file object to attach
          $file = DeliciousBackup::UriToFile($img_path);

          // attributes
          if (isset($img['alt'])) $file->alt = $img['alt'];
          if (isset($img['title'])) $file->title = $img['title'];

          DeliciousBackup::AttachFileToNode($this->node, self::FIELD_ATTACH_IMAGE, $file, true);

          $this->log('Image downloaded: ' . $img['absolute_url']);
          
        }

      } catch (Exception $e) {
        $this->log('ImageAttach: ' . $e->getMessage());
        watchdog_exception('delicious_backup', $e);
      }
    }

    return true;
  }

  private function CreateDOM($html) {
    return filter_dom_load($html);
  }

  function GetHtml() {
    $this->html = file_get_contents($this->url);
  }

  function SetHtml($html) {
    $this->html = $html;
  }

  function GetImagesInfo() {

    /*
     *     [2] => Array
        (
            [src] => http://0.gravatar.com/avatar/24bb29f53072c133590b929e56d6e298?s=90&d=http%3A%2F%2F0.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D90&r=G
            [absolute_url] => http://0.gravatar.com/avatar/24bb29f53072c133590b929e56d6e298?s%3D90%26d%3Dhttp%3A%2F%2F0.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D90%26r%3DG
        )
     *
     */
    $xpath = new DOMXPath($this->CreateDOM($this->content));

    $imgs = array();

    $baseurl = $this->GetBaseUrl();

    foreach($xpath->query( "//img") as $element) {
      // add image data to array
      $img_t = array(
       'src' => $src = $element->getAttribute( 'src' ),
       'absolute_url' => url_to_absolute($baseurl, $src),
      );

      if ($element->getAttribute('title')) $img_t['title'] = $element->getAttribute('title');
      if ($element->getAttribute('alt')) $img_t['alt'] = $element->getAttribute('alt');

      // check if image is a thumbnail; mostly it is clickable (lightbox)
      $parent = $element->parentNode;
      if ($parent->nodeName == 'a' AND $this->_is_img_url($parent->getAttribute( 'href')) AND url_to_absolute($baseurl, $parent->getAttribute( 'href' )) != $img_t['absolute_url']) {
        $img_t['thumbnail'] = $img_t['absolute_url'];
        $img_t['absolute_url'] = url_to_absolute($baseurl, $parent->getAttribute( 'href' ));
        $img_t['parent'] = '1';

        // use title tag of link is set
        if (!isset($img_t['title']) AND $parent->getAttribute('title')) $img_t['title'] = $parent->getAttribute('title');
      }

      if ($this->_is_img_url($img_t['absolute_url'])) $imgs[] = $img_t;

    }

    return $imgs;

  }

  private function _is_img_url($url) {
    return preg_match('@\.(png|jpg|gif|jpeg)$@i', $url);
  }

  private function ReplaceExternalImages($imgs) {

    $doc = $this->CreateDOM($this->content);

    $xpath = new DOMXPath($doc);

    foreach ($xpath->query("//img") as $element) {

      $ele = $element;
      $img_src = $element->getAttribute('src');

      if ($element->parentNode->nodeName == 'a' AND $this->_is_img_url($url = url_to_absolute($this->url, $element->parentNode->getAttribute('href')))) {
        // @TODO: $url can contant javascript: http://www.rainer-grundel.de/wissensdb/typo3/module/artikel/article/rssxml_newsfeeds_erstellen.html
        // 
        // lightbox pictures
        $ele = $element->parentNode;
        $img_src = $element->parentNode->getAttribute('href');
      }


      $filename = $this->TransliterateFilename(basename($img_src));

      // Replace external image src tags with internal if we get a internal url
      if ($ele AND isset($imgs[$filename])) {

        // create new image element and add attributes
        $new_img = $doc->createElement('img');
        foreach ($imgs[$filename] as $attr => $value)
          $new_img->setAttribute($attr, $value);

        $ele->parentNode->replaceChild($new_img, $ele);

      } else {

        // no image to replace found; remove it
        $ele->parentNode->removeChild($ele);
      }

    }


    $this->content = $this->myinnerHTML($doc);
  }

  function GetBaseUrl() {

    $xpath = new DOMXPath($this->CreateDOM($this->html));

    //http://www.compago.it/php/phpckbk-CHP-13-SECT-17.html

    // Compute the Base URL for relative links
    // Check if there is a <base href=""/> in the page
    $nodeList = $xpath->query('//base/@href');
    if ($nodeList->length == 1)
      return $nodeList->item(0)->nodeValue;


    // return fallback (mostly complete url for save if in main app)
    return $this->url;
  }

  private function myinnerHTML($doc){
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

    $this->content = preg_replace('/<!--(.*)-->/Uis', '', $this->content);
    #$this->content = preg_replace('/<p>&nbsp;<\/p>/Uis', '', $this->content);
    $this->content = _filter_autop(htmLawed($this->content, $config));

    // replace external images with internal
    if ($this->node) {
      $this->ReplaceImages();
    }
  }

  private function log($str) {
    $this->logArray[] = $str;
  }

  public function GetLog() {
    return implode("\r\n", $this->logArray);
  }

  public function GetContent() {
    return $this->content;
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

  
  private function Exception($msg, $severity = self::ERROR_LOG_AND_THROW, Exception $e = null) {

    if($e) 
      watchdog_exception($e);
    
    if ($severity & self::ERROR_LOG OR $severity & self::ERROR_LOG_AND_THROW)
      $this->log($msg);
    
    if ($severity & self::ERROR_WATCHDOG)
      watchdog('delicious_backup', $msg);
    
    if ($severity & self::ERROR_THROW OR $severity & self::ERROR_LOG_AND_THROW)
      throw new Exception($msg);
    
  }
}



?>