<?php

declare(strict_types=1);

namespace Drupal\yatmo_map\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shows a Geofield as the Yatmo neighbourhood text of the property.
 */
#[FieldFormatter(
  id: 'yatmo_map_text',
  label: new TranslatableMarkup('Yatmo neighbourhood text'),
  description: new TranslatableMarkup('The written description of the neighbourhood (shops, schools, transport, leisure), rendered on the server for search engines.'),
  field_types: ['geofield'],
)]
class YatmoTextFormatter extends YatmoFormatterBase {

  protected function kind(): string {
    return 'text';
  }

}
