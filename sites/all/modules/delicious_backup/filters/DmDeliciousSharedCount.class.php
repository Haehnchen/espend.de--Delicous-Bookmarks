<?php

class DmDeliciousSharedCount extends DmBase {
  
  const FLAG_NAME = 'delicious_weight';
  
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
    $opts = array('timeout' => 10);
    $url = 'http://feeds.delicious.com/v2/json/urlinfo/' . $hash;
    
    $res = drupal_http_request($url, $opts);
    
    if ($res->code == 200) {
      $ar = drupal_json_decode($res->data);
      if (isset($ar[0])) return current($ar);
    }
    
    return array();
  }    
  
}
?>
