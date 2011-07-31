<?php

class DmImages extends DmBase {
  
  const FIELD = 'delicious_bookmark_image';
  const DIR_IMAGES = 'images';
  
  
  public function preFilter() {
    $this->log(__CLASS__ . ':' . __FUNCTION__);
    $this->ImagesDownload();
  }
  
  public function postFilter() {
    $this->log(__CLASS__ . ':' . __FUNCTION__);    
    $this->ReplaceImages();
  }


  private function ImagesDownload() {

    // nothing todo here
    if ($this->getimages == false OR !count($imgs = $this->GetImagesInfo($this->obj->content)) > 0) return;

    foreach($imgs as $img) {
      try {
        
        $img_path = $this->GetDirectory(self::DIR_IMAGES, $this->TransliterateFilename(basename($img['absolute_url'])));

        // check if image already attached to this node
        if (!DeliciousBackup::ImageIsAttached(self::FIELD, $this->obj->node, $img_path)) {
          
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

          DeliciousBackup::AttachFileToNode($this->obj->node, self::FIELD, $file, true);

          $this->log('Image downloaded: ' . $img['absolute_url']);
          
        }

      } catch (Exception $e) {
        $this->log('ImageAttach: ' . $e->getMessage());
        watchdog_exception('delicious_backup', $e);
      }
    }

    return true;
  }  
  
  private function ReplaceImages() {

      if (!$field = field_get_items('node', $this->obj->node, self::FIELD, $this->obj->node->language)) {
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
  
  private function GetImagesInfo() {

    $xpath = new DOMXPath($this->CreateDOM($this->obj->content));

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
      if ($parent->nodeName == 'a' AND $this->IsImgUrl($parent->getAttribute( 'href')) AND url_to_absolute($baseurl, $parent->getAttribute( 'href' )) != $img_t['absolute_url']) {
        $img_t['thumbnail'] = $img_t['absolute_url'];
        $img_t['absolute_url'] = url_to_absolute($baseurl, $parent->getAttribute( 'href' ));
        $img_t['parent'] = '1';

        // use title tag of link is set
        if (!isset($img_t['title']) AND $parent->getAttribute('title')) $img_t['title'] = $parent->getAttribute('title');
      }

      if ($this->IsImgUrl($img_t['absolute_url'])) $imgs[] = $img_t;

    }

    return $imgs;

  }  
  
 private function ReplaceExternalImages($imgs) {

    $doc = $this->CreateDOM($this->obj->content);

    $xpath = new DOMXPath($doc);

    foreach ($xpath->query("//img") as $element) {

      $ele = $element;
      $img_src = $element->getAttribute('src');

      if ($element->parentNode->nodeName == 'a' AND $this->IsImgUrl($url = url_to_absolute($this->obj->url, $element->parentNode->getAttribute('href')))) {
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


    $this->obj->content = $this->myinnerHTML($doc);
  }  
  
  static public function fields() {
    $t = get_t();
    $field = array(
      self::FIELD => array(
        'field_name' => self::FIELD,
        'cardinality' => FIELD_CARDINALITY_UNLIMITED,
        'type' => 'image',
        'settings' => array(
          'uri_scheme' => 'public',
        ),
      ),
    );

    $instance = array(
      self::FIELD => array(
        'field_name' => self::FIELD,
        'label' => $t('Image'),
        'settings' => array(
          'file_extensions' => 'png gif jpg jpeg',
        ),
      ),
    );

    return array(
      'fields' => $field,
      'instances' => $instance,
    );
  }   
  
}
?>
