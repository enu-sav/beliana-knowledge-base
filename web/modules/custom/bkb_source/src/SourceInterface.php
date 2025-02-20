<?php

declare(strict_types=1);

namespace Drupal\bkb_source;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a zdroj entity type.
 */
interface SourceInterface extends ContentEntityInterface, EntityOwnerInterface {

}
