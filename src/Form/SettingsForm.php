<?php

declare(strict_types=1);

namespace Drupal\yatmo_map\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\yatmo_map\YatmoClient;
use Drupal\yatmo_map\YatmoRenderer;

/**
 * Configuration > Web services > Yatmo Map: key, country, defaults, location fields.
 */
class SettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'yatmo_map_settings';
  }

  protected function getEditableConfigNames(): array {
    return ['yatmo_map.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $c = $this->config('yatmo_map.settings');

    $form['licence'] = ['#type' => 'details', '#title' => $this->t('Licence'), '#open' => TRUE];
    $form['licence']['license_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Licence key'),
      '#default_value' => $c->get('license_key'),
      '#required' => TRUE,
      '#description' => $this->t('Your Yatmo frontend key, the one locked to your domains; it is written into the map iframe. Get one at <a href=":url" target="_blank" rel="noopener">yatmo.com</a>.', [':url' => 'https://yatmo.com']),
    ];
    $form['licence']['backend_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backend key (optional)'),
      '#default_value' => $c->get('backend_key'),
      '#description' => $this->t('Used for the server calls (neighbourhood text, geocoding) when your licence has a separate backend key. Empty: the licence key is used.'),
    ];
    $form['licence']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country of your properties'),
      '#options' => array_combine(YatmoClient::COUNTRIES, YatmoClient::COUNTRIES),
      '#default_value' => $c->get('country'),
    ];
    $form['licence']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => ['auto' => $this->t('Same as the page (recommended)')] + YatmoRenderer::LANGUAGES,
      '#default_value' => $c->get('language'),
    ];

    $form['location'] = [
      '#type' => 'details',
      '#title' => $this->t('Location of the content'),
      '#open' => TRUE,
      '#description' => $this->t('How the blocks find the position of the property shown on the page. Machine names of fields of your content type, for example <code>field_location</code>. The first one that is filled wins.'),
    ];
    $form['location']['geofield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Geofield'),
      '#default_value' => $c->get('geofield'),
      '#description' => $this->t('A field of the <a href=":url" target="_blank" rel="noopener">Geofield</a> module (lat and lon).', [':url' => 'https://www.drupal.org/project/geofield']),
    ];
    $form['location']['latitude_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Latitude field'),
      '#default_value' => $c->get('latitude_field'),
      '#description' => $this->t('A decimal, float or text field.'),
    ];
    $form['location']['longitude_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Longitude field'),
      '#default_value' => $c->get('longitude_field'),
    ];
    $form['location']['address_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address field'),
      '#default_value' => $c->get('address_field'),
      '#description' => $this->t('A text field or a field of the Address module; the address is located by Yatmo in the country above and remembered for 90 days.'),
    ];

    $form['map'] = ['#type' => 'details', '#title' => $this->t('Map defaults'), '#open' => FALSE, '#description' => $this->t('Every block and formatter starts from these; each can override them. Reference: <a href=":url" target="_blank" rel="noopener">iframe plugin</a>.', [':url' => 'https://documentation.yatmo.com/plugins/iframe'])];
    $form['map']['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => self::modeOptions(),
      '#default_value' => $c->get('mode'),
    ];
    $form['map']['height'] = ['#type' => 'textfield', '#title' => $this->t('Height'), '#default_value' => $c->get('height'), '#description' => $this->t('Pixels (560) or a CSS length (70vh).'), '#size' => 10];
    $form['map']['zoom'] = ['#type' => 'number', '#title' => $this->t('Zoom'), '#min' => 7, '#max' => 20, '#default_value' => $c->get('zoom')];
    $form['map']['map_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Map style'),
      '#options' => [1 => 'Liberty', 2 => 'Basic', 3 => 'Bright', 4 => '3D', 5 => 'Positron', 6 => 'Dark', 7 => 'Liberty Stonehedge'],
      '#default_value' => $c->get('map_style'),
    ];
    $form['map']['accent_color'] = ['#type' => 'textfield', '#title' => $this->t('Accent colour'), '#default_value' => $c->get('accent_color'), '#size' => 10, '#pattern' => '#[0-9a-fA-F]{6}'];
    $form['map']['rounded'] = ['#type' => 'number', '#title' => $this->t('Rounded corners (px)'), '#min' => 0, '#max' => 15, '#default_value' => $c->get('rounded')];
    $form['map']['marker'] = [
      '#type' => 'select',
      '#title' => $this->t('Marker'),
      '#options' => ['pin' => $this->t('Pin (exact location)'), 'circle' => $this->t('Circle (hides the exact address)')],
      '#default_value' => $c->get('marker'),
    ];
    $form['map']['circle_radius'] = ['#type' => 'number', '#title' => $this->t('Circle radius (m)'), '#min' => 50, '#max' => 2000, '#default_value' => $c->get('circle_radius')];
    $form['map']['isochrone'] = [
      '#type' => 'select',
      '#title' => $this->t('Isochrones (reachable area in a travel time)'),
      '#options' => ['' => $this->t('Off'), 'left' => $this->t('On, panel on the left'), 'right' => $this->t('On, panel on the right')],
      '#default_value' => $c->get('isochrone'),
    ];
    $form['map']['route_from'] = [
      '#type' => 'select',
      '#title' => $this->t('Routes from the property'),
      '#options' => ['' => $this->t('Off'), 'left' => $this->t('On, panel on the left'), 'right' => $this->t('On, panel on the right'), 'popup' => $this->t('Travel times in the place popup only')],
      '#default_value' => $c->get('route_from'),
    ];
    $form['map']['favorites'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Favourite addresses for logged-in users'),
      '#default_value' => $c->get('favorites'),
      '#description' => $this->t('Lets a logged-in visitor save the addresses that matter to them (work, school) and see the travel times from every property. Only an anonymous hash of the user id is sent to Yatmo.'),
    ];

    $form['text'] = ['#type' => 'details', '#title' => $this->t('Neighbourhood text defaults'), '#open' => FALSE];
    $form['text']['text_paragraphs'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Paragraphs'),
      '#options' => array_map(fn ($label) => $this->t($label), YatmoRenderer::PARAGRAPHS),
      '#default_value' => $c->get('text_paragraphs') ?: [],
      '#description' => $this->t('None checked = all of them.'),
    ];
    $form['text']['text_heading'] = [
      '#type' => 'select',
      '#title' => $this->t('Heading level'),
      '#options' => array_combine(YatmoRenderer::HEADINGS, YatmoRenderer::HEADINGS),
      '#default_value' => $c->get('text_heading'),
    ];
    $form['text']['text_street'] = ['#type' => 'checkbox', '#title' => $this->t('Name the street in the first heading'), '#default_value' => $c->get('text_street'), '#description' => $this->t('Uncheck both for discreet listings.')];
    $form['text']['text_city'] = ['#type' => 'checkbox', '#title' => $this->t('Name the city in the second heading'), '#default_value' => $c->get('text_city')];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $c = $this->config('yatmo_map.settings');
    foreach (['license_key', 'backend_key', 'country', 'language', 'mode', 'height', 'accent_color', 'marker', 'isochrone', 'route_from', 'text_heading', 'geofield', 'latitude_field', 'longitude_field', 'address_field'] as $key) {
      $c->set($key, trim((string) $form_state->getValue($key)));
    }
    foreach (['zoom', 'map_style', 'rounded', 'circle_radius'] as $key) {
      $c->set($key, (int) $form_state->getValue($key));
    }
    foreach (['favorites', 'text_street', 'text_city'] as $key) {
      $c->set($key, (bool) $form_state->getValue($key));
    }
    $c->set('text_paragraphs', array_values(array_filter(array_map('strval', (array) $form_state->getValue('text_paragraphs')))));
    $c->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * The layouts of the iframe plugin.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   */
  public static function modeOptions(): array {
    return [
      'overlay' => t('Map + summary (summary over the map)'),
      'overlay-scores' => t('Map + scores (scores over the map)'),
      'map-top' => t('Map above the summary'),
      'map' => t('Map only'),
      'summary' => t('Summary only'),
      'summary-tabs' => t('Summary as tabs'),
    ];
  }

}
