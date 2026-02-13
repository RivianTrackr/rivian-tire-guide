/* Admin scripts for Rivian Tire Guide */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Image preview on URL change.
        var $imageInput = $('#image');
        if ($imageInput.length) {
            $imageInput.on('input', function() {
                var url = $(this).val().trim();
                var $preview = $('#image-preview');
                var $container = $('#image-preview-container');

                if (url && url.match(/^https?:\/\/.+\.(jpg|jpeg|png|webp|gif)/i)) {
                    if ($preview.length) {
                        $preview.attr('src', url);
                        if ($container.length) {
                            $container.show();
                        }
                    }
                } else {
                    if ($container.length) {
                        $container.hide();
                    }
                }
            });
        }

        // Select all checkboxes.
        $('#cb-select-all').on('change', function() {
            $('input[name="tire_ids[]"]').prop('checked', this.checked);
        });

        // Confirm bulk delete.
        $('form').on('submit', function() {
            var action = $('select[name="rtg_bulk_action"]').val();
            if (action === 'delete') {
                var checked = $('input[name="tire_ids[]"]:checked').length;
                if (checked === 0) {
                    alert('No tires selected.');
                    return false;
                }
                return confirm('Delete ' + checked + ' tire(s)? This cannot be undone.');
            }
        });

        // Dismiss custom notices.
        $('.rtg-notice-dismiss').on('click', function() {
            $(this).closest('.rtg-notice').fadeOut(200, function() {
                $(this).remove();
            });
        });
    });
})(jQuery);
