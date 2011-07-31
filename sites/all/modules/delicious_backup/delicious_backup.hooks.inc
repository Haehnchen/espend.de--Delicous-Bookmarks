<?php

/*
 * Implements hook_menu()
 * 
 */
function delicious_backup_menu() {
  // administrative items
  $items['admin/config/services/delicious'] = array(
    'title' => 'Delicious Bookmarks Settings',
    'description' => 'Settings und workers for Delicious Bookmarks.',
    'access arguments' => array('administer delicious'),
    'page callback' => 'drupal_get_form',
    'page arguments' => array('delicious_backup_data'),
    'file' => 'delicious_backup.admin.inc',
    #'type' => MENU_NORMAL_ITEM,
  );

  $items['admin/config/services/delicious/account'] = array(
    'title' => 'Account',
    'type' => MENU_DEFAULT_LOCAL_TASK,
    'weight' => -10,
  );

  $items['admin/config/services/delicious/sync'] = array(
    'title' => 'Sync/Import Bookmarks',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('delicious_backup_sync'),
    'access arguments' => array('administer delicious'),
    'type' => MENU_LOCAL_TASK,
    'weight' => 30,
    'file' => 'delicious_backup.admin.inc',
  );

  $items['admin/config/services/delicious/body_update'] = array(
    'title' => 'Update Content',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('delicious_backup_body'),
    'access arguments' => array('administer delicious'),
    'type' => MENU_LOCAL_TASK,
    'weight' => 40,
    'file' => 'delicious_backup.admin.inc',
  );

  $items['admin/config/services/delicious/html_import'] = array(
    'title' => 'Import HTML',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('delicious_backup_html_import'),
    'access arguments' => array('administer delicious'),
    'type' => MENU_LOCAL_TASK,
    'weight' => 40,
    'file' => 'delicious_backup.admin.inc',
  );

  $items['node/%node/bookmark'] = array(
      'title' => 'Bookmark',
      'access callback'=>'delicious_backup_bookmark_access',
      'access arguments'=>array(1),
      'page callback' => 'delicious_backup_bookmark_dsp',
      'page arguments' => array(1),
      'type' => MENU_LOCAL_TASK,
      'weight' => 4,
      'file' => 'delicious_backup.node.inc',      
  );
  
  $items['node/%node/bookmark_update'] = array(
      'title' => 'Bookmark Update',
      'access callback'=>'delicious_backup_bookmark_access',
      'access arguments'=>array(1),
      'page callback' => 'delicious_backup_bookmark_upd',
      'page arguments' => array(1),
      'type' => MENU_LOCAL_TASK,
      'weight' => 4,
      'file' => 'delicious_backup.node.inc',
  );  
  
  return $items;
}

/**
 * Implements hook_block_info().
 */
function delicious_backup_block_info() {
  $blocks = array();

  $blocks['delicious_info'] = array(
    'info' => t('Delicious Node Info'),
    'cache' => DRUPAL_CACHE_PER_PAGE,
    #'pages' => 'node/*', // Not usually provided here.
  );

  return $blocks;
}

/**
 * Implements hook_block_view().
 */
function delicious_backup_block_view($delta = '') {
  global $user;

//  if ($delta == 'delicious_info') {
  $block = array();

  $nid = arg(1);
  if (!is_numeric($nid))
    return;

  $obj = db_query("SELECT * FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $nid))->fetchAssoc();

  $node = node_load($nid);

  $block['content'] = render(field_view_field('node', $node, 'field_delicious_screenshot', 'default'));
  
  $test = field_view_field('node', $node, 'field_delicious_pdf', 'default');
  $test[0]['#file']->description = 'Download website as PDF';
 
  $block['content'] .= render($test);

  $block['content'] .= _delicious_backup_get_bookmark_info($nid, 'full');

  return $block;
//  }
}

function delicious_backup_cron() {
  
  
  //delicious_backup_get_content(1242);
  //delicious_backup_update_node(1242, true);
  //exit;
 

  
  /* fetch all bookmarks */
  $cron_time = time() - variable_get('delicious_backup_last_cron', 0);
  if ($cron_time > (variable_get('delicious_backup_cron_time', 60*60*12))) delicious_backup_import_bookmarks();
  variable_set('delicious_backup_last_cron', time());

  
  /* fetch html content worker */
  //http://api.drupal.org/api/drupal/modules--aggregator--aggregator.module/function/aggregator_cron/7
  //http://api.drupal.org/api/drupal/modules--aggregator--aggregator.module/function/aggregator_refresh/7
  $result = db_query_range("SELECT bid FROM {delicious_bookmarks_backup} WHERE response_code != :response_code AND response_errors < 4 AND queued = 0", 0, 20100, array(
    ':response_code' => 200,
  ));

  $queue = DrupalQueue::get('delicious_backup_get_content') ;
  $queue->createQueue();
  foreach ($result as $record) {
    $queue->createItem($record->bid);
    db_update('delicious_bookmarks_backup')->fields(array('queued' => REQUEST_TIME))->condition('bid', $record->bid)->execute();
  }

}

/**
 * Implements hook_cron_queue_info().
 */
function delicious_backup_cron_queue_info() {
  $queues = array();
  
  $queues['delicious_backup_get_content'] = array(
    'worker callback' => 'delicious_backup_get_content',
    'time' => 15,
  );
  
  $queues['delicious_backup_update_node'] = array(
    'worker callback' => 'delicious_backup_update_node',
    'time' => 15,
  );  
  
  return $queues;
}

/**
 * Implements hook_node_delete().
 */
function delicious_backup_node_delete($node) {
  if  ($node->type == 'delicious_bookmark') {
    
    // delete html file
    $hash = db_query("SELECT hash FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $node->nid))->fetchField();
    if (file_exists('public://delicious_backup/' . $hash)) unlink('public://delicious_backup/' . $hash);
    
    // reset bookmark
    db_merge('delicious_bookmarks_backup')
      ->key(array('nid' => $node->nid))
      ->fields(array('nid' => 0))
      ->execute();
  }
}

function delicious_backup_token_info($type = 'all') {
  
  
    $data['tokens']['node']['hash'] = array(
      'name' => t('Hash'),
      'description' => t('Bookmark hash'),
    );
     
    $data['tokens']['node']['hash_short'] = array(
      'name' => t('Short Url for Bookmarks'),
      'description' => t('Bookmark short url'),
      'needs-data' => 'bookmark',
    );    
    
    return $data;
}

function delicious_backup_tokens($type, $tokens, array $data = array(), array $options = array()) {

  $replacements = array();

  if ($type == 'node' && !empty($data['node'])) {

    $node = $data['node'];
    if ($node->type != 'delicious_bookmark')
      return $replacements;

    foreach ($tokens as $name => $original) {

      switch ($name) {
        case 'hash':
          $hash = db_query("SELECT hash FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $node->nid))->fetchField();
          $replacements[$original] = $hash;
          break;
        case 'hash_short':
          require_once drupal_get_path('module', 'delicious_backup') . '/Files/BNID.class.php';
          $href = db_query("SELECT href FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $node->nid))->fetchField();

          $hash = sprintf('%u', crc32($href));
          $conv = new BNID(); // default base 62 implementation

          $replacements[$original] = $conv->base10ToN($hash);
          break;
      }
    }
  }

  return $replacements;
}

?>
