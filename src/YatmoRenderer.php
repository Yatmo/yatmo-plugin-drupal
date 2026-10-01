<?php

declare(strict_types=1);

namespace Drupal\yatmo_map;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;

/**
 * Builds the map (iframe plugin) and the neighbourhood text as render arrays.
 *
 * Options accepted by map() and text(), all optional, empty = the settings:
 * entity (the node or any fieldable entity holding the location), address, latitude, longitude,
 * country, language, mode, marker, circle_radius, map_style, accent_color, zoom, height, rounded,
 * isochrone, route_from, title, class; and for the text: paragraphs (list or comma separated),
 * heading (h2 to h6), street_in_title, city_in_title. Service id: yatmo_map.renderer.
 */
class YatmoRenderer {

  public const VERSION = '1.0.0';
  public const IFRAME_URL = 'https://map.yatmo.com/plugin.html';

  public const MODES = ['overlay', 'overlay-scores', 'map-top', 'map', 'summary', 'summary-tabs'];
  public const MARKERS = ['pin', 'circle'];
  public const SIDES = ['', 'left', 'right'];
  public const ROUTE_FROM = ['', 'left', 'right', 'popup'];
  public const HEADINGS = ['h2', 'h3', 'h4', 'h5', 'h6'];

  /**
   * Paragraph types of the text, in the order of the settings.
   */
  public const PARAGRAPHS = [
    'education' => 'Education',
    'shopping' => 'Shopping',
    'publictransports' => 'Public transport',
    'transports' => 'Roads, stations and airports',
    'tourism' => 'Leisure and tourism',
    'cities' => 'Nearby cities',
  ];

  /**
   * Languages of the widget, with their native names.
   */
  public const LANGUAGES = [
    'EN' => 'English', 'FR' => 'Français', 'NL' => 'Nederlands', 'DE' => 'Deutsch', 'IT' => 'Italiano', 'ES' => 'Español',
    'PT' => 'Português', 'EL' => 'Ελληνικά', 'HR' => 'Hrvatski', 'SL' => 'Slovenščina', 'SR' => 'Srpski', 'BS' => 'Bosanski',
    'CNR' => 'Crnogorski', 'SQ' => 'Shqip', 'MT' => 'Malti', 'TR' => 'Türkçe', 'BG' => 'Български', 'AR' => 'العربية', 'JA' => '日本語',
  ];

  public function __construct(
    protected YatmoClient $client,
    protected ConfigFactoryInterface $configFactory,
    protected LanguageManagerInterface $languageManager,
    protected AccountProxyInterface $currentUser,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * The settings as an array.
   *
   * @return array<string, mixed>
   */
  public function settings(): array {
    return $this->configFactory->get('yatmo_map.settings')->get() ?: [];
  }

  /**
   * The map as a render array: the iframe, a notice for editors, or nothing for visitors.
   *
   * @param array<string, mixed> $options
   */
  public function map(array $options = []): array {
    try {
      $context = $this->context($options);
      $params = $this->params($context);
    }
    catch (YatmoException $e) {
      return $this->notice($e->getMessage(), $options);
    }
    $height = self::height((string) ($options['height'] ?? ''), (string) $context['settings']['height']);
    $title = trim((string) ($options['title'] ?? '')) ?: 'Map and neighbourhood of the property';
    $build = [
      '#theme' => 'yatmo_map_embed',
      '#src' => self::IFRAME_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986),
      '#height' => $height,
      // Not #title: Drupal would take it as the block label.
      '#iframe_title' => $title,
      '#attributes' => ['class' => self::classes($options)],
    ];
    return $this->cacheable($build, $options);
  }

  /**
   * The iframe parameters of a location, for themes or modules that build their own markup.
   *
   * @param array<string, mixed> $options
   * @return array<string, string>
   *
   * @throws \Drupal\yatmo_map\YatmoException
   */
  public function iframeParams(array $options = []): array {
    return $this->params($this->context($options));
  }

