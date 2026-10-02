<?php

declare(strict_types=1);

namespace Drupal\Tests\chr_core\Kernel;

use Drupal\chr_core\Plugin\Field\FieldFormatter\CourthouseDefaultFormatter;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\chr_core\Traits\CourtHierarchyTrait;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Courthouse hierarchy" field formatter.
 *
 * @group chr_core
 */
#[CoversClass(CourthouseDefaultFormatter::class)]
#[Group('chr_core')]
class CourthouseDefaultFormatterTest extends KernelTestBase {

  use CourtHierarchyTrait;
  use EntityReferenceFieldCreationTrait;

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
    'entity_test',
    'chr_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('taxonomy_term');
    $this->container->get('router.builder')->rebuild();

    $this->createCourtHierarchy();
    $this->createEntityReferenceField(
      'entity_test',
      'entity_test',
      'field_courthouse',
      'Courthouse',
      'taxonomy_term',
      'default',
      ['target_bundles' => ['vocabulary_4' => 'vocabulary_4']],
      -1,
    );
  }

  /**
   * The formatter is discovered under its plugin id.
   */
  public function testFormatterIsDiscovered(): void {
    $definition = $this->container->get('plugin.manager.field.formatter')
      ->getDefinition('courthouse_default', FALSE);

    $this->assertNotNull($definition);
    $this->assertSame(CourthouseDefaultFormatter::class, $definition['class']);
  }

  /**
   * Renders the full root-to-term chain, marking the selected term.
   */
  public function testRendersHierarchy(): void {
    $this->renderField(['civil'], FALSE);

    $items = $this->cssSelect('ul.shs-hierarchy li');
    $this->assertSame(
      ['State Courts', 'Alpha Courthouse', 'Superior Court', 'Civil Division'],
      array_map(fn ($li) => trim((string) $li), $items),
    );

    $this->assertCount(3, $this->cssSelect('ul.shs-hierarchy li.shs-parent'));
    $selected = $this->cssSelect('ul.shs-hierarchy li.shs-term-selected');
    $this->assertCount(1, $selected);
    $this->assertSame('Civil Division', trim((string) $selected[0]));

    $this->assertEmpty($this->cssSelect('ul.shs-hierarchy a'), 'Terms are not linked by default.');
  }

  /**
   * A top-level term renders as a single selected item.
   */
  public function testRendersTopLevelTerm(): void {
    $this->renderField(['federal'], FALSE);

    $this->assertEmpty($this->cssSelect('li.shs-parent'));
    $this->assertCount(1, $this->cssSelect('li.shs-term-selected'));
  }

  /**
   * With "linked" enabled each term links to its canonical page.
   */
  public function testLinkedSetting(): void {
    $this->renderField(['alpha'], TRUE);

    $links = $this->cssSelect('ul.shs-hierarchy a');
    $this->assertCount(2, $links);
    $this->assertStringEndsWith('/taxonomy/term/' . $this->tid('state'), (string) $links[0]['href']);
    $this->assertStringEndsWith('/taxonomy/term/' . $this->tid('alpha'), (string) $links[1]['href']);
  }

  /**
   * Each field value gets its own list.
   */
  public function testMultipleValues(): void {
    $this->renderField(['civil', 'beta'], FALSE);

    $this->assertCount(2, $this->cssSelect('ul.shs-hierarchy'));
  }

  /**
   * The summary reflects the linked setting.
   */
  public function testSettingsSummary(): void {
    $field_definition = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('entity_test', 'entity_test')['field_courthouse'];
    $manager = $this->container->get('plugin.manager.field.formatter');

    foreach ([TRUE => 'Linked', FALSE => 'Not linked'] as $linked => $expected) {
      $formatter = $manager->getInstance([
        'field_definition' => $field_definition,
        'view_mode' => 'default',
        'configuration' => [
          'type' => 'courthouse_default',
          'settings' => ['linked' => (bool) $linked],
        ],
      ]);
      $this->assertSame($expected, (string) $formatter->settingsSummary()[0]);
    }
  }

  /**
   * Saves an entity referencing the given terms and renders the field.
   *
   * @param string[] $term_keys
   *   Keys into $this->terms.
   * @param bool $linked
   *   The formatter's "linked" setting.
   */
  protected function renderField(array $term_keys, bool $linked): void {
    $entity = EntityTest::create([
      'name' => 'Test attorney',
      'field_courthouse' => array_map(fn ($key) => ['target_id' => $this->terms[$key]->id()], $term_keys),
    ]);
    $entity->save();

    $build = $entity->get('field_courthouse')->view([
      'type' => 'courthouse_default',
      'label' => 'hidden',
      'settings' => ['linked' => $linked],
    ]);
    $this->render($build);
  }

}
