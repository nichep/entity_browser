<?php

namespace Drupal\entity_browser\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\style\Table;
use Drupal\views\ResultRow;
use Drupal\views\Render\ViewsRenderPipelineMarkup;

/**
 * Defines a bulk operation form element that works with entity browser.
 *
 * @ViewsField("entity_browser_select")
 */
class SelectForm extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['use_field_cardinality'] = [
      'default' => TRUE,
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['use_field_cardinality'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use field cardinality'),
      '#default_value' => $this->options['use_field_cardinality'],
      '#description' => $this->t('In case of cardinality 1, radios instead of checboxes will be displayed.')
    ];
  }

  /**
   * Returns the ID for a result row.
   *
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   *
   * @return string
   *   The row ID, in the form ENTITY_TYPE:ENTITY_ID.
   */
  public function getRowId(ResultRow $row) {
    $entity = $this->getEntity($row);
    return $entity->getEntityTypeId() . ':' . $entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    return ViewsRenderPipelineMarkup::create('<!--form-item-' . $this->options['id'] . '--' . $this->getRowId($values) . '-->');
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    parent::preRender($values);

    // If the view is using a table style, provide a placeholder for a
    // "select all" checkbox.
    if (!empty($this->view->style_plugin) && $this->view->style_plugin instanceof Table) {
      // Add the tableselect css classes.
      $this->options['element_label_class'] .= 'select-all';
      // Hide the actual label of the field on the table header.
      $this->options['label'] = '';
    }
  }

  /**
   * Form constructor for the bulk form.
   *
   * @param array $render
   *   An associative array containing the structure of the form.
   */
  public function viewsForm(&$render) {
    // Only add the bulk form options and buttons if there are results.
    if (!empty($this->view->result)) {
      // Render checkboxes for all rows.
      $render[$this->options['id']]['#tree'] = TRUE;
      $render[$this->options['id']]['#printed'] = TRUE;
      foreach ($this->view->result as $row) {
        $value = $this->getRowId($row);

        $render[$this->options['id']][$value] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Select this item'),
          '#title_display' => 'invisible',
          '#return_value' => $value,
          '#attributes' => ['name' => "entity_browser_select[$value]"],
          '#default_value' => NULL,
        ];

        if ($this->options['use_field_cardinality'] && isset($this->view->cardinality) && $this->view->cardinality == 1) {
          $render[$this->options['id']][$value]['#type'] = 'radio';
          $render[$this->options['id']][$value]['#attributes'] = ['name' => "entity_browser_select"];
          $render[$this->options['id']][$value]['#parents'] = ['entity_browser_select'];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

}
