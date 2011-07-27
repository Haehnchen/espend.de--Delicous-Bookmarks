<?php

class DmGithub extends DmBase {
  public function preFilter() {
    $this->log(__CLASS__ . ':' . __FUNCTION__);

    //check for external gist scripts
    if (!preg_match_all('@<script src="(http://gist.github.com/(\d+).js)"></script>@i', $this->obj->content, $match, PREG_SET_ORDER))
      return;

    // walk through all javascript code snippets
    foreach ($match as $git_code) {

      // request javascript file and filter out code
      $req = drupal_http_request($git_code[1]);
      if (isset($req->data) AND preg_match('@<a href="(.*gist\.github.*)"[ ]@i', stripslashes($req->data), $res)) {
        $req1 = drupal_http_request($res[1]);

        // replace external javascript file with raw source code
        if (isset($req1->data)) {
          $this->obj->content = str_replace($git_code[0], '<pre>' . htmlentities($req1->data) . '</pre>', $this->obj->content);
        }

      }
    }  
  }
}
?>
