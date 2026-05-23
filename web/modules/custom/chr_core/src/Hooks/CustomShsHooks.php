<?php

namespace Drupal\chr_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the chr_core module.
 *
 * Using #[Hook] attributes (standard in Drupal 11) keeps hook logic in typed,
 * injectable classes instead of scattered procedural .module functions.
 */
class CustomShsHooks {

  // ---------------------------------------------------------------------------
  // hook_views_data_alter  (D7: custom_views_data_alter)
  // ---------------------------------------------------------------------------

  /**
   * Registers the custom SHS filter handler on the courts taxonomy field.
   *
   * The hard-coded field_data_taxonomy_vocabulary_4 table from D7 has been
   * replaced by the standard taxonomy_term_field_data table. Adjust the table
   * and field names to match your actual field configuration.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $table = 'taxonomy_term_field_data';
    $base  = $data[$table]['tid'] ?? NULL;

    if ($base === NULL) {
      return;
    }

    $data[$table]['chr_core_term_node_tid'] = $base;
    $data[$table]['chr_core_term_node_tid']['help']            = t('Custom handler for SHS to be used with CHR');
    $data[$table]['chr_core_term_node_tid']['filter']['title'] = t('Counties, Courthouses and Courts (@type)', ['@type' => 'CHR: Simple hierarchical select']);
    $data[$table]['chr_core_term_node_tid']['filter']['id']    = 'chr_core_filter_term_node_tid';
  }

  // ---------------------------------------------------------------------------
  // hook_shs_my_field_js_settings_alter
  // D7: chr_core_my_field_js_settings_alter()
  // ---------------------------------------------------------------------------

  /**
   * Alters SHS JS settings for the my_field widget instance.
   */
  #[Hook('shs_my_field_js_settings_alter')]
  public function shsMyFieldJsSettingsAlter(array &$settings_js, string $field_name, mixed $vocabulary_identifier): void {
    $key = 'my_container[my_field]';

    if (empty($settings_js['shs'][$key])) {
      return;
    }

    foreach ($settings_js['shs'][$key] as &$config) {
      $config['any_label']                 = t('- Any -');
      $config['display']['animationSpeed'] = 100;
    }
    unset($config);
  }

}