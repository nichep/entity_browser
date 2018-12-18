<?php

namespace Drupal\Tests\entity_browser\FunctionalJavascript;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\Role;

/**
 * Tests the Entity Reference Widget.
 *
 * @group entity_browser
 */
class EntityReferenceWidgetTest extends EntityBrowserJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'access test_entity_browser_iframe_node_view entity browser pages',
      'bypass node access',
      'administer node form display',
    ]);

  }

  /**
   * Tests Entity Reference widget.
   */
  public function testEntityReferenceWidget() {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Create an entity_reference field to test the widget.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_entity_reference1',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_entity_reference1',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Referenced articles',
      'settings' => [],
    ]);
    $field->save();

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.article.default');

    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();

    // Create a dummy node that will be used as target.
    $target_node = Node::create([
      'title' => 'Target example node 1',
      'type' => 'article',
    ]);
    $target_node->save();

    $this->drupalGet('/node/add/article');
    $page->fillField('title[0][value]', 'Referencing node 1');
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $page->pressButton('Save');

    $assert_session->pageTextContains('Article Referencing node 1 has been created.');
    $nid = $this->container->get('entity.query')->get('node')->condition('title', 'Referencing node 1')->execute();
    $nid = reset($nid);

    $this->drupalGet('node/' . $nid . '/edit');
    $assert_session->pageTextContains('Target example node 1');
    // Make sure both "Edit" and "Remove" buttons are visible.
    $assert_session->buttonExists('edit-field-entity-reference1-current-items-0-remove-button');
    $assert_session->buttonExists('edit-field-entity-reference1-current-items-0-edit-button');

    // Test whether changing these definitions on the browser config effectively
    // change the visibility of the buttons.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => FALSE,
        'field_widget_remove' => FALSE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    $this->drupalGet('node/' . $nid . '/edit');
    $assert_session->buttonNotExists('edit-field-entity-reference1-current-items-0-remove-button');
    $assert_session->buttonNotExists('edit-field-entity-reference1-current-items-0-edit-button');

    // Set them to visible again.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => FALSE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    $this->drupalGet('node/' . $nid . '/edit');
    $remove_button = $assert_session->buttonExists('edit-field-entity-reference1-current-items-0-remove-button');
    $this->assertEquals('Remove', $remove_button->getValue());
    $this->assertTrue($remove_button->hasClass('remove-button'));
    $edit_button = $assert_session->buttonExists('edit-field-entity-reference1-current-items-0-edit-button');
    $this->assertEquals('Edit', $edit_button->getValue());
    $this->assertTrue($edit_button->hasClass('edit-button'));
    // Make sure the "Replace" button is not there.
    $assert_session->buttonNotExists('edit-field-entity-reference1-current-items-0-replace-button');

    // Test the "Remove" button on the widget works.
    $page->pressButton('Remove');
    $this->waitForAjaxToFinish();
    $assert_session->pageTextNotContains('Target example node 1');

    // Test the "Replace" button functionality.
    $form_display->setComponent('field_entity_reference1', [
      'type' => 'entity_browser_entity_reference',
      'settings' => [
        'entity_browser' => 'test_entity_browser_iframe_node_view',
        'open' => TRUE,
        'field_widget_edit' => TRUE,
        'field_widget_remove' => TRUE,
        'field_widget_replace' => TRUE,
        'selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
        'field_widget_display' => 'label',
        'field_widget_display_settings' => [],
      ],
    ])->save();
    // In order to ensure the replace button opens the browser, it needs to be
    // closed.
    /** @var \Drupal\entity_browser\EntityBrowserInterface $browser */
    $browser = $this->container->get('entity_type.manager')
      ->getStorage('entity_browser')
      ->load('test_entity_browser_iframe_node_view');
    $browser->getDisplay()
      ->setConfiguration([
        'width' => 650,
        'height' => 500,
        'link_text' => 'Select entities',
        'auto_open' => FALSE,
      ]);
    $browser->save();
    // We'll need a third node to be able to make a new selection.
    $target_node2 = Node::create([
      'title' => 'Target example node 2',
      'type' => 'article',
    ]);
    $target_node2->save();
    $this->drupalGet('node/' . $nid . '/edit');
    // If there is only one entity in the current selection the button should
    // show up.
    $replace_button = $assert_session->buttonExists('edit-field-entity-reference1-current-items-0-replace-button');
    $this->assertEquals('Replace', $replace_button->getValue());
    $this->assertTrue($replace_button->hasClass('replace-button'));
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node3');
    $page->pressButton('Select entities');
    $session->wait(1000);
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    // Even in the AJAX-built markup for the newly selected element, the replace
    // button should be there.
    $assert_session->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Adding a new node to the selection, however, should make it disappear.
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $session->wait(1000);
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->elementNotExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Article Referencing node 1 has been updated.');

    // Test the replace button again with different field cardinalities.
    FieldStorageConfig::load('node.field_entity_reference1')->setCardinality(1)->save();
    $this->drupalGet('/node/add/article');
    $page->fillField('title[0][value]', 'Referencing node 2');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $session->wait(1000);
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Target example node 1');
    // All three buttons should be visible.
    $assert_session->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-remove-button"]');
    $assert_session->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-edit-button"]');
    $replace_button = $assert_session->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node2');
    $page->pressButton('Select entities');
    $session->wait(1000);
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Referencing node 1');

    // Do the same as above but now with cardinality 2.
    FieldStorageConfig::load('node.field_entity_reference1')->setCardinality(2)->save();
    $this->drupalGet('/node/add/article');
    $page->fillField('title[0][value]', 'Referencing node 3');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $session->wait(1000);
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Target example node 1');
    // All three buttons should be visible.
    $assert_session->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-remove-button"]');
    $assert_session->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-edit-button"]');
    $replace_button = $assert_session->elementExists('css', 'input[data-drupal-selector="edit-field-entity-reference1-current-items-0-replace-button"]');
    // Clicking on the button should empty the selection and automatically
    // open the browser again.
    $replace_button->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node2');
    $page->pressButton('Select entities');
    $session->wait(1000);
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->elementContains('css', '#edit-field-entity-reference1-wrapper', 'Referencing node 1');

    // Verify that if the user cannot edit the entity, the "Edit" button does
    // not show up, even if configured to.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $role->revokePermission('bypass node access')->trustData()->save();
    $this->drupalGet('node/add/article');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $page->checkField('edit-entity-browser-select-node1');
    $page->pressButton('Select entities');
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    $assert_session->buttonNotExists('edit-field-entity-reference1-current-items-0-edit-button');

    // Test the cardinality handling.
    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'access test_entity_browser_iframe_node_view entity browser pages',
      'bypass node access',
      'administer node form display',
    ]);
    FieldStorageConfig::load('node.field_entity_reference1')->setCardinality(2)->save();
    // Without using field cardinality, the view should contain checkboxes.
    $view = \Drupal::configFactory()->getEditable('views.view.test_entity_reference_widget_view');
    $field = $view->get('display.default.display_options.fields.entity_browser_select', TRUE);
    $field['use_field_cardinality'] = FALSE;
    $view->set('display.default.display_options.fields.entity_browser_select', $field);
    $view->save();
    $page->fillField('title[0][value]', 'Referencing node 3');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $style_selector = $page->find('css', 'input[value="node:1"].form-checkbox');
    $this->assertTrue($style_selector->isVisible());
    // If using field cardinality and field cardinality is greater than 1 then
    // there should be still checkboxes.
    $field = $view->get('display.default.display_options.fields.entity_browser_select', TRUE);
    $field['use_field_cardinality'] = TRUE;
    $view->set('display.default.display_options.fields.entity_browser_select', $field);
    $view->save();
    $this->drupalGet('/node/add/article');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $style_selector = $page->find('css', 'input[value="node:1"].form-checkbox');
    $this->assertTrue($style_selector->isVisible());
    // If we select 3 nodes, EB shouldn't allow us.
    $target_node3 = Node::create([
      'title' => 'Target example node 3',
      'type' => 'article',
    ]);
    $target_node3->save();
    $this->drupalGet('/node/add/article');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $style_selector = $page->find('css', 'input[value="node:1"].form-checkbox');
    $this->assertTrue($style_selector->isVisible());
    $style_selector->click();
    $style_selector = $page->find('css', 'input[value="node:3"].form-checkbox');
    $this->assertTrue($style_selector->isVisible());
    $style_selector->click();
    $style_selector = $page->find('css', 'input[value="node:4"].form-checkbox');
    $this->assertTrue($style_selector->isVisible());
    $style_selector->click();
    $page->pressButton('Select entities');
    $assert_session->pageTextContains('You can only select up to 2 items');
    // If we change the cardinality to 1, we should have radios.
    FieldStorageConfig::load('node.field_entity_reference1')->setCardinality(1)->save();
    $this->drupalGet('/node/add/article');
    $open_iframe_link = $assert_session->elementExists('css', 'a[data-drupal-selector="edit-field-entity-reference1-entity-browser-entity-browser-link"]');
    $open_iframe_link->click();
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $style_selector = $page->find('css', 'input[value="node:1"].form-radio');
    $this->assertTrue($style_selector->isVisible());
    $style_selector->click();
    $page->pressButton('Select entities');
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    // Assert the selected entity.
    $assert_session->pageTextContains('Target example node 1');
    // Attempt to select more than one element.
    $page->pressButton('Replace');
    $this->waitForAjaxToFinish();
    $session->switchToIFrame('entity_browser_iframe_test_entity_browser_iframe_node_view');
    $this->waitForAjaxToFinish();
    $style_selector = $page->find('css', 'input[value="node:1"].form-radio');
    $this->assertTrue($style_selector->isVisible());
    $style_selector->click();
    $style_selector = $page->find('css', 'input[value="node:3"].form-radio');
    $this->assertTrue($style_selector->isVisible());
    $style_selector->click();
    $page->pressButton('Select entities');
    $session->switchToIFrame();
    $this->waitForAjaxToFinish();
    // Assert the selected entity.
    $assert_session->pageTextContains('Target example node 2');
    $assert_session->pageTextNotContains('Target example node 1');
  }

}
