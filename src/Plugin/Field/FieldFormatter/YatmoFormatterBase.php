<?php

declare(strict_types=1);

namespace Drupal\yatmo_map\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\yatmo_map\YatmoOptionsForm;
use Drupal\yatmo_map\YatmoRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared by the map and the text formatters of a Geofield: the field gives the location.
 */
abstract class YatmoFormatterBase extends FormatterBase {

  protected YatmoRenderer $renderer;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->renderer = $container->get('yatmo_map.renderer');
    return $instance;
  }

  /**
   * 'map' or 'text'.
   */
  abstract protected function kind(): string;

  public static function defaultSettings(): array {
    return ['yatmo' => YatmoOptionsForm::defaults(FALSE)] + parent::defaultSettings();
  }

  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $name = $this->fieldDefinition->getName();
    $form['yatmo'] = YatmoOptionsForm::build($this->getSetting('yatmo') ?: [], $this->kind(), FALSE, 'fields[' . $name . '][settings_edit_form][settings][yatmo]');
    return $form;
  }

  public function settingsSummary(): array {
    $set = array_filter($this->getSetting('yatmo') ?: [], fn ($v) => $v !== '' && $v !== []);
    return [$set ? $this->t('Overrides: @keys', ['@keys' => implode(', ', array_keys($set))]) : $this->t('Yatmo Map settings')];
  }

  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $options = YatmoOptionsForm::options($this->getSetting('yatmo') ?: []);
      $options['latitude'] = $item->get('lat')->getValue();
      $options['longitude'] = $item->get('lon')->getValue();
      $options['entity'] = $items->getEntity();
      $elements[$delta] = $this->kind() === 'map' ? $this->renderer->map($options) : $this->renderer->text($options);
    }
    return $elements;
  }

  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getType() === 'geofield';
  }

}
