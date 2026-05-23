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
 * D7 equivalent: custom_views_filter_form() + custom_views_filter_form_submit()
 */
class CustomViewsFilterForm extends FormBase {

  // Vocabulary machine name replaces the hard-coded vid=4 from D7.
  const VOCABULARY_ID = 'vocabulary_4';

  public function __construct(
    protected $vocabularyStorage,
    protected $termStorage,
    protected $routeMatch,
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

    // Determine depth from current URL argument (mirrors D7 arg(1)).
    $tid = $this->routeMatch->getRawParameter('tid') ?? 'all';

    $depth = match (TRUE) {
      $tid === 'all'      => 1,
      is_numeric($tid)    => count($this->termStorage->loadAllParents((int) $tid)),
      default             => 0,
    };

    // Build a flat term list for the given depth.
    $tree = $this->termStorage->loadTree($vocabulary->id(), 0, $depth);
    $terms = [];
    foreach ($tree as $term) {
      $terms[$term->tid] = $term->name;
    }

    $form['my_container'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['field-widget-taxonomy-shs']],
      '#tree'       => TRUE,
    ];

    $form['my_container']['my_field'] = [
      '#type'             => 'select',
      '#title'            => $this->t('Choose Jurisdiction and Court'),
      '#options'          => $terms,
      '#attributes'       => ['class' => ['shs-enabled']],
      //'#element_validate' => ['shs_field_widget_validate'],
      //'#after_build'      => ['shs_field_widget_afterbuild'],
      '#shs_settings'     => [
        'create_new_levels'      => 0,
        'create_new_terms'       => 0,
        'force_deepest'          => 0,
        'node_count'             => 0,
        'test_create_new_terms'  => [],
        'required'               => TRUE,
      ],
      '#language'         => NULL,
      '#field_name'       => 'my_field',
      '#default_value'    => array_key_exists($tid, $terms) ? $tid : 0,
      '#field_parents'    => NULL,
      '#shs_vocabularies' => [$vocabulary],
      '#suffix'           => $this->t('Choose jurisdiction, plus courthouse, court and division if listed.'),
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Apply'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * D7 equivalent: custom_views_filter_form_submit()
   * Redirects to /<base-path>/<selected-tid> instead of using drupal_goto().
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected_tid = $form_state->getValue(['my_container', 'my_field']);
    $base         = $this->routeMatch->getRawParameter('base') ?? 'charter-search';

    $form_state->setRedirectUrl(Url::fromUserInput("/{$base}/{$selected_tid}"));
  }

}