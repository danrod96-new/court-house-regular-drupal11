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
      '#theme' => 'affiliate_center',
      '#user' => \Drupal::currentUser(),
      '#current_path' => \Drupal::request()->getPathInfo(),
      '#title_page' => "Affiliate Center",
      '#sales_today' => 0,
      '#sales_last_seven_days' => 0,
      '#sales_last_year' => 0,
      '#unique_todays_clicks' => 0,
      '#unique_last_seven_days' => 0,
      '#unique_last_year' => 0, 
    ];
  }

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function bookmarksPage() {
    return [
      '#theme' => 'bookmarks_page',
      '#user' => \Drupal::currentUser(),
      '#current_path' => \Drupal::request()->getPathInfo(),
      '#title_page' => "Bookmarks",
      '#bookmarks' => [],
    ];
  }

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function friendsPage() {
    return [
      '#markup' => $this->t('Hello, world from my custom controller!'),
    ];
  }

  /**
   * This might be a form.
   *
   * @return array
   *   A simple renderable array.
   */
  public function invitePage() {
    return [
      '#markup' => $this->t('Hello, world from my custom controller!'),
    ];
  }

  /**
   * This might be also a form.
   *
   * @return array
   *   A simple renderable array.
   */
  public function inviteByMailPage() {
    return [
      '#markup' => $this->t('Hello, world from my custom controller!'),
    ];
  }

}