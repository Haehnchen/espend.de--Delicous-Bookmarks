<?php
function delicious_backup_data() {

  //delicious_backup_get_content(2);

  $form = array();
  $form['delicious_backup_data_username'] = array(
    '#type' => 'textfield',
    '#title' => t('Delicious username'),
    '#required' => 1,
   // '#description' => t('The public API key of of your Disqus Application. Generate one <a href="@key">here</a>.', array('@key' => 'http://disqus.com/api/applications/')),
    '#default_value' => $user = variable_get('delicious_backup_username'),
  );

  $form['delicious_backup_data_password'] = array(
    '#type' => 'password',
    '#title' => t('Delicious Password'),
    '#required' => 1,
    //'#description' => t('Your forum name representing all page comments (should be the same as "Domain"; overwrite it here. See your forum list <a href="@link">here</a>)', array('@link' => 'http://disqus.com/api/console/?endpoint=users/listForums')),
    '#default_value' => $pw = variable_get('delicious_backup_password'),
  );
  /*
  $form['disqus_api_cron_time'] = array(
    '#type' => 'textfield',
    '#title' => t('Import new posts'),
    '#description' => t('Import new post on cronjob every x seconds'),
    '#default_value' => variable_get('disqus_api_cron_time', 60*60*24),
  );
  */

  if ($user && $pw) {
    $form['delicious_backup_data_test'] = array(
      '#title' => t('Delicious raw info on above logindata data'),
      '#type' => 'textarea',
      '#disabled' => 'true',
      '#description' => t('You should see some when last post was added. Else check userdata!'),
      '#value' => DeliciousBackup::GetResponse('posts/update'),
    );
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );

  return $form;
}

function delicious_backup_data_submit($form, &$form_state) {
 
  // setting public api key
  variable_set('delicious_backup_username', $form_state['values']['delicious_backup_data_username']);

  // setting public api key
  variable_set('delicious_backup_password', $form_state['values']['delicious_backup_data_password']);
 /*
  // check if we need to overwrite the disqus forum identifier
  if($form_state['values']['disqus_api_public_key'] != variable_get('disqus_domain', '') AND $form_state['values']['disqus_api_overwrite_forum'] != '') {
    variable_set('disqus_api_overwrite_forum', $form_state['values']['disqus_api_overwrite_forum']);
  } else {
    variable_del('disqus_api_overwrite_forum');
  }
*/
}

function delicious_backup_sync_submit($form, &$form_state) {
  if(isset($form_state['values']['update']) AND $form_state['values']['update'] == 1) {
    drupal_set_message('Nodes and Bookmarks are updated / imported. Check logs for further information.');
    delicious_backup_import_bookmarks();
  } else {
    drupal_set_message('Nothing to do, please activate checkbox to import bookmarks.', 'warning');
  }

}

function delicious_backup_sync() {
  $form = array();

  $form['update'] = array(
    '#type' => 'checkbox',
    '#title' => t('Please import all Bookmarks from Delicius now'),
    #'#options' => array(),
    #'#default_value' => array(),
  );
  
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Sync Now'),
  );

  return $form;
}

function delicious_backup_body() {
  $form = array();

  $form['update'] = array(
    '#type' => 'checkbox',
    '#title' => t('Please import all Bookmarks from Delicius now') . db_select('delicious_bookmarks_backup')->countQuery()->execute()->fetchField(),
    #'#options' => array(),
    #'#default_value' => array(),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Sync Now'),
  );

  return $form;
}

function delicious_backup_body_submit($form, &$form_state) {
  if(isset($form_state['values']['update']) AND $form_state['values']['update'] == 1) {
    #drupal_set_message('Nodes and Bookmarks are updated / imported. Check logs for further information.');
    #delicious_backup_import_raw();

    $callback = 'delicious_backup_update_node';
    $batch = array(
      'title' => t('Updating body content'),
      'operations' => array(
        //array('delicious_backup_node_update_batch_start', array()),
        array('delicious_backup_batch_process', array($callback)),
      ),
      //'finished' => 'delicious_backup_node_update_batch_finished',
      'file' => drupal_get_path('module', 'delicious_backup') . '/delicious_backup.admin.inc',
    );

    batch_set($batch);

  } else {
    drupal_set_message('Nothing to do, please activate checkbox to import bookmarks.', 'warning');
  }
}


function delicious_backup_node_update_batch_start(&$context) {
  //$context['results']['count_before'] = db_select('delicious_bookmarks_backup')->countQuery()->execute()->fetchField();
}



function delicious_backup_html_import() {
  $form = array();

  $form['update'] = array(
    '#type' => 'checkbox',
    '#title' => t('Please get HTML content of bookmarks') . db_select('delicious_bookmarks_backup')->countQuery()->execute()->fetchField(),
    #'#options' => array(),
    #'#default_value' => array(),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Import Now'),
  );

  return $form;
}

function delicious_backup_html_import_submit($form, &$form_state) {
  if(isset($form_state['values']['update']) AND $form_state['values']['update'] == 1) {
    #drupal_set_message('Nodes and Bookmarks are updated / imported. Check logs for further information.');
    #delicious_backup_import_raw();

    $callback = 'delicious_backup_get_content';
    $batch = array(
      'title' => t('Updating body content'),
      'operations' => array(
        //array('delicious_backup_node_update_batch_start', array()),
        array('delicious_backup_batch_process', array($callback)),
      ),
      //'finished' => 'delicious_backup_node_update_batch_finished',
      'file' => drupal_get_path('module', 'delicious_backup') . '/delicious_backup.admin.inc',
    );

    batch_set($batch);

  } else {
    drupal_set_message('Nothing to do, please activate checkbox to import bookmarks.', 'warning');
  }
}


function delicious_backup_batch_process($callback, &$context) {
  $def = ($callback == 'delicious_backup_update_node') ? '=' : '!=';
  if (empty($context['sandbox'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['current_node'] = 0;
    $context['sandbox']['max'] = db_query('SELECT COUNT(DISTINCT bid) FROM {delicious_bookmarks_backup}  WHERE response_code '. $def .' :response_code', array(':response_code' => 200))->fetchField();
  }

  $limit = 1;
  $result = db_select('delicious_bookmarks_backup')
    ->fields('delicious_bookmarks_backup', array('bid', 'hash', 'href', 'nid'))
    ->condition('bid', $context['sandbox']['current_node'], '>')
    ->condition('response_code', 200, $def)
    ->orderBy('bid')
    ->range(0, $limit)
    ->execute();

  foreach ($result as $row) {
    $context['sandbox']['current_node'] = $row->bid;
    $context['sandbox']['progress']++;
    //$context['message'] = $row->nid; //$context['sandbox']['progress'] . '/' . $context['sandbox']['max'];
    $context['message'] = $context['sandbox']['progress'] . '/' . $context['sandbox']['max'];
    #usleep(200000);
    $callback($row);
  }

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}

?>
