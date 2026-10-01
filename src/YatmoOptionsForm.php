<?php

declare(strict_types=1);

namespace Drupal\yatmo_map;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\yatmo_map\Form\SettingsForm;

/**
 * The option form shared by the blocks and the formatters (empty = the settings).
 */
final class YatmoOptionsForm {

  /**
   * Default values of the options, as stored in the block or formatter settings.
   *
   * @return array<string, mixed>
   */
  public static function defaults(bool $withLocation): array {
    $defaults = [
      'country' => '', 'language' => '', 'mode' => '', 'height' => '', 'zoom' => '', 'map_style' => '', 'accent_color' => '',
      'marker' => '', 'circle_radius' => '', 'rounded' => '', 'isochrone' => '', 'route_from' => '',
      'paragraphs' => [], 'heading' => '', 'street_in_title' => '', 'city_in_title' => '',
    ];
    if ($withLocation) {
      $defaults = ['source' => 'page', 'address' => '', 'latitude' => '', 'longitude' => ''] + $defaults;
    }
    return $defaults;
  }

  /**
   * The form elements, under a 'yatmo' tree.
   *
   * @param array<string, mixed> $values
   * @param 'map'|'text' $kind
   * @param bool $withLocation
   *   Blocks choose their location; formatters take it from the field.
   */
  public static function build(array $values, string $kind, bool $withLocation, string $statesSelector = 'settings[yatmo]'): array {
    $t = fn (string $s, array $args = []) => new TranslatableMarkup($s, $args);
    $form = ['#type' => 'container', '#tree' => TRUE];

    if ($withLocation) {
      $form['source'] = [
        '#type' => 'radios',
        '#title' => $t('Location'),
        '#options' => ['page' => $t('The content shown on the page (fields chosen in the Yatmo Map settings)'), 'fixed' => $t('This address or these coordinates')],
        '#default_value' => $values['source'] ?? 'page',
      ];
      $fixed = ['visible' => [':input[name="' . $statesSelector . '[source]"]' => ['value' => 'fixed']]];
      $form['address'] = ['#type' => 'textfield', '#title' => $t('Address'), '#default_value' => $values['address'] ?? '', '#states' => $fixed, '#description' => $t('Located by Yatmo in the country of the block.')];
      $form['latitude'] = ['#type' => 'textfield', '#title' => $t('Latitude'), '#default_value' => $values['latitude'] ?? '', '#size' => 12, '#states' => $fixed];
      $form['longitude'] = ['#type' => 'textfield', '#title' => $t('Longitude'), '#default_value' => $values['longitude'] ?? '', '#size' => 12, '#states' => $fixed, '#description' => $t('Coordinates take precedence over the address.')];
    }

    $form['country'] = ['#type' => 'select', '#title' => $t('Country'), '#options' => ['' => $t('- Settings -')] + array_combine(YatmoClient::COUNTRIES, YatmoClient::COUNTRIES), '#default_value' => $values['country'] ?? ''];
    $form['language'] = ['#type' => 'select', '#title' => $t('Language'), '#options' => ['' => $t('- Settings -')] + YatmoRenderer::LANGUAGES, '#default_value' => $values['language'] ?? ''];

    if ($kind === 'map') {
      $form['mode'] = ['#type' => 'select', '#title' => $t('Layout'), '#options' => ['' => $t('- Settings -')] + SettingsForm::modeOptions(), '#default_value' => $values['mode'] ?? ''];
      $form['height'] = ['#type' => 'textfield', '#title' => $t('Height'), '#default_value' => $values['height'] ?? '', '#size' => 10, '#description' => $t('Pixels or a CSS length; empty = settings.')];
      $form['zoom'] = ['#type' => 'number', '#title' => $t('Zoom'), '#min' => 7, '#max' => 20, '#default_value' => $values['zoom'] ?? ''];
      $form['map_style'] = ['#type' => 'select', '#title' => $t('Map style'), '#options' => ['' => $t('- Settings -'), 1 => 'Liberty', 2 => 'Basic', 3 => 'Bright', 4 => '3D', 5 => 'Positron', 6 => 'Dark', 7 => 'Liberty Stonehedge'], '#default_value' => $values['map_style'] ?? ''];
      $form['accent_color'] = ['#type' => 'textfield', '#title' => $t('Accent colour'), '#default_value' => $values['accent_color'] ?? '', '#size' => 10];
      $form['rounded'] = ['#type' => 'number', '#title' => $t('Rounded corners (px)'), '#min' => 0, '#max' => 15, '#default_value' => $values['rounded'] ?? ''];
      $form['marker'] = ['#type' => 'select', '#title' => $t('Marker'), '#options' => ['' => $t('- Settings -'), 'pin' => $t('Pin (exact location)'), 'circle' => $t('Circle (hides the exact address)')], '#default_value' => $values['marker'] ?? ''];
      $form['circle_radius'] = ['#type' => 'number', '#title' => $t('Circle radius (m)'), '#min' => 50, '#max' => 2000, '#default_value' => $values['circle_radius'] ?? ''];
      $form['isochrone'] = ['#type' => 'select', '#title' => $t('Isochrones'), '#options' => ['' => $t('- Settings -'), 'off' => $t('Off'), 'left' => $t('Panel on the left'), 'right' => $t('Panel on the right')], '#default_value' => $values['isochrone'] ?? ''];
      $form['route_from'] = ['#type' => 'select', '#title' => $t('Routes'), '#options' => ['' => $t('- Settings -'), 'off' => $t('Off'), 'left' => $t('Panel on the left'), 'right' => $t('Panel on the right'), 'popup' => $t('Travel times in the place popup only')], '#default_value' => $values['route_from'] ?? ''];
    }
    else {
      $form['paragraphs'] = ['#type' => 'checkboxes', '#title' => $t('Paragraphs'), '#options' => array_map(fn ($l) => $t($l), YatmoRenderer::PARAGRAPHS), '#default_value' => $values['paragraphs'] ?? [], '#description' => $t('None checked = settings.')];
      $form['heading'] = ['#type' => 'select', '#title' => $t('Heading level'), '#options' => ['' => $t('- Settings -')] + array_combine(YatmoRenderer::HEADINGS, YatmoRenderer::HEADINGS), '#default_value' => $values['heading'] ?? ''];
      $yesNo = ['' => $t('- Settings -'), '1' => $t('Yes'), '0' => $t('No')];
      $form['street_in_title'] = ['#type' => 'select', '#title' => $t('Name the street in the first heading'), '#options' => $yesNo, '#default_value' => $values['street_in_title'] ?? ''];
      $form['city_in_title'] = ['#type' => 'select', '#title' => $t('Name the city in the second heading'), '#options' => $yesNo, '#default_value' => $values['city_in_title'] ?? ''];
    }
    return $form;
  }

  /**
   * The submitted values, cleaned: strings trimmed, paragraphs as a list.
   *
   * @return array<string, mixed>
   */
  public static function submitted(FormStateInterface $form_state, bool $withLocation, array $parents = ['yatmo']): array {
    $values = (array) $form_state->getValue($parents);
    $clean = [];
    foreach (self::defaults($withLocation) as $key => $default) {
      $clean[$key] = $key === 'paragraphs'
        ? array_values(array_filter(array_map('strval', (array) ($values[$key] ?? []))))
        : trim((string) ($values[$key] ?? $default));
    }
    return $clean;
  }

  /**
   * The renderer options of stored settings: the location of a "page" source comes from the entity.
   *
   * @param array<string, mixed> $stored
   * @return array<string, mixed>
   */
  public static function options(array $stored): array {
    $options = $stored;
    if (($stored['source'] ?? 'page') !== 'fixed') {
      unset($options['address'], $options['latitude'], $options['longitude']);
    }
    unset($options['source']);
    return array_filter($options, fn ($v) => $v !== '' && $v !== []);
  }

}
