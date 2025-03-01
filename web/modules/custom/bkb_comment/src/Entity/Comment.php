<?php

declare(strict_types=1);

namespace Drupal\bkb_comment\Entity;

use Drupal\bkb_comment\CommentInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\link\LinkItemInterface;
use Drupal\user\EntityOwnerTrait;

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
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "source_comment",
 *   admin_permission = "administer source_comment",
 *   entity_keys = {
 *     "id" = "id",
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
 *   },
 *   field_ui_base_route = "entity.source_comment.settings",
 * )
 */
final class Comment extends ContentEntityBase implements CommentInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
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
      ->setRequired(TRUE)
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
      ->setDisplayConfigurable('view', TRUE);

    $fields['url'] = BaseFieldDefinition::create('link')
      ->setLabel(t('comment-entity-url-label'))
      ->setDescription(t('comment-entity-url-description'))
      ->setSettings([
        'link_type' => LinkItemInterface::LINK_EXTERNAL,
        'title' => DRUPAL_DISABLED,
      ])
      ->setRequired(TRUE)
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
      ->setDisplayConfigurable('view', TRUE);

    $fields['sources'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('comment-entity-sources-label'))
      ->setSetting('target_type', 'source_group')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'auto_create' => TRUE,
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(TRUE)
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
      ->setDisplayConfigurable('view', TRUE);

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

    return $fields;
  }

}
