<?php

declare(strict_types=1);

namespace Drupal\yatmo_map\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The Yatmo neighbourhood text, rendered on the server and indexable, for the property on the page.
 */
#[Block(
  id: 'yatmo_map_text',
  admin_label: new TranslatableMarkup('Yatmo neighbourhood text'),
  category: new TranslatableMarkup('Yatmo'),
)]
class YatmoTextBlock extends YatmoBlockBase {

  protected function kind(): string {
    return 'text';
  }

}
