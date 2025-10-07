<?php

namespace Drupal\bkb_user_sync\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Drupal\user\Entity\User;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for syncing users from Drupal 7.
 */
class UserSyncService {

  protected $logger;
  protected $entityTypeManager;
  protected $httpClient;

  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ClientInterface $http_client
  ) {
    $this->logger = $logger_factory->get('bkb_user_sync');
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient = $http_client;
  }

  /**
   * Verify user credentials against Drupal 7 API.
   *
   * @param string $username
   *   The username.
   * @param string $password
   *   The password.
   *
   * @return array|false
   *   User data array from D7 or FALSE on failure.
   */
  public function verifyD7Credentials($username, $password) {
    try {
      $api_url = getenv('WEBRS_SITE');

      if (empty($api_url)) {
        throw new \Exception('WEBRS_SITE environment variable not set');
      }

      // Get CSRF token
      $csrf_response = $this->httpClient->request('GET', $api_url . '/services/session/token', [
        'timeout' => 10,
        'http_errors' => false,
      ]);

      if ($csrf_response->getStatusCode() !== 200) {
        $this->logger->error('Failed to get CSRF token from D7 (HTTP @code)', [
          '@code' => $csrf_response->getStatusCode(),
        ]);
        return FALSE;
      }

      $csrf_token = trim($csrf_response->getBody()->getContents());

      // Authenticate with Drupal 7
      $response = $this->httpClient->request('POST', $api_url . '/api/users/user/login', [
        'headers' => [
          'Content-Type' => 'application/json',
          'X-CSRF-Token' => $csrf_token,
        ],
        'json' => [
          'username' => $username,
          'password' => $password,
        ],
        'timeout' => 10,
        'http_errors' => false,
      ]);

      if ($response->getStatusCode() !== 200) {
        // Authentication failed - this is normal, not an error
        return FALSE;
      }

      $user_data = json_decode($response->getBody()->getContents(), TRUE);

      if (!isset($user_data['user']['name'])) {
        $this->logger->warning('Invalid D7 response format for user @username', [
          '@username' => $username,
        ]);
        return FALSE;
      }

      return $user_data;

    } catch (GuzzleException $e) {
      $this->logger->error('D7 API connection error: @error', [
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    } catch (\Exception $e) {
      $this->logger->error('D7 authentication error: @error', [
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Sync user from Drupal 7 data.
   *
   * @param string $username
   *   The username.
   * @param array $user_data
   *   User data from D7 API.
   * @param string $password
   *   The plain text password (to set for new/updated users).
   *
   * @return \Drupal\user\Entity\User|false
   *   The synced user entity or FALSE on failure.
   */
  public function syncUser($username, array $user_data, $password) {
    // Role mapping
    $role_map = [
      'administrátor' => 'administrator',
      'Redaktor' => 'redactor',
      'Výstupný redaktor' => 'redactor',
    ];

    $d7_roles = $user_data['user']['roles'] ?? [];

    $allowed_roles = array_filter($d7_roles, function ($role) use ($role_map) {
      return array_key_exists($role, $role_map);
    });

    if (empty($allowed_roles)) {
      $this->logger->warning('User @username has no allowed roles in D7', [
        '@username' => $username,
      ]);
      return FALSE;
    }

    // Map to D10 roles
    $roles = array_unique(array_map(
      function ($role) use ($role_map) {
        return $role_map[$role];
      },
      $allowed_roles
    ));

    try {
      // Load or create user
      $user_storage = $this->entityTypeManager->getStorage('user');
      $existing_users = $user_storage->loadByProperties(['name' => $username]);

      if ($existing_users) {
        $user = reset($existing_users);
        $is_new = FALSE;
      } else {
        $user = User::create([
          'name' => $username,
          'status' => 1,
        ]);
        $is_new = TRUE;
      }

      // Update user data
      $user->setEmail($user_data['user']['mail']);

      // Set password (Drupal will hash it automatically)
      $user->setPassword($password);

      // Clear existing roles (except authenticated)
      foreach ($user->getRoles(TRUE) as $role) {
        $user->removeRole($role);
      }

      // Add new roles
      foreach ($roles as $role) {
        $user->addRole($role);
      }

      $user->save();

      $this->logger->info('User @username @action from D7 with roles: @roles', [
        '@username' => $username,
        '@action' => $is_new ? 'created and synced' : 'updated and synced',
        '@roles' => implode(', ', $roles),
      ]);

      return $user;

    } catch (\Exception $e) {
      $this->logger->error('Failed to sync user @username: @error', [
        '@username' => $username,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }
}
