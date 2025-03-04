<?php

namespace Drupal\bkb_user_sync\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use GuzzleHttp\ClientInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Event Subscriber for user authentication and sync.
 */
class UserLoginSubscriber implements EventSubscriberInterface {

  protected $logger;
  protected $entityTypeManager;
  protected $httpClient;

  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, ClientInterface $http_client) {
    $this->logger = $logger_factory->get('bkb_user_sync');
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
  }

  public static function getSubscribedEvents() {
    return [
      'kernel.request' => ['onUserLogin', 30],
    ];
  }

  public function onUserLogin(RequestEvent $event) {
    $request = $event->getRequest();

    if ($request->attributes->get('_route') !== 'user.login') {
      return;
    }

    $username = $request->get('name');
    $password = $request->get('pass');

    if (!$username || !$password) {
      return;
    }

    try {
      $api_url = getenv('WEBRS_SITE');

      // Get CSRF token from Drupal 7
      $csrf_response = $this->httpClient->request('GET', $api_url . '/services/session/token');
      $csrf_token = trim($csrf_response->getBody()->getContents());

      // Login to Drupal 7
      $response = $this->httpClient->request('POST', $api_url . '/api/users/user/login', [
        'headers' => [
          'Content-Type' => 'application/json',
          'X-CSRF-Token' => $csrf_token,
        ],
        'json' => [
          'username' => $username,
          'password' => $password,
        ],
      ]);

      $user_data = json_decode($response->getBody()->getContents(), TRUE);

      if (!isset($user_data['user']['name'])) {
        return;
      }

      // Map roles from Drupal 7 to Drupal 10
      $role_map = [
        'administrátor' => 'administrator',
        'Redaktor' => 'redactor',
      ];

      $allowed_roles = array_filter($user_data['user']['roles'], function ($role) use ($role_map) {
        return array_key_exists($role, $role_map);
      });

      // User from D7 has no allowed role assigned
      if (empty($allowed_roles)) {
        return;
      }

      // Build D10 roles by mapping
      $roles = array_unique(array_map(function ($role) use ($role_map) {
        return $role_map[$role];
      }, $allowed_roles));

      // Sync user to Drupal 10
      $user_storage = $this->entityTypeManager->getStorage('user');
      $existing_users = $user_storage->loadByProperties(['name' => $username]);

      if ($existing_users) {
        $user = reset($existing_users);
      } else {
        // Create new user
        $user = User::create([
          'name' => $username,
          'status' => 1,
        ]);
      }

      // Update user data
      $user->setEmail($user_data['user']['mail']);
      $user->setPassword($password);
      $user->set('roles', $roles);
      $user->save();

      user_login_finalize($user);
      $this->logger->info(t('User @username was synchronized and logged in.'), ['@username' => $username]);

      // Redirect to home page
      $response = new RedirectResponse('/');
      $response->send();
      exit;

    } catch (\Exception $e) {
      $this->logger->error('Error during user synchronization: ' . $e->getMessage());
    }
  }
}
