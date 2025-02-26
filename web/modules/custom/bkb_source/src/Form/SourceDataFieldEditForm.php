<?php

declare(strict_types=1);

namespace Drupal\bkb_source\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for editing the data field of source entities referenced by a source comment.
 *
 * This form allows users to edit the data field of each source entity that is referenced by a source comment.
 * It dynamically generates form elements for each referenced source entity that has a data field.
 *
 * @package Drupal\bkb_base\Form
 */
class SourceDataFieldEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bkb_base_source_data_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    $comment = \Drupal::entityTypeManager()->getStorage('source_comment')->load($id);
    $sourceGroups = $comment->get('sources')->referencedEntities();

    // Loop through each referenced entity and create a form element for it.
    foreach ($sourceGroups as $sourceGroup) {
      $referenced_entities = $sourceGroup->get('source')->referencedEntities();
      foreach ($referenced_entities as $referenced_entity) {
        // Ensure the referenced entity has the desired field.
        if ($referenced_entity->hasField('data')) {
          // Get the current value of the field to edit.
          $current_value = $referenced_entity->get('data')->value;
          // Create a form element for the field to edit.
          $form['source']['source_' . $referenced_entity->id()] = [
            '#type' => 'textarea',
            '#title' => $this->t('Edit data for @label', ['@label' => $referenced_entity->label()]),
            '#default_value' => $current_value,
            '#description' => $this->t('Edit the value of the source data for the referenced entity: @label', ['@label' => $referenced_entity->label()]),
            '#attributes' => [
              'rows' => 10,
            ],
          ];
        }
      }
    }

    // Add a submit button.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $formValues = $form_state->getValues();
    foreach ($formValues as $name => $value) {
      if (strpos($name, 'source_') === 0) {
        preg_match('/source_(\d+)/', $name, $matches);
        $source = \Drupal::entityTypeManager()->getStorage('source')->load($matches[1]);
        $source->set('data', $value);
        $source->save();
      }
    }

    $form_state->setRedirect('entity.source_comment.collection');
  }

}
