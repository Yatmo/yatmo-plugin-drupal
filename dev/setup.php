<?php

/**
 * @file
 * Demo content for the yatmo_map module, run by `drush php:script`.
 *
 * Content type "property" with a geofield (field_location) and an address text (field_address),
 * the Yatmo formatters on field_location, the Yatmo blocks on property pages, two listings, and the
 * module settings with the key found in YATMO_KEY.
 */

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

$key = getenv('YATMO_KEY') ?: '';
\Drupal::configFactory()->getEditable('yatmo_map.settings')
  ->set('license_key', $key)
  ->set('country', 'BE')
  ->set('language', 'auto')
  ->set('geofield', 'field_location')
  ->set('address_field', 'field_address')
  ->set('isochrone', 'right')
  ->set('rounded', 12)
  ->save();

if (!$type = NodeType::load('property')) {
  $type = NodeType::create(['type' => 'property', 'name' => 'Property']);
  $type->save();
  node_add_body_field($type);
}
foreach ([
  'field_location' => ['type' => 'geofield', 'label' => 'Location'],
  'field_address' => ['type' => 'string', 'label' => 'Address'],
  'field_price' => ['type' => 'integer', 'label' => 'Price'],
] as $name => $def) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'node', 'type' => $def['type']])->save();
  }
  if (!FieldConfig::loadByName('node', 'property', $name)) {
    FieldConfig::create(['field_name' => $name, 'entity_type' => 'node', 'bundle' => 'property', 'label' => $def['label']])->save();
  }
}

// Manage display: price and address as plain fields, the Yatmo text as the formatter of the geofield
// (the map comes from a block, to show both ways).
$display = EntityViewDisplay::load('node.property.default') ?: EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'property', 'mode' => 'default', 'status' => TRUE]);
$display->setComponent('field_price', ['type' => 'number_integer', 'weight' => 0, 'label' => 'inline'])
  ->setComponent('field_address', ['type' => 'string', 'weight' => 1, 'label' => 'inline'])
  ->setComponent('body', ['type' => 'text_default', 'weight' => 2, 'label' => 'hidden'])
  ->setComponent('field_location', ['type' => 'yatmo_map_text', 'weight' => 10, 'label' => 'hidden', 'settings' => ['yatmo' => ['heading' => 'h3'] + \Drupal\yatmo_map\YatmoOptionsForm::defaults(FALSE)]])
  ->save();

// The map block on property pages, before the content.
$theme = \Drupal::config('system.theme')->get('default');
if (!Block::load('yatmo_demo_map')) {
  Block::create([
    'id' => 'yatmo_demo_map',
    'theme' => $theme,
    'region' => 'content',
    'weight' => -10,
    'plugin' => 'yatmo_map_map',
    'settings' => ['id' => 'yatmo_map_map', 'label' => 'Neighbourhood', 'label_display' => 'visible', 'provider' => 'yatmo_map', 'yatmo' => ['height' => '520', 'marker' => 'circle'] + \Drupal\yatmo_map\YatmoOptionsForm::defaults(TRUE)],
    'visibility' => ['entity_bundle:node' => ['id' => 'entity_bundle:node', 'bundles' => ['property' => 'property'], 'negate' => FALSE, 'context_mapping' => ['node' => '@node.node_route_context:node']]],
  ])->save();
}

if (!Node::load(1)) {
  Node::create([
    'type' => 'property', 'title' => 'Bright 2-bedroom apartment, Rue de la Loi', 'status' => 1,
    'body' => ['value' => '92 m² on the third floor of a 1930s building, south-facing living room, fitted kitchen, cellar. Energy class C.', 'format' => 'basic_html'],
    'field_price' => 395000, 'field_address' => 'Rue de la Loi 16, 1000 Bruxelles',
    'field_location' => ['lat' => 50.8461, 'lon' => 4.3664, 'value' => 'POINT (4.3664 50.8461)'],
  ])->save();
  Node::create([
    'type' => 'property', 'title' => 'Loft in Antwerp Zuid (address on request)', 'status' => 1,
    'body' => ['value' => '140 m² loft in a converted warehouse, 4.2 m ceilings, roof terrace.', 'format' => 'basic_html'],
    'field_price' => 549000, 'field_address' => 'Antwerpen',
    'field_location' => ['lat' => 51.2093, 'lon' => 4.3936, 'value' => 'POINT (4.3936 51.2093)'],
  ])->save();
}
echo "demo content ready\n";
