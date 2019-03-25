<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_browser\Entity\EntityBrowser;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the config UI for adding and editing entity browsers.
 *
 * @group entity_browser
 *
 * @package Drupal\Tests\entity_browser\FunctionalJavascript
 */
class FieldWidgetConfigTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_browser',
    'entity_browser_test',
    'block',
    'node',
    'taxonomy',
    'views',
    'token',
    'field_ui',
  ];

  /**
   * The test administrative user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');

    $this->adminUser = $this->drupalCreateUser([
      'administer entity browsers',
      'access administration pages',
      'administer node fields',
      'administer node display',
      'administer nodes',
      'administer node form display',
      'create article content',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests 'selection_edit' validation on field widget form and warning message on content entity forms.
   */
  public function testSelectionModeValidation() {

    // Create an entity_reference field to test the widget.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_dalek',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_dalek',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Seek! Locate! Exterminate!',
      'settings' => [],
    ]);
    $field->save();

    $this->drupalGet('/admin/structure/types/manage/article/form-display');
    // Drag to enabled.
    $target = $this->assertSession()
      ->elementExists('css', '#title');
    $this->assertSession()
      ->elementExists('css', '#field-dalek')
      ->find('css', '.handle')
      ->dragTo($target);
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Set to Entity Browser Widget.
    $this->assertSession()->selectExists('fields[field_dalek][type]')->selectOption('entity_browser_entity_reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Open settings form.
    $this->assertSession()->waitforButton('field_dalek_settings_edit')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $form_prefix = 'fields[field_dalek][settings_edit_form][settings]';

    // Select entity browser with "no_selection" selection display.
    $this->assertSession()->selectExists($form_prefix . '[entity_browser]')->selectOption('test_entity_browser_iframe_node_view');
    $this->assertSession()->selectExists($form_prefix . '[selection_mode]')->selectOption('selection_edit');
    $this->assertSession()->buttonExists('field_dalek_plugin_settings_update')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->selectExists($form_prefix . '[entity_browser]')->hasClass('error');
    $this->assertSession()->selectExists($form_prefix . '[selection_mode]')->hasClass('error');

    $error_message = 'The selection mode Edit selection requires an entity browser with a selection display plugin that supports preselection. Either change the selection mode or update the Test entity browser iframe with view widget for nodes entity browser to use a selection display plugin that supports preselection.';

    $this->assertSession()->pageTextContains($error_message);

    // Switch to an entity browser that supports preselection.
    $this->assertSession()->selectExists($form_prefix . '[entity_browser]')->selectOption('test_entity_browser_iframe_view');

    $this->assertSession()->buttonExists('field_dalek_plugin_settings_update')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->responseNotContains($error_message);

    $this->assertSession()->buttonExists('Save')->press();

    // Update selected entity browser so it will trigger a warning.
    $entity_browser = EntityBrowser::load('test_entity_browser_iframe_view');
    $entity_browser->setSelectionDisplay('no_display');
    $entity_browser->save();

    $this->drupalGet('/node/add/article');
    // Error message should be shown.
    $this->assertSession()->pageTextContains('There is a configuration problem with field "Seek! Locate! Exterminate!". The selection mode Edit selection requires an entity browser with a selection display plugin that supports preselection. Either change the selection mode or update the Test entity browser iframe with view widget entity browser to use a selection display plugin that supports preselection.');
  }

}
