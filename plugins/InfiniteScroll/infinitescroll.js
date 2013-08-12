jQuery(document).ready(function($){
  $('notices_primary').infinitescroll({
    debug: false,
    infiniteScroll  : !infinite_scroll_on_next_only,
    nextSelector    : 'body#public li.nav_next a,'+
                      'body#all li.nav_next a,'+
                      'body#showstream li.nav_next a,'+
                      'body#replies li.nav_next a,'+
                      'body#showfavorites li.nav_next a,'+
                      'body#showgroup li.nav_next a,'+
                      'body#favorited li.nav_next a',
    loadingImg      : ajax_loader_url,
    text            : "<em>Loading the next set of posts...</em>",
    donetext        : "<em>Congratulations, you\'ve reached the end of the Internet.</em>",
    navSelector     : "#pagination",
    contentSelector : "#notices_primary ol.notices",
    itemSelector    : "#notices_primary ol.notices > li"
    },function(){
        // Reply button and attachment magic need to be set up
        // for each new notice.
        // DO NOT run SN.Init.Notices() which will duplicate stuff.
        $(this).find('.notice').each(function() {
            SN.U.NoticeReplyTo($(this));
            SN.U.NoticeWithAttachment($(this));
        });
    });
});
