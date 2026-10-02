<?php

declare(strict_types=1);

namespace Drupal\Tests\chr_core\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\chr_core\Traits\CourtHierarchyTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the charter-search filter block end to end, without JavaScript.
 *
 * Uses chr_core_test's stand-in for the Views route, so the block sees the
 * same {arg_0} parameter it gets on the real /charter-search page.
 *
 * @group chr_core
 */
#[Group('chr_core')]
class CharterSearchBlockTest extends BrowserTestBase {

  use CourtHierarchyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'chr_core', 'chr_core_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createCourtHierarchy();
    $this->drupalPlaceBlock('custom_views_filter', ['id' => 'chr_filter']);
  }

  /**
   * Selections survive each search, and Reset clears them.
   */
  public function testSearchKeepsSelectionAndResets(): void {
    $assert = $this->assertSession();

    $this->drupalGet('/charter-search/all');
    $assert->pageTextContains('Charter search results for all');
    $assert->fieldValueEquals('mc[jurisdiction]', '');
    $assert->fieldDisabled('mc[courthouse]');
    $assert->buttonNotExists('Reset');

    // Jurisdiction only.
    $this->submitForm(['mc[jurisdiction]' => $this->tid('state')], 'Apply');
    $assert->addressEquals('/charter-search/' . $this->tid('state'));
    $assert->fieldValueEquals('mc[jurisdiction]', $this->tid('state'));
    $assert->fieldEnabled('mc[courthouse]');
    $assert->optionExists('mc[courthouse]', 'Alpha Courthouse');
    $assert->optionExists('mc[courthouse]', 'Beta Courthouse');
    $assert->buttonExists('Reset');

    // Drill down a level; the courthouse options are now on the page.
    $this->submitForm([
      'mc[jurisdiction]' => $this->tid('state'),
      'mc[courthouse]' => $this->tid('alpha'),
    ], 'Apply');
    $assert->addressEquals('/charter-search/' . $this->tid('alpha'));
    $assert->fieldValueEquals('mc[jurisdiction]', $this->tid('state'));
    $assert->fieldValueEquals('mc[courthouse]', $this->tid('alpha'));
    $assert->optionExists('mc[court]', 'Superior Court');

    // Reset goes back to the unfiltered page with everything cleared.
    $this->submitForm([], 'Reset');
    $assert->addressEquals('/charter-search/all');
    $assert->fieldValueEquals('mc[jurisdiction]', '');
    $assert->fieldDisabled('mc[courthouse]');
    $assert->buttonNotExists('Reset');
  }

  /**
   * Landing directly on a deep term restores the whole chain.
   */
  public function testDirectLinkRestoresFullChain(): void {
    $assert = $this->assertSession();

    $this->drupalGet('/charter-search/' . $this->tid('civil'));
    $assert->fieldValueEquals('mc[jurisdiction]', $this->tid('state'));
    $assert->fieldValueEquals('mc[courthouse]', $this->tid('alpha'));
    $assert->fieldValueEquals('mc[court]', $this->tid('superior'));
    $assert->fieldValueEquals('mc[division]', $this->tid('civil'));
  }

  /**
   * Only the allowed jurisdictions are offered, in their fixed order.
   */
  public function testJurisdictionOptions(): void {
    $this->drupalGet('/charter-search/all');

    $options = $this->getSession()->getPage()
      ->findAll('css', 'select[name="mc[jurisdiction]"] option');
    $labels = array_map(fn ($option) => $option->getText(), $options);

    $this->assertSame(['- Any -', 'State Courts', 'Federal Courts'], $labels);
  }

}
