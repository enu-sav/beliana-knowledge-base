<?php

declare(strict_types=1);

namespace Drupal\bkb_source;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a group entity type.
 */
interface GroupInterface extends ContentEntityInterface, EntityOwnerInterface {

}
