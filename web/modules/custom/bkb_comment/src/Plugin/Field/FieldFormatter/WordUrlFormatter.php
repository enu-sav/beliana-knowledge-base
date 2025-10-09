<?php

namespace Drupal\bkb_comment\Plugin\Field\FieldFormatter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'word_url' formatter.
 *
 * @FieldFormatter(
 *   id = "word_url",
 *   label = @Translation("Word URL with web type"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class WordUrlFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a WordUrlFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();
    $config = $this->configFactory->get('bkb_base.settings');

    foreach ($items as $delta => $item) {
      $url = $item->value;
      $web_type = $entity->get('web_type')->value;

      if (empty($url)) {
        continue;
      }

      $full_url = $url;

      // Build full URL based on web_type
      if (!empty($web_type) && strpos($url, '/') === 0) {
        $base_url = $config->get($web_type . '_url');
        if ($base_url) {
          $full_url = rtrim($base_url, '/') . $url;
        }
      }

      $elements[$delta] = [
        '#type' => 'link',
        '#title' => $full_url,
        '#url' => Url::fromUri($full_url),
        '#options' => [
          'attributes' => [
            'target' => '_blank',
          ],
        ],
      ];
    }

    return $elements;
  }

}
