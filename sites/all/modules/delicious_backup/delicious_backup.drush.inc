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
  
  
  $items['run-all'] = array(
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
  
  $items['run-my-queue'] = array(
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

  $items['bookmarks'] = array(
    'description' => "All Bookmarks Stuff", 
    'options' => array(
      'hooks' => 'Comma delimited list of hooks: ' . implode(', ', array_keys(DeliciousBackup::InvokeList())),
    ),
    'arguments' => array(
      'workon' => 'Which Bookmarks should we work on: all or queue',
    ),    
    'examples' => array(
      'drush bookmarks all --hooks=filtered,hooks',
    ), 
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // No bootstrap at all.
  );     
  
  
  return $items;
}

function drush_delicious_backup_bookmarks($workon = 'all') {
  
  if (!drush_get_option('uri')) {
    return drush_set_error('NO_URI', dt('We need an uri to run this command. Please use --uri=http:://UrlToPage'));
  }  
  
  if ($hooks = drush_get_option('hooks')) {
    $hooks = explode(',', $hooks);
  }
  
  if ($workon == 'all') {
      drush_print('Running on all Bookmarks');
    
    foreach (delicious_backup_get_valid_bookmarks() as $row) {
      drush_print('running:' . $row->nid . '/' . $row->bid);
      $test = new DeliciousBackupReader($row->nid);
      if ($hooks) $test->SetInvokeFilter($hooks);
      $test->UpdateNode();
      drush_print($test->GetLog());    
      unset($test);
    }
  }
  
  if ($workon == 'queue') {
    drush_delicious_backup_run_my_queue();
  }

}

function drush_delicious_backup_run_my_queue() {
  
  drush_print('Queue running');
  
  /* queue workers */
  $queue = DrupalQueue::get('delicious_backup_get_content');
  while ($item = $queue->claimItem(15)) {
    drush_print('Queue:' . $item->data);
    drush_print(delicious_backup_get_content($item->data));
    $queue->deleteItem($item);
  }  
  
  /* queue workers */
  $queue = DrupalQueue::get('delicious_backup_update_node');
  while ($item = $queue->claimItem(15)) {
    delicious_backup_update_node($item->data);
    drush_print('Queue:' . $item->data);    
    $queue->deleteItem($item);
  }
  
  drush_print('Queue finish');  
  
}

function drush_delicious_backup_run_all() {
  foreach (delicious_backup_get_valid_bookmarks() as $row) {
    drush_print('running:' . $row->nid . '/' . $row->bid);
    $test = new DeliciousBackupReader($row->nid);
    $test->UpdateNode();
    drush_print($test->GetLog());
  }
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
      $test = new DeliciousBackupReader($row->nid);
      $test->GetExternalHTML();    
    } catch (Exception $e) {
      drush_print('error:' . $e->getMessage());
    }
  }
}




?>
