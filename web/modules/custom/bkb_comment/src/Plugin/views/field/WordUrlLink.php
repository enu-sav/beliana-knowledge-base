<?php

namespace Drupal\bkb_comment\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a custom URL link field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("word_url_link")
 */
class WordUrlLink extends FieldPluginBase {

  /**
   * Constructs a WordUrlLink object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Add the fields we need to the query.
    $this->ensureMyTable();
    $this->addAdditionalFields(['url', 'web_type']);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Get values from the result row.
    $prefix = '';
    $url = $this->getValue($values, 'url');
    $web_type = $this->getValue($values, 'web_type');

    if (empty($url)) {
      return '';
    }

    $full_url = $url;

    // Build full URL based on web_type
    if (!empty($web_type) && strpos($url, '/') === 0) {
      $base_url = getenv(strtoupper($web_type) . '_SITE');

      if ($base_url) {
        $prefix = '[' . strtoupper($web_type) . '] ';
        $full_url = rtrim($base_url, '/') . $url;
      }
    }

    return [
      '#markup' => $prefix . '<a href="' . $full_url . '" target="_blank">' . $url . '</a>',
    ];
  }

}
