<?php
/**
 * Loggin configuration
 */
$log_config = [
  'targets' => [
    // exceptions go into error.log
    'error' => [
      'class' => \yii\log\FileTarget::class,
      'levels' => ['error'],
      'logFile' => '@runtime/logs/error.log',
      'logVars' => [],
      'exportInterval' => 1
    ],
    // everything else into app.log
    'app' => [
      'class' => \yii\log\FileTarget::class,
      'levels' => ['trace','info', 'warning'],
      'except' => ['yii\*','auth'],
      'logVars' => [],
      'exportInterval' => 1
    ],
  ]
];
// Do we have an error email target?
$ini = require('ini.php');
$email = $ini['email'];
if( isset($email['errors_from']) and isset($email['errors_to']) and isset($email['errors_subject']) ){
  $log_config['targets']['mail'] = [
    'class' => \yii\log\EmailTarget::class,
    'mailer' => 'mailer',
    'levels' => ['error'],
    'except' => ['jsonrpc','yii\web\HttpException*'],
    'message' => [
      'from' => [$email['errors_from']],
      'to' => [$email['errors_to']],
      'subject' => $email['errors_subject'],
    ],
  ];
}
return $log_config;