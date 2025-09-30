<?php

declare(strict_types=1);

namespace Drupal\bkb_base;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Trait for ownership-based access checking.
 */
trait OwnershipAccessTrait {

  /**
   * Generic ownership-based access check.
   */
  protected function checkOwnershipBasedAccess(EntityInterface $entity, AccountInterface $account, string $operation, string $entity_type): AccessResult {
    // Check if user has general permission
    if ($account->hasPermission("{$operation} {$entity_type}")) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Check if user has permission for their own content and is the owner
    if ($account->hasPermission("{$operation} own {$entity_type}") &&
        $entity->getOwnerId() == $account->id() &&
        $account->isAuthenticated()) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }

    return AccessResult::neutral()->cachePerPermissions();
  }

}