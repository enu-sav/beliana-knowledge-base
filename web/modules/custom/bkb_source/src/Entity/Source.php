<?php

declare(strict_types=1);

namespace Drupal\bkb_source\Entity;

use Drupal\bkb_base\BibTeXConverter;
use Drupal\bkb_source\SourceInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;
use Drupal\Component\Utility\Html;

/**
 * Defines the source entity class.
 *
 * @ContentEntityType(
 *   id = "source",
 *   label = @Translation("Source"),
 *   label_collection = @Translation("Sources"),
 *   label_singular = @Translation("source"),
 *   label_plural = @Translation("sources"),
 *   label_count = @PluralTranslation(
 *     singular = "@count sources",
 *     plural = "@count sources",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\bkb_source\SourceListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\bkb_source\SourceAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\bkb_source\Form\SourceForm",
 *       "edit" = "Drupal\bkb_source\Form\SourceForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" =
 *   "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\bkb_source\Routing\SourceHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "source",
 *   admin_permission = "administer source",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/source",
 *     "add-form" = "/source/add",
 *     "canonical" = "/source/{source}",
 *     "edit-form" = "/source/{source}",
 *     "delete-form" = "/source/{source}/delete",
 *     "delete-multiple-form" = "/admin/content/source/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.source.settings",
 * )
 */
final class Source extends ContentEntityBase implements SourceInterface {

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

    if ($this->isNew()) {
      $config = \Drupal::configFactory()->get('bkb_base.settings');
      $response_text = \Drupal::service('bkb_base.ai_bibtex')
        ->getBibtex($config->get('api_key'), $this->get('label')->value, $config->get('ai_prompt'));

      if ($response_text) {
        $this->set('data', $response_text);
      }
    }

    // Create citation form the bibtex value
    if (!$this->get('data')->isEmpty()) {
      $escapedValue = Html::escape($this->get('data')->value);

      // Create a new BibTeXConverter instance with the escaped value.
      $converter = new BibTeXConverter($escapedValue);
      $harvardCitations = $converter->convertToHarvard();
      $citation = reset($harvardCitations);

      // Set the 'citation' field with the first Harvard citation.
      $this->set('citation', $citation);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
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

    $fields['data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Data'))
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'basic_string',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['attachment'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Attachment'))
      ->setSettings([
        'file_extensions' => 'pdf',
        'uri_scheme' => 'private',
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 11,
        'settings' => [
          'file_extensions' => 'pdf',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'file_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
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

    $fields['citation'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Citation'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
