<?php

/**
 * @file
 * Provide views data for bkb_comment module.
 */

/**
 * Implements hook_views_data_alter().
 */
function bkb_comment_views_data_alter(array &$data) {
  $data['source_comment_node']['word_url_link'] = [
    'title' => t('URL hesla (s linkovaním podla typu webu)'),
    'field' => [
      'id' => 'word_url_link',
    ],
  ];
}
