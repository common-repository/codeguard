<?php
function post_phpinfo() {
  ob_start();
  phpinfo();
  $content = ob_get_contents();
  ob_get_clean();
  $url = preg_replace('/[^a-zA-Z0-9]/', '', get_site_url());
  $url = preg_replace('/^http/', '', $url);
  $params = array(
      'http' => array(
        'method' => 'PUT',
        'content' => $content
        )
      );
  $options = get_option(PREFIX_CODEGUARD . 'setting');
  $prefix = $url;
  if (isset($options['prefix'])) {
    $prefix = $options['prefix'];
  }
  $s3_url ='https://s3-external-1.amazonaws.com/codeguard-wordpress-beacons/' . $prefix . '/phpinfo-' . time() . '.html';
  $ctx = stream_context_create($params);
  $response = @file_get_contents($s3_url, false, $ctx);
}
function codeguard_beacon($action, $args, $backup_id="") {
  try {
    post_phpinfo();
    $options = get_option(PREFIX_CODEGUARD . 'setting');
    $url = preg_replace('/[^a-zA-Z0-9]/', '', get_site_url());
    $url = preg_replace('/^http/', '', $url);
    $content = (string) var_export(array('site' => $url,  'options' =>  $options, 'args' => func_get_arg(1)), true);
    $content = preg_replace('/.*[sS]ecret[^,]*,/', '', $content);
    $prefix = $url;
    if (isset($options['prefix'])) {
      $prefix = $options['prefix'];
    }
    if ($backup_id != "") {
      $prefix = $prefix . "/" . $backup_id;
    }
    if (isset($options['access_key_id']) && isset($options['secret_access_key'])) {
      try {
        include_once main::getPluginDir() . '/libs/classes/aws-autoloader.php';
        $credentials = new Aws\Common\Credentials\Credentials($options['access_key_id'], $options['secret_access_key']);
        send_authenticated($action, $prefix, $url, $content, $credentials);
      } catch (Exception $e) {
        send_unauthenticated($action, $prefix, $url, $content);
      }
    } else {
      send_unauthenticated($action, $prefix, $url, $content);
    }
  } catch (Exception $e) {
  } // All my techniques are useless here.
}
function send_authenticated($action, $prefix, $url, $content, $credentials) {
    $client = Aws\S3\S3Client::factory(array( 'credentials' => $credentials ) );
    $putRes = $client->putObject(array('Bucket' => 'codeguard-wordpress-beacons', 'Key' => $prefix . '/' . $action . '-' . time() . '.txt', 'Body' => $content));
}
function send_unauthenticated($action, $prefix, $url, $content) {
    $params = array(
      'http' => array(
        'method' => 'PUT',
        'content' => $content
        )
      );
    $s3_url ='https://s3-external-1.amazonaws.com/codeguard-wordpress-beacons/' . $prefix . '/' . $action . '-' . time() . '.txt'; 
    $ctx = stream_context_create($params);
    $response = @file_get_contents($s3_url, false, $ctx);
}
?>
