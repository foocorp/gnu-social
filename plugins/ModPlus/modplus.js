/**
 * modplus.js
 * (c) 2010 StatusNet, Inc
 */

$(function() {
    // Notice lists...
    $(document).on('mouseenter', '.notice .author', function(e) {
        var notice = $(this).closest('.notice');
        var popup = notice.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeIn();
        }
    });
    $(document).on('mouseleave', '.notice', function(e) {
        var notice = $(this);
        var popup = notice.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeOut();
        }
    });

    // Profile lists...
    $(document).on('mouseenter', '.profile .avatar', function(e) {
        var profile = $(this).closest('.profile');
        var popup = profile.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeIn();
        }
    });
    $(document).on('mouseleave', '.profile', function(e) {
        var profile = $(this);
        var popup = profile.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeOut();
        }
    });

});
