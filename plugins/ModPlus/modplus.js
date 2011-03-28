/**
 * modplus.js
 * (c) 2010 StatusNet, Inc
 */

$(function() {
    // Notice lists...
    $('.notice .author').live('mouseenter', function(e) {
        var notice = $(this).closest('.notice');
        var popup = notice.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeIn();
        }
    });
    $('.notice').live('mouseleave', function(e) {
        var notice = $(this);
        var popup = notice.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeOut();
        }
    });

    // Profile lists...
    $('.profile .avatar').live('mouseenter', function(e) {
        var profile = $(this).closest('.profile');
        var popup = profile.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeIn();
        }
    });
    $('.profile').live('mouseleave', function(e) {
        var profile = $(this);
        var popup = profile.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeOut();
        }
    });

});
