<?php

declare(strict_types=1);

namespace Drupal\yatmo_map\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The Yatmo map (iframe plugin) for the property shown on the page, or a fixed location.
 */
#[Block(
  id: 'yatmo_map_map',
  admin_label: new TranslatableMarkup('Yatmo map'),
  category: new TranslatableMarkup('Yatmo'),
)]
class YatmoMapBlock extends YatmoBlockBase {

  protected function kind(): string {
    return 'map';
  }

}
