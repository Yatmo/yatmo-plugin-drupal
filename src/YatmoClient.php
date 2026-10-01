<?php

declare(strict_types=1);

namespace Drupal\yatmo_map;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Calls the Yatmo API (https://<country>.yatmo.com) and caches the answers.
 *
 * One call to /Summary/text returns the neighbourhood text in every language of the country:
 * it is cached 30 days per location and the language is picked at render time. The geocoder
 * answers are cached 90 days (found) or 1 hour (not found); outages and refused keys are not
 * cached. Service id: yatmo_map.client.
 */
class YatmoClient {

  public const COUNTRIES = ['AL', 'AT', 'AU', 'BA', 'BE', 'BG', 'CA', 'CH', 'CY', 'DE', 'ES', 'FR', 'GR', 'HR', 'IE', 'IT', 'LU', 'MA', 'ME', 'MT', 'NL', 'PT', 'RS', 'SI', 'UK'];

  public const TEXT_TTL = 30 * 86400;
  public const GEOCODE_FOUND_TTL = 90 * 86400;
  public const GEOCODE_MISS_TTL = 3600;

  public function __construct(
    protected ClientInterface $http,
    protected CacheBackendInterface $cache,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
  ) {}

  /**
   * The key used for server calls: the backend key when set, else the licence key.
   */
  public function serverKey(): string {
    $config = $this->configFactory->get('yatmo_map.settings');
    $key = trim((string) $config->get('backend_key'));
    return $key !== '' ? $key : trim((string) $config->get('license_key'));
  }

  /**
   * The neighbourhood text of a location, every language, from the cache or from Yatmo.
   *
   * @return array<string, mixed>
   *   The API answer (key Paragraphs).
   *
   * @throws \Drupal\yatmo_map\YatmoException
   *   With a message for editors when Yatmo cannot provide the text.
   */
  public function summaryText(string $country, float $latitude, float $longitude): array {
    $lat = self::coordinate($latitude);
    $lng = self::coordinate($longitude);
    $cid = 'yatmo_map:text:' . strtolower($country) . ':' . $lat . ':' . $lng;
    $cached = $this->cache->get($cid);
    if ($cached && is_array($cached->data) && isset($cached->data['Paragraphs'])) {
      return $cached->data;
    }

    $url = 'https://' . strtolower($country) . '.yatmo.com/Summary/text?latitude=' . $lat . '&longitude=' . $lng;
    [$status, $body] = $this->get($url, 15);
    if ($status === 0) {
      throw new YatmoException('Yatmo could not be reached, the text will appear on a later visit.');
    }
    if ($status === 401 || $status === 403) {
      throw new YatmoException('Your Yatmo licence does not include the neighbourhood text, or does not cover this country. Contact Yatmo.');
    }
    if ($status === 400) {
      throw new YatmoException(sprintf('Yatmo refused this location: check that it lies in %s, or choose the right country.', $country));
    }
    if ($status !== 200) {
      throw new YatmoException(sprintf('Yatmo answered with an error (%d), the text will be retried on a later visit.', $status));
    }
    $summary = json_decode($body, TRUE);
    if (!is_array($summary) || !isset($summary['Paragraphs']) || !is_array($summary['Paragraphs'])) {
      throw new YatmoException('Yatmo returned an unexpected answer, the text will be retried on a later visit.');
    }
    $this->cache->set($cid, $summary, time() + self::TEXT_TTL);
    return $summary;
  }

  /**
   * Coordinates of an address in a country, or NULL when Yatmo does not find it.
   *
   * @return array{lat: float, lng: float}|null
   */
  public function geocode(string $address, string $country): ?array {
    $address = trim(strip_tags($address), " \t\n\r\0\x0B'\"“”");
    if ($address === '') {
      return NULL;
    }
    $cid = 'yatmo_map:geo:' . strtolower($country) . ':' . md5(mb_strtolower($address));
    $cached = $this->cache->get($cid);
    if ($cached) {
      return is_array($cached->data) && isset($cached->data['lat'], $cached->data['lng']) ? $cached->data : NULL;
    }

    $url = 'https://' . strtolower($country) . '.yatmo.com/Geolocation?language=EN&address=' . rawurlencode($address);
    [$status, $body] = $this->get($url, 5);
    if ($status !== 200) {
      // Outage or refused key: do not remember it as "address not found".
      return NULL;
    }
    $data = json_decode($body, TRUE);
    $feature = is_array($data) ? ($data['features'][0] ?? NULL) : NULL;
    $coordinates = is_array($feature) ? ($feature['geometry']['coordinates'] ?? NULL) : NULL;
    if (is_array($coordinates) && isset($coordinates[0], $coordinates[1])) {
      $location = ['lat' => (float) $coordinates[1], 'lng' => (float) $coordinates[0]];
      $this->cache->set($cid, $location, time() + self::GEOCODE_FOUND_TTL);
      return $location;
    }
    $this->cache->set($cid, [], time() + self::GEOCODE_MISS_TTL);
    return NULL;
  }

  /**
   * A coordinate with 7 decimals, as the API wants it (a decimal comma is accepted).
   */
  public static function coordinate(float|string $value): string {
    return number_format((float) str_replace(',', '.', (string) $value), 7, '.', '');
  }

  /**
   * GET with the licence key; status 0 when unreachable.
   *
   * @return array{0: int, 1: string}
   */
  protected function get(string $url, int $timeout): array {
    try {
      $response = $this->http->request('GET', $url, [
        'timeout' => $timeout,
        'http_errors' => FALSE,
        'headers' => ['LicenseKey' => $this->serverKey(), 'Accept' => 'application/json', 'X-Yatmo-SDK' => 'yatmo-drupal/' . YatmoRenderer::VERSION],
      ]);
      return [$response->getStatusCode(), (string) $response->getBody()];
    }
    catch (\Throwable $e) {
      $this->logger->warning('Yatmo unreachable: @message', ['@message' => $e->getMessage()]);
      return [0, ''];
    }
  }

}