  /**
   * The neighbourhood text as a render array (indexable headings and paragraphs).
   *
   * @param array<string, mixed> $options
   */
  public function text(array $options = []): array {
    try {
      $context = $this->context($options);
      $summary = $this->client->summaryText($context['country'], $context['location']['lat'], $context['location']['lng']);
    }
    catch (YatmoException $e) {
      return $this->notice($e->getMessage(), $options);
    }
    $s = $context['settings'];
    $wanted = self::paragraphList($options['paragraphs'] ?? NULL) ?: self::paragraphList($s['text_paragraphs'] ?? NULL) ?: array_keys(self::PARAGRAPHS);
    $heading = strtolower((string) ($options['heading'] ?? ''));
    if (!in_array($heading, self::HEADINGS, TRUE)) {
      $heading = in_array($s['text_heading'] ?? '', self::HEADINGS, TRUE) ? $s['text_heading'] : 'h3';
    }
    $street = self::flag($options['street_in_title'] ?? NULL, (bool) ($s['text_street'] ?? TRUE));
    $city = self::flag($options['city_in_title'] ?? NULL, (bool) ($s['text_city'] ?? TRUE));

    $paragraphs = self::paragraphs($summary, $context['language'], $wanted, $street, $city);
    if ($paragraphs === []) {
      return $this->notice('Yatmo has no text for this location with the selected paragraphs.', $options);
    }
    $build = [
      '#theme' => 'yatmo_map_text',
      '#paragraphs' => $paragraphs,
      '#heading' => $heading,
      '#attributes' => ['class' => self::classes($options)],
    ];
    return $this->cacheable($build, $options);
  }

  /**
   * The paragraphs to show, from the API answer: title, sentences (markup) and items (markup).
   *
   * First heading names the street, second the city, the others stay generic: this is what reads
   * naturally. Both off for discreet listings. Pure, so themes and tests can call it.
   *
   * @param array<string, mixed> $summary
   * @param string[] $wanted
   * @return array<int, array{title: string, sentences: \Drupal\Core\Render\MarkupInterface|string, items: array<int, \Drupal\Core\Render\MarkupInterface>}>
   */
  public static function paragraphs(array $summary, string $language, array $wanted, bool $street, bool $city): array {
    $out = [];
    foreach ($summary['Paragraphs'] ?? [] as $paragraph) {
      if (empty($paragraph['IconId']) || !in_array($paragraph['IconId'], $wanted, TRUE)) {
        continue;
      }
      $lang = self::pickLanguage((array) ($paragraph['Title'] ?? []), $language);
      if ($lang === '') {
        continue;
      }
      $titleKey = 'Title';
      if (count($out) === 0 && $street && !empty($paragraph['TitleBis'][$lang])) {
        $titleKey = 'TitleBis';
      }
      elseif (count($out) === 1 && $city && !empty($paragraph['TitleTer'][$lang])) {
        $titleKey = 'TitleTer';
      }
      $sentences = [];
      foreach ((array) ($paragraph['Sentences'] ?? []) as $sentence) {
        if (is_array($sentence) && !empty($sentence[$lang])) {
          $sentences[] = self::sentence((string) $sentence[$lang]);
        }
      }
      $items = [];
      foreach ((array) ($paragraph['List'] ?? []) as $item) {
        if (is_array($item) && !empty($item[$lang])) {
          $items[] = Markup::create(self::sentence((string) $item[$lang]));
        }
      }
      if ($sentences === [] && $items === []) {
        continue;
      }
      $out[] = [
        'title' => (string) ($paragraph[$titleKey][$lang] ?? $paragraph['Title'][$lang] ?? ''),
        'sentences' => $sentences ? Markup::create(implode(' ', $sentences)) : '',
        'items' => $items,
      ];
    }
    return $out;
  }

