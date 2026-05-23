<?php

namespace Drupal\chr_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'courthouse_shs' widget.
 *
 * D7 equivalent: custom_field_widget_info() + custom_field_widget_form() +
 *   custom_field_widget_afterbuild() + custom_field_widget_validate()
 */
#[FieldWidget(
  id: 'courthouse_shs',
  label: new TranslatableMarkup('Courthouse Simple Hierarchical Select'),
  field_types: ['entity_reference'],
)]
class CourthouseShsWidget extends WidgetBase {

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected TermStorageInterface $termStorage,
    protected VocabularyStorageInterface $vocabularyStorage,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $entityTypeManager = $container->get('entity_type.manager');
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $entityTypeManager->getStorage('taxonomy_term'),
      $entityTypeManager->getStorage('taxonomy_vocabulary'),
      $container->get('current_user'),
    );
  }

  // ---------------------------------------------------------------------------
  // Settings
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'create_new_terms'  => FALSE,
      'create_new_levels' => FALSE,
      'force_deepest'     => FALSE,
      'use_chosen'        => 'chosen',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * D7 equivalent: custom_field_widget_settings_form()
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();

    $element['create_new_terms'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Allow creating new terms'),
      '#description'   => $this->t('If checked the user will be able to create new terms (permission to edit terms in this vocabulary must be set).'),
      '#default_value' => $settings['create_new_terms'],
    ];

    $element['create_new_levels'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Allow creating new levels'),
      '#description'   => $this->t('If checked the user will be able to create new children for items which do not have any children yet.'),
      '#default_value' => $settings['create_new_levels'],
      '#states'        => [
        'visible' => [
          ':input[name$="[create_new_terms]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['force_deepest'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Force selection of deepest level'),
      '#description'   => $this->t('If checked the user will be forced to select terms from the deepest level.'),
      '#default_value' => $settings['force_deepest'],
    ];

    // "Chosen" integration (only shown when the chosen module is installed).
    if (\Drupal::moduleHandler()->moduleExists('chosen')) {
      $element['use_chosen'] = [
        '#type'          => 'select',
        '#title'         => $this->t('Use Chosen'),
        '#description'   => $this->t('Select when the Chosen module should be applied to each level.'),
        '#default_value' => $settings['use_chosen'],
        '#options'       => [
          'chosen' => $this->t('Let Chosen decide'),
          'always' => $this->t('Always'),
          'never'  => $this->t('Never'),
        ],
      ];
    }

    return $element;
  }

  // ---------------------------------------------------------------------------
  // Form element
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   *
   * D7 equivalent: custom_field_widget_form()
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();

    // Resolve vocabularies from the field configuration.
    $vocabularies = $this->resolveVocabularies();
    if (empty($vocabularies)) {
      return [];
    }

    // Determine the current value: saved DB value takes priority, then
    // form_state (needed for multi-value cardinality=-1 fields).
    $element_value = $items[$delta]->target_id ?? NULL;
    $submitted     = $form_state->getValue(array_merge($element['#field_parents'], [$this->fieldDefinition->getName(), $delta, 'target_id']));
    if (!empty($submitted)) {
      $element_value = $submitted;
    }

    // Guard against deleted terms.
    if ($element_value && !$this->termStorage->load($element_value)) {
      $element_value = NULL;
    }

    // Permission check: only allow term creation when a single vocabulary is
    // selected and the user has the right permission.
    $allow_create = $settings['create_new_terms'];
    if (count($vocabularies) > 1) {
      $allow_create = FALSE;
    }
    elseif ($allow_create) {
      $vocabulary      = reset($vocabularies);
      $allow_create    = $this->currentUser->hasPermission("edit terms in {$vocabulary->id()}");
    }

    $shs_settings = $settings + [
      'create_new_terms'       => $allow_create,
      'test_create_new_terms'  => \Drupal::moduleHandler()->getImplementationInfo('shs_add_term_access'),
      'required'               => $element['#required'],
    ];

    $options = $element_value ? ['_none' => '-', $element_value => $element_value] : [];

    $element += [
      '#type'             => 'select',
      '#default_value'    => $element_value,
      '#options'          => $options,
      '#attributes'       => ['class' => ['shs-enabled']],
      '#maxlength'        => NULL,
      '#element_validate' => [[$this, 'validateElement']],
      '#after_build'      => [[$this, 'afterBuild']],
      '#shs_settings'     => $shs_settings,
      '#shs_vocabularies' => $vocabularies,
    ];

    return ['target_id' => $element];
  }

  // ---------------------------------------------------------------------------
  // After-build: attach JS/CSS (D7: custom_field_widget_afterbuild)
  // ---------------------------------------------------------------------------

  /**
   * After-build callback: attaches the SHS library and drupalSettings.
   *
   * D7 equivalent: custom_field_widget_afterbuild()
   */
  public function afterBuild(array $element, FormStateInterface $form_state): array {
    static $js_hash = NULL;
    static $library_attached = FALSE;

    $js_hash ??= _shs_create_hash();

    // Attach library once per form.
    if (!$library_attached) {
      $element['#attached']['library'][] = 'shs/shs';
      $library_attached = TRUE;
    }

    $default_value = $form_state->getValue($element['#parents']) ?? $element['#default_value'];
    if (!empty($default_value)) {
      $element['#default_value'] = $default_value;
    }

    // Build the ancestor chain for pre-selecting dropdowns.
    $parents = [];
    if (empty($default_value) || $default_value === '_none') {
      $parents[] = ['tid' => 0];
    }
    else {
      foreach ($this->termStorage->loadAllParents((int) $default_value) as $term) {
        $parents[] = ['tid' => $term->id()];
      }
    }

    $vocabularies = $element['#shs_vocabularies'];
    $vid          = count($vocabularies) === 1
      ? $vocabularies[0]->id()
      : ['field_name' => $element['#field_name']];

    $settings_js = [
      'shs' => [
        $element['#name'] => [
          $js_hash => [
            'vid'           => $vid,
            'settings'      => $element['#shs_settings'],
            'default_value' => $element['#default_value'],
            'parents'       => array_reverse($parents),
            'any_label'     => empty($element['#required']) ? $this->t('- None -') : $this->t('- Select a value -'),
            'any_value'     => '_none',
          ],
        ],
      ],
    ];

    \Drupal::moduleHandler()->alter(
      ['shs_js_settings', "shs_{$element['#field_name']}_js_settings"],
      $settings_js,
      $element['#field_name'],
      $vid,
    );

    $element['#attached']['drupalSettings'] = array_merge_recursive(
      $element['#attached']['drupalSettings'] ?? [],
      $settings_js,
    );

    unset($element['#needs_validation']);
    return $element;
  }

  // ---------------------------------------------------------------------------
  // Validation (D7: custom_field_widget_validate)
  // ---------------------------------------------------------------------------

  /**
   * Element validation callback.
   *
   * D7 equivalent: custom_field_widget_validate()
   */
  public function validateElement(array $element, FormStateInterface $form_state): void {
    $settings         = $element['#shs_settings'] ?? $this->getSettings();
    $force_deepest    = $settings['force_deepest'] ?? FALSE;
    $value            = $element['#value'] ?: '_none';

    if ($value === '_none') {
      $form_state->setValueForElement($element, NULL);
    }

    if ($element['#required'] && $value === '_none') {
      $label = $element['#title'] ?: $this->fieldDefinition->getLabel();
      $form_state->setError($element, $this->t('@name field is required.', ['@name' => $label]));
      return;
    }

    if ($force_deepest && $value && $value !== '_none') {
      $vocabularies = $this->resolveVocabularies();
      foreach ($vocabularies as $vocabulary) {
        $children = shs_term_get_children($vocabulary->id(), $value);
        if (!empty($children)) {
          $form_state->setError(
            $element,
            $this->t('You need to select a term from the deepest level in field %field_name.', [
              '%field_name' => $this->fieldDefinition->getLabel(),
            ])
          );
          return;
        }
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Helper
  // ---------------------------------------------------------------------------

  /**
   * Resolves vocabulary objects from the field's handler configuration.
   *
   * D7 equivalent: inline vocabulary loading in custom_field_widget_form().
   *
   * @return \Drupal\taxonomy\VocabularyInterface[]
   */
  protected function resolveVocabularies(): array {
    $settings    = $this->fieldDefinition->getSettings();
    $handler     = $settings['handler'] ?? 'default';
    $vocabularies = [];

    if ($handler === 'views') {
      // Delegate to the SHS helper that inspects the view's filter config.
      $view_settings     = $settings['handler_settings']['view'];
      $vocabulary_names  = _shs_entityreference_views_get_vocabularies(
        $view_settings['view_name'],
        $view_settings['display_name'],
      );
    }
    else {
      $vocabulary_names = array_keys($settings['handler_settings']['target_bundles'] ?? []);
    }

    foreach ((array) $vocabulary_names as $name) {
      $vocabulary = $this->vocabularyStorage->load($name);
      if (!$vocabulary) {
        return [];
      }
      $vocabularies[] = $vocabulary;
    }

    return $vocabularies;
  }

}