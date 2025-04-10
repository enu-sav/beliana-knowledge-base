(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.bkbCommentOperations = {
    attach: function (context, settings) {
      let self = this;

      $(once('operations-process', 'form.source-comment-node-form', context)).each(function () {
        let $form = $(this);
        let $addMoreButton = $form.find('input[name="comments_add_more"]');

        const query = self.parseQuery();

        // Trigger button click
        if (query.hasOwnProperty('operation')) {
          if (query.operation === 'add-new-comment') {
            $addMoreButton.trigger('mousedown');
          }
        }

        // Set focus on new input
        $(document).ajaxComplete(function (event, xhr, settings) {
          const $newContent = $form.find('.ajax-new-content');
          const $firstInput = $newContent.find('input:visible, textarea:visible, select:visible').first();

          if ($firstInput.length) {
            setTimeout(() => {
              $firstInput.trigger('focus');
            }, 50);
          }
        });
      });
    },
    parseQuery: function () {
      let query = {};

      window.location.search.replace('?', '').split('&').forEach(function (item) {
        let param = item.split('=');
        query[param[0]] = param[1];
      });

      return query;
    },
  };

})(jQuery, Drupal, once);
