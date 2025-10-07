<?php

declare(strict_types=1);

namespace Drupal\bkb_comment;

use Drupal\bkb_base\OwnershipAccessTrait;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the word entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class WordAccessControlHandler extends EntityAccessControlHandler {

  use OwnershipAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view source_comment_node'),
      'update' => $this->checkUpdateAccess($entity, $account),
      'delete' => $this->checkDeleteAccess($entity, $account),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create source_comment_node', 'administer source_comment_node'], 'OR');
  }

  /**
   * Checks update access for word entities.
   */
  protected function checkUpdateAccess(EntityInterface $entity, AccountInterface $account): AccessResult {
    return $this->checkOwnershipBasedAccess($entity, $account, 'edit', 'source_comment_node');
  }

  /**
   * Checks delete access for word entities.
   */
  protected function checkDeleteAccess(EntityInterface $entity, AccountInterface $account): AccessResult {
    return $this->checkOwnershipBasedAccess($entity, $account, 'delete', 'source_comment_node');
  }

}
