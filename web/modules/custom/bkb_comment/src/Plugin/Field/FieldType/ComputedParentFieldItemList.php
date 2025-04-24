<?php

namespace Drupal\bkb_comment\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

class ComputedParentFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  protected function computeValue() {
    $entity = $this->getEntity();
    $words = \Drupal::entityTypeManager()
      ->getStorage('source_comment_node')
      ->loadByProperties(['comments' => $entity->id()]);

    if (empty($words)) {
      return;
    }

    $delta = 0;
    foreach ($words as $word) {
      $this->list[$delta] = $this->createItem($delta, $word->id());
      $delta++;
    }
  }

}
