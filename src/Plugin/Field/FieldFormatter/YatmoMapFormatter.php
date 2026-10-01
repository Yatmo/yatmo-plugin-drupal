<?php

declare(strict_types=1);

namespace Drupal\yatmo_map\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shows a Geofield as the Yatmo map of the property.
 */
#[FieldFormatter(
  id: 'yatmo_map_map',
  label: new TranslatableMarkup('Yatmo map'),
  description: new TranslatableMarkup('The Yatmo neighbourhood map (points of interest, travel times, isochrones) centred on the location.'),
  field_types: ['geofield'],
)]
class YatmoMapFormatter extends YatmoFormatterBase {

  protected function kind(): string {
    return 'map';
  }

}
