<?php

declare(strict_types=1);

namespace Drupal\bkb_comment;

use Drupal\bkb_base\OwnershipAccessTrait;
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

  use OwnershipAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view source_comment'),
      'update' => $this->checkUpdateAccess($entity, $account),
      'delete' => $this->checkDeleteAccess($entity, $account),
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

  /**
   * Checks update access for comment entities.
   */
  protected function checkUpdateAccess(EntityInterface $entity, AccountInterface $account): AccessResult {
    // Anyone can edit comments (requirement: "Upraviť text komentára môže každý")
    return AccessResult::allowedIfHasPermission($account, 'edit source_comment');
  }

  /**
   * Checks delete access for comment entities.
   */
  protected function checkDeleteAccess(EntityInterface $entity, AccountInterface $account): AccessResult {
    return $this->checkOwnershipBasedAccess($entity, $account, 'delete', 'source_comment');
  }

}
