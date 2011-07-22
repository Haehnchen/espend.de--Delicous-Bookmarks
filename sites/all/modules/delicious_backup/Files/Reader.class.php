<?php
class Reader {

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
    
    $this->obj = db_query("SELECT nid,bid,hash,href FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $this->node->nid))->fetchObject();
    $this->url = $this->obj->href;
    
    if ($cache == true) {
      if (!$this->html = file_get_contents('public://delicious_backup/' . $this->obj->hash)) {
        watchdog('delicious_backup', 'Error getting hash file %id - %url ', array('%id' => $this->obj->bid, '%url' => $this->obj->href));
      }
      
      if ($node) {
        $this->content = $this->node->body[$this->node->language][0]['value'];
      }

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

      $file = 'public://delicious_backup/' . $obj->hash;

      // validate file
      if (!file_exists($file)) throw new Exception('no file content');
      if ($this->FileIsBinary($file)) throw new Exception('file is binary'); //@TODO: PDF download or other?
      if (!$html = file_get_contents($file)) throw new Exception('error reading hash file');
      if (strlen($html) == 0) throw new Exception('zero content');
      if ($obj->nid == 0) throw new Exception('no node nid found');

      $this->html = $html;

      $this->log('got content html');

      $this->content = DeliciousBackup::Readability($this->html);
      $this->content = mb_convert_encoding($this->content, 'HTML-ENTITIES', "UTF-8");

      $this->node = node_load($obj->nid);

      if ($this->getimages == true) {
        $this->ImagesDownload();
        $this->ReplaceImages();
      }

      #$this->content = delicious_backup_filter($this->content, false, $this->node);
      #$this->FilterContent();

      $this->node = node_load($obj->nid);
      $this->node->body[$this->node->language][0]['value'] = $this->content;
      $this->node->body[$this->node->language][0]['format'] = 'full_html';

      node_save($this->node);

      return true;

    } catch (Exception $e) {
      watchdog('delicious_backup', 'error on bid: ' . $obj->bid . ' ' . $e->getMessage());
      return false;
    }  

  }  
  
  function ReplaceImages() {

      if (!$field = field_get_items('node', $this->node, 'delicious_bookmark_image', $this->node->language)) {
        return;
      }

      $imgs = array();
      foreach($field as $img) {
        $imgs[$img['filename']] = array(
          'width' => '200',
          'src' => file_create_url($img['uri']),
         );

        if ($img['alt']) $imgs[$img['filename']]['alt'] = $img['alt'];
        if ($img['title']) $imgs[$img['filename']]['title'] = $img['title'];

      };

      $this->_ReplaceExternalImages($imgs);
  }  
  
  function ImagesDownload() {

    if (!count($imgs = $this->GetImagesInfo($this->content)) > 0) return false;


    foreach($imgs as $img) {
      $this->ImageAttach($img);
    }

    return true;
  }  
  
  private function ImageAttach($img_ar) {
    try {
      $node = $this->GetNode();
      
      // check if image already attached to this node
      if (DeliciousBackup::ImageIsAttached('delicious_bookmark_image', $node, $img_ar['absolute_url']))
        return true;

      // download image and attach to node

      $img_path = 'public://link_image/'. $node->nid .'/' . basename($img_ar['absolute_url']);
      file_prepare_directory($dest_path = dirname($img_path), FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

      if (!file_exists($img_path)) {
        $res = drupal_http_request($img_ar['absolute_url']);
        if ($res->code != 200) throw new Exception('error getting image: ' . $img_ar['absolute_url']);
        file_put_contents($img_path, $res->data);    
      }
      
      if (!DeliciousBackup::FileIsImage($img_path)) {
        unlink($img_path);
        throw new Exception('error getting image: ' . t('Only JPEG, PNG and GIF images are allowed.') . ' : '. $img_ar['absolute_url']);
      }      

      // create a file object to attach
      $file = DeliciousBackup::UriToFile($img_path);

      // attributes
      if (isset($img_ar['alt'])) $file->alt = $img_ar['alt'];
      if (isset($img_ar['title'])) $file->title = $img_ar['title'];    

      DeliciousBackup::AttachFileToNode($node, 'delicious_bookmark_image', $file);

      $this->log('Image downloaded: ' . $img_ar['absolute_url']);

    } catch (Exception $e) {
      watchdog_exception('delicious_backup', $e);
      return false;
    }

    return true;  
  }

  private function CreateDOM($html) {
    
    $oldSetting = libxml_use_internal_errors(true);
    libxml_clear_errors();    
    
    
    $doc = new DOMDocument();
    #$doc->encoding = 'UTF-8'; // insert proper
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    
    libxml_clear_errors();
    libxml_use_internal_errors( $oldSetting );    
    
    return $doc;
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
    $doc = $this->CreateDOM($this->content);

    $xpath = new DOMXPath($doc);

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

      $imgs[] = $img_t;

    }

    return $imgs;

  }

