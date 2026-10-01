<?php

declare(strict_types=1);

namespace Drupal\Tests\yatmo_map\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Drupal\yatmo_map\YatmoClient;
use Drupal\yatmo_map\YatmoException;
use Drupal\yatmo_map\YatmoRenderer;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;

/**
 * Hermetic: Guzzle is mocked, the cache is an array. The API is never called.
 *
 * @group yatmo_map
 */
class YatmoRendererTest extends UnitTestCase {

  private const TEXT = ['Paragraphs' => [
    ['IconId' => 'shopping', 'Title' => ['FR' => 'Commerces', 'EN' => 'Shops'], 'TitleBis' => ['FR' => 'Commerces près de la Rue de la Loi', 'EN' => 'Shops near Rue de la Loi'], 'TitleTer' => ['FR' => 'Commerces à Bruxelles'],
      'Sentences' => [['FR' => 'Un [STRONG]Carrefour[/STRONG] à 3 minutes.', 'EN' => 'A [STRONG]Carrefour[/STRONG] 3 minutes away.']], 'List' => []],
    ['IconId' => 'education', 'Title' => ['FR' => 'Écoles', 'EN' => 'Schools'], 'TitleTer' => ['FR' => 'Écoles à Bruxelles'], 'Sentences' => [['FR' => 'Deux écoles <b>& plus</b>.']], 'List' => [['FR' => 'Athénée']]],
    ['IconId' => 'cities', 'Title' => ['FR' => 'Villes'], 'Sentences' => [], 'List' => []],
  ]];

  private MockHandler $mock;

  /** @var array<string, array{0: string, 1: array<string, string>}> */
  private array $requests = [];

  private array $cacheStore = [];

  protected function setUp(): void {
    parent::setUp();
    new Settings(['hash_salt' => 'test-salt']);
  }

  private function renderer(array $settings = [], bool $admin = TRUE, bool $authenticated = TRUE): YatmoRenderer {
    $settings += ['license_key' => 'key123', 'country' => 'BE', 'language' => 'auto', 'mode' => 'overlay', 'map_style' => 1, 'accent_color' => '#428BFF', 'marker' => 'pin', 'circle_radius' => 500, 'zoom' => 15, 'height' => '560', 'rounded' => 0, 'isochrone' => '', 'route_from' => '', 'favorites' => FALSE, 'text_paragraphs' => [], 'text_heading' => 'h3', 'text_street' => TRUE, 'text_city' => TRUE];
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(fn ($key = '') => $key === '' ? $settings : ($settings[$key] ?? NULL));
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);

