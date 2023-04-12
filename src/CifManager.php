<?php

namespace Drupal\cif;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinition;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * The helper for attach data to renderable array.
 */
class CifManager {

  /**
   * The status property for include popup template.
   */
  protected bool $include = FALSE;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructors.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Processing renderable array.
   *
   * @param array $vars
   *   The renderable array.
   */
  public function process(array &$vars): void {
    if (empty($vars['attributes']['cropper-data'])) {
      return;
    }

    if (!is_array($vars['attributes']['cropper-data'])) {
      return;
    }

    $data = $vars['attributes']['cropper-data'];
    $accessResult = \Drupal::entityTypeManager()
      ->getStorage($data['type'])
      ->load($data['id'])
      ->access('update');

    if (!$accessResult) {
      unset($vars['attributes']['cropper-data']);

      return;
    }

    $fieldConfig = FieldConfig::loadByName($data['type'], $data['bundle'], $data['field']);
    $data['ext'] = $fieldConfig->getSetting('file_extensions');
    $vars['attributes']['cropper-data'] = self::encode($data);
    $vars['#attached']['library'][] = 'cif/init';
    $this->include = TRUE;
  }

  /**
   * Returns status for attach popup template.
   *
   * @return bool
   *   The status.
   */
  public function attachPopup(): bool {
    return $this->include;
  }
  
  /**
   * Encodes source data.
   *
   * @param array $data
   *   The source data.
   *
   * @return string
   *   The encoded data.
   */
  public static function encode(array $data): string {
    return base64_encode(Json::encode($data));
  }

  /**
   * Decode data.
   *
   * @param string $data
   *   The source data.
   *
   * @return array
   *   The decoded data.
   */
  public static function decode(string $data): array {
    return Json::decode(base64_decode($data));
  }

}