  function GetImagesReplacedMarker($content = '') {
  
    $doc = $this->CreateDOM($content);
 
    $xpath = new DOMXPath($doc);

    $imgs = array();

    $baseurl = $this->GetBaseUrl();

    $img_count = 0;

    foreach($xpath->query( "//img") as $element) {

      $image_marker = "[IMAGE-" . $img_count++ . "]";

      // check if image is a thumbnail; mostly it is clickable (lightbox)
      if ($element->parentNode->nodeName == 'a' AND preg_match('@\.(png|jpg|gif|jpeg)$@i', $element->parentNode->getAttribute( 'href')) AND url_to_absolute($baseurl, $element->parentNode->getAttribute( 'href' )) != $element->getAttribute( 'src' )) {
        // lightbox elements
        $element->parentNode->parentNode->replaceChild($doc->createTextNode($image_marker), $element->parentNode);
      } else {
        $element->parentNode->replaceChild($doc->createTextNode($image_marker), $element);
      }


    }

    return $this->myinnerHTML($doc);
  }

  private function _is_img_url($url) {
    return preg_match('@\.(png|jpg|gif|jpeg)$@i', $url);
  }
  
  private function _ReplaceExternalImages($imgs) {
    $doc = $this->CreateDOM($this->content);

    $xpath = new DOMXPath($doc);

    foreach($xpath->query( "//img") as $element) {
      
      $ele = null; $img_src = null;

      if ($element->parentNode->nodeName == 'a') {
        
        // lightbox images
        if ($this->_is_img_url($element->parentNode->getAttribute('href'))) {
          $ele = $element->parentNode;
          $img_src = $element->parentNode->getAttribute('href');
        } else {
          
          // image link to external !?
          $img_src = $element->getAttribute('src');
          $ele = $element;
        }
        
      } else {

        // normal image tags
        if ($this->_is_img_url($element->getAttribute('src'))) {
          $ele = $element;
          $img_src = $element->getAttribute('src');          
        }
        
      }
      
      
      // Replace external image src tags with internal if we get a internal url
      if ($ele AND isset($imgs[basename($img_src)])) {
          
        // create new image element and add attributes
        $new_img = $doc->createElement('img');
        foreach($imgs[basename($img_src)] as $attr => $value) $new_img->setAttribute($attr, $value);

        $ele->parentNode->replaceChild($new_img, $ele);
        //$ele->parentNode->replaceChild($doc->createTextNode('test'), $ele);
        //$ele->parentNode->replaceChild($doc->createTextNode($image_marker), $ele);
      } else {
        
        // no image to replace found remove it
        if ($ele) $ele->parentNode->removeChild($ele);
      }
        
        
      if (!$ele) {
        //image not found or unknown format; remove it
        $element->parentNode->removeChild($element);
      }


    }


    $this->content = $this->myinnerHTML($doc);
  }   
  
  function ReplaceExternalImages($content, $imgs) {
    $doc = $this->CreateDOM($content);

    $xpath = new DOMXPath($doc);

    foreach($xpath->query( "//img") as $element) {
      
      $ele = null; $img_src = null;

      if ($element->parentNode->nodeName == 'a') {
        
        // lightbox images
        if ($this->_is_img_url($element->parentNode->getAttribute('href'))) {
          $ele = $element->parentNode;
          $img_src = $element->parentNode->getAttribute('href');
        } else {
          
          // image link to external !?
          $img_src = $element->getAttribute('src');
          $ele = $element;
        }
        
      } else {

        // normal image tags
        if ($this->_is_img_url($element->getAttribute('src'))) {
          $ele = $element;
          $img_src = $element->getAttribute('src');          
        }
        
      }
      
      
      // Replace external image src tags with internal if we get a internal url
      if ($ele AND isset($imgs[basename($img_src)])) {
          
        // create new image element and add attributes
        $new_img = $doc->createElement('img');
        foreach($imgs[basename($img_src)] as $attr => $value) $new_img->setAttribute($attr, $value);

        $ele->parentNode->replaceChild($new_img, $ele);
        //$ele->parentNode->replaceChild($doc->createTextNode('test'), $ele);
        //$ele->parentNode->replaceChild($doc->createTextNode($image_marker), $ele);
      } else {
        
        // no image to replace found remove it
        if ($ele) $ele->parentNode->removeChild($ele);
      }
        
        
      if (!$ele) {
        //image not found or unknown format; remove it
        $element->parentNode->removeChild($element);
      }


    }


    return $this->myinnerHTML($doc);
  }  
  
  function GetBaseUrl() {

    $doc = $this->CreateDOM($this->html);    

    $xpath = new DOMXPath($doc);

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

    
    $this->content = preg_replace('/<!--(.*)-->/Uis', '', $this->content);
    #$this->content = preg_replace('/<p>&nbsp;<\/p>/Uis', '', $this->content);
    $this->content = _filter_autop(htmLawed($this->content, $config));
    
    // replace external images with internal
    if ($this->node) 
      $this->ReplaceImages();
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
  
}





?>