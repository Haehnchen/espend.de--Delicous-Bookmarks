<?php

class DeliciousBackup {
  
  static function RequestHeaders() {
    return array(
      'User-Agent' => 'drupal.contributions.delicious_backup.module/1.0',
    );
  }
  
  static function GetResponse($type = 'posts/all?') {
    $url = "https://". variable_get('delicious_backup_username') .":". variable_get('delicious_backup_password') ."@api.del.icio.us/v1/";
    $res = drupal_http_request($url . $type, array('headers' => self::RequestHeaders()));
    #print_r($res); exit;
    
    if ($res->code != 200) return false;
    
    return $res->data;     
  }

  static function CreateDOM($html) {
    
    $oldSetting = libxml_use_internal_errors(true);
    libxml_clear_errors();    
    
    
    $doc = new DOMDocument();
    #$doc->encoding = 'UTF-8'; // insert proper
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    
    libxml_clear_errors();
    libxml_use_internal_errors( $oldSetting );    
    
    return $doc;
  }
  
  static function RemoveDivIDs($html, $unwated = array('footer','header', 'sidebar', 'credits', 'copyright', 'navigation', 'head', 'edit-comment-body', 'comments', 'nav')) {

    $doc = self::CreateDOM($html);

    $xpath = new DOMXPath($doc);

    //theChildNode[intersect(tokenize(@name, '\s+'),('one','all'))]
    foreach($unwated as $removeid) {
      foreach($xpath->query( "//div[@id='$removeid']") as $element){
        $element->parentNode->removeChild($element); // This is a hint from the manual comments.
      }
    }

    foreach($unwated as $removeid) {
      foreach($xpath->query( "//div[contains(@id,'$removeid')]") as $element){
        $element->parentNode->removeChild($element); // This is a hint from the manual comments.
      }
    }

    return $doc->saveHTML();    
  }
  
  static function GetDivByID($html, $wanted = array('content','center')) {
    $doc = self::CreateDOM($html);

    $xpath = new DOMXPath($doc);

    foreach($wanted as $id) {
      foreach($xpath->query( "//div[@id='$id']") as $element){
        // innerHTML Function: http://refactormycode.com/codes/708-innerhtml-of-a-domelement
        $doc = new DOMDocument();
        $doc->appendChild($doc->importNode($element, true));
        return $doc->saveHTML();
      }
    }

    return $html;  
  }  
  
  static function Readability($html) {
    
    // give it to Readability
    $readability = new Readability($html);
    // print debug output?
    // useful to compare against Arc90's original JS version -
    // simply click the bookmarklet with FireBug's console window open
    $readability->debug = false;
    // convert links to footnotes?
    #$readability->convertLinksToFootnotes = true;
    // process it
    $result = $readability->init();
    // does it look like we found what we wanted?
    if ($result) {
      #echo "== Title =====================================\n";
      #echo $readability->getTitle()->textContent, "\n\n";
      #echo "== Body ======================================\n";
      $content = $readability->getContent()->innerHTML;
      // if we've got Tidy, let's clean it up for output
    } else {
     $content = false;
    }

    return $content;
  }
  
}
?>
