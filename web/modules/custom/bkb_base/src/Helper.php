<?php

namespace Drupal\bkb_base;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Helper
 */
class Helper {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  private EntityTypeManager $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   *   $entityDefinitionManager
   */
  private EntityDefinitionUpdateManagerInterface $entityDefinitionManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   */
  private EntityFieldManager $entityFieldManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface $currentUser
   */
  private AccountInterface $currentUser;

  /**
   * Constructor for Helper.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_manager
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
  public function __construct(EntityTypeManager $entity_type_manager, EntityDefinitionUpdateManagerInterface $entity_definition_manager, EntityFieldManager $entity_field_manager, AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDefinitionManager = $entity_definition_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Function to install custom entity
   *
   * @param $entity_type
   *
   * @return void
   */
  function installEntityType($entity_type) {
    if ($entity_type_definition = $this->entityTypeManager->getDefinition($entity_type)) {
      $this->entityDefinitionManager->installEntityType($entity_type_definition);
    }
  }

  /**
   * Function to install field storage definition
   *
   * @param $module_name
   * @param $entity_type
   * @param $fields
   *
   * @return void
   */
  public function installFieldStorageDefinition($module_name, $entity_type, $fields): void {
    $this->entityTypeManager->clearCachedDefinitions();

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity_type);

