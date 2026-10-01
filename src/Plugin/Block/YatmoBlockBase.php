<?php

declare(strict_types=1);

namespace Drupal\yatmo_map\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\yatmo_map\YatmoOptionsForm;
use Drupal\yatmo_map\YatmoRenderer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared by the map and the text blocks: options form, storage, renderer.
 */
abstract class YatmoBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  protected YatmoRenderer $renderer;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->renderer = $container->get('yatmo_map.renderer');
    return $instance;
  }

  /**
   * 'map' or 'text'.
   */
  abstract protected function kind(): string;

  public function defaultConfiguration(): array {
    return ['yatmo' => YatmoOptionsForm::defaults(TRUE)] + parent::defaultConfiguration();
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form['yatmo'] = YatmoOptionsForm::build($this->configuration['yatmo'] ?? [], $this->kind(), TRUE);
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['yatmo'] = YatmoOptionsForm::submitted($form_state, TRUE);
  }

  public function build(): array {
    $options = YatmoOptionsForm::options($this->configuration['yatmo'] ?? []);
    return $this->kind() === 'map' ? $this->renderer->map($options) : $this->renderer->text($options);
  }

}
