/**
 * modplus.js
 * (c) 2010 StatusNet, Inc
 */

$(function() {
    function ModPlus_setup(notice) {
        if ($(notice).find('.remote-profile-options').size()) {
            var $options = $(notice).find('.remote-profile-options');
            $options.prepend($())
            $(notice).find('.author').mouseenter(function(event) {
                $(notice).find('.remote-profile-options').fadeIn();
            });
            $(notice).mouseleave(function(event) {
                $(notice).find('.remote-profile-options').fadeOut();
            });
        }
    }

    $('.notice').each(function() {
        ModPlus_setup(this);
    });
});