  /**
   * The iframe parameters of a resolved context, validated like the WordPress plugin does.
   *
   * @param array<string, mixed> $context
   * @return array<string, string>
   */
  public function params(array $context): array {
    $s = $context['settings'];
    $o = $context['options'];
    $params = [
      'licenseKey' => $s['license_key'],
      'country' => $context['country'],
      'language' => $context['language'],
      'latitude' => YatmoClient::coordinate($context['location']['lat']),
      'longitude' => YatmoClient::coordinate($context['location']['lng']),
      'mode' => self::allowed($o['mode'] ?? '', self::MODES, (string) $s['mode']),
      'zoom' => (string) self::intBetween($o['zoom'] ?? '', 7, 20, (int) $s['zoom']),
      'mapStyle' => (string) self::intBetween($o['map_style'] ?? '', 1, 7, (int) $s['map_style']),
      'accentColor' => self::hexColor($o['accent_color'] ?? '') ?: (string) $s['accent_color'],
    ];
    $marker = self::allowed($o['marker'] ?? '', self::MARKERS, (string) $s['marker']);
    $params['marker'] = $marker;
    if ($marker === 'circle') {
      $params['circleRadiusInMeters'] = (string) self::intBetween($o['circle_radius'] ?? '', 50, 2000, (int) $s['circle_radius']);
    }
    $rounded = self::intBetween($o['rounded'] ?? '', 0, 15, (int) $s['rounded']);
    if ($rounded > 0) {
      $params['rounded'] = $rounded . 'px';
    }
    // "off" in a block switches a module off even when the settings enable it.
    $isochrone = self::allowed($o['isochrone'] ?? '', ['off', 'left', 'right'], (string) $s['isochrone']);
    if (in_array($isochrone, ['left', 'right'], TRUE)) {
      $params['isochrone'] = $isochrone;
    }
    $routeFrom = self::allowed($o['route_from'] ?? '', ['off', 'left', 'right', 'popup'], (string) $s['route_from']);
    if (in_array($routeFrom, ['left', 'right', 'popup'], TRUE)) {
      $params['routeFrom'] = $routeFrom;
    }
    if (!empty($s['favorites']) && $this->currentUser->isAuthenticated()) {
      // Stable, anonymous id of the user for the favourite addresses, never the uid itself.
      $params['userId'] = substr(Crypt::hmacBase64('yatmo-map|' . $this->currentUser->id(), Settings::getHashSalt()), 0, 32);
    }
    return $params;
  }

  /**
   * Resolves the settings, the overrides and the location.
   *
   * @param array<string, mixed> $options
   * @return array{settings: array<string, mixed>, options: array<string, mixed>, country: string, language: string, location: array{lat: float, lng: float}}
   *
   * @throws \Drupal\yatmo_map\YatmoException
   */
  public function context(array $options): array {
    $s = $this->settings();
    if (trim((string) ($s['license_key'] ?? '')) === '') {
      throw new YatmoException('Yatmo: enter your licence key in Configuration > Web services > Yatmo Map.');
    }
    $country = strtoupper(trim((string) ($options['country'] ?? '')));
    if (!in_array($country, YatmoClient::COUNTRIES, TRUE)) {
      $country = strtoupper((string) ($s['country'] ?? 'BE'));
    }
    $language = strtoupper(trim((string) ($options['language'] ?? '')));
    if ($language === '' || $language === 'AUTO') {
      $language = strtoupper((string) ($s['language'] ?? 'auto'));
    }
    if ($language === '' || $language === 'AUTO') {
      $language = strtoupper(substr($this->languageManager->getCurrentLanguage()->getId(), 0, 3));
      $language = preg_replace('/[^A-Z]/', '', $language) ?: 'EN';
    }
    $location = $this->locate($options, $country, $s);
    return ['settings' => $s, 'options' => $options, 'country' => $country, 'language' => $language, 'location' => $location];
  }

