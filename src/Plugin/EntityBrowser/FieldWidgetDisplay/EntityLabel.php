<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\FieldWidgetDisplay;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_browser\FieldWidgetDisplayBase;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Displays a label of the entity.
 *
 * @EntityBrowserFieldWidgetDisplay(
 *   id = "label",
 *   label = @Translation("Entity label"),
 *   description = @Translation("Displays entity with a label.")
 * )
 */
class EntityLabel extends FieldWidgetDisplayBase {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity) {
    return ['#markup' => $entity->label()];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel(EntityTypeInterface $entity_type) {
    return $this->t('Label');
  }

}
