<?php

declare(strict_types=1);

namespace Drupal\Tests\chr_core\Traits;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;

/**
 * Builds a small courthouse vocabulary shared by the chr_core tests.
 *
 * Hierarchy (vocabulary_4):
 * - Federal Courts
 * - Other Courts                    (top level, not an allowed jurisdiction)
 *   - Orphan Courthouse
 * - State Courts
 *   - Alpha Courthouse
 *     - Superior Court
 *       - Civil Division
 *       - Criminal Division
 *   - Beta Courthouse
 *
 * Terms are created alphabetically, so the vocabulary's own order (weight,
 * then name) differs from the jurisdiction order the filter form enforces.
 */
trait CourtHierarchyTrait {

  /**
   * Created terms, keyed by a short machine-friendly name.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms = [];

  /**
   * Creates vocabulary_4 and the term hierarchy described above.
   */
  protected function createCourtHierarchy(): void {
    Vocabulary::create([
      'vid' => 'vocabulary_4',
      'name' => 'Courts',
    ])->save();

    $this->terms['federal'] = $this->createCourtTerm('Federal Courts');
    $this->terms['other'] = $this->createCourtTerm('Other Courts');
    $this->terms['orphan'] = $this->createCourtTerm('Orphan Courthouse', $this->terms['other']);
    $this->terms['state'] = $this->createCourtTerm('State Courts');
    $this->terms['alpha'] = $this->createCourtTerm('Alpha Courthouse', $this->terms['state']);
    $this->terms['beta'] = $this->createCourtTerm('Beta Courthouse', $this->terms['state']);
    $this->terms['superior'] = $this->createCourtTerm('Superior Court', $this->terms['alpha']);
    $this->terms['civil'] = $this->createCourtTerm('Civil Division', $this->terms['superior']);
    $this->terms['criminal'] = $this->createCourtTerm('Criminal Division', $this->terms['superior']);
  }

  /**
   * Creates and saves one term in vocabulary_4.
   */
  protected function createCourtTerm(string $name, ?TermInterface $parent = NULL): TermInterface {
    $term = Term::create([
      'vid' => 'vocabulary_4',
      'name' => $name,
      'parent' => $parent ? $parent->id() : 0,
    ]);
    $term->save();
    return $term;
  }

  /**
   * Returns the term id for a key from $this->terms, as a string.
   */
  protected function tid(string $key): string {
    return (string) $this->terms[$key]->id();
  }

}
