<?php

declare(strict_types=1);

namespace Drupal\bkb_comment\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the word entity edit forms.
 */
final class WordForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $excluded = [];
    $new_source = FALSE;

    $message_args = ['%label' => $this->entity->toLink()->toString()];
    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New word %label has been created.', $message_args));
        $this->logger('bkb_comment')->notice('New word %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The word %label has been updated.', $message_args));
        $this->logger('bkb_comment')->notice('The word %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    foreach ($form['comments']['widget'] as $i => $comment) {
      if (is_numeric($i)) {
        foreach ($comment['inline_entity_form']['sources']['widget'] as $j => $source) {
          if (is_numeric($j)) {
            $default = $source['inline_entity_form']['source']['widget'][0]['target_id']['#default_value'];

            if (is_null($default)) {
              $new_source = TRUE;
            }
            else {
              $excluded[] = $default->id();
            }
          }
        }
      }
    }

    if ($new_source) {
      $form_state->setRedirect(
        'entity.source.data.edit',
        ['id' => $this->entity->id()],
        ['query' => ['excluded' => implode(',', $excluded)]]
      );
    }

    return $result;
  }

}
