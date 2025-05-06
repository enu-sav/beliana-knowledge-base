<?php

declare(strict_types=1);

namespace Drupal\bkb_comment\Entity;

use Drupal\bkb_comment\WordInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\link\LinkItemInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the word entity class.
 *
 * @ContentEntityType(
 *   id = "source_comment_node",
 *   label = @Translation("Word"),
 *   label_collection = @Translation("Words"),
 *   label_singular = @Translation("word"),
 *   label_plural = @Translation("words"),
 *   label_count = @PluralTranslation(
 *     singular = "@count words",
 *     plural = "@count words",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\bkb_comment\WordListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\bkb_comment\WordAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\bkb_comment\Form\WordForm",
 *       "edit" = "Drupal\bkb_comment\Form\WordForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" =
 *   "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "source_comment_node",
 *   admin_permission = "administer source_comment_node",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/word",
 *     "add-form" = "/word/add",
 *     "canonical" = "/word/{source_comment_node}",
 *     "edit-form" = "/word/{source_comment_node}/edit",
 *     "delete-form" = "/word/{source_comment_node}/delete",
 *     "delete-multiple-form" = "/admin/content/word/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.source_comment_node.settings",
 * )
 */
final class Word extends ContentEntityBase implements WordInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }

    // Set path alias
    $this->setPathAlias();

    // Get referenced comment entities
    $comment_ids = [];
    $comments = $this->get('comments')->referencedEntities();

    if (!empty($comments)) {
      // Copy url to Comment entity due to JSONAPI filter limitations
      foreach ($comments as $comment) {
        $comment_ids[] = $comment->id();
        $comment->set('url', $this->get('url')->getValue());
        $comment->save();
      }
    }

    if ($this->isNew()) {
      return;
    }

    $original_comments = $this->original->get('comments')->referencedEntities();

    if (empty($original_comments)) {
      return;
    }

    // Remove deleted comments entities
    foreach ($original_comments as $original_comment) {
      if (!in_array($original_comment->id(), $comment_ids)) {
        $original_comment->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('comment-node-entity-label-label'))
      ->setDescription(t('comment-node-entity-label-description'))
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
      ->setLabel(t('comment-node-entity-url-label'))
      ->setDescription(t('comment-node-entity-url-description'))
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

    $fields['comments'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('comment-node-entity-comments-label'))
      ->setSetting('target_type', 'source_comment')
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
      ->setLabel(t('comment-node-entity-uid-label'))
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

  /**
   * {@inheritdoc}
   */
  public function setPathAlias() {
    $aliases = $this->entityTypeManager()
      ->getStorage('path_alias')
      ->loadByProperties(['path' => '/word/' . $this->id()]);

    if (empty($aliases)) {
      $alias = $this->entityTypeManager()->getStorage('path_alias')->create([
        'path' => '/word/' . $this->id(),
      ]);
    }
    else {
      $alias = reset($aliases);
    }

    $transliterated = \Drupal::service('transliteration')
      ->transliterate($this->label());
    $cleaned = preg_replace('/\s+/', '-', trim(preg_replace('/[^a-z0-9 ]+/', '', strtolower($transliterated))));

    $alias->set('alias', '/subor-komentarov/' . $cleaned);
    $alias->save();
  }

}
