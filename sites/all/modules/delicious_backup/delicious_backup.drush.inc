<?php

function delicious_backup_drush_command() {
  $items = array();

  $items['make-screenshot'] = array(
    'description' => "Makes a delicious sandwich.", 
    'arguments' => array(
      'filling' => 'The type of the sandwich (turkey, cheese, etc.)',
    ), 
    'options' => array(
      'spreads' => 'Comma delimited list of spreads (e.g. mayonnaise, mustard)',
    ), 
    'examples' => array(
      'drush make-me-a-sandwich turkey --spreads=ketchup,mustard',
    ), 
    'aliases' => array('mmas'), 
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // No bootstrap at all.
  );
  
  $items['get-all-content'] = array(
    'description' => "Makes a delicious sandwich.", 
    'arguments' => array(
      'filling' => 'The type of the sandwich (turkey, cheese, etc.)',
    ), 
    'options' => array(
      'spreads' => 'Comma delimited list of spreads (e.g. mayonnaise, mustard)',
    ), 
    'examples' => array(
      'drush make-me-a-sandwich turkey --spreads=ketchup,mustard',
    ), 
    #'aliases' => array('mmas'), 
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // No bootstrap at all.
  );  
  
  $items['get-external-html'] = array(
    'description' => "Makes a delicious sandwich.", 
    'arguments' => array(
      'filling' => 'The type of the sandwich (turkey, cheese, etc.)',
    ), 
    'options' => array(
      'spreads' => 'Comma delimited list of spreads (e.g. mayonnaise, mustard)',
    ), 
    'examples' => array(
      'drush make-me-a-sandwich turkey --spreads=ketchup,mustard',
    ), 
    #'aliases' => array('mmas'), 
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // No bootstrap at all.
  );    

  return $items;
}

function drush_delicious_backup_get_external_html() {
  $res = db_select('delicious_bookmarks_backup')
    ->fields('delicious_bookmarks_backup', array('bid', 'hash', 'href', 'nid'))
    #->condition('response_code', 200)
    ->condition('bid', 1300, '=')
    ->orderBy('bid')
    ->execute();  
  
  foreach ($res as $row) {
    drush_print('running:' . $row->nid . '/' . $row->bid);
    $test = new Reader($row->nid);
    $test->GetExternalHTML();
  }
}

function drush_delicious_backup_get_all_content() {
  foreach (delicious_backup_get_valid_bookmarks() as $row) {
    drush_print('running:' . $row->nid . '/' . $row->bid);
    $test = new Reader($row->nid);
    $test->UpdateNode();
  }
}

function drush_delicious_backup_make_screenshot($filling = 'ascii') {
 
  require_once drupal_get_path('module', 'delicious_backup') . '/Files/WebsiteToImage.class.php';
  foreach (delicious_backup_get_valid_bookmarks() as $row) {
    $node = node_load($row->nid);
   
    $filename = 'screenshots/' . $row->nid . '/screenshot.jpg';    
    
    // check if image already attached to this node
    if (DeliciousBackup::ImageIsAttached('field_delicious_screenshot', $node, basename($filename))) {
      continue;
    }

    file_prepare_directory($dest_path = dirname('public://' . $filename) , FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    
    $websiteToImage = new WebsiteToImage();
    $websiteToImage->setProgramPath('/root/wkhtmltoimage-i386')->setOutputFile(drupal_realpath('public://') . '/' . $filename)->setUrl($row->href)->start();
    
    if (!DeliciousBackup::FileIsImage('public://' . $filename)) {
      unlink('public://' . $filename);
      continue;
    }
    
    DeliciousBackup::AttachFileToNode($node, 'field_delicious_screenshot', DeliciousBackup::UriToFile('public://' . $filename));
  }  
  
  
}

?>
