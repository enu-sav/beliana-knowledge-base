<?php
/**
 * Disable CSS and JS aggregation.
 */
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

$settings['container_yamls'][] = __DIR__ . '/services.development.yml';

$settings['skip_permissions_hardening'] = TRUE;


// Mailer MailHog settings override
$config['symfony_mailer.mailer_transport.smtp']['configuration'] = [
  'host' => 'mailhog', // Replace with your SMTP server host.
  'port' => 1025, // Replace with the appropriate port number.
  'encryption' => '', // Replace with the desired encryption type ('tls' or 'ssl').
  'username' => '', // Replace with your SMTP username.
  'password' => '', // Replace with your SMTP password.
];
