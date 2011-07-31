<?php

class DmBase extends DeliciousBackupReader {

  public function __construct(DeliciousBackupReaderObj $test) {
    $this->obj = $test;
  }
  
  public function preFilter() { }
  public function postFilter() { }
  
  public function install() { }
  public function uninstall() { }
  public function schema() { }
  
  static public function fields() { return array(); }
}
?>
