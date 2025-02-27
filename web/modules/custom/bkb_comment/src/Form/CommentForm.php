<?php

declare(strict_types=1);

namespace Drupal\bkb_comment\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the comment entity edit forms.
 */
final class CommentForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $message_args = ['%label' => $this->entity->toLink()->toString()];
    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];
    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New comment11 %label has been created.', $message_args));
        $this->logger('bkb_comment')->notice('New comment %label has been created.', $logger_args);
        $form_state->setRedirect(
          'entity.source.data.edit',
          ['id' => $this->entity->id()]
        );
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The comment22 %label has been updated.', $message_args));
        $this->logger('bkb_comment')->notice('The comment %label has been updated.', $logger_args);
        $form_state->setRedirect(
          'entity.source.data.edit',
          ['id' => $this->entity->id()]
        );
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    return $result;
  }

}