  /**
   * The location: explicit coordinates, else an address, else the fields of the entity.
   *
   * @param array<string, mixed> $options
   * @param array<string, mixed> $settings
   * @return array{lat: float, lng: float}
   *
   * @throws \Drupal\yatmo_map\YatmoException
   */
  protected function locate(array $options, string $country, array $settings): array {
    $lat = self::number($options['latitude'] ?? '');
    $lng = self::number($options['longitude'] ?? '');
    if ($lat !== NULL && $lng !== NULL) {
      return ['lat' => $lat, 'lng' => $lng];
    }
    $address = trim((string) ($options['address'] ?? ''));
    if ($address !== '') {
      $found = $this->client->geocode($address, $country);
      if (!$found) {
        throw new YatmoException(sprintf('Yatmo did not find "%s" in %s: check the address or enter the coordinates.', $address, $country));
      }
      return $found;
    }
    $entity = $options['entity'] ?? $this->routeMatch->getParameter('node');
    if ($entity instanceof FieldableEntityInterface) {
      $found = $this->locateEntity($entity, $country, $settings);
      if ($found) {
        return $found;
      }
      throw new YatmoException('Yatmo: this content has no location. Fill its location fields, or choose them in Configuration > Web services > Yatmo Map.');
    }
    throw new YatmoException('Yatmo: give the block an address or coordinates, or place it on a page that has a location.');
  }

  /**
   * The location of an entity from the configured fields: geofield, latitude and longitude, or address.
   *
   * @param array<string, mixed> $settings
   * @return array{lat: float, lng: float}|null
   */
  public function locateEntity(FieldableEntityInterface $entity, string $country, array $settings): ?array {
    $geofield = (string) ($settings['geofield'] ?? '');
    if ($geofield !== '' && $entity->hasField($geofield) && !$entity->get($geofield)->isEmpty()) {
      $item = $entity->get($geofield)->first();
      $lat = self::number($item->get('lat')->getValue() ?? '');
      $lng = self::number($item->get('lon')->getValue() ?? '');
      if ($lat !== NULL && $lng !== NULL) {
        return ['lat' => $lat, 'lng' => $lng];
      }
    }
    $latField = (string) ($settings['latitude_field'] ?? '');
    $lngField = (string) ($settings['longitude_field'] ?? '');
    if ($latField !== '' && $lngField !== '' && $entity->hasField($latField) && $entity->hasField($lngField)) {
      $lat = self::number($entity->get($latField)->value ?? '');
      $lng = self::number($entity->get($lngField)->value ?? '');
      if ($lat !== NULL && $lng !== NULL) {
        return ['lat' => $lat, 'lng' => $lng];
      }
    }
    $addressField = (string) ($settings['address_field'] ?? '');
    if ($addressField !== '' && $entity->hasField($addressField) && !$entity->get($addressField)->isEmpty()) {
      $item = $entity->get($addressField)->first();
      $values = $item->getValue();
      // The address module stores structured parts; a text field stores one string.
      $address = isset($values['address_line1'])
        ? implode(', ', array_filter([trim(($values['address_line1'] ?? '') . ' ' . ($values['address_line2'] ?? '')), trim(($values['postal_code'] ?? '') . ' ' . ($values['locality'] ?? ''))]))
        : (string) ($values['value'] ?? '');
      if (trim($address) !== '') {
        return $this->client->geocode($address, $country);
      }
    }
    return NULL;
  }

  /**
   * A notice for people who administer the module, nothing for visitors; never cached for long.
   *
   * @param array<string, mixed> $options
   */
  protected function notice(string $message, array $options): array {
    $build = ['#cache' => ['max-age' => 300, 'contexts' => ['user.permissions']]];
    if ($this->currentUser->hasPermission('administer yatmo map')) {
      $build['#type'] = 'html_tag';
      $build['#tag'] = 'p';
      $build['#attributes'] = ['class' => ['yatmo-map-notice'], 'style' => 'border:1px dashed #c33;padding:8px;color:#c33'];
      $build['#value'] = 'Yatmo (visible to administrators only): ' . $message;
    }
    return $this->cacheable($build, $options);
  }

