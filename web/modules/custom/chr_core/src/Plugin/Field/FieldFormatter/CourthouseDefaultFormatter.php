<?php

namespace Drupal\chr_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'courthouse_default' formatter.
 *
 * D7 equivalent: custom_field_formatter_info() + custom_field_formatter_view()
 *   + custom_field_formatter_prepare_view()
 *
 * Note: prepareView() is replaced by loading parent terms directly in view()
 * using the injected storage — simpler and testable without static caches.
 */
#[FieldFormatter(
  id: 'courthouse_default',
  label: new TranslatableMarkup('Courthouse hierarchy'),
  field_types: ['entity_reference'],
)]
class CourthouseDefaultFormatter extends FormatterBase {

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    protected TermStorageInterface $termStorage,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
    );
  }

  // ---------------------------------------------------------------------------
  // Settings
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['linked' => FALSE] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['linked'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Link terms to their taxonomy pages'),
      '#default_value' => $this->getSetting('linked'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [$this->getSetting('linked') ? $this->t('Linked') : $this->t('Not linked')];
  }

  // ---------------------------------------------------------------------------
  // Rendering (D7: custom_field_formatter_view + prepare_view combined)
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $linked   = (bool) $this->getSetting('linked');

    foreach ($items as $delta => $item) {
      $tid = $item->target_id;
      if (!$tid) {
        continue;
      }

      // Build the full ancestor chain (loadAllParents returns deepest first).
      $all_parents  = $this->termStorage->loadAllParents($tid);
      $current_term = array_shift($all_parents);   // remove selected term
      $ancestors    = array_reverse($all_parents);  // root → parent order

      if (!$current_term) {
        continue;
      }

      $list_items = [];

      // Parent terms.
      foreach ($ancestors as $parent) {
        $list_items[] = $this->buildListItem($parent, $linked, 'shs-parent');
      }

      // Selected term.
      $list_items[] = $this->buildListItem($current_term, $linked, 'shs-term-selected');

      $elements[$delta] = [
        '#theme'      => 'item_list',
        '#items'      => $list_items,
        '#attributes' => ['class' => ['shs-hierarchy']],
        '#attached'   => ['library' => ['shs/shs.formatter']],
      ];
    }

    return $elements;
  }

  /**
   * Builds one item_list entry for a term.
   *
   * The D7 'data' / 'class' item keys aren't supported by the D8+ item_list
   * theme: a non-# key is treated as a child render element, so 'class' broke
   * rendering. Items are render arrays, and <li> attributes go in
   * #wrapper_attributes.
   */
  protected function buildListItem(TermInterface $term, bool $linked, string $class): array {
    $item = $linked
      ? [
        '#type' => 'link',
        '#title' => $term->label(),
        '#url' => Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()]),
      ]
      : ['#plain_text' => $term->label()];
    $item['#wrapper_attributes']['class'][] = $class;
    return $item;
  }

}
