<?php

/**
 * @file
 * Re-parents the confirmed state terms under "State Courts".
 *
 * Usage:
 *   drush scr chr_state_reparent.php            (dry run - prints the plan)
 *   drush scr chr_state_reparent.php -- apply   (actually saves)
 *
 * Fill $tids_to_reparent with the tids you confirmed from
 * chr_state_report.php. Every term is safety-checked before saving:
 * it must exist, belong to vocabulary_4, and currently be top-level.
 * Anything failing a check is skipped and reported, never modified.
 */

// Confirmed state tids: in every case the county/borough/parish-structured
// term, not the city-list duplicates in the 25xxx range.
$tids_to_reparent = [
  10756, // Alabama (68 counties)
  639,   // Alaska (20 boroughs)
  640,   // Arizona (15 counties)
  641,   // Arkansas (76 counties)
  642,   // California (60 counties)
  643,   // Colorado (65 counties)
  644,   // Connecticut (9 counties)
  645,   // Delaware (3 counties)
  647,   // Florida (68 counties)
  648,   // Georgia (158 counties)
  650,   // Hawai'i - VERIFY children first, see note
  651,   // Idaho (45 counties)
  652,   // Illinois (103 counties)
  653,   // Indiana (92 counties)
  654,   // Iowa (100 counties)
  655,   // Kansas (106 counties)
  656,   // Kentucky (121 counties)
  657,   // Louisiana (65 parishes)
  658,   // Maine (17 counties)
  659,   // Maryland (25 counties)
  660,   // Massachusetts (15 counties)
  661,   // Michigan (84 counties)
  662,   // Minnesota (87 counties)
  667,   // Mississippi (88 counties)
  668,   // Missouri (116 counties)
  669,   // Montana (58 counties)
  670,   // Nebraska (92 counties)
  671,   // Nevada (18 counties)
  672,   // New Hampshire (11 counties)
  673,   // New Jersey (22 counties)
  674,   // New Mexico (34 counties)
  675,   // New York (63 counties)
  676,   // North Carolina (99 counties)
  677,   // North Dakota (55 counties)
  679,   // Ohio (90 counties)
  680,   // Oklahoma (73 counties)
  681,   // Oregon (37 counties)
  682,   // Pennsylvania (68 counties)
  684,   // Rhode Island (6 counties)
  685,   // South Carolina (47 counties)
  686,   // South Dakota (67 counties)
  687,   // Tennessee (98 counties)
  688,   // Texas (261 counties)
  689,   // Utah (30 counties)
  690,   // Vermont (15 counties)
  691,   // Virginia (Counties / Independent Cities grouping)
  693,   // Washington (40 counties)
  694,   // West Virginia (56 counties)
  695,   // Wisconsin (72 counties)
  696,   // Wyoming (24 counties)
];

$parent_tid = 12395;
$vid = 'vocabulary_4';

$apply = in_array('apply', $extra ?? [], TRUE);
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

if (!$tids_to_reparent) {
  echo "Nothing to do: \$tids_to_reparent is empty. Fill it in from the report first.\n";
  return;
}

$parent = $storage->load($parent_tid);
if (!$parent || $parent->bundle() !== $vid) {
  echo "ABORT: parent term {$parent_tid} not found in {$vid}.\n";
  return;
}
echo 'Target parent: ' . $parent->label() . " ({$parent_tid})\n";
echo $apply ? "Mode: APPLY - changes will be saved.\n\n" : "Mode: DRY RUN - nothing will be saved. Re-run with '-- apply' to save.\n\n";

$done = 0;
$skipped = 0;

foreach ($tids_to_reparent as $tid) {
  $term = $storage->load($tid);

  if (!$term) {
    echo "SKIP {$tid}: term not found.\n";
    $skipped++;
    continue;
  }
  if ($term->bundle() !== $vid) {
    echo "SKIP {$tid} ({$term->label()}): wrong vocabulary ({$term->bundle()}).\n";
    $skipped++;
    continue;
  }

  $current_parents = $storage->loadParents($tid);
  if ($current_parents) {
    $p = reset($current_parents);
    echo "SKIP {$tid} ({$term->label()}): already has parent " . $p->label() . " ({$p->id()}).\n";
    $skipped++;
    continue;
  }

  echo ($apply ? 'REPARENT ' : 'WOULD REPARENT ') . "{$tid} ({$term->label()}) -> {$parent_tid}\n";
  if ($apply) {
    $term->set('parent', [$parent_tid]);
    $term->save();
  }
  $done++;
}

echo "\n" . ($apply ? 'Re-parented' : 'Would re-parent') . ": {$done}, skipped: {$skipped}.\n";
if ($apply && $done) {
  echo "Done. Run 'drush cr' if the filter block still shows stale options.\n";
}
