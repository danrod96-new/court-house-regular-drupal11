<?php

namespace Drupal\chr_core\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for page profiles.
 */
class ProfilePage extends ControllerBase {

  /**
   * Returns the affiliate page.
   *
   * @return array
   *   The render array.
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
   * Returns the bookmarks page.
   *
   * @return array
   *   The render array.
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
   * Returns the friends/colleagues page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function friendsPage() {
    return [
      '#theme' => 'colleagues_page',
      '#user' => \Drupal::currentUser(),
      '#current_path' => \Drupal::request()->getPathInfo(),
      '#title_page' => "All Colleagues",
      '#friends' => [],
    ];
  }

  /**
   * Invite Appearance Attorneys form
   *
   * @return array
   *   A simple renderable array.
   */
  public function invitePage() {
    return [
      '#theme' => 'invite_attorneys_page',
      '#user' => \Drupal::currentUser(),
      '#current_path' => \Drupal::request()->getPathInfo(),
      '#title_page' => "Invite Appearance Attorneys",
      '#webform_id' => "invite_appearance_attorneys",
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