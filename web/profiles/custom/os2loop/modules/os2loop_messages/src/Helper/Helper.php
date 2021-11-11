<?php

namespace Drupal\os2loop_messages\Helper;

use Drupal\comment\CommentInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\message\Entity\Message;
use Drupal\node\NodeInterface;
use Drupal\os2loop_settings\Settings;

/**
 * Os2Loop messages helper.
 *
 * Helper class for creating messages.
 */
class Helper extends ControllerBase {
  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   */
  public function __construct(Settings $settings) {
    $this->config = $settings->getConfig('os2loop_subscriptions.settings');
  }

  /**
   * Implements hook_entity_insert().
   *
   * Create message on insert entity.
   */
  public function entityInsert(EntityInterface $entity) {
    $this->createMessage($entity, 'ins');
  }

  /**
   * Implements hook_entity_update().
   *
   * Create message on update entity.
   */
  public function entityUpdate(EntityInterface $entity) {
    $this->createMessage($entity, 'upd');
  }

  /**
   * Create a message.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being to relate to.
   * @param string $operation
   *   The operation performed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createMessage(EntityInterface $entity, string $operation) {
    $template = $this->getMessageTemplate($entity, $operation);
    if (NULL !== $template) {
      $message = Message::create(['template' => $template]);
      if ($entity instanceof NodeInterface) {
        if ($entity->hasField('os2loop_notify_users') && FALSE == $entity->get('os2loop_notify_users')->getValue()[0]['value']) {
          return;
        }
        $message->set('os2loop_message_node_refer', $entity);
        if (isset($entity->revision_log->value)) {
          $message->set('os2loop_revision_message', $entity->revision_log->value);
        }
      }
      elseif ($entity instanceof CommentInterface) {
        $node_storage = $this->entityTypeManager()->getStorage('node');
        $node = $node_storage->load($entity->get('entity_id')->getValue()[0]['target_id']);
        $message->set('os2loop_message_node_refer', $node);
        $message->set('os2loop_message_comment_refer', $entity);
      }
      $message->save();
    }
  }

  /**
   * Map from bundle to message type.
   *
   * @var array
   */
  private const BUNDLE_MESSAGE_TYPES = [
    'os2loop_documents_collection' => 'os2loop_message_collection',
    'os2loop_documents_document' => 'os2loop_message_document',
    'os2loop_post' => 'os2loop_message_post',
    'os2loop_post_comment' => 'os2loop_message_comment',
    'os2loop_question' => 'os2loop_message_question',
    'os2loop_question_answer' => 'os2loop_message_answer',
  ];

  /**
   * Get message template for an entity operation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $operation
   *   The operation ('ins' or 'upd').
   *
   * @return null|string
   *   The message template name if any.
   */
  private function getMessageTemplate(EntityInterface $entity, string $operation) {
    $template = self::BUNDLE_MESSAGE_TYPES[$entity->bundle()] ?? NULL;
    if (NULL !== $template) {
      $template .= '_' . $operation;
    }

    return $template;
  }

  /**
   * Implements hook_form_alter().
   *
   * If subscription is enabled on documents/document collections
   * show notify checkbox on content.
   */
  public function formAlter(&$form, $form_id) {
    // Forms that may contain notify checkbox.
    $contentTypes = [
      'os2loop_documents_document' => [
        'node_os2loop_documents_document_form',
        'node_os2loop_documents_document_edit_form',
      ],
      'os2loop_documents_collection' => [
        'node_os2loop_documents_collection_form',
        'node_os2loop_documents_collection_edit_form',
      ],
    ];
    foreach ($contentTypes as $contentType => $possibleCheckboxForm) {
      if (in_array($form_id, $possibleCheckboxForm)) {
        $form['os2loop_notify_users']['widget']['value']['#default_value'] = 1;
        $nodeSubscriptions = $this->config->get('subscribe_node_types');
        if ($nodeSubscriptions[$contentType] !== $contentType) {
          $form['os2loop_notify_users']['#access'] = FALSE;
        }
      }
    }
  }

}
