<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the config UI for adding and editing entity browsers.
 *
 * @group entity_browser
 *
 * @package Drupal\Tests\entity_browser\FunctionalJavascript
 */
class ConfigurationTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser',
    'entity_browser_entity_form',
    'node',
    'taxonomy',
    'views',
  ];

  /**
   * Browser storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $browserStorage;

  /**
   * The test administrative user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A test node to be used for embedding.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->browserStorage = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser');

    $this->adminUser = $this->drupalCreateUser([
      'administer entity browsers',
    ]);

  }

  /**
   * Tests EntityBrowserEditForm.
   */
  public function testEntityBrowserEditForm() {

    // Test that anonymous user can't access admin pages.
    $this->drupalGet('/admin/config/content/entity_browser');
    $this->assertSession()->responseContains('Access denied. You must log in to view this page.');
    $this->drupalGet('/admin/config/content/entity_browser/add');
    $this->assertSession()->responseContains('Access denied. You must log in to view this page.');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/content/entity_browser');
    $this->assertSession()->responseNotContains('Access denied. You must log in to view this page.');
    $this->assertSession()->responseContains('There are no entity browser entities yet.');

    $this->clickLink('Add Entity browser');
    $this->assertSession()->fieldExists('entity_id')->setValue('Test entity browser');
    $this->assertSession()->selectExists('display')->selectOption('modal');
    $this->assertSession()->fieldExists('display_configuration[width]')->setValue('700');
    $this->assertSession()->fieldExists('display_configuration[height]')->setValue('300');
    $this->assertSession()->fieldExists('display_configuration[link_text]')->setValue('Select some entities');
    $this->assertSession()->selectExists('widget_selector')->selectOption('widget_selector');
    $this->assertSession()->selectExists('selection_display')->selectOption('no_display');
    $this->assertSession()->buttonExists('Save')->press();

    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/widgets');
    $this->assertSession()->selectExists('widget');

    $this->clickLink('General Settings');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/edit');

    /** @var \Drupal\entity_browser\Entity\EntityBrowser $entity_browser */
    $entity_browser = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser')
      ->load('test_entity_browser');

    $this->assertEquals('modal', $entity_browser->display);
    $this->assertEquals('tabs', $entity_browser->widget_selector);
    $this->assertEquals('no_display', $entity_browser->selection_display);

    $display_configuration = $entity_browser->getDisplay()->getConfiguration();

    $this->assertEquals('700', $display_configuration['width']);
    $this->assertEquals('300', $display_configuration['height']);
    $this->assertEquals('Select some entities', $display_configuration['link_text']);

    $this->assertSession()->fieldValueEquals('display_configuration[width]', '700');
    $this->assertSession()->fieldValueEquals('display_configuration[height]', '300');
    $this->assertSession()->fieldValueEquals('display_configuration[link_text]', 'Select some entities');

    $this->assertSession()->selectExists('display')->selectOption('iframe');

    $this->assertSession()->waitForField('display_configuration[auto_open]')->check();
    $this->assertSession()->fieldExists('display_configuration[width]')->setValue('100');
    $this->assertSession()->fieldExists('display_configuration[height]')->setValue('100');
    $this->assertSession()->fieldExists('display_configuration[link_text]')->setValue('All animals are created equal');
    $this->assertSession()->buttonExists('Save')->press();

    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/widgets');
    $this->assertSession()->selectExists('widget');

    $this->clickLink('General Settings');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/edit');

    $entity_browser = $this->browserStorage->load('test_entity_browser');

    $this->assertEquals('iframe', $entity_browser->display);
    $this->assertEquals('tabs', $entity_browser->widget_selector);
    $this->assertEquals('no_display', $entity_browser->selection_display);

    $display_configuration = $entity_browser->getDisplay()->getConfiguration();

    $this->assertEquals('100', $display_configuration['width']);
    $this->assertEquals('100', $display_configuration['height']);
    $this->assertEquals('All animals are created equal', $display_configuration['link_text']);
    $this->assertEquals(TRUE, $display_configuration['auto_open']);

    $this->assertSession()->fieldValueEquals('display_configuration[width]', '100');
    $this->assertSession()->fieldValueEquals('display_configuration[height]', '100');
    $this->assertSession()->fieldValueEquals('display_configuration[link_text]', 'All animals are created equal');
    $this->assertSession()->checkboxChecked('display_configuration[auto_open]');

    $this->assertSession()->selectExists('display')->selectOption('standalone');
    $this->assertSession()->waitForField('display_configuration[path]')->setValue('/all-animals');
    $this->assertSession()->buttonExists('Save')->press();

    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/widgets');
    $this->assertSession()->selectExists('widget');

    $this->clickLink('General Settings');
    $this->assertSession()->addressEquals('/admin/config/content/entity_browser/test_entity_browser/edit');

    $entity_browser = $this->browserStorage->load('test_entity_browser');

    $this->assertEquals('standalone', $entity_browser->display);

    $display_configuration = $entity_browser->getDisplay()->getConfiguration();

    $this->assertEquals('/all-animals', $display_configuration['path']);
    $this->assertSession()->fieldValueEquals('display_configuration[path]', '/all-animals');

    // Test validation of leading forward slash.
    $this->assertSession()->fieldExists('display_configuration[path]')->setValue('no-forward-slash');
    $this->assertSession()->buttonExists('Save')->press();
    // Should show error, but doesn't currently.
    // See https://www.drupal.org/project/entity_browser/issues/3033493
    $this->assertSession()->fieldExists('display_configuration[path]')->setValue('/all-animals');
    $this->assertSession()->buttonExists('Save')->press();

    // Test ajax update of display settings.
    $this->assertSession()->selectExists('display')->selectOption('iframe');
    $this->assertSession()->waitForField('display_configuration[width]');
    $this->assertSession()->responseContains('Width of the iFrame', 'iFrame Display config form present');

    $this->assertSession()->selectExists('display')->selectOption('standalone');
    $this->assertSession()->waitForField('display_configuration[path]');
    $this->assertSession()->responseContains('The path at which the browser will be accessible.', 'Standalone Display config form present');

    $this->assertSession()->selectExists('display')->selectOption('modal');
    $this->assertSession()->waitForField('display_configuration[width]');
    $this->assertSession()->responseContains('Width of the modal', 'iFrame Display config form present');

    // Test ajax update of Selection display plugin settings.
    $this->assertSession()->selectExists('selection_display')->selectOption('multi_step_display');
    $this->assertSession()->waitForField('selection_display_configuration[select_text]');
    $this->assertSession()->fieldExists('selection_display_configuration[selection_hidden]');
    $this->assertSession()->selectExists('selection_display_configuration[entity_type]');
    $this->assertSession()->selectExists('selection_display_configuration[display]')->selectOption('rendered_entity');
    $this->assertSession()->waitForField('selection_display_configuration[display_settings][view_mode]');
    $this->assertSession()->responseContains('Select view mode to be used when rendering entities.');

    // Test ajax update of Multi step selection display "Entity display plugin".
    $this->assertSession()->selectExists('selection_display_configuration[display]')->selectOption('label');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldNotExists('selection_display_configuration[display_settings][view_mode]');
    $this->assertSession()->selectExists('selection_display_configuration[display]')->selectOption('rendered_entity');
    $this->assertSession()->waitForField('selection_display_configuration[display_settings][view_mode]');
    $this->assertSession()->responseContains('Select view mode to be used when rendering entities.');

    // Test ajax update of Multi step selection display "Entity type".
    $entity_type = $this->assertSession()->selectExists('selection_display_configuration[entity_type]')->selectOption('taxonomy_term');
    $this->assertSession()
      ->waitForField('selection_display_configuration[display_settings][view_mode]')
      ->has('option', 'Taxonomy term page');

    $entity_type->selectOption('node');
    $this->assertSession()
      ->waitForField('selection_display_configuration[display_settings][view_mode]')
      ->has('option', 'Full content');

    // Test view selection display.
    $this->assertSession()->selectExists('selection_display')->selectOption('view');
    $this->assertSession()
      ->waitForField('selection_display_configuration[view]')
      ->has('Content: Master');
    $this->assertSession()->responseContains('View display to use for displaying currently selected items.');

    $this->assertSession()->selectExists('selection_display')->selectOption('no_display');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementContains('This plugin has no configuration options', 'data-drupal-selector="edit-selection-display-configuration"');

  }

}
