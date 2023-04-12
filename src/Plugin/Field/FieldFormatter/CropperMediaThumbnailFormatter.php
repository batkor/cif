<?php

namespace Drupal\cif\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;

/**
 * Plugin implementation of the 'CropperFormatter' formatter.
 *
 * @FieldFormatter(
 *   id = "cif_cropper_media_thumbnail",
 *   label = @Translation("Cropper thumbnail"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CropperMediaThumbnailFormatter extends MediaThumbnailFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $elements['#attached']['library'][] = 'cif/init';

    foreach ($items as $delta => $item) {
      /** @var \Drupal\media\MediaInterface $media */
      $media = $item->get('entity')->getValue();
      $elements[$delta]['#item_attributes']['cropper-data'] = [
        'type' => $media->getEntityTypeId(),
        'bundle' => $media->bundle(),
        'id' => $media->id(),
        'field' => $media->getSource()->getConfiguration()['source_field'],
        'delta' => $delta,
      ];
    }

    return $elements;
  }
}
