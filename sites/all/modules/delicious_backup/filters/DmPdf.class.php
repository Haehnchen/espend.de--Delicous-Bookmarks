<?php

class DmPdf extends DmBase {
  
  const DIR_CLASS = '';
  const SCREENSHOT_FORMAT = 'pdf';
  const FIELD = 'field_delicious_pdf';
  
  public function postFilter() {
    $this->log(__CLASS__ . ':' . __FUNCTION__);

    $this->MakePdf();  
 
  }
  
  private function MakePdf() {

    // @TODO convert internal path to absolute some better solution
    $filename = $this->GetDirectory(self::DIR_CLASS, 'doc.' . self::SCREENSHOT_FORMAT);
    $filename = str_replace('public://', '', $filename);



    
    // check if image already attached to this node
    if (DeliciousBackup::ImageIsAttached(self::FIELD, $this->obj->node, basename($filename))) {
      return;
    }
    

    file_prepare_directory($dest_path = dirname('public://' . $filename) , FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

        
    try {
      require_once drupal_get_path('module', 'delicious_backup') . '/Files/WebsiteToImage.class.php';
      $websiteToImage = new WebsiteToImage();
      $websiteToImage->setOutputFile(drupal_realpath('public://') . '/' . $filename)->setUrl($this->obj->href)->start();
      $this->log(__CLASS__ . ':' . __FUNCTION__ . ' got pdf' );
    } catch (Exception $e) {
      $this->Exception($e->getMessage(), self::ERROR_LOG | self::ERROR_WATCHDOG);
    }
    
    // generated file is no image so delete it or attach it to the node
    if (file_exists('public://' . $filename) AND @filesize('public://' . $filename) == 0) {
      $this->log(__CLASS__ . ':' . __FUNCTION__ . ' not a valid file:' . 'public://' . $filename );
      drupal_unlink('public://' . $filename);
      return;
    }

    $test = DeliciousBackup::UriToFile('public://' . $filename);
    
    DeliciousBackup::AttachFileToNode($this->obj->node, self::FIELD, DeliciousBackup::UriToFile('public://' . $filename), true);
  }    
  
}
?>