<?php
return [
  'database' => [
    'driver' => 'pdo_mysql',
    'host' => 'localhost',
    'port' => '',
    'charset' => 'utf8mb4',
    'dbname' => 'espocrm',
    'user' => 'root',
    'password' => ''
  ],
  'smtpPassword' => '',
  'logger' => [
    'path' => 'data/logs/espo.log',
    'level' => 'WARNING',
    'rotation' => true,
    'maxFileNumber' => 30,
    'printTrace' => false
  ],
  'restrictedMode' => false,
  'webSocketMessager' => 'ZeroMQ',
  'clientSecurityHeadersDisabled' => false,
  'clientCspDisabled' => false,
  'clientCspScriptSourceList' => [
    0 => 'https://maps.googleapis.com'
  ],
  'isInstalled' => true,
  'microtimeInternal' => 1674562977.242378,
  'passwordSalt' => 'a1a01ff101fadcec',
  'cryptKey' => '7f3498dea9430e424c5460c17703a15c',
  'hashSecretKey' => '842d2f23a322770ba794e5a61bef6762',
  'actualDatabaseType' => 'mysql',
  'actualDatabaseVersion' => '8.0.31'
];
