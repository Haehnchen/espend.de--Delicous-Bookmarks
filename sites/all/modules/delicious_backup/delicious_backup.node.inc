<?php

function delicious_backup_bookmark_upd($node) {
  
  $bid = db_query("SELECT bid FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $node->nid))->fetchField();
  
  $href= db_query("SELECT href FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $node->nid))->fetchField();


  //delicious_backup_get_content($bid);

  delicious_backup_update_node($bid, true);
  return 'updated';
}

function delicious_backup_bookmark_dsp($node) {
  
  $obj = db_query("SELECT nid,bid,hash,href FROM {delicious_bookmarks_backup} WHERE nid = :nid", array(':nid' => $node->nid))->fetchObject();
  
  $file = 'public://delicious_backup/' . $obj->hash;

  if (!$html = file_get_contents($file)) {
    watchdog('delicious_backup', 'Error getting hash file %id - %url ', array('%id' => $obj->bid, '%url' => $obj->href));
    return false;
  }

  _delicious_back_preload($html);
  
  $content = DeliciousBackup::Readability($html);
  $content = mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8");


  $content = delicious_backup_filter($content, false, $node);

  $test = node_load($node->nid);
  $test->body[$test->language][0]['value'] = $content;
  $test->body[$test->language][0]['format'] = 'full_html';
  
  node_save($test);
  
  return $content;
}

?>
