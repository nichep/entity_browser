<?php

namespace Drupal\entity_browser\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\entity_browser\Plugin\Field\FieldWidget\EntityReferenceBrowserWidget;

/**
 * Defines a class to build a listing of selected entities.
 *
 * @ingroup entity_browser
 */
class GridListBuilder extends TableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {

    // The "Replace" button will only be shown if this setting is enabled in the
    // widget, and there is only one entity in the current selection.
    $replace_button_access = $this->settings['field_widget_replace'] && (count($this->entities) === 1);

    $edit_button_access = $this->settings['field_widget_edit'] && $entity->access('update');

    $hash = md5(json_encode($this->fieldParents));
    $data_entity_id = $entity->getEntityTypeId() . ':' . $entity->id();
    $limit_validation_errors = [array_merge($this->fieldParents, [$this->fieldName])];

    $row = [
      '#theme_wrappers' => ['container'],
      '#attributes' => [
        'class' => ['item-container', Html::getClass($this->display->getPluginId())],
        'data-entity-id' => $entity->getEntityTypeId() . ':' . $entity->id(),
        'data-row-id' => $this->delta,
      ],
      'display' => $this->display->view($entity),
      'remove_button' => [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#ajax' => [
          'callback' => [EntityReferenceBrowserWidget::class, 'updateWidgetCallback'],
          'wrapper' => $this->ajaxWrapper,
        ],
        '#submit' => [[EntityReferenceBrowserWidget::class, 'removeItemSubmit']],
        '#name' => $this->fieldName . '_remove_' . $entity->id() . '_' . $this->delta . '_' . $hash,
        '#limit_validation_errors' => $limit_validation_errors,
        '#attributes' => [
          'data-entity-id' => $data_entity_id,
          'data-row-id' => $this->delta,
          'class' => ['remove-button'],
        ],
        '#access' => (bool) $this->settings['field_widget_remove'],
      ],
      'replace_button' => [
        '#type' => 'submit',
        '#value' => $this->t('Replace'),
        '#ajax' => [
          'callback' => [EntityReferenceBrowserWidget::class, 'updateWidgetCallback'],
          'wrapper' => $this->ajaxWrapper,
        ],
        '#submit' => [[EntityReferenceBrowserWidget::class, 'removeItemSubmit']],
        '#name' => $this->fieldName . '_replace_' . $entity->id() . '_' . $this->delta . '_' . $hash,
        '#limit_validation_errors' => $limit_validation_errors,
        '#attributes' => [
          'data-entity-id' => $data_entity_id,
          'data-row-id' => $this->delta,
          'class' => ['replace-button'],
        ],
        '#access' => $replace_button_access,
      ],
      'edit_button' => [
        '#type' => 'submit',
        '#value' => $this->t('Edit'),
        '#ajax' => [
          'url' => Url::fromRoute(
            'entity_browser.edit_form', [
              'entity_type' => $entity->getEntityTypeId(),
              'entity' => $entity->id(),
            ]
          ),
          'options' => [
            'query' => [
              'details_id' => $this->ajaxWrapper,
            ],
          ],
        ],
        '#attributes' => [
          'class' => ['edit-button'],
        ],
        '#access' => $edit_button_access,
      ],
    ];

    if (isset($row['weight'])) {
      $row['weight']['#delta'] = $this->delta;
    }

    $row['#weight'] = $this->delta;
    $row['weight']['#default_value'] = $this->delta;

    return $row;
  }

  /**
   * {@inheritdoc}
   *
   * Builds the entity listing as renderable array.
   */
  public function render() {

    $classes = ['entities-list'];

    if ($this->cardinality != 1) {
      $classes[] = 'sortable';
    }

    $build = [
      '#theme_wrappers' => ['container'],
      '#attributes' => ['class' => $classes],
      'items' => [],
    ];

    $delta = 10;

    $this->delta = 0;
    foreach ($this->entities as $entity) {
      $row = $this->buildRow($entity);
      $build['items'][] = $row;

      $this->delta++;
    }

    return $build;
  }

}
