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
  
  $items['get-delicious-weight'] = array(
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
  
  $items['get-bookmarks'] = array(
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

function drush_delicious_backup_get_bookmarks() {
  delicious_backup_import_bookmarks();
}

function drush_delicious_backup_get_external_html() {
  $res = db_select('delicious_bookmarks_backup')
    ->fields('delicious_bookmarks_backup', array('bid', 'hash', 'href', 'nid'))
    #->condition('response_code', 200)
    #->condition('bid', 1300, '=')
    ->orderBy('bid')
    ->execute();  
  
  foreach ($res as $row) {
    try {
      drush_print('running:' . $row->nid . '/' . $row->bid);
      $test = new Reader($row->nid);
      $test->GetExternalHTML();    
    } catch (Exception $e) {
      drush_print('error:' . $e->getMessage());
    }
  }
}

function drush_delicious_backup_get_delicious_weight() {

  foreach (delicious_backup_get_valid_bookmarks() as $row) {
    $sum = DeliciousBackup::GetDeliciousWeight($row->hash);
    if (isset($sum['total_posts'])) {
     
      // fake admin user!?
      $user = user_load(1);
      flag_weights_set_flag_with_flag('delicious_weight', $row->nid, $user, $sum['total_posts']);
    
    }
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
   
    // @TODO convert internal path to absolute some better solution
    $filename = str_replace('public://', '', DELICIOUS_BACKUP_ROOT_DIR . $row->hash . '/screenshot.png');
    
    // check if image already attached to this node
    if (DeliciousBackup::ImageIsAttached('field_delicious_screenshot', $node, basename($filename))) {
      continue;
    }
    
    drush_print('running:' . $row->nid . '/' . $row->bid);    

    file_prepare_directory($dest_path = dirname('public://' . $filename) , FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);
    
    try {
      $websiteToImage = new WebsiteToImage();
      $websiteToImage->setProgramPath('/root/wkhtmltoimage-i386')->setOutputFile(drupal_realpath('public://') . '/' . $filename)->setUrl($row->href)->start();
    } catch (Exception $e) {
      watchdog_exception('delicious_backup', $e, null, array(), WATCHDOG_NOTICE);
    }
    
    // generated file is no image so delete it or attach it to the node
    if (!DeliciousBackup::FileIsImage('public://' . $filename)) {
      if (file_exists('public://' . $filename)) drupal_unlink('public://' . $filename);
    } else {
      DeliciousBackup::AttachFileToNode($node, 'field_delicious_screenshot', DeliciousBackup::UriToFile('public://' . $filename));
    }
    
    
  }  
  
  
}

?>
