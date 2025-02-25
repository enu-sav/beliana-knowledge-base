<?php

namespace Drupal\bkb_user_sync\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use GuzzleHttp\ClientInterface;
use Drupal\user\Entity\User;

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
      $webrs_site = getenv('WEBRS_SITE');

      // Get CSRF token from Drupal 7
      $csrf_response = $this->httpClient->request('GET', $webrs_site . '/services/session/token');
      $csrf_token = trim($csrf_response->getBody()->getContents());

      // Login to Drupal 7
      $response = $this->httpClient->request('POST', $webrs_site . '/api/users/user/login', [
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

      $email = $user_data['user']['mail'] ?? "";
      $roles = $user_data['user']['roles'] ?? [];

      // Map roles from Drupal 7 to Drupal 10
      $role_map = [
        'administrátor' => 'administrator',
        'Redaktor' => 'redactor',
      ];
      $mapped_roles = [];
      foreach ($roles as $role) {
        if (isset($role_map[$role])) {
          $mapped_roles[] = $role_map[$role];
        }
      }

      // Ensure roles include 'administrator' and 'redactor'
      $required_roles = ['administrator', 'redactor'];
      $roles = array_unique(array_merge($mapped_roles, $required_roles));

      // Sync user to Drupal 10
      $user_storage = $this->entityTypeManager->getStorage('user');
      $existing_users = $user_storage->loadByProperties(['name' => $username]);

      if ($existing_users) {
        $user = reset($existing_users);
        // Update existing user
        $user->setEmail($email);
        $user->setPassword($password);
        foreach ($required_roles as $role) {
          if (!$user->hasRole($role)) {
            $user->addRole($role);
          }
        }
        $user->save();
      } else {
        // Create new user
        $user = User::create([
          'name' => $username,
          'mail' => $email,
          'pass' => $password,
          'status' => 1,
          'roles' => $roles,
        ]);
        $user->save();
      }

      user_login_finalize($user);
      $this->logger->info("User {$username} was synchronized and logged in.");

    } catch (\Exception $e) {
      $this->logger->error("Error during user synchronization: " . $e->getMessage());
    }
  }
}
