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
      // Get CSRF token from Drupal 7
      $csrf_response = $this->httpClient->request('GET', 'https://webrs.beliana.lndo.site/services/session/token');
      $csrf_token = trim($csrf_response->getBody()->getContents());

      // Login to Drupal 7
      $response = $this->httpClient->request('POST', 'https://webrs.beliana.lndo.site/api/users/user/login', [
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

      if (!isset($user_data['name'])) {
        return;
      }

      $email = $user_data['mail'] ?? "";
      $roles = $user_data['roles'] ?? [];

      // Sync user to Drupal 10
      $user_storage = $this->entityTypeManager->getStorage('user');
      $existing_users = $user_storage->loadByProperties(['name' => $username]);

      if ($existing_users) {
        $user = reset($existing_users);
        // Update existing user
        $user->setEmail($email);
        $user->setPassword($password);
        $user->save();
      }
      else {
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
      $this->logger->info("Používateľ {$username} bol zosynchronizovaný a prihlásený.");

    } catch (\Exception $e) {
      $this->logger->error("Chyba pri synchronizácii používateľa: " . $e->getMessage());
    }
  }
}
