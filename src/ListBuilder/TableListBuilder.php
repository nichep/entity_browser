<?php

namespace Drupal\entity_browser\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\entity_browser\Plugin\Field\FieldWidget\EntityReferenceBrowserWidget;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of selected entities.
 *
 * @ingroup entity_browser
 */
class TableListBuilder extends EntityListBuilder {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * An array of entities to display.
   *
   * @var array
   */
  protected $entities;

  /**
   * The FieldWidgetDisplay plugin.
   *
   * @var \Drupal\entity_browser\FieldWidgetDisplayInterface
   */
  protected $display;

  /**
   * The current delta.
   *
   * @var int
   */
  protected $delta;

  /**
   * The ID of the current details dom element.
   *
   * @var string
   */
  protected $ajaxWrapper;

  /**
   * The EntityReferenceBrowserWidget settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * The field parents array.
   *
   * @var array
   */
  protected $fieldParents;

  /**
   * The current field machine name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The current field cardinality.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param array $entities
   *   An array of entities to display.
   * @param \Drupal\entity_browser\FieldWidgetDisplayInterface
   *   A field Widget display plugin.
   * @param \Drupal\entity_browser\FieldWidgetDisplayInterface $display
   *   The display plugin.
   * @param string $wrapper
   *   The ajax wrapper.
   * @param array $settings
   *   The EntityReferenceBrowserWidget settings.
   * @param array $parents
   *   The element's field parents array.
   * @param string $field_name
   *   The current field machine name.
   * @param int $cardinality
   *   The current field cardinality.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, array $entities = [], $display, $wrapper, $settings, $parents, $field_name, $cardinality) {
    $this->entityTypeId = $entity_type->id();
    $this->storage = $storage;
    $this->entityType = $entity_type;
    $this->entities = $entities;
    $this->display = $display;
    $this->ajaxWrapper = $wrapper;
    $this->settings = $settings;
    $this->fieldParents = $parents;
    $this->fieldName = $field_name;
    $this->cardinality = $cardinality;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'display' => $this->display
        ->getDisplayLabel($this->entityType),
      'weight' => $this->t('Weight'),
      'operations' => $this->t('Operations'),
    ];
  }

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

    $row = [];
    $row['#attributes']['class'][] = 'draggable';
    $row['#weight'] = 0;
    $row['display'] = $this->display->view($entity);
    // Add weight column.
    $row['weight'] = [
      '#type' => 'weight',
      '#title' => t('Weight for @title', ['@title' => $entity->label()]),
      '#title_display' => 'invisible',
      '#default_value' => 0,
      '#attributes' => ['class' => ['weight']],
    ];
    $row['operations'] = [];

    $row['operations'][] = [
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
    ];

    $row['operations'][] = [
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
    ];

    $row['operations'][] = [
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
   * Builds the entity listing as renderable array for table.html.twig.
   */
  public function render() {

    $build = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#empty' => $this->t('There are no @label yet.', ['@label' => $this->entityType->getPluralLabel()]),
      '#cache' => [
        // @todo add cache tags
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'weight',
        ],
      ],
    ];

    $this->delta = 0;
    foreach ($this->entities as $entity) {
      $row = $this->buildRow($entity);
      $build[$entity->id()] = $row;

      $this->delta++;
    }

    return $build;
  }

}
