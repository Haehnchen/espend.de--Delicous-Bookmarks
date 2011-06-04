<?php
class Reader {

  var $html = '';
  var $url = '';
  var $content = '';

  function __construct($url = '', $html = '', $content = '') {
    $this->html = $html;
    $this->url = $url;
    $this->content = $content;

  }


  function GetHtml() {
    $this->html = file_get_contents($this->url);
  }

  function SetHtml($html) {
    $this->html = $html;
  }

  function GetImagesInfo ($content = '') {

    /*
     *     [2] => Array
        (
            [src] => http://0.gravatar.com/avatar/24bb29f53072c133590b929e56d6e298?s=90&d=http%3A%2F%2F0.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D90&r=G
            [absolute_url] => http://0.gravatar.com/avatar/24bb29f53072c133590b929e56d6e298?s%3D90%26d%3Dhttp%3A%2F%2F0.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D90%26r%3DG
        )
     *
     */

    $doc = new DOMDocument();
    $doc->loadHTML($content);

    $xpath = new DOMXPath($doc);

    $imgs = array();

    $baseurl = $this->GetPaseUrl($this->url);

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
      if ($parent->nodeName == 'a' AND preg_match('@\.(png|jpg|gif|jpeg)$@i', $parent->getAttribute( 'href')) AND url_to_absolute($baseurl, $parent->getAttribute( 'href' )) != $img_t['absolute_url']) {
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
    $doc = new DOMDocument();
    $doc->loadHTML($content);

    $xpath = new DOMXPath($doc);

    $imgs = array();

    $baseurl = $this->GetPaseUrl($this->url);

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

    return $doc->saveHTML();
  }

  private function _is_img_url($url) {
    return preg_match('@\.(png|jpg|gif|jpeg)$@i', $url);
  }
  
  function ReplaceExternalImages($content, $imgs) {
    $doc = new DOMDocument();
    $doc->loadHTML($content);

    $xpath = new DOMXPath($doc);

    foreach($xpath->query( "//img") as $element) {
      
      $ele = null; $img_src = null;

      if ($element->parentNode->nodeName == 'a') {
        
        // lightbox images
        if ($this->_is_img_url($element->parentNode->getAttribute('href'))) {
          $ele = $element->parentNode;
          $img_src = $element->parentNode->getAttribute('href');
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


      /*
      // check if image is a thumbnail; mostly it is clickable (lightbox)
      if ($element->parentNode->nodeName == 'a' AND preg_match('@\.(png|jpg|gif|jpeg)$@i', $element->parentNode->getAttribute('href')) AND url_to_absolute($baseurl, $element->parentNode->getAttribute( 'href' )) != $element->getAttribute( 'src' )) {
        // lightbox elements
        $element->parentNode->parentNode->replaceChild($doc->createTextNode($image_marker), $element->parentNode);
      } else {
        $element->parentNode->replaceChild($doc->createTextNode($image_marker), $element);
      }
*/


    }


    return $doc->saveHTML();
  }  
  
  function GetPaseUrl($fallbackurl = '') {

  $oldSetting = libxml_use_internal_errors( true );
  libxml_clear_errors();

    $doc = new DOMDocument();
    $doc->loadHTML($this->html);

    $xpath = new DOMXPath($doc);

    //http://www.compago.it/php/phpckbk-CHP-13-SECT-17.html

    // Compute the Base URL for relative links
    // Check if there is a <base href=""/> in the page
    $nodeList = $xpath->query('//base/@href');
    if ($nodeList->length == 1)
      return $nodeList->item(0)->nodeValue;


    // return fallback (mostly complete url for save if in main app)
    if ($fallbackurl != '') return $fallbackurl;

    return false;
  }

}
?>