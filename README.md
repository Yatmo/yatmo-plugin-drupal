# Yatmo Map for Drupal

Add a real estate map, points of interest and neighbourhood data to Drupal property pages.
`yatmo_map` is the official Drupal module of [Yatmo](https://yatmo.com): an interactive map of the
surroundings (schools, shops, public transport, leisure, with real travel times on foot, by bike, car and
transit, isochrones), and a written neighbourhood text rendered on the server, so search engines index it
with the listing. A settings page for the licence key and the defaults, two blocks you place on the pages
of your Property content type, two field formatters for a Geofield, in 25 countries and 23 languages.
Drupal 10.3 and 11.

<p align="center">
  <img src="https://raw.githubusercontent.com/Yatmo/yatmo-plugin-drupal/main/docs/screenshot.png" width="720" alt="A Drupal property page with the Yatmo map and the neighbourhood text">
</p>

## Install in 5 minutes

1. Get a licence key at [yatmo.com](https://yatmo.com) ([keys explained](https://documentation.yatmo.com/license)).
2. `composer require drupal/yatmo_map` ([drupal.org/project/yatmo_map](https://www.drupal.org/project/yatmo_map)) then enable **Yatmo Map** (`drush en yatmo_map`).
3. **Configuration > Web services > Yatmo Map**: enter the key, the country of your properties, and the
   fields that hold the location of your content (a Geofield, or a latitude and a longitude field, or an
   address field that Yatmo geocodes). Optionally change the map and text defaults.
4. **Structure > Block layout**: place **Yatmo map** and **Yatmo neighbourhood text** in a region, restricted
   to your Property content type (Content type condition). Both read the location of the node shown on the
   page. Or, in **Manage display** of the content type, pick the **Yatmo map** or **Yatmo neighbourhood
   text** formatter on the Geofield.

That is all: the text is fetched once per location and cached for 30 days. Without a key or without a
location, administrators see an explanation in place of the block; visitors see nothing.

## What you get

- **Yatmo map block**: the [iframe plugin](https://documentation.yatmo.com/plugins/iframe), built with
  your frontend key. Location from the page's node, or a fixed address or coordinates typed in the block
  (an agency page showing its own neighbourhood). Every option of the plugin: layout (map and summary,
  scores, map only, summary only, tabs), height, zoom, 7 map styles, accent colour, rounded corners, pin
  or circle for discreet listings, isochrones, routes, favourite addresses for logged-in users.
- **Yatmo neighbourhood text block**: headings and paragraphs (shops, schools, public transport, roads
  and stations, leisure, nearby cities) in the language of the page, the key places in `<strong>`,
  rendered through a Twig template your theme can override (`yatmo-map-text.html.twig`). Options:
  paragraphs, heading level, street and city in the first two headings (off for discreet listings).
- **Field formatters** on a [Geofield](https://www.drupal.org/project/geofield): the same two renderings
  from Manage display, with the same options.
- **Service** `yatmo_map.renderer` for your own modules and themes:

  ```php
  $yatmo = \Drupal::service('yatmo_map.renderer');
  $build['map'] = $yatmo->map(['entity' => $node, 'mode' => 'map-top', 'marker' => 'circle']);
  $build['text'] = $yatmo->text(['address' => 'Rue de la Loi 16, 1000 Bruxelles', 'paragraphs' => 'education,shopping']);
  $params = $yatmo->iframeParams(['latitude' => 50.8461, 'longitude' => 4.3664]);   // for your own <iframe>
  ```

  Options: `entity`, `address`, `latitude`, `longitude`, `country`, `language`, `mode`, `marker`,
  `circle_radius`, `map_style`, `accent_color`, `zoom`, `height`, `rounded`, `isochrone`, `route_from`,
  `title`, `class`, and for the text `paragraphs`, `heading`, `street_in_title`, `city_in_title`.
  Everything left out comes from the settings.

## Caching

The neighbourhood text is cached 30 days per location in the default cache bin, the geocoding 90 days
(not found: 1 hour); an outage or a refused key is never cached. The render arrays carry the usual cache
metadata (language, path, permissions; tags of the node and of the settings), so Drupal's page and
dynamic page caches keep working.

## Why

A listing page that describes the neighbourhood, with real travel times to real places, is more useful to
the visitor and brings long-tail search traffic that a map alone never brings. Yatmo computes the text and
the places from its own data (official registers, transport feeds, brand networks) and routing engines; the
module serves them as plain HTML.

## Development

`dev/` in the [source repository](https://github.com/Yatmo/yatmo-plugin-drupal) holds a Docker Compose
file (Drupal 10 or 11 on SQLite), a setup script that creates a demo Property content type, and the unit
tests (`tests/src/Unit`, run with Drupal core's PHPUnit).

## Links

- [Yatmo](https://yatmo.com) and its [documentation](https://documentation.yatmo.com/plugins/drupal)
- [WordPress plugin](https://wordpress.org/plugins/yatmo-map/), [Odoo module](https://apps.odoo.com/apps/modules/20.0/yatmo_map),
  [Laravel package](https://github.com/Yatmo/yatmo-laravel), [npm packages](https://www.npmjs.com/org/yatmo)
- [More examples](https://github.com/Yatmo/yatmo-examples)

GPL-2.0-or-later. Yatmo is a paid service for real estate portals, agency networks and developers; a licence key is required.
