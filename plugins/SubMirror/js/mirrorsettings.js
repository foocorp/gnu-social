$(function() {
    /**
     * Append 'ajax=1' parameter onto URL.
     */
    function ajaxize(url) {
        if (url.indexOf('?') == '-1') {
            return url + '?ajax=1';
        } else {
            return url + '&ajax=1';
        }
    }

    var addMirror = $('#add-mirror');
    var wizard = $('#add-mirror-wizard');
    if (wizard.length > 0) {
        var list = wizard.find('.provider-list');
        var providers = list.find('.provider-heading');
        providers.click(function(event) {
            console.log(this);
            var targetUrl = $(this).find('a').attr('href');
            if (targetUrl) {
                // Make sure we don't accidentally follow the direct link
                event.preventDefault();

                var node = this;
                function showNew() {
                    var detail = $('<div class="provider-detail" style="display: none"></div>').insertAfter(node);
                    detail.load(ajaxize(targetUrl), function(responseText, testStatus, xhr) {
                        detail.slideDown('fast', function() {
                            detail.find('input[type="text"]').focus();
                        });
                    });
                }

                var old = addMirror.find('.provider-detail');
                if (old.length) {
                    old.slideUp('fast', function() {
                        old.remove();
                        showNew();
                    });
                } else {
                    showNew();
                }
            }
        });
    }
});