<?php

namespace Drupal\cif\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'CropperFormatter' formatter.
 *
 * @FieldFormatter(
 *   id = "cif_cropper_formatter",
 *   label = @Translation("Cropper"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class CropperFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $elements['#attached']['library'][] = 'cif/init';

    foreach (Element::children($elements) as $delta) {
      $elements[$delta]['#item_attributes']['cropper-data'] = [
        'type' => $items->getEntity()->getEntityTypeId(),
        'bundle' => $items->getEntity()->bundle(),
        'id' => $items->getEntity()->id(),
        'field' => $items->getName(),
        'delta' => $delta,
      ];
    }

    return $elements;
  }

}