  /**
   * Cache metadata: per language and page, invalidated by the settings and the entity.
   *
   * @param array<string, mixed> $options
   */
  protected function cacheable(array $build, array $options): array {
    $build['#cache']['contexts'] = array_values(array_unique(array_merge($build['#cache']['contexts'] ?? [], ['languages:language_interface', 'url.path', 'user.permissions'])));
    $tags = ['config:yatmo_map.settings'];
    $entity = $options['entity'] ?? $this->routeMatch->getParameter('node');
    if ($entity instanceof FieldableEntityInterface) {
      $tags = array_merge($tags, $entity->getCacheTags());
    }
    $build['#cache']['tags'] = array_values(array_unique(array_merge($build['#cache']['tags'] ?? [], $tags)));
    if (!empty($options['max_age'])) {
      $build['#cache']['max-age'] = (int) $options['max_age'];
    }
    return $build;
  }

  /**
   * CSS height: a bare number is pixels, else the settings.
   */
  public static function height(string $value, string $default): string {
    $value = strtolower(trim($value));
    if (preg_match('/^(\d{2,4})(px)?$/', $value, $m)) {
      return $m[1] . 'px';
    }
    if (preg_match('/^\d{1,3}(vh|%|rem|em)$/', $value)) {
      return $value;
    }
    $default = trim($default);
    return is_numeric($default) ? $default . 'px' : ($default ?: '560px');
  }

  /**
   * Escapes a sentence and turns the [STRONG] markers of Yatmo into <strong>.
   */
  public static function sentence(string $sentence): string {
    return str_replace(['[STRONG]', '[/STRONG]'], ['<strong>', '</strong>'], Html::escape(trim($sentence)));
  }

  /**
   * Requested language when the text exists in it, else English, else the first one.
   *
   * @param array<string, string> $titles
   */
  public static function pickLanguage(array $titles, string $language): string {
    if ($titles === []) {
      return '';
    }
    foreach ([$language, 'EN'] as $candidate) {
      if (!empty($titles[$candidate])) {
        return $candidate;
      }
    }
    return (string) array_key_first($titles);
  }

  /**
   * Paragraph types from a list or a comma separated string, unknown ones dropped.
   *
   * @return string[]
   */
  public static function paragraphList(mixed $value): array {
    $list = is_array($value) ? $value : explode(',', (string) $value);
    $list = array_map(fn ($v) => strtolower(trim((string) $v)), $list);
    return array_values(array_filter($list, fn ($v) => isset(self::PARAGRAPHS[$v])));
  }

  /**
   * The CSS classes of a wrapper: the option "class", split on spaces.
   *
   * @param array<string, mixed> $options
   * @return string[]
   */
  protected static function classes(array $options): array {
    return array_values(array_filter(array_map([Html::class, 'getClass'], preg_split('/\s+/', trim((string) ($options['class'] ?? ''))) ?: [])));
  }

  protected static function allowed(mixed $value, array $allowed, string $default): string {
    $value = strtolower(trim((string) $value));
    return in_array($value, $allowed, TRUE) && $value !== '' ? $value : $default;
  }

  protected static function intBetween(mixed $value, int $min, int $max, int $default): int {
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
      return $default;
    }
    return max($min, min($max, (int) $value));
  }

  protected static function hexColor(mixed $value): string {
    $value = trim((string) $value);
    return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value) ? $value : '';
  }

  protected static function number(mixed $value): ?float {
    $value = str_replace(',', '.', trim((string) $value));
    return $value !== '' && is_numeric($value) ? (float) $value : NULL;
  }

  protected static function flag(mixed $value, bool $default): bool {
    $value = strtolower(trim((string) $value));
    if ($value === '' || $value === 'default') {
      return $default;
    }
    return in_array($value, ['1', 'true', 'yes', 'on'], TRUE);
  }

}
