<?php

declare(strict_types=1);

namespace Drupal\bkb_comment\Controller;

use Drupal\Core\Entity\Controller\VersionHistoryController;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Link;

/**
 * Provides a controller showing revision history for source_comment entity.
 */
final class CommentVersionHistoryController extends VersionHistoryController {

  /**
   * {@inheritdoc}
   */
  protected function revisionOverview(RevisionableInterface $entity): array {
    $build['entity_revisions_table'] = [
      '#theme' => 'table',
      '#header' => [
        'revision' => ['data' => $this->t('Revision')],
        'date' => ['data' => $this->t('Date')],
        'author' => ['data' => $this->t('Author')],
        'operations' => ['data' => $this->t('Operations')],
      ],
    ];

    foreach ($this->loadRevisions($entity) as $revision) {
      $build['entity_revisions_table']['#rows'][$revision->getRevisionId()] = $this->buildRow($revision);
    }

    $build['pager'] = ['#type' => 'pager'];

    $cacheableMetadata = new \Drupal\Core\Cache\CacheableMetadata();
    $cacheableMetadata
      ->addCacheableDependency($entity)
      ->addCacheContexts(['languages:language_content'])
      ->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRow(RevisionableInterface $revision): array {
    $row = [];
    $rowAttributes = [];

    $linkText = $revision->label();

    $url = $revision->hasLinkTemplate('revision') ? $revision->toUrl('revision') : NULL;
    $row['revision']['data'] = $url && $url->access()
      ? Link::fromTextAndUrl($linkText, $url)->toString()
      : (string) $linkText;

    // Date column
    if ($revision instanceof RevisionLogInterface) {
      $row['date']['data'] = $this->dateFormatter->format(
        $revision->getRevisionCreationTime(),
        'short'
      );
    }
    else {
      $row['date']['data'] = '';
    }

    // Author column
    if ($revision instanceof RevisionLogInterface) {
      $row['author']['data'] = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];
    }
    else {
      $row['author']['data'] = '';
    }

    // Operations column
    $row['operations']['data'] = [];

    // Revision status
    if ($revision->isDefaultRevision()) {
      $rowAttributes['class'][] = 'revision-current';
      $row['operations']['data']['status']['#markup'] = $this->t('<em>Current revision</em>');
    }

    // Operation links
    $links = $this->getOperationLinks($revision);
    if (count($links) > 0) {
      $row['operations']['data']['operations'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    return ['data' => $row] + $rowAttributes;
  }

  /**
   * Get operation links for a revision.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The revision entity.
   *
   * @return array
   *   Array of operation links.
   */
  protected function getOperationLinks(RevisionableInterface $revision): array {
    $links = [];

    if (!$revision->isDefaultRevision()) {
      $revertLink = $this->buildRevertRevisionLink($revision);
      if ($revertLink) {
        $links['revert'] = $revertLink;
      }

      $deleteLink = $this->buildDeleteRevisionLink($revision);
      if ($deleteLink) {
        $links['delete'] = $deleteLink;
      }
    }

    return $links;
  }

}
