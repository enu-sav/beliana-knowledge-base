<?php

namespace Drupal\bkb_base;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

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

}
