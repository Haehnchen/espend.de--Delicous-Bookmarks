<?php

class DmWebsiteScreenshot extends DmBase {
  
  const DIR_SCREENSHOT = '';
  const SCREENSHOT_FORMAT = 'png';
  const FIELD = 'bookmark_screenshot';
  
  public function postFilter() {
    $this->log(__CLASS__ . ':' . __FUNCTION__);

    $this->MakeScreenshot();  
 
  }
  
  private function MakeScreenshot() {

    // @TODO convert internal path to absolute some better solution
    $filename = $this->GetDirectory(self::DIR_SCREENSHOT, 'screenshot.' . self::SCREENSHOT_FORMAT);
    $filename = str_replace('public://', '', $filename);

    
    // check if image already attached to this node
    if (DeliciousBackup::ImageIsAttached(self::FIELD, $this->obj->node, basename($filename))) {
      return;
    }

    file_prepare_directory($dest_path = dirname('public://' . $filename) , FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    
    if (!file_exists('public://' . $filename)) {
      try {
        require_once drupal_get_path('module', 'delicious_backup') . '/Files/WebsiteToImage.class.php';
        $websiteToImage = new WebsiteToImage();
        $websiteToImage->setOutputFile(drupal_realpath('public://') . '/' . $filename)->setUrl($this->obj->href)->start();
        $this->log(__CLASS__ . ':' . __FUNCTION__ . ' got image' );
      } catch (Exception $e) {
        $this->Exception($e->getMessage(), self::ERROR_LOG | self::ERROR_WATCHDOG);
      }

      // generated file is no image so delete it or attach it to the node
      if (!DeliciousBackup::FileIsImage('public://' . $filename)) {
        $this->log(__CLASS__ . ':' . __FUNCTION__ . ' not a image:' . 'public://' . $filename );
        if (file_exists('public://' . $filename)) drupal_unlink('public://' . $filename);
        return;
      }
    }

    DeliciousBackup::AttachFileToNode($this->obj->node, self::FIELD, DeliciousBackup::UriToFile('public://' . $filename), true, true);
  }    
  
  static public function fields() {
    $t = get_t();
    $field = array(
      self::FIELD => array(
        'field_name' => self::FIELD,
        'cardinality' => 1,
        'type' => 'image',
        'settings' => array(
          'uri_scheme' => 'public',
        ),
      ),
    );

    $instance = array(
      self::FIELD => array(
        'field_name' => self::FIELD,
        'label' => $t('Screenshot'),
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



