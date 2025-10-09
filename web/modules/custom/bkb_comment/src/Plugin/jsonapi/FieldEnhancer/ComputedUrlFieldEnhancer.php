<?php

namespace Drupal\bkb_comment\Plugin\jsonapi\FieldEnhancer;

use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;

/**
 * Enhance computed URL field for filtering.
 *
 * @ResourceFieldEnhancer(
 *   id = "computed_url",
 *   label = @Translation("Computed URL (filterable)"),
 *   description = @Translation("Makes computed URL field filterable.")
 * )
 */
class ComputedUrlFieldEnhancer extends ResourceFieldEnhancerBase {

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($value, Context $context) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'string',
    ];
  }

}
