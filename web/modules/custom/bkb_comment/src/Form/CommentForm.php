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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    if (isset($form['revision'])) {
      $form['revision']['#access'] = FALSE;
    }

    return $form;
  }

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
        /** @var \Drupal\bkb_base\Helper $helper */
        $helper = \Drupal::service('bkb_base.helper');

        if ($word = $helper->getWordFromRequest($this->getRequest(), TRUE)) {
          $comments = $word->get('comments')->referencedEntities();
          $values = [];
          $excluded = [];

          foreach ($comments as $comment) {
            $values[] = ['target_id' => $comment->id()];
            $groups = $comment->get('sources')->referencedEntities();

            // Collect excluded sources from other word comments
            foreach ($groups as $group) {
              $excluded = array_merge($excluded, array_map(function($source) {
                return $source['target_id'];
              }, $group->get('source')->getValue()));
            }
          }

          // Set new comment as word reference
          $values[] = [
            'target_id' => $this->entity->id(),
          ];

          $word->set('comments', $values);
          $word->save();

          $form_state->setRedirectUrl($word->toUrl());

          // Exclude existing sources from current comment
          $excluded_sources = $helper->isSourceNew($form_state->getValue('sources'));

          if ($excluded_sources !== FALSE) {
            $excluded = array_unique(array_merge($excluded, $excluded_sources));

            $form_state->setRedirect(
              'entity.source.data.edit',
              ['id' => $word->id()],
              ['query' => ['excluded' => implode(',', $excluded)]]
            );
          }
        }

        $this->messenger()
          ->addStatus($this->t('New comment %label has been created.', $message_args));
        $this->logger('bkb_comment')
          ->notice('New comment %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()
          ->addStatus($this->t('The comment %label has been updated.', $message_args));
        $this->logger('bkb_comment')
          ->notice('The comment %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    return $result;
  }

}
