// Checks the demo Drupal site on http://localhost:8090 after setup.sh: the property page for a visitor
// (map iframe with the key, neighbourhood text in the HTML, no notice), the settings form and the
// block options form for admin/admin, a notice for admins on content without location, and a
// screenshot for the documentation. Run: node browser-check.cjs (Playwright + Chrome on the host).
const { chromium } = require('playwright');
const BASE = 'http://localhost:8090';
(async () => {
  const browser = await chromium.launch({ channel: 'chrome' });
  const context = await browser.newContext({ viewport: { width: 1280, height: 1750 } });
  const checks = {};
  const html = await (await context.request.get(`${BASE}/node/1`)).text();
  checks['iframe with key'] = /<iframe src="https:\/\/map\.yatmo\.com\/plugin\.html\?licenseKey=[a-z0-9]+&amp;country=BE&amp;language=EN[^"]*marker=circle[^"]*isochrone=right"/.test(html);
  checks['text in html'] = /<div class="yatmo-text">/.test(html) && /<h3>[^<]*Rue de la Loi/.test(html) && /<strong>/.test(html);
  checks['no notice for visitors'] = !/yatmo-map-notice/.test(html);
  const discreet = await (await context.request.get(`${BASE}/node/2`)).text();
  checks['second listing'] = /<h3>[^<]*Antwerp/.test(discreet) || /<div class="yatmo-text">/.test(discreet);

  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(`${BASE}/node/1`);
  await page.waitForSelector('iframe[src*="map.yatmo.com"]');
  await page.waitForTimeout(7000);
  await page.screenshot({ path: `${__dirname}/../docs/screenshot.png` });

  await page.goto(`${BASE}/user/login`);
  await page.fill('#edit-name', 'admin');
  await page.fill('#edit-pass', 'admin');
  await page.click('#edit-submit');
  await page.goto(`${BASE}/admin/config/services/yatmo-map`);
  checks['settings form'] = (await page.locator('#edit-license-key').count()) === 1 && (await page.locator('#edit-geofield').inputValue()) === 'field_location';
  await page.goto(`${BASE}/admin/structure/block/manage/yatmo_demo_map`);
  checks['block options form'] = (await page.locator('input[name="settings[yatmo][source]"]').count()) === 2 && (await page.locator('select[name="settings[yatmo][mode]"]').count()) === 1;
  await page.goto(`${BASE}/admin/structure/types/manage/property/display/default`);
  checks['formatter on geofield'] = (await page.locator('select[name="fields[field_location][type]"] option[value="yatmo_map_text"]').count()) === 1;
  // A property without location: the admin sees a notice.
  await page.goto(`${BASE}/node/add/property`);
  await page.fill('#edit-title-0-value', 'No location yet');
  await page.click('#edit-submit');
  await page.waitForURL(/\/node\/\d+$/);
  const body = await page.content();
  checks['notice for admins'] = /yatmo-map-notice/.test(body) && /has no location/.test(body);
  checks['no js errors'] = errors.length === 0;
  for (const [name, ok] of Object.entries(checks)) console.log(`${ok ? 'OK  ' : 'FAIL'} ${name}`);
  await browser.close();
  process.exit(Object.values(checks).every(Boolean) ? 0 : 1);
})();
