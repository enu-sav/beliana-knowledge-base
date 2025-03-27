<?php

declare(strict_types=1);

namespace Drupal\bkb_comment;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a word entity type.
 */
interface WordInterface extends ContentEntityInterface, EntityOwnerInterface {

}
