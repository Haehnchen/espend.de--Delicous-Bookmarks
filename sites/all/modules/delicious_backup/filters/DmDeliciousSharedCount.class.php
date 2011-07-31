<?php

class DmDeliciousSharedCount extends DmBase {
  
  const FLAG_NAME = 'delicious_weight';
  const DELICOIUS_JSON_URL = 'http://feeds.delicious.com/v2/json/urlinfo/';
  
  public function postFilter() {
    $this->log(__CLASS__ . ':' . __FUNCTION__);

    $sum = $this->GetDeliciousWeight($this->obj->hash);
    if (isset($sum['total_posts'])) {
      $this->log(__CLASS__ . ':' . __FUNCTION__ . ' - ' . $sum['total_posts']);
      
      // fake admin user!?
      $user = user_load(1);
      flag_weights_set_flag_with_flag(self::FLAG_NAME, $this->obj->nid, $user, $sum['total_posts']);
    
    }    
 
  }
  
  private function GetDeliciousWeight($hash) {

    try {
      
      $url = self::DELICOIUS_JSON_URL . $hash;
      $res = $this->HTTPDownloadAutoPath($url, $hash);
            
      if (!$ret = drupal_json_decode($res->data)) {
        $this->Exception('Error decoding json request');
      }
      
      return $ret;
      
    } catch (Exception $e) {
      $this->log($e->getMessage());
    }

    return array();
  }    
  
}
?>
