<?php

declare(strict_types=1);

namespace Drupal\Tests\chr_core\Kernel;

use Drupal\chr_core\Controller\ProfilePage;
use Drupal\chr_core\Hook\CustomShsHooks;
use Drupal\chr_core\Plugin\views\filter\CustomShsFilterTermNodeTid;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests chr_core's hooks, Views filter plugin, permissions and page arrays.
 *
 * @group chr_core
 */
#[CoversClass(CustomShsHooks::class)]
#[CoversClass(CustomShsFilterTermNodeTid::class)]
#[CoversClass(ProfilePage::class)]
#[Group('chr_core')]
class ModuleIntegrationTest extends KernelTestBase {

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
    'views',
    'shs',
    'chr_core',
  ];

  /**
   * hook_views_data_alter() registers the CHR filter on term ids.
   */
  public function testViewsDataAlter(): void {
    $data = $this->container->get('views.views_data')->get('taxonomy_term_field_data');

    $this->assertArrayHasKey('chr_core_term_node_tid', $data);
    $this->assertSame('chr_core_filter_term_node_tid', $data['chr_core_term_node_tid']['filter']['id']);
    $this->assertStringContainsString('CHR: Simple hierarchical select', (string) $data['chr_core_term_node_tid']['filter']['title']);
  }

  /**
   * The Views filter plugin is discovered and defaults to the court terms.
   */
  public function testFilterPlugin(): void {
    $manager = $this->container->get('plugin.manager.views.filter');
    $definition = $manager->getDefinition('chr_core_filter_term_node_tid', FALSE);

    $this->assertNotNull($definition);
    $this->assertSame(CustomShsFilterTermNodeTid::class, $definition['class']);

    $filter = $manager->createInstance('chr_core_filter_term_node_tid');
    $options = (new \ReflectionMethod($filter, 'defineOptions'))->invoke($filter);
    $this->assertSame('vocabulary_4', $options['vid']['default']);
    $this->assertSame('shs', $options['type']['default']);
    $this->assertTrue($options['hierarchy']['default']);
  }

  /**
   * The SHS settings alter hook only touches the my_field instance.
   */
  public function testShsSettingsAlter(): void {
    $settings = [
      'shs' => [
        'my_container[my_field]' => [
          'hash' => ['any_label' => 'original', 'display' => ['animationSpeed' => 400]],
        ],
        'other_field' => [
          'hash' => ['any_label' => 'untouched'],
        ],
      ],
    ];

    $field_name = 'my_field';
    $vid = 'vocabulary_4';
    $this->container->get('module_handler')
      ->alter('shs_my_field_js_settings', $settings, $field_name, $vid);

    $altered = $settings['shs']['my_container[my_field]']['hash'];
    $this->assertSame('- Any -', (string) $altered['any_label']);
    $this->assertSame(100, $altered['display']['animationSpeed']);
    $this->assertSame('untouched', $settings['shs']['other_field']['hash']['any_label']);
  }

  /**
   * The alter hook tolerates settings without the my_field instance.
   */
  public function testShsSettingsAlterWithoutInstance(): void {
    $settings = ['shs' => []];
    $field_name = 'my_field';
    $vid = 'vocabulary_4';
    $this->container->get('module_handler')
      ->alter('shs_my_field_js_settings', $settings, $field_name, $vid);
    $this->assertSame(['shs' => []], $settings);
  }

  /**
   * The permissions ported from hook_permission() are defined.
   */
  public function testPermissions(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();

    $this->assertArrayHasKey('invite new users', $permissions);
    $this->assertSame('chr_core', $permissions['invite new users']['provider']);
    $this->assertSame('Invite Appearance Attorneys', (string) $permissions['invite new users']['title']);

    $this->assertArrayHasKey('administer courthouseregular', $permissions);
    $this->assertTrue($permissions['administer courthouseregular']['restrict access']);
  }

  /**
   * The profile page controllers return the expected themed arrays.
   */
  public function testProfilePageRenderArrays(): void {
    $controller = ProfilePage::create($this->container);

    $expected = [
      'affiliatePage' => ['affiliate_center', 'Affiliate Center'],
      'bookmarksPage' => ['bookmarks_page', 'Bookmarks'],
      'friendsPage' => ['colleagues_page', 'All Colleagues'],
      'invitePage' => ['invite_attorneys_page', 'Invite Appearance Attorneys'],
      'inviteByMailPage' => ['invite_law_firms_page', 'Invite Law Firms'],
    ];
    foreach ($expected as $method => [$theme, $title]) {
      $build = $controller->$method();
      $this->assertSame($theme, $build['#theme'], $method);
      $this->assertSame($title, $build['#title_page'], $method);
    }

    $this->assertSame('invite_appearance_attorneys', $controller->invitePage()['#webform_id']);
    $this->assertSame('invite_law_firms', $controller->inviteByMailPage()['#webform_id']);

    // Every #theme used by the controller is registered by hook_theme().
    $registry = $this->container->get('theme.registry')->get();
    foreach ($expected as [$theme]) {
      $this->assertArrayHasKey($theme, $registry);
    }
  }

}
