<?php

declare(strict_types=1);

namespace Drupal\Tests\chr_core\Kernel;

use Drupal\chr_core\Form\CustomViewsFilterForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\chr_core\Traits\CourtHierarchyTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the cascading courthouse search form.
 *
 * @group chr_core
 */
#[CoversClass(CustomViewsFilterForm::class)]
#[Group('chr_core')]
class CustomViewsFilterFormTest extends KernelTestBase {

  use CourtHierarchyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'taxonomy',
    'chr_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->container->get('router.builder')->rebuild();
    $this->createCourtHierarchy();
  }

  /**
   * Only the allowed jurisdictions are listed, in the configured order.
   */
  public function testJurisdictionOptionsAreRestrictedAndOrdered(): void {
    $form = $this->buildFilterForm('all');

    $options = $form['mc']['jurisdiction']['#options'];
    $this->assertSame(
      ['', $this->tid('state'), $this->tid('federal')],
      array_map('strval', array_keys($options)),
    );
    $this->assertArrayNotHasKey($this->terms['other']->id(), $options);
  }

  /**
   * With nothing selected, lower levels are disabled and Reset is hidden.
   */
  public function testUnfilteredPageHasNoSelection(): void {
    $form = $this->buildFilterForm('all');

    $this->assertSame('', $form['mc']['jurisdiction']['#default_value']);
    foreach (['courthouse', 'court', 'division'] as $level) {
      $this->assertTrue($form['mc'][$level]['#disabled'], "$level is disabled");
    }
    $this->assertFalse($form['actions']['reset']['#access']);
  }

  /**
   * The selects are rebuilt from the term in the URL.
   *
   * Covers both ways Views can name the contextual argument: the default
   * {arg_0} and a named %tid placeholder.
   *
   * @dataProvider routeParameterProvider
   */
  #[DataProvider('routeParameterProvider')]
  public function testSelectionIsRestoredFromUrl(string $path, string $parameter): void {
    $form = $this->buildFilterForm($this->tid('civil'), $path, $parameter);

    $this->assertSame($this->tid('state'), (string) $form['mc']['jurisdiction']['#default_value']);
    $this->assertSame($this->tid('alpha'), (string) $form['mc']['courthouse']['#default_value']);
    $this->assertSame($this->tid('superior'), (string) $form['mc']['court']['#default_value']);
    $this->assertSame($this->tid('civil'), (string) $form['mc']['division']['#default_value']);

    // Each level offers the children of the level above.
    $courthouses = array_map('strval', array_keys($form['mc']['courthouse']['#options']));
    $this->assertSame(['', $this->tid('alpha'), $this->tid('beta')], $courthouses);
    $this->assertArrayHasKey($this->terms['criminal']->id(), $form['mc']['division']['#options']);

    $this->assertTrue($form['actions']['reset']['#access']);
  }

  /**
   * Data provider for testSelectionIsRestoredFromUrl().
   */
  public static function routeParameterProvider(): array {
    return [
      'Views default placeholder' => ['/charter-search/{arg_0}', 'arg_0'],
      'Named placeholder' => ['/charter-search/{tid}', 'tid'],
    ];
  }

  /**
   * A partial selection restores only the levels down to that term.
   */
  public function testPartialSelectionIsRestored(): void {
    $form = $this->buildFilterForm($this->tid('alpha'));

    $this->assertSame($this->tid('alpha'), (string) $form['mc']['courthouse']['#default_value']);
    $this->assertSame('', $form['mc']['court']['#default_value']);
    $this->assertFalse($form['mc']['court']['#disabled']);
    $this->assertTrue($form['mc']['division']['#disabled']);
  }

  /**
   * Non-numeric arguments and terms outside the allowed jurisdictions.
   */
  public function testInvalidArgumentsAreIgnored(): void {
    $form = $this->buildFilterForm('not-a-term');
    $this->assertSame('', $form['mc']['jurisdiction']['#default_value']);

    // Its root ("Other Courts") isn't an allowed jurisdiction, so the whole
    // chain is discarded rather than leaving a courthouse with no parent.
    $form = $this->buildFilterForm($this->tid('orphan'));
    $this->assertSame('', $form['mc']['jurisdiction']['#default_value']);
    $this->assertTrue($form['mc']['courthouse']['#disabled']);
  }

  /**
   * Apply redirects to the deepest selected level.
   */
  public function testApplyRedirectsToDeepestSelection(): void {
    $form_state = $this->submitFilterForm('Apply', [
      'jurisdiction' => $this->tid('state'),
      'courthouse' => $this->tid('alpha'),
      'court' => $this->tid('superior'),
      'division' => '',
    ]);

    $this->assertStringEndsWith(
      '/charter-search/' . $this->tid('superior'),
      $form_state->getRedirect()->toString(),
    );
  }

  /**
   * Apply with nothing selected goes to the unfiltered listing.
   */
  public function testApplyWithoutSelectionRedirectsToAll(): void {
    $form_state = $this->submitFilterForm('Apply', ['jurisdiction' => '']);

    $this->assertStringEndsWith('/charter-search/all', $form_state->getRedirect()->toString());
  }

  /**
   * Reset ignores the current selection and goes to the unfiltered listing.
   */
  public function testResetRedirectsToAll(): void {
    $form_state = $this->submitFilterForm('Reset', [
      'jurisdiction' => $this->tid('state'),
      'courthouse' => $this->tid('alpha'),
    ], $this->tid('alpha'));

    $this->assertStringEndsWith('/charter-search/all', $form_state->getRedirect()->toString());
  }

  /**
   * Builds the form as if on /charter-search/{$argument}.
   */
  protected function buildFilterForm(string $argument, string $path = '/charter-search/{arg_0}', string $parameter = 'arg_0'): array {
    $this->setCurrentRoute($argument, $path, $parameter);
    return $this->container->get('form_builder')->getForm(CustomViewsFilterForm::class);
  }

  /**
   * Programmatically submits the form with the given button and levels.
   */
  protected function submitFilterForm(string $op, array $levels, string $argument = 'all'): FormState {
    $this->setCurrentRoute($argument);
    $form_state = (new FormState())->setValues([
      'mc' => $levels,
      'op' => $op,
    ]);
    $this->container->get('form_builder')->submitForm(CustomViewsFilterForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors(), 'The form validated.');
    // FormState::getRedirect() always returns FALSE for programmed forms, as
    // there's no response to redirect. The submit handlers have already
    // stored the Url, so clear the flag to read it back.
    $form_state->setProgrammed(FALSE);
    $this->assertNotFalse($form_state->getRedirect(), 'The form set a redirect.');
    return $form_state;
  }

  /**
   * Pushes a request matching a Views-style charter-search route.
   */
  protected function setCurrentRoute(string $argument, string $path = '/charter-search/{arg_0}', string $parameter = 'arg_0'): void {
    $route = new Route($path, [], [], [
      '_view_argument_map' => ['arg_0' => $parameter],
    ]);

    $request = Request::create('/charter-search/' . $argument);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, 'view.charter_search.page_1');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->attributes->set($parameter, $argument);
    $request->attributes->set('_raw_variables', new ParameterBag([$parameter => $argument]));
    $request->setSession($this->container->get('session'));

    $this->container->get('request_stack')->push($request);
  }

}
