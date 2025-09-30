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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $query = \Drupal::request()->query->all();

    // Prepopulate values from url query
    foreach ($query as $key => $value) {
      $property = $key == 'url' ? 'uri' : 'value';
      if (isset($form[$key]) && empty($form[$key]['widget'][0][$property]['#default_value'])) {
        $form[$key]['widget'][0][$property]['#default_value'] = $value;
      }
    }

    $form['label']['widget'][0]['value']['#disabled'] = TRUE;
    $form['url']['widget'][0]['uri']['#disabled'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $form_state->setRedirect('view.comments_overview.page');

    $message_args = ['%label' => $this->entity->toLink()->toString()];
    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => $this->entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()
          ->addStatus($this->t('New word %label has been created.', $message_args));
        $this->logger('bkb_comment')
          ->notice('New word %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()
          ->addStatus($this->t('The word %label has been updated.', $message_args));
        $this->logger('bkb_comment')
          ->notice('The word %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    /** @var \Drupal\bkb_base\Helper $helper */
    $helper = \Drupal::service('bkb_base.helper');
    $excluded = [];
    $values = $form_state->getValues();

    foreach ($values['comments'] as $i => $comment) {
      if (is_numeric($i)) {
        $excluded_sources = $helper->isSourceNew($comment['inline_entity_form']['sources']);
        if ($excluded_sources !== FALSE) {
          $excluded = array_unique(array_merge($excluded, $excluded_sources));
        }
      }
    }

    if (!empty($excluded)) {
      $form_state->setRedirect(
        'entity.source.data.edit',
        ['id' => $this->entity->id()],
        ['query' => ['excluded' => implode(',', $excluded)]]
      );
    }

    return $result;
  }

}
