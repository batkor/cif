<?php

namespace Drupal\cif\Controller;

use Drupal\cif\CifManager;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\Entity\File;
use Drupal\file\Upload\FileUploadHandler;
use Drupal\file\Upload\FormUploadedFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Returns responses for Cropper image formatter routes.
 */
class CifController implements ContainerInjectionInterface {

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file upload handler.
   */
  protected FileUploadHandler $uploadHandler;

  /**
   * Token replacement.
   */
  protected Token $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $static = new static();
    $static->requestStack = $container->get('request_stack');
    $static->entityTypeManager = $container->get('entity_type.manager');
    $static->uploadHandler = $container->get('file.upload_handler');
    $static->token = $container->get('token');

    return $static;
  }

  /**
   * Callback for "cif.upload" route.
   */
  public function upload() {
    $data = $this->getRequestData();
    $this->checkRequiredKeys(['field', 'delta']);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager
      ->getStorage($data['type'])
      ->load($data['id']);

    if (!$entity->hasField($data['field'])) {
      throw new BadRequestHttpException(sprintf('Not found "%s" field on entity type "%s".', $data['field'], $data['type']));
    }

    $fieldItems = $entity->get($data['field']);
    $value = $fieldItems->getValue();

    if ($file = $this->uploadFile($fieldItems->getDataDefinition())) {
      $this->fastDeleteHandler($fieldItems, $data);

      $value[$data['delta']]['target_id'] = $file->id();
    }

    $entity->set($data['field'], $value)->save();

    return new Response('Successfully');
  }

  /**
   * Handler for delete old file entity.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItems
   *   The field items list.
   * @param array $data
   *   The request data.
   *
   * @code
   *   Settings on settings.php file for delete file entity for all fields.
   *   $settings['cif']['fast_delete_all'] = TRUE;
   *   For specific fields:
   *   - Delete
   *   $settings['cif']['fast_delete']['node.article.field_image'] = TRUE;
   *   - Not delete
   *   $settings['cif']['fast_delete']['node.article.field_image'] = FALSE;
   * @endcode
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function fastDeleteHandler(FieldItemListInterface $fieldItems, array $data): void {
    $settings = Settings::get('cif', []);
    $isDelete = FALSE;

    if (isset($settings['fast_delete']["{$data['type']}.{$data['bundle']}.{$data['field']}"])) {
      $isDelete = (bool) $settings['fast_delete']["{$data['type']}.{$data['bundle']}.{$data['field']}"];
    }
    elseif (!empty($settings['fast_delete_all'])) {
      $isDelete = TRUE;
    }

    if ($isDelete) {
      $fieldItems
        ->get($data['delta'])
        ->get('entity')
        ->getValue()
        ->delete();
    }
  }

  /**
   * Create and return File entity by file resource from request.
   *
   * @return \Drupal\file\Entity\File
   *   The file entity.
   */
  protected function uploadFile(DataDefinitionInterface $fieldDef): File {
    $request = $this->requestStack->getCurrentRequest();
    $file = $request->files->get('image');

    if (empty($file)) {
      throw new BadRequestHttpException('Not found upload file.');
    }

    $maxFileSize = min(Bytes::toNumber($fieldDef->getSetting('max_filesize')), Environment::getUploadMaxSize());
    $validators = [
      'file_validate_extensions' => [$fieldDef->getSetting('file_extensions')],
      'file_validate_size' => [$maxFileSize],
    ];
    $min = $fieldDef->getSetting('min_resolution');
    $max = $fieldDef->getSetting('max_resolution');

    if ($min || $max) {
      $validators['file_validate_image_resolution'] = [$max, $min];
    }

    $fileUpload = new FormUploadedFile($file);
    $result = $this->uploadHandler
      ->handleFileUpload($fileUpload, $validators, $this->getDestination($fieldDef->getSettings()), FileSystemInterface::EXISTS_RENAME);

    return $result->getFile();
  }

  /**
   * Returns file destination by settings.
   *
   * @param array $settings
   *   The field definition settings.
   *
   * @return string
   *   The file destination.
   */
  protected function getDestination(array $settings): string {
    $destination = trim($settings['file_directory'], '/');
    $destination = PlainTextOutput::renderFromHtml($this->token->replace($destination));

    return $settings['uri_scheme'] . '://' . $destination;
  }

  /**
   * Access check handler for "cif.upload" route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access check result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $this->checkRequiredKeys(['type', 'id']);
    $data = $this->getRequestData();

    return $this->entityTypeManager
      ->getStorage($data['type'])
      ->load($data['id'])
      ->access('update', $account, TRUE);
  }

  /**
   * Check key list on request data.
   *
   * @param array $keys
   *   The key list.
   */
  protected function checkRequiredKeys(array $keys): void {
    $data = $this->getRequestData();

    foreach ($keys as $key) {
      if (!array_key_exists($key, $data)) {
        throw new BadRequestHttpException(sprintf('Not found required "%s" key.', $key));
      }
    }
  }

  /**
   * Returns data from request.
   *
   * @return array
   *   The decode data from request.
   */
  protected function getRequestData(): array {
    $request = $this->requestStack->getCurrentRequest();

    return CifManager::decode($request->get('data'));
  }

}
