<?php

namespace Drupal\chr_core\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends the core taxonomy filter with a Simple Hierarchical Select widget.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("chr_core_filter_term_node_tid")
 */
class CustomShsFilterTermNodeTid extends TaxonomyIndexTid {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\taxonomy\VocabularyStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * Constructs the filter handler.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    LanguageManagerInterface $language_manager,
    TermStorageInterface $term_storage,
    VocabularyStorageInterface $vocabulary_storage,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
    $this->termStorage = $term_storage;
    $this->vocabularyStorage = $vocabulary_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['type']['default'] = 'chr_shs';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function extraOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::extraOptionsForm($form, $form_state);

    $form['type']['#options'] += [
      'chr_shs' => $this->t('CHR Simple hierarchical select'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state): void {
    $vocabulary = $this->vocabularyStorage->load($this->options['vocabulary']);

    if (empty($vocabulary) && $this->options['limit']) {
      $form['markup'] = [
        '#markup' => '<div class="form-item">' . $this->t('An invalid vocabulary is selected. Please change it in the options.') . '</div>',
      ];
      return;
    }

    // Fall back to standard widget when not using SHS or not exposed.
    if ($this->options['type'] !== 'chr_shs' || empty($this->options['exposed'])) {
      parent::valueForm($form, $form_state);
      return;
    }

    $multiple = $this->options['expose']['multiple'] ?? FALSE;
    $identifier = $this->options['expose']['identifier'];
    $language = $this->languageManager->getCurrentLanguage();

    // Resolve the default/current value.
    $default_value = !empty($this->value) ? $this->value : 0;
    $input = $form_state->getUserInput();

    if (!empty($input[$identifier])) {
      $default_value = $input[$identifier];
      if ($multiple && !is_array($default_value)) {
        $default_value = [$default_value];
      }
    }

    // Build the parent-chain that SHS needs to pre-select dropdowns.
    $parents = $this->buildShsParents($default_value, $multiple, $identifier, $form_state);

    // Always append a blank slot for the next (unselected) level.
    $parents[] = ['tid' => 0];

    $element_settings = [
      'create_new_terms'  => FALSE,
      'create_new_levels' => FALSE,
      'required'          => !empty($this->options['exposed']) && !empty($this->options['expose']['required']),
      'language'          => $language,
    ];

    // Build JS settings for the SHS library.
    // A unique hash prevents attachments from merging multiple instances of
    // this filter on the same page.
    $js_hash = _shs_create_hash();
    $field_key = $identifier . ($multiple ? '[]' : '');

    $settings_js = [
      'shs' => [
        $field_key => [
          $js_hash => [
            'vid'           => $vocabulary->id(),
            'settings'      => $element_settings,
            'default_value' => $default_value,
            'parents'       => $parents,
            'multiple'      => $multiple,
            'any_label'     => $this->t('- Any -'),
            'any_value'     => 'All',
          ],
        ],
      ],
    ];

    // Allow other modules to alter the SHS JS settings.
    \Drupal::moduleHandler()->alter(
      ['shs_js_settings', "shs_{$identifier}_js_settings"],
      $settings_js,
      $identifier,
      $vocabulary->id()
    );

    // Attach the SHS library and pass settings via drupalSettings.
    $form['#attached']['library'][] = 'shs/shs';
    $form['#attached']['drupalSettings'] = array_merge_recursive(
      $form['#attached']['drupalSettings'] ?? [],
      $settings_js
    );

    // Wrapper container that the SHS JS targets.
    $form['mc'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['field-widget-taxonomy-shs']],
      '#tree'       => TRUE,
    ];

    $form['mc']['value'] = [
      '#type'             => 'select',
      '#title'            => $this->t('Choose Jurisdiction and Court'),
      '#options'          => ['1' => $this->t('One')],
      '#attributes'       => ['class' => ['element-invisible', 'shs-enabled']],
      //'#element_validate' => ['shs_field_widget_validate'],
      //'#after_build'      => ['shs_field_widget_afterbuild'],
      '#shs_settings'     => [
        'create_new_levels' => 0,
        'create_new_terms'  => 0,
        'force_deepest'     => 0,
        'node_count'        => 0,
        'required'          => TRUE,
      ],
      '#language'         => NULL,
      '#field_name'       => 'value',
      '#field_parents'    => NULL,
      '#shs_vocabularies' => [$vocabulary],
      '#suffix'           => $this->t('Choose the appropriate court system, geographical location, plus courthouse and court, if listed'),
    ];

    // Hidden fallback so Views always has something to work with.
    $form['value'] = ['#type' => 'value', '#value' => 0];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    $this->value_options = [];

    if ($this->value === 'All') {
      $this->value = NULL;
    }

    return parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   *
   * Bridges the SHS widget's non-standard input key back to the field name
   * that the parent validator expects.
   */
  public function validateExposed(&$form, FormStateInterface $form_state): void {
    $mc_value = $form_state->getValue(['mc', 'value']);
    $form_state->setValue('chr_core_term_node_tid', $mc_value);
    parent::validateExposed($form, $form_state);
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Builds the SHS parent-chain array from the current default value.
   *
   * SHS needs every ancestor tid so it can pre-select each level's dropdown
   * on page load.
   *
   * @param mixed $default_value
   *   The currently selected tid(s), or 0 / 'All' when nothing is selected.
   * @param bool $multiple
   *   Whether this is a multi-value filter.
   * @param string $identifier
   *   The exposed filter identifier (used to rewrite combined values back into
   *   the form input).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state (may be mutated when splitting comma/plus-separated tids).
   *
   * @return array
   *   Array of ['tid' => …] entries from root to the selected term, without
   *   the trailing zero-slot (the caller appends that).
   */
  protected function buildShsParents(mixed &$default_value, bool $multiple, string $identifier, FormStateInterface $form_state): array {
    $parents = [];

    if (empty($default_value) || $default_value === 'All') {
      return $parents;
    }

    // Multiple selection: values may arrive comma- or plus-separated from the
    // URL; split and normalise them first.
    if (is_array($default_value) && $default_value[0] !== 'All') {
      $needs_split = array_filter($default_value, static fn($v) => strpbrk((string) $v, ',+') !== FALSE);

      if (!empty($needs_split)) {
        $flat = [];
        foreach ($default_value as $v) {
          array_push($flat, ...preg_split('/[,+]+/', (string) $v, -1, PREG_SPLIT_NO_EMPTY));
        }
        $default_value = empty($flat) ? 'All' : $flat;
        $form_state->setValueForElement(['#parents' => [$identifier]], $default_value);
      }

      $parents[] = ['tid' => array_values((array) $default_value)];
      return $parents;
    }

    // Single selection: walk the term's ancestor chain so SHS can open each
    // parent dropdown in sequence.
    if (is_string($default_value) || is_numeric($default_value)) {
      $term_parents = $this->termStorage->loadAllParents((int) $default_value);
      // loadAllParents() returns the term itself first; remove it.
      array_shift($term_parents);

      foreach (array_reverse($term_parents) as $term) {
        $parents[] = ['tid' => $term->id()];
      }
      $parents[] = ['tid' => $default_value];
    }

    return $parents;
  }

}