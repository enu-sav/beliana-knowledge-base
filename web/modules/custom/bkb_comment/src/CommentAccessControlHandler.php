<?php

declare(strict_types=1);

namespace Drupal\bkb_comment;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the comment entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class CommentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view source_comment'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit source_comment'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete source_comment'),
      'view all revisions' => AccessResult::allowedIfHasPermission($account, 'view all source_comment revisions'),
      'view revision' => AccessResult::allowedIfHasPermission($account, 'view source_comment revisions'),
      'revert' => AccessResult::allowedIfHasPermission($account, 'revert source_comment revisions'),
      'delete revision' => AccessResult::allowedIfHasPermission($account, 'delete source_comment revisions'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create source_comment', 'administer source_comment'], 'OR');
  }

}
