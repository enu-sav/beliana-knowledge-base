<?php

declare(strict_types=1);

namespace Drupal\bkb_source\Form;

use Drupal\bkb_base\AiBibtex;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a form for editing the data field of source entities referenced by
 * a source comment.
 *
 * This form allows users to edit the data field of each source entity that is
 * referenced by a source comment. It dynamically generates form elements for
 * each referenced source entity that has a data field.
 *
 * @package Drupal\bkb_base\Form
 */
class SourceDataFieldEditForm extends FormBase {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /** @var \Drupal\Core\Config\ConfigFactory */
  protected $config;

  /** @var \Drupal\bkb_base\AiBibtex */
  protected $bibtexService;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, AiBibtex $ai_bibtex_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('bkb_base.settings');
    $this->bibtexService = $ai_bibtex_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('bkb_base.ai_bibtex')
    );
  }

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
    $word = $this->entityTypeManager
      ->getStorage('source_comment_node')
      ->load($id);

    $form_state->set('source_comment_node', $id);

    $source_groups = [];
    $comments = $word->get('comments')->referencedEntities();

    foreach ($comments as $comment) {
      $source_groups = array_merge($source_groups, $comment->get('sources')->referencedEntities());
    }

    $query = \Drupal::request()->query->get('excluded');
    $excluded = $query ? explode(',', $query) : [];

    // Loop through each referenced entity and create a form element for it.
    foreach ($source_groups as $source_group) {
      $referenced_entities = $source_group->get('source')->referencedEntities();
      foreach ($referenced_entities as $referenced_entity) {
        if (!empty($excluded) && in_array($referenced_entity->id(), $excluded)) {
          continue;
        }

        $form['source']['source_' . $referenced_entity->id()] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Source: @source', ['@source' => $referenced_entity->label()]),
          '#tree' => TRUE,
          '#open' => TRUE,
        ];

        $form['source']['source_' . $referenced_entity->id()]['label'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['label-wrapper']],
        ];

        $form['source']['source_' . $referenced_entity->id()]['label']['input'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#default_value' => $referenced_entity->get('label')->value,
        ];

//        $form['source']['source_' . $referenced_entity->id()]['label']['update'] = [
//          '#type' => 'button',
//          '#value' => $this->t('bkb-source-fetch-bibtex-button-label'),
//          '#attributes' => [
//            'data-id' => $referenced_entity->id(),
//          ],
//          '#ajax' => [
//            'callback' => '::updateBibtex',
//            'wrapper' => 'bibtex-data-' . $referenced_entity->id(),
//          ],
//        ];

        $form['source']['source_' . $referenced_entity->id()]['data'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Data'),
          '#default_value' => $referenced_entity->get('data')->value,
          '#prefix' => '<div id="bibtex-data-' . $referenced_entity->id() . '">',
          '#suffix' => '</div>',
          '#attributes' => [
            'rows' => 10,
          ],
        ];
      }
    }

    if (!isset($form['source'])) {
      $url = Url::fromRoute('view.comments_overview.page')->toString();
      $response = new RedirectResponse($url);
      $response->send();

      return [];
    }

    // Add a submit button.
    $form['actions'] = ['#type' => 'actions'];

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
    $form_values = $form_state->getValues();
    $storage = $this->entityTypeManager->getStorage('source');

    foreach ($form_values as $name => $value) {
      if (strpos($name, 'source_') === 0) {
        preg_match('/source_(\d+)/', $name, $matches);

        if ($source = $storage->load($matches[1])) {
          $source->set('data', $value['data']);
          $source->save();
        }
      }
    }

    $form_state->setRedirect('entity.source_comment_node.canonical', ['source_comment_node' => $form_state->get('source_comment_node')]);
  }

  /**
   * Ajax callback to regenerate bibtex with AI
   */
  public function updateBibtex(array $form, FormStateInterface $form_state) {
    $id = $form_state->getTriggeringElement()['#attributes']['data-id'];
    $label = $form_state->getUserInput()['source_' . $id]['label']['input'];

    $data = $this->bibtexService->getBibtex($this->config->get('api_key'), $label, $this->config->get('ai_prompt'));
    $form['source']['source_' . $id]['data']['#value'] = $data;

    return $form['source']['source_' . $id]['data'];
  }

}
