<?php

declare(strict_types=1);

namespace Drupal\chr_core_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Placeholder results page for the charter-search route.
 */
class CharterSearchController extends ControllerBase {

  /**
   * Renders a fixed marker plus the argument, so tests can assert on it.
   */
  public function results(string $arg_0 = 'all'): array {
    return [
      '#markup' => $this->t('Charter search results for @arg', ['@arg' => $arg_0]),
      '#cache' => ['max-age' => 0],
    ];
  }

}
