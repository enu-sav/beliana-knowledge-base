<?php

namespace Drupal\bkb_comment\TwigExtension;

use Drupal\Core\Config\ConfigFactoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension to provide config filter.
 */
class ConfigExtension extends AbstractExtension {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ConfigExtension object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('config', [$this, 'getConfig']),
    ];
  }

  /**
   * Gets a configuration value.
   *
   * @param string $config_name
   *   The configuration object name.
   * @param string $key
   *   The configuration key.
   *
   * @return mixed
   *   The configuration value.
   */
  public function getConfig($config_name, $key) {
    return $this->configFactory->get($config_name)->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'bkb_comment.twig.config';
  }

}
