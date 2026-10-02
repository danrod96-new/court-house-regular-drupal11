<?php

declare(strict_types=1);

namespace Drupal\Tests\chr_core\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the CHR settings forms and route access.
 *
 * @group chr_core
 */
#[Group('chr_core')]
class AdminAndAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['chr_core'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin-only and member-only routes are closed to anonymous users.
   */
  public function testAnonymousAccessIsDenied(): void {
    $paths = [
      '/admin/config/courthouseregular',
      '/admin/config/courthouseregular/config',
      '/admin/config/courthouseregular/sync-missing-profile-info',
      '/user/1/affiliate',
      '/user/1/bookmarks',
      '/user/1/friends',
      '/user/1/invite',
    ];
    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  /**
   * Authenticated users without admin permissions can't reach the settings.
   */
  public function testAuthenticatedNonAdminIsDenied(): void {
    $this->drupalLogin($this->drupalCreateUser());
    foreach (['/admin/config/courthouseregular', '/admin/config/courthouseregular/config'] as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  /**
   * The general settings form saves its values to config.
   */
  public function testSettingsFormSaves(): void {
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));

    $this->drupalGet('/admin/config/courthouseregular');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'title' => 'Default subject',
      'message_body' => 'Default body',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('chr_core.adminsettings');
    $this->assertSame('Default subject', $config->get('invite_subject'));
    $this->assertSame('Default body', $config->get('message_body'));

    // The saved values are shown on the next visit.
    $this->drupalGet('/admin/config/courthouseregular');
    $this->assertSession()->fieldValueEquals('title', 'Default subject');
  }

  /**
   * The subject is required.
   */
  public function testSettingsFormRequiresSubject(): void {
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));

    $this->drupalGet('/admin/config/courthouseregular');
    $this->submitForm(['title' => ''], 'Save configuration');
    $this->assertSession()->pageTextContains('Subject field is required.');
    $this->assertNull($this->config('chr_core.adminsettings')->get('invite_subject'));
  }

  /**
   * The invite form shows its token defaults and saves overrides.
   */
  public function testInviteSettingsForm(): void {
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));

    $this->drupalGet('/admin/config/courthouseregular/config');
    $this->assertSession()->fieldValueEquals('title', '[yourname] has sent you an invite!');
    $body = $this->getSession()->getPage()->findField('message_body')->getValue();
    $this->assertStringContainsString('[home_link]', $body);
    $this->assertStringContainsString('[registration_link]', $body);

    $this->submitForm(['title' => 'Join us, [yourname]'], 'Save configuration');
    $this->assertSame('Join us, [yourname]', $this->config('chr_core.adminsettingsinvite')->get('invite_subject'));
  }

}
