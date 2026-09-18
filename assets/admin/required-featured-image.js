(function ($, wp) {
    'use strict';

    var config = window.hwsRequiredFeaturedImage || {};
    var lastValid = null;

    function editorStore() {
        return wp && wp.data && wp.data.select ? wp.data.select('core/editor') : null;
    }

    function featuredImage() {
        var store = editorStore();
        var mediaId = store && store.getEditedPostAttribute
            ? Number(store.getEditedPostAttribute('featured_media') || 0)
            : Number($('#_thumbnail_id').val() || 0);
        var image = document.querySelector('.editor-post-featured-image img, .editor-post-featured-image__container img, #postimagediv img');

        return { id: mediaId, image: image };
    }

    function isValid() {
        var featured = featuredImage();
        if (!featured.id) {
            return false;
        }

        var minWidth = Number(config.minWidth || 0);
        var minHeight = Number(config.minHeight || 0);
        if (!minWidth && !minHeight) {
            return true;
        }

        if (!featured.image) {
            return false;
        }

        var width = Number(featured.image.naturalWidth || featured.image.width || 0);
        var height = Number(featured.image.naturalHeight || featured.image.height || 0);

        return width >= minWidth && height >= minHeight;
    }

    function showNotice() {
        if (wp && wp.data && wp.data.dispatch) {
            var notices = wp.data.dispatch('core/notices');
            if (notices && notices.createErrorNotice) {
                notices.createErrorNotice(config.message || 'Add a featured image before publishing.', {
                    id: config.noticeId || 'hws-required-featured-image',
                    isDismissible: false
                });
                return;
            }
        }

        if (!document.getElementById('hws-required-featured-image-notice')) {
            $('#post').before('<div id="hws-required-featured-image-notice" class="notice notice-error"><p>' +
                $('<div>').text(config.message || 'Add a featured image before publishing.').html() + '</p></div>');
        }
    }

    function removeNotice() {
        if (wp && wp.data && wp.data.dispatch) {
            var notices = wp.data.dispatch('core/notices');
            if (notices && notices.removeNotice) {
                notices.removeNotice(config.noticeId || 'hws-required-featured-image');
            }
        }
        $('#hws-required-featured-image-notice').remove();
    }

    function setPublishDisabled(shouldDisable) {
        $('#publish').prop('disabled', shouldDisable);
        $('.editor-post-publish-panel__toggle, .editor-post-publish-button, .editor-post-publish-button__button')
            .prop('disabled', shouldDisable)
            .attr('aria-disabled', shouldDisable ? 'true' : 'false');
    }

    function refresh() {
        var valid = isValid();
        if (valid === lastValid) {
            return;
        }
        lastValid = valid;
        setPublishDisabled(!valid);
        if (valid) {
            removeNotice();
        } else {
            showNotice();
        }
    }

    $(refresh);
    $(document).on('change load', '#_thumbnail_id, #postimagediv img, .editor-post-featured-image img, .editor-post-featured-image__container img', refresh);

    if (wp && wp.data && wp.data.subscribe) {
        wp.data.subscribe(refresh);
    }

    if (window.MutationObserver) {
        new MutationObserver(refresh).observe(document.documentElement, { childList: true, subtree: true, attributes: true });
    }
}(window.jQuery, window.wp));
