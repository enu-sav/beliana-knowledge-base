<?php

declare(strict_types=1);

namespace Drupal\bkb_comment\Entity;

use Drupal\bkb_comment\CommentInterface;
use Drupal\bkb_comment\Plugin\Field\FieldType\ComputedParentFieldItemList;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\link\LinkItemInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;

/**
 * Defines the comment entity class.
 *
 * @ContentEntityType(
 *   id = "source_comment",
 *   label = @Translation("Comment"),
 *   label_collection = @Translation("Comments"),
 *   label_singular = @Translation("comment"),
 *   label_plural = @Translation("comments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count comments",
 *     plural = "@count comments",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\bkb_comment\CommentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\bkb_comment\CommentAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\bkb_comment\Form\CommentForm",
 *       "edit" = "Drupal\bkb_comment\Form\CommentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *       "revision-delete" = \Drupal\Core\Entity\Form\RevisionDeleteForm::class,
 *       "revision-revert" = \Drupal\Core\Entity\Form\RevisionRevertForm::class,
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *       "revision" = \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider::class,
 *     },
 *   },
 *   base_table = "source_comment",
 *   revision_table = "source_comment_revision",
 *   admin_permission = "administer source_comment",
 *   show_revision_ui = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/source-comment",
 *     "add-form" = "/source-comment/add",
 *     "canonical" = "/source-comment/{source_comment}",
 *     "edit-form" = "/source-comment/{source_comment}/edit",
 *     "delete-form" = "/source-comment/{source_comment}/delete",
 *     "delete-multiple-form" = "/admin/content/source-comment/delete-multiple",
 *     "revision" = "/source-comment/{source_comment}/revisions/{source_comment_revision}/view",
 *     "revision-delete-form" = "/source-comment/{source_comment}/revisions/{source_comment_revision}/delete",
 *     "revision-revert-form" = "/source-comment/{source_comment}/revisions/{source_comment_revision}/revert",
 *     "version-history" = "/source-comment/{source_comment}/revisions",
 *   },
 *   field_ui_base_route = "entity.source_comment.settings",
 * )
 */
final class Comment extends ContentEntityBase implements CommentInterface, RevisionLogInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    // Handle revision URLs with empty revision IDs
    if (in_array($rel, ['revision', 'revision-delete-form', 'revision-revert-form']) && empty($this->getRevisionId())) {
      // Return the canonical URL instead for entities without valid revision IDs
      return parent::toUrl('canonical', $options);
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }

    $comment = $this->get('comment')->value;
    if (!empty($comment)) {
      $label = substr($comment, 0, 60);
      $label .= strlen($comment) > 60 ? '...' : '';

      $this->set('label', $label);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    // Delete referenced source_groups
    foreach ($entities as $entity) {
      foreach ($entity->get('sources')->referencedEntities() as $group) {
        $group->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('comment-entity-label-label'))
      ->setDescription(t('comment-entity-label-description'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setRevisionable(TRUE);

    $fields['url'] = BaseFieldDefinition::create('link')
      ->setLabel(t('comment-entity-url-label'))
      ->setDescription(t('comment-entity-url-description'))
      ->setSettings([
        'link_type' => LinkItemInterface::LINK_EXTERNAL,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => 30,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'link_default',
        'weight' => 30,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['comment'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('comment-entity-comment-label'))
      ->setDescription(t('comment-entity-comment-description'))
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'basic_string',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setRevisionable(TRUE);

    $fields['sources'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('comment-entity-sources-label'))
      ->setSetting('target_type', 'source_group')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'auto_create' => TRUE,
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'weight' => 25,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 25,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRevisionable(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('comment-entity-uid-label'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['parent'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Word ID'))
      ->setComputed(TRUE)
      ->setClass(ComputedParentFieldItemList::class)
      ->setReadOnly(TRUE);

    $fields['revision_timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Revision create time'))
      ->setDescription(t('The time that the current revision was created.'))
      ->setRevisionable(TRUE);

    $fields['revision_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Revision user'))
      ->setDescription(t('The user ID of the author of the current revision.'))
      ->setSetting('target_type', 'user')
      ->setRevisionable(TRUE);

    $fields['revision_log'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Revision log message'))
      ->setDescription(t('Briefly describe the changes you have made.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue('');

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionCreationTime(): int {
    return (int) $this->get('revision_timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRevisionCreationTime($timestamp): static {
    $this->set('revision_timestamp', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionUser(): ?UserInterface {
    return $this->get('revision_uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setRevisionUser(UserInterface $account): static {
    $this->set('revision_uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRevisionUserId($uid): static {
    $this->set('revision_uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionUserId(): ?int {
    return $this->get('revision_uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRevisionLogMessage(): ?string {
    return $this->get('revision_log')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRevisionLogMessage($revision_log_message): static {
    $this->set('revision_log', $revision_log_message);
    return $this;
  }

}
