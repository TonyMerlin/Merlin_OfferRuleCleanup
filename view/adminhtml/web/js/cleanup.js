define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function ($, confirm, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $button = $root.find('[data-role="cleanup-offer-rules"]'),
            $count = $root.find('[data-role="expired-count"]'),
            $progress = $root.find('[data-role="cleanup-progress"]'),
            deleted = 0,
            failed = [];

        function finish(remaining) {
            $count.text($t('Make an Offer rules ended on or before %1: %2')
                .replace('%1', config.cutoffDate).replace('%2', remaining));
            $progress.text($t('Finished: %1 rules deleted.').replace('%1', deleted));
            if (failed.length) {
                $progress.append(document.createTextNode(' ' +
                    $t('Rules that could not be deleted: %1.').replace('%1', failed.join(', '))));
            }
            $button.prop('disabled', false);
            $button.hide();
        }

        function runBatch(afterId) {
            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: config.formKey,
                    after_id: afterId
                }
            }).done(function (response) {
                if (!response || response.error || !Array.isArray(response.failed_ids) ||
                    typeof response.last_id !== 'number' || typeof response.deleted !== 'number') {
                    $progress.text(response && response.error || $t('Cleanup stopped because the server returned an invalid response.'));
                    $button.prop('disabled', false);
                    return;
                }

                deleted += response.deleted;
                failed = failed.concat(response.failed_ids);
                $progress.text($t('Deleted %1 rules so far.').replace('%1', deleted));
                if (response.done) {
                    finish(response.remaining);
                } else {
                    runBatch(response.last_id);
                }
            }).fail(function () {
                $progress.text($t('Cleanup stopped after %1 deletions. Refresh this page to continue with the remaining rules.').replace('%1', deleted));
                $button.prop('disabled', false);
            });
        }

        $button.on('click', function () {
            confirm({
                title: $t('Delete older Make an Offer rules?'),
                content: $t('This will permanently delete Make an Offer rules that ended at least six months ago and their coupons.'),
                actions: {
                    confirm: function () {
                        deleted = 0;
                        failed = [];
                        $button.prop('disabled', true);
                        $progress.text($t('Deleting expired rules...'));
                        runBatch(0);
                    }
                }
            });
        });
    };
});
