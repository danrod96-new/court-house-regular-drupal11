<?php

namespace Drupal\chr_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for page profiles.
 */
class ProfilePage extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function affiliatePage() {
    return [
      '#markup' => $this->t('Hello, world from my custom controller!'),
    ];
  }

}