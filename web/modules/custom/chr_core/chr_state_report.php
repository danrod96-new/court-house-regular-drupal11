<?php

/**
 * @file
 * Report: top-level terms in vocabulary_4 whose name matches a US state.
 *
 * Usage: drush scr chr_state_report.php
 *
 * Prints every candidate with its tid, direct-children count and a sample
 * of child names so duplicates (e.g. the three Californias) can be told
 * apart. Review the output, then copy the confirmed tids into
 * chr_state_reparent.php. Nothing is modified by this script.
 */

$state_names = [
  'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado',
  'Connecticut', 'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho',
  'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine',
  'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi',
  'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey',
  'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio',
  'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina',
  'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia',
  'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
];

$vid = 'vocabulary_4';
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

// Index the state list case-insensitively so "New york" still matches.
$state_lookup = array_combine(array_map('mb_strtolower', $state_names), $state_names);

$candidates = [];
$other_top_level = [];

foreach ($storage->loadTree($vid, 0, 1) as $term) {
  $key = mb_strtolower(trim($term->name));
  if (isset($state_lookup[$key])) {
    $candidates[$state_lookup[$key]][] = $term;
  }
  else {
    $other_top_level[] = $term;
  }
}

echo "=== Top-level terms matching US state names ===\n\n";

$missing = [];
foreach ($state_names as $state) {
  if (empty($candidates[$state])) {
    $missing[] = $state;
    continue;
  }

  $dupe_flag = count($candidates[$state]) > 1 ? '  << DUPLICATES - pick one' : '';
  echo $state . $dupe_flag . "\n";

  foreach ($candidates[$state] as $term) {
    $kids = $storage->loadTree($vid, $term->tid, 1);
    $sample = implode(', ', array_slice(array_map(
      static fn($k) => $k->name,
      $kids,
    ), 0, 3));
    printf("  tid %-7d children: %-4d [%s]\n", $term->tid, count($kids), $sample);
  }
  echo "\n";
}

if ($missing) {
  echo "=== State names with NO top-level term (may already be parented, or absent) ===\n";
  echo implode(', ', $missing) . "\n\n";
}

echo "=== Other top-level terms (left alone) ===\n";
foreach ($other_top_level as $term) {
  printf("  tid %-7d %s\n", $term->tid, $term->name);
}
