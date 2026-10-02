<?php

namespace Drupal\chr_core\Plugin\views\filter;

use Drupal\shs\Plugin\views\filter\ShsTaxonomyIndexTid;
use Drupal\views\Attribute\ViewsFilter;

/**
 * Filters by courthouse term id using Simple hierarchical select widgets.
 *
 * Registered on the courts taxonomy field by
 * \Drupal\chr_core\Hook\CustomShsHooks::viewsDataAlter(), which exposes it in
 * the Views UI as "Counties, Courthouses and Courts (CHR: Simple
 * hierarchical select)".
 *
 * D7 equivalent: the custom SHS term_node_tid handler from custom_views.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('chr_core_filter_term_node_tid')]
class CustomShsFilterTermNodeTid extends ShsTaxonomyIndexTid {

  /**
   * Vocabulary machine name for courthouses (vid=4 in D7).
   */
  const VOCABULARY_ID = 'vocabulary_4';

  /**
   * {@inheritdoc}
   *
   * Defaults the handler to the courthouse vocabulary, shown as an SHS
   * widget with the full hierarchy, so a newly added instance works without
   * reconfiguring every option in the Views UI.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['vid']['default'] = self::VOCABULARY_ID;
    $options['type']['default'] = 'shs';
    $options['hierarchy']['default'] = TRUE;
    return $options;
  }

}
