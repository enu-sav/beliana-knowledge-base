<?php

namespace Drupal\bkb_comment\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

class ComputedUrlFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  protected function computeValue() {
    $entity = $this->getEntity();

    if ($entity->isNew()) {
      return;
    }

    $words = \Drupal::entityTypeManager()
      ->getStorage('source_comment_node')
      ->loadByProperties(['comments' => $entity->id()]);

    if (empty($words)) {
      return;
    }

    $word = reset($words);
    $url = $word->get('url')->value;
    $web_type = $word->get('web_type')->value;

    if (empty($url)) {
      return;
    }

    // If URL is a path and we have web_type, construct full URL
    if (!empty($web_type) && strpos($url, '/') === 0) {
      $config = \Drupal::config('bkb_base.settings');
      $base_url = $config->get($web_type . '_url');

      if ($base_url) {
        $url = rtrim($base_url, '/') . $url;
      }
    }

    $this->list[0] = $this->createItem(0, $url);
  }

}
