<?php

namespace Drupal\bkb_base;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
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
   * Constructor for Helper.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_manager
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   */
  public function __construct(EntityTypeManager $entity_type_manager, EntityDefinitionUpdateManagerInterface $entity_definition_manager, EntityFieldManager $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDefinitionManager = $entity_definition_manager;
    $this->entityFieldManager = $entity_field_manager;
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
        $word = $storage->create([
          'label' => $title,
          'url' => $url,
        ]);
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

}
