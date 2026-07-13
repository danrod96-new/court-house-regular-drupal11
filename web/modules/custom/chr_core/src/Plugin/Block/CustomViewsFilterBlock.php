<?php

namespace Drupal\chr_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\chr_core\Form\CustomViewsFilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the "Custom Views Filter" block.
 */
#[Block(
  id: 'custom_views_filter',
  admin_label: new TranslatableMarkup('Custom View Filter'),
)]
class CustomViewsFilterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $form = $this->formBuilder->getForm(CustomViewsFilterForm::class);
    // Forms are inherently uncacheable (form_build_id must always be fresh),
    // but make it explicit at the block level too so a cached block render
    // never serves a stale form_build_id that no longer matches any cached
    // form state -- that produces silent AJAX failures.
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

}