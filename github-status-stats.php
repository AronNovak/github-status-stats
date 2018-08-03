<?php

/**
 * @file
 * Statistics for your commits based on automated checks like Travis.
 */

use Github\Client;

require_once __DIR__ . '/vendor/autoload.php';

$username = $argv[1];

$client = new Client();
$events = $client->api('user')->publicEvents($username);
$stats = [];
foreach ($events as $event) {
  if ($event['type'] != 'PushEvent') {
    continue;
  }
  list($owner, $repo) = explode('/', $event['repo']['name']);
  $commits = $event['payload']['commits'];
  foreach ($commits as $commit) {
    $statuses = $client->api('repo')->statuses()->show($owner, $repo, $commit['sha']);
    foreach ($statuses as $status) {
      $stats[$status['state']]++;
    }
  }
}

print json_encode($stats, JSON_PRETTY_PRINT);
