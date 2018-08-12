<?php

/**
 * @file
 * Statistics for your commits based on automated checks like Travis.
 */

use Github\Client;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;

require_once __DIR__ . '/vendor/autoload.php';

$username = isset($argv[1]) ? $argv[1] : NULL;
if (empty($username)) {
  exit(1);
}

$client = new Client();

// Activate caching.
$cache_target = __DIR__ . '/.github-cache/';
if (!is_dir($cache_target)) {
  mkdir($cache_target);
}
$filesystemAdapter = new Local($cache_target);
$filesystem = new Filesystem($filesystemAdapter);
$pool = new FilesystemCachePool($filesystem);
$client->addCache($pool);

// Optionally authenticate.
$token_path = __DIR__ . '/.github-token';
if (is_file($token_path)) {
  $token = file_get_contents($token_path);
  if (!empty($token)) {
    $client->authenticate($token, NULL, $client::AUTH_HTTP_TOKEN);
  }
}

$events = $client->api('user')->publicEvents($username);
$stats = [];
foreach ($events as $event) {
  if ($event['type'] != 'PushEvent') {
    continue;
  }
  $timestamp = strtotime($event['created_at']);
  $hour_of_day = date('G', $timestamp);
  if (!isset($stats[$hour_of_day])) {
    $stats[$hour_of_day] = [];
  }
  list($owner, $repo) = explode('/', $event['repo']['name']);
  $commits = $event['payload']['commits'];
  foreach ($commits as $commit) {
    $statuses = $client->api('repo')
      ->statuses()
      ->show($owner, $repo, $commit['sha']);

    foreach ($statuses as $status) {
      if ($status['state'] == 'pending') {
        continue;
      }
      $counter = &$stats[$hour_of_day][$status['state']];
      if (!isset($counter)) {
        $counter = 0;
      }

      $counter++;
    }
  }
}

print json_encode($stats, JSON_PRETTY_PRINT);
