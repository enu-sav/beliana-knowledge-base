<?php

namespace Drupal\bkb_comment\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for custom Comment API endpoints.
 */
class CommentApiController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a CommentApiController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Find comments by full URL.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with comments.
   */
  public function findByUrl(Request $request) {
    $url = $request->query->get('url');

    if (empty($url)) {
      return new JsonResponse([
        'errors' => [
          [
            'status' => '400',
            'title' => 'Bad Request',
            'detail' => 'Missing required parameter: url',
          ],
        ],
      ], 400);
    }

    $config = $this->configFactory->get('bkb_base.settings');
    $rs_url = $config->get('rs_url');
    $webrs_url = $config->get('webrs_url');

    // Parse the URL to extract path and determine web_type
    $parsed_url = parse_url($url);
    $path = NULL;
    $web_type = NULL;

    // Check if it's a full URL
    if (isset($parsed_url['scheme']) && isset($parsed_url['host'])) {
      // Extract path from full URL
      $path = ($parsed_url['path'] ?? '') .
        (isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '') .
        (isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '');

      // Determine web_type from URL
      if ($rs_url && strpos($url, $rs_url) === 0) {
        $web_type = 'rs';
      }
      elseif ($webrs_url && strpos($url, $webrs_url) === 0) {
        $web_type = 'webrs';
      }
    }
    else {
      // Assume it's a path
      $path = $url;
    }

    // Find Word entities matching the criteria
    $word_storage = $this->entityTypeManager->getStorage('source_comment_node');
    $query = $word_storage->getQuery()
      ->accessCheck(FALSE);

    if ($path) {
      $query->condition('url', $path);
    }

    if ($web_type) {
      $query->condition('web_type', $web_type);
    }

    $word_ids = $query->execute();

    if (empty($word_ids)) {
      return new JsonResponse([
        'data' => [],
        'meta' => [
          'count' => 0,
        ],
      ]);
    }

    // Find all comments that belong to these words
    $comment_storage = $this->entityTypeManager->getStorage('source_comment');
    $words = $word_storage->loadMultiple($word_ids);

    $comment_ids = [];
    foreach ($words as $word) {
      $comments = $word->get('comments')->referencedEntities();
      foreach ($comments as $comment) {
        $comment_ids[] = $comment->id();
      }
    }

    if (empty($comment_ids)) {
      return new JsonResponse([
        'data' => [],
        'meta' => [
          'count' => 0,
        ],
      ]);
    }

    $comments = $comment_storage->loadMultiple($comment_ids);

    // Build JSON:API compatible response
    $data = [];
    foreach ($comments as $comment) {
      // Get the full URL for this comment
      $words = array_filter($words, function($w) use ($comment) {
        foreach ($w->get('comments')->referencedEntities() as $c) {
          if ($c->id() === $comment->id()) {
            return TRUE;
          }
        }
        return FALSE;
      });

      $word = reset($words);

      $comment_url = '';
      if ($word) {
        $url_value = $word->get('url')->value;
        $word_web_type = $word->get('web_type')->value;

        if (!empty($word_web_type) && strpos($url_value, '/') === 0) {
          $base_url = $config->get($word_web_type . '_url');
          if ($base_url) {
            $comment_url = rtrim($base_url, '/') . $url_value;
          }
        }
        else {
          $comment_url = $url_value;
        }
      }

      $data[] = [
        'type' => 'comment',
        'id' => (string) $comment->id(),
        'attributes' => [
          'comment' => $comment->get('comment')->value,
          'url' => $comment_url,
          'parent' => $word ? (int) $word->id() : NULL,
        ],
        'relationships' => [
          'sources' => [
            'data' => array_map(function($source) {
              return [
                'type' => 'source_group',
                'id' => (string) $source->id(),
              ];
            }, $comment->get('sources')->referencedEntities()),
          ],
        ],
      ];
    }

    return new JsonResponse([
      'data' => $data,
      'meta' => [
        'count' => count($data),
      ],
    ]);
  }

}
