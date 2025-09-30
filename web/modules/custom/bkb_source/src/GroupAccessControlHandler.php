<?php

declare(strict_types=1);

namespace Drupal\bkb_source;

use Drupal\bkb_base\OwnershipAccessTrait;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the group entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class GroupAccessControlHandler extends EntityAccessControlHandler {

  use OwnershipAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view source_group'),
      'update' => $this->checkOwnershipBasedAccess($entity, $account, 'edit', 'source_group'),
      'delete' => $this->checkOwnershipBasedAccess($entity, $account, 'delete', 'source_group'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create source_group', 'administer source_group'], 'OR');
  }

}
