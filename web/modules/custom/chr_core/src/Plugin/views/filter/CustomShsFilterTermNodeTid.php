<?php

namespace Drupal\chr_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the custom Views filter form rendered in a block.
 *
 * Renders four cascading select lists (Jurisdiction / Courthouse / Court /
 * Division) instead of one mixed-level dropdown. Submitting redirects to
 * /<base>/<tid> using whichever level is deepest, same as before.
 *
 * D7 equivalent: custom_views_filter_form() + custom_views_filter_form_submit()
 */
class CustomViewsFilterForm extends FormBase {

  // Vocabulary machine name replaces the hard-coded vid=4 from D7.
  const VOCABULARY_ID = 'vocabulary_4';

  /**
   * Ordered hierarchy levels, keyed by depth (0 = top of the vocabulary).
   */
  protected const LEVELS = [
    0 => ['key' => 'jurisdiction', 'label' => 'Jurisdiction'],
    1 => ['key' => 'courthouse', 'label' => 'Courthouse'],
    2 => ['key' => 'court', 'label' => 'Court'],
    3 => ['key' => 'division', 'label' => 'Division'],
  ];

  public function __construct(
    protected VocabularyStorageInterface $vocabularyStorage,
    protected TermStorageInterface $termStorage,
    protected RouteMatchInterface $currentRouteMatch,
  ) {}

  public static function create(ContainerInterface $container): static {
    $entityTypeManager = $container->get('entity_type.manager');
    return new static(
      $entityTypeManager->getStorage('taxonomy_vocabulary'),
      $entityTypeManager->getStorage('taxonomy_term'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'custom_views_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $vocabulary = $this->vocabularyStorage->load(self::VOCABULARY_ID);
    $vid = $vocabulary->id();
    $wrapper_id = 'chr-cascade-block-filter-wrapper';

    $selected = $this->getSelectedLevelValues($form_state);

    $form['mc'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $wrapper_id,
        'class' => ['field-widget-taxonomy-shs', 'chr-cascading-select-filter'],
      ],
      '#tree' => TRUE,
    ];

    $form['mc']['help'] = [
      '#markup' => '<div class="description">' . $this->t('Choose jurisdiction, plus courthouse, court and division if listed.') . '</div>',
    ];

    // Walk the hierarchy top-down. Each level's options are the children of
    // whatever was selected at the level above; once a level has no
    // selection (or no children), every level beneath it renders disabled
    // with a placeholder, since there's nothing valid to choose yet.
    $parent_tid = 0;
    foreach (self::LEVELS as $level) {
      $key = $level['key'];
      $options = $parent_tid !== NULL ? $this->loadLevelOptions($vid, $parent_tid) : [];
      $current_value = $selected[$key] ?? '';

      // A stale selection whose parent no longer has it as a child (e.g. the
      // taxonomy changed) shouldn't be kept.
      if ($current_value !== '' && !isset($options[$current_value])) {
        $current_value = '';
      }

      $form['mc'][$key] = [
        '#type' => 'select',
        '#title' => $this->t('@label', ['@label' => $level['label']]),
        '#options' => $options
          ? ['' => $this->t('- Any -')] + $options
          : ['' => $this->t('- N/A -')],
        '#default_value' => $current_value,
        '#disabled' => empty($options),
        '#attributes' => ['class' => ['chr-cascade-level', 'chr-cascade-level--' . $key]],
        '#ajax' => [
          'callback' => [$this, 'ajaxCascadeCallback'],
          'wrapper' => $wrapper_id,
          'event' => 'change',
        ],
      ];

      $parent_tid = $current_value !== '' ? (int) $current_value : NULL;
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    return $form;
  }

  /**
   * AJAX callback: re-renders the four selects after any level changes.
   */
  public function ajaxCascadeCallback(array $form, FormStateInterface $form_state): array {
    return $form['mc'];
  }

  /**
   * {@inheritdoc}
   *
   * D7 equivalent: custom_views_filter_form_submit()
   * Redirects to /<base-path>/<selected-tid>, using whichever of the four
   * levels is deepest, instead of using drupal_goto().
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('mc') ?? [];
    $tid = 0;

    foreach (array_reverse(self::LEVELS) as $level) {
      $key = $level['key'];
      if (!empty($values[$key])) {
        $tid = $values[$key];
        break;
      }
    }

    $base = $this->currentRouteMatch->getRawParameter('base') ?? 'charter-search';

    $form_state->setRedirectUrl(Url::fromUserInput("/{$base}/{$tid}"));
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Loads the immediate child terms of $parent_tid as select options.
   *
   * @param string $vid
   *   The vocabulary machine name.
   * @param int $parent_tid
   *   The parent term id (0 for top-level terms).
   *
   * @return array
   *   An array of term name keyed by tid.
   */
  protected function loadLevelOptions(string $vid, int $parent_tid): array {
    $options = [];
    foreach ($this->termStorage->loadTree($vid, $parent_tid, 1, FALSE) as $term) {
      $options[$term->tid] = $term->name;
    }
    return $options;
  }

  /**
   * Determines the currently selected tid for each of the four levels.
   *
   * On an AJAX request this comes from the raw submitted input. On a fresh
   * page load it's derived from the {tid} route parameter (mirrors the
   * original D7 arg(1) behaviour) by walking that term's ancestor chain.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Level key => tid, only for levels that have a selection. The chain
   *   always starts at 'jurisdiction'; it stops at the first unselected
   *   level.
   */
  protected function getSelectedLevelValues(FormStateInterface $form_state): array {
    $input = $form_state->getUserInput();
    $submitted = $input['mc'] ?? NULL;

    if (is_array($submitted)) {
      $selected = [];
      foreach (self::LEVELS as $level) {
        $key = $level['key'];
        if (empty($submitted[$key])) {
          break;
        }
        $selected[$key] = $submitted[$key];
      }
      return $selected;
    }

    // Fresh load: derive the chain from the {tid} route parameter, root
    // first.
    $tid = $this->currentRouteMatch->getRawParameter('tid');
    if (empty($tid) || $tid === 'all' || !is_numeric($tid)) {
      return [];
    }

    // loadAllParents() returns the term itself first, then ancestors up to
    // the root; reverse it so index 0 is the root (jurisdiction).
    $chain = array_reverse($this->termStorage->loadAllParents((int) $tid));

    $selected = [];
    $depth = 0;
    foreach ($chain as $term) {
      if (!isset(self::LEVELS[$depth])) {
        break;
      }
      $selected[self::LEVELS[$depth]['key']] = $term->id();
      $depth++;
    }

    return $selected;
  }

}