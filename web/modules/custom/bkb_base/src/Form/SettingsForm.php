<?php

declare(strict_types=1);

namespace Drupal\bkb_base\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure BKB Base settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_provider_openai.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bkb_base_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bkb_base.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('bkb_base.settings');

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Default AI'),
      '#description' => $this->t('The API Key. Can be found on <a href="https://platform.openai.com/">https://platform.openai.com/</a>.'),
      '#default_value' => $config->get('api_key') ?? 'perplexity',
    ];

    $form['ai_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt for AI'),
      '#description' => $this->t('Available tokens: [source:title]'),
      '#default_value' => $config->get('ai_prompt') ?? 'Give me bibtex record (no additional text, just the record) for [source:title]',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('bkb_base.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('ai_prompt', $form_state->getValue('ai_prompt'))
      ->save();

    // Clear all caches to ensure config changes are reflected everywhere
    drupal_flush_all_caches();

    $formValues = $form_state->getValues();
    foreach ($formValues as $name => $value) {
      if (strpos($name, 'source_') === 0) {
        preg_match('/source_(\d+)/', $name, $matches);
        $source = \Drupal::entityTypeManager()->getStorage('source')->load($matches[1]);
        $source->set('data', $value);
        $source->save();
      }
    }

    parent::submitForm($form, $form_state);
  }

}