    $this->mock = new MockHandler();
    $stack = HandlerStack::create($this->mock);
    $stack->push(fn (callable $handler) => function ($request, array $options) use ($handler) {
      $this->requests[] = [(string) $request->getUri(), ['LicenseKey' => $request->getHeaderLine('LicenseKey')]];
      return $handler($request, $options);
    });
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturnCallback(fn ($cid) => isset($this->cacheStore[$cid]) ? (object) ['data' => $this->cacheStore[$cid]] : FALSE);
    $cache->method('set')->willReturnCallback(function ($cid, $data) { $this->cacheStore[$cid] = $data; });
    $client = new YatmoClient(new Client(['handler' => $stack]), $cache, $factory, new NullLogger());

    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('fr');
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getCurrentLanguage')->willReturn($language);
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')->willReturn($admin);
    $user->method('isAuthenticated')->willReturn($authenticated);
    $user->method('id')->willReturn(7);
    $route = $this->createMock(RouteMatchInterface::class);
    $route->method('getParameter')->willReturn(NULL);
    return new YatmoRenderer($client, $factory, $languages, $user, $route);
  }

  public function testIframeParamsFollowSettingsAndOverrides(): void {
    $renderer = $this->renderer();
    $params = $renderer->iframeParams(['latitude' => '50,8461', 'longitude' => 4.3664]);
    $this->assertSame(['licenseKey' => 'key123', 'country' => 'BE', 'language' => 'FR', 'latitude' => '50.8461000', 'longitude' => '4.3664000', 'mode' => 'overlay', 'zoom' => '15', 'mapStyle' => '1', 'accentColor' => '#428BFF', 'marker' => 'pin'], $params);

    $params = $renderer->iframeParams(['latitude' => 50.8461, 'longitude' => 4.3664, 'mode' => 'map-top', 'zoom' => '99', 'marker' => 'circle', 'circle_radius' => '10', 'rounded' => '8', 'isochrone' => 'right', 'route_from' => 'popup', 'accent_color' => 'red', 'language' => 'nl', 'country' => 'xx']);
    $this->assertSame('map-top', $params['mode']);
    $this->assertSame('20', $params['zoom']);
    $this->assertSame('50', $params['circleRadiusInMeters']);
    $this->assertSame('8px', $params['rounded']);
    $this->assertSame('right', $params['isochrone']);
    $this->assertSame('popup', $params['routeFrom']);
    $this->assertSame('#428BFF', $params['accentColor']);
    $this->assertSame('NL', $params['language']);
    $this->assertSame('BE', $params['country']);
    $this->assertArrayNotHasKey('userId', $params);

    // "off" in a block beats the settings; favourites send a hash, never the uid.
    $params = $this->renderer(['isochrone' => 'left', 'favorites' => TRUE])->iframeParams(['latitude' => 50.8461, 'longitude' => 4.3664, 'isochrone' => 'off']);
    $this->assertArrayNotHasKey('isochrone', $params);
    $this->assertSame(32, strlen($params['userId']));
    $this->assertSame($params['userId'], $this->renderer(['favorites' => TRUE])->iframeParams(['latitude' => 50.8461, 'longitude' => 4.3664])['userId'], 'stable per user');
  }

  public function testMapBuildAndNotices(): void {
    $build = $this->renderer()->map(['latitude' => 50.8461, 'longitude' => 4.3664, 'height' => '400', 'class' => 'mine  two']);
    $this->assertSame('yatmo_map_embed', $build['#theme']);
    $this->assertSame('https://map.yatmo.com/plugin.html?licenseKey=key123&country=BE&language=FR&latitude=50.8461000&longitude=4.3664000&mode=overlay&zoom=15&mapStyle=1&accentColor=%23428BFF&marker=pin', $build['#src']);
    $this->assertSame('400px', $build['#height']);
    $this->assertSame('Map and neighbourhood of the property', $build['#iframe_title']);
    $this->assertSame(['mine', 'two'], $build['#attributes']['class']);
    $this->assertContains('config:yatmo_map.settings', $build['#cache']['tags']);

    $build = $this->renderer()->map([]);
    $this->assertSame('p', $build['#tag']);
    $this->assertStringContainsString('address or coordinates', $build['#value']);
    $this->assertSame(300, $build['#cache']['max-age']);

    $build = $this->renderer([], FALSE)->map([]);
    $this->assertArrayNotHasKey('#tag', $build, 'visitors see nothing');

    $this->expectException(YatmoException::class);
    $this->renderer(['license_key' => ''])->iframeParams(['latitude' => 50.8461, 'longitude' => 4.3664]);
  }

  public function testTextIsFetchedOnceAndRendered(): void {
    $renderer = $this->renderer(['backend_key' => 'server-key']);
    $this->mock->append(new Response(200, [], json_encode(self::TEXT)));
    $build = $renderer->text(['latitude' => 50.8461, 'longitude' => 4.3664]);
    $this->assertSame('yatmo_map_text', $build['#theme']);
    $this->assertSame('h3', $build['#heading']);
    $this->assertCount(2, $build['#paragraphs'], 'the empty "cities" paragraph is dropped');
    $this->assertSame('Commerces près de la Rue de la Loi', $build['#paragraphs'][0]['title']);
    $this->assertSame('Un <strong>Carrefour</strong> à 3 minutes.', (string) $build['#paragraphs'][0]['sentences']);
    $this->assertSame('Écoles à Bruxelles', $build['#paragraphs'][1]['title']);
    $this->assertSame('Deux écoles &lt;b&gt;&amp; plus&lt;/b&gt;.', (string) $build['#paragraphs'][1]['sentences']);
    $this->assertSame('Athénée', (string) $build['#paragraphs'][1]['items'][0]);
    $this->assertSame('https://be.yatmo.com/Summary/text?latitude=50.8461000&longitude=4.3664000', $this->requests[0][0]);
    $this->assertSame('server-key', $this->requests[0][1]['LicenseKey']);

    // Second render: from the cache, other language and options, no second call.
    $build = $renderer->text(['latitude' => 50.8461, 'longitude' => 4.3664, 'language' => 'EN', 'paragraphs' => 'education,shopping', 'heading' => 'h2', 'street_in_title' => '0']);
    $this->assertCount(1, $this->requests);
    $this->assertSame('h2', $build['#heading']);
    $this->assertSame('Shops', $build['#paragraphs'][0]['title']);
    $this->assertSame('A <strong>Carrefour</strong> 3 minutes away.', (string) $build['#paragraphs'][0]['sentences']);
    $this->assertCount(1, $build['#paragraphs'], 'the education paragraph has no English sentence');
  }

  public function testTextErrorsBecomeNotices(): void {
    $renderer = $this->renderer();
    $this->mock->append(new Response(403, [], ''));
    $build = $renderer->text(['latitude' => 50.8461, 'longitude' => 4.3664]);
    $this->assertStringContainsString('licence does not include', $build['#value']);
    $this->assertSame([], $this->cacheStore, 'a refused key is not cached');

    $this->mock->append(new Response(200, [], json_encode(self::TEXT)));
    $build = $renderer->text(['latitude' => 50.8461, 'longitude' => 4.3664, 'paragraphs' => 'tourism']);
    $this->assertStringContainsString('no text for this location', $build['#value']);
  }

  public function testAddressIsGeocodedAndMissesExpire(): void {
    $renderer = $this->renderer();
    $this->mock->append(new Response(200, [], json_encode(['features' => [['geometry' => ['coordinates' => [4.3664, 50.8461]]]]])));
    $params = $renderer->iframeParams(['address' => 'Rue de la Loi 16, Bruxelles']);
    $this->assertSame('50.8461000', $params['latitude']);
    $this->assertStringContainsString('/Geolocation?language=EN&address=Rue%20de%20la%20Loi%2016%2C%20Bruxelles', $this->requests[0][0]);
    $renderer->iframeParams(['address' => 'rue de la loi 16, bruxelles']);
    $this->assertCount(1, $this->requests, 'case-insensitive cache hit');

    $this->mock->append(new Response(200, [], json_encode(['features' => []])));
    $build = $renderer->map(['address' => 'Nowhere']);
    $this->assertStringContainsString('did not find "Nowhere" in BE', $build['#value']);
    $this->assertSame([], array_values(array_filter($this->cacheStore, fn ($v) => $v === []))[0]);
  }

}