    foreach ($fields as $field_name) {
      if (!empty($field_definitions[$field_name]) && $field_definitions[$field_name] instanceof FieldStorageDefinitionInterface) {
        $this->entityDefinitionManager
          ->installFieldStorageDefinition(
            $field_name,
            $entity_type,
            $module_name,
            $field_definitions[$field_name]);
      }
    }
  }

  /**
   * Function to get Word entity from request query params
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param bool $create
   *
   * @return EntityInterface|bool
   */
  public function getWordFromRequest(Request $request, bool $create = FALSE): EntityInterface|bool {
    $storage = $this->entityTypeManager->getStorage('source_comment_node');
    if ($word_id = $request->query->get('word')) {
      return $storage->load($word_id);
    }
    else {
      $title = $request->query->get('title');
      $url = $request->query->get('url');

      if ($create && ($title && $url)) {
        // Process URL to extract path and web_type
        $processed = $this->processUrlForWebType($url, getenv('RS_SITE'), getenv('WEBRS_SITE'));

        $word_data = [
          'label' => $title,
          'url' => $processed['url'],
        ];

        // Set web_type if detected
        if (!empty($processed['web_type'])) {
          $word_data['web_type'] = $processed['web_type'];
        }

        $word = $storage->create($word_data);
        $word->save();

        return $word;
      }
    }

    return FALSE;
  }

  /**
   * Function to get check if Comment sources include new one and return list
   * of existing
   *
   * @param array $sources
   *
   * @return array|bool
   */
  public function isSourceNew($sources): array|bool {
    $new_source = FALSE;
    $excluded = [];

    foreach ($sources as $i => $source) {
      if (is_numeric($i)) {
        $target_id = $source['inline_entity_form']['source'][0]['target_id'];

        if (is_numeric($target_id)) {
          $excluded[] = $target_id;
        }
        else {
          $new_source = TRUE;
        }
      }
    }

    return $new_source ? $excluded : FALSE;
  }

  /**
   * Change field type from varchar to text while preserving data.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name to change.
   * @param array $field_spec
   *   The new field specification.
   *
   * @return void
   */
  public function changeFieldType(string $entity_type, string $field_name, array $field_spec): void {
    $database = \Drupal::database();

    // Store existing field data in PHP variable.
    $existing_data = $database->select($entity_type, 'e')
      ->fields('e', ['id', $field_name])
      ->execute()
      ->fetchAllKeyed();

    // Drop the original column.
    $database->schema()->dropField($entity_type, $field_name);

    // Add the new column.
    $database->schema()->addField($entity_type, $field_name, $field_spec);

    // Restore data from PHP variable.
    foreach ($existing_data as $id => $field_value) {
      if (!empty($field_value)) {
        $database->update($entity_type)
          ->fields([$field_name => $field_value])
          ->condition('id', $id)
          ->execute();
      }
    }

    // Clear entity cache to ensure the new field definition is used.
    $this->entityTypeManager->clearCachedDefinitions();
  }

  /**
   * @param \Drupal\bkb_comment\Entity\Comment $comment
   *
   * @return bool
   */
  public function userIsCommentAuthor($comment) {
    return $comment->getOwnerId() === $this->currentUser->id() || $this->currentUser->hasPermission('administer source_comment');
  }

  /**
   * Process URL to extract path and determine web_type.
   *
   * @param string|null $url_value
   *   The URL value to process.
   * @param string|null $rs_url
   *   The RS base URL from config.
   * @param string|null $webrs_url
   *   The WEBRS base URL from config.
   *
   * @return array
   *   Array with 'url' and 'web_type' keys.
   */
  public function processUrlForWebType(?string $url_value, ?string $rs_url, ?string $webrs_url): array {
    if (empty($url_value)) {
      return ['url' => NULL, 'web_type' => NULL];
    }

    $parsed = parse_url($url_value);
    if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
      return ['url' => $url_value, 'web_type' => NULL];
    }

    $path = ($parsed['path'] ?? '') .
      (isset($parsed['query']) ? '?' . $parsed['query'] : '') .
      (isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '');

    // Check if domain matches RS or WEBRS
    if ($rs_url && strpos($url_value, $rs_url) === 0) {
      return ['url' => $path, 'web_type' => 'rs'];
    }

    if ($webrs_url && strpos($url_value, $webrs_url) === 0) {
      return ['url' => $path, 'web_type' => 'webrs'];
    }

    return ['url' => $url_value, 'web_type' => NULL];
  }

  /**
   * Convert link field to string field in a table.
   *
   * @param string $table_name
   *   The table name.
   * @param bool $has_web_type
   *   Whether the table has a web_type field.
   * @param callable $process_url_callback
   *   Callback function to process URLs.
   *
   * @return void
   */
  public function convertLinkFieldToString(string $table_name, bool $has_web_type, callable $process_url_callback): void {
    $database = \Drupal::database();

    // Get existing data
    $fields_to_select = ['id', 'url__uri'];
    if ($has_web_type) {
      $fields_to_select[] = 'web_type';
    }

    $query = $database->select($table_name, 't')
      ->fields('t', $fields_to_select);

    // Add revision_id for revision tables
    if (strpos($table_name, '_revision') !== FALSE) {
      $query->addField('t', 'revision_id');
    }

    $data = $query->execute()->fetchAll();

    // Drop old link field columns
    $database->schema()->dropField($table_name, 'url__uri');
    $database->schema()->dropField($table_name, 'url__title');
    $database->schema()->dropField($table_name, 'url__options');

    // Add new string field column
    $database->schema()->addField($table_name, 'url', [
      'type' => 'varchar',
      'length' => 2048,
      'not null' => FALSE,
    ]);

    // Migrate data
    foreach ($data as $row) {
      $processed = $process_url_callback($row->url__uri);
      $fields = ['url' => $processed['url']];

      // Update web_type only for Word entity if detected and not already set
      if ($has_web_type && $processed['web_type'] && empty($row->web_type)) {
        $fields['web_type'] = $processed['web_type'];
      }

      $update = $database->update($table_name)->fields($fields);

      // Add conditions based on table type
      if (isset($row->revision_id)) {
        $update->condition('id', $row->id)
          ->condition('revision_id', $row->revision_id);
      }
      else {
        $update->condition('id', $row->id);
      }

      $update->execute();
    }
  }

  /**
   * Update field storage definition for an entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name to update.
   *
   * @return void
   */
  public function updateFieldStorageDefinition(string $entity_type_id, string $field_name): void {
    $this->entityTypeManager->clearCachedDefinitions();

    $definition = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!$definition) {
      \Drupal::logger('bkb_base')
        ->error('Entity type @type not found', ['@type' => $entity_type_id]);
      return;
    }

    $storage_definitions = $definition->getClass()::baseFieldDefinitions($definition);

    if (!isset($storage_definitions[$field_name])) {
      \Drupal::logger('bkb_base')->error('Field @field not found in @type', [
        '@field' => $field_name,
        '@type' => $entity_type_id,
      ]);
      return;
    }

    \Drupal::service('field_storage_definition.listener')
      ->onFieldStorageDefinitionUpdate(
        $storage_definitions[$field_name],
        $storage_definitions[$field_name]
      );
  }

}
