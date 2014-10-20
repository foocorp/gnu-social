
// notices
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

        // moving the loaded notices out of their container
		$('#infscr-loading').remove();	
		var ids_to_append = Array(); var i=0;
		$.each($('.infscr-pages').children('.notice'),function(){
			
			// remove dupes
			if($('.threaded-notices > #' + $(this).attr('id')).length > 0) {
				$(this).remove();
				}
			
			// keep new unique notices
			else {
				ids_to_append[i] = $(this).attr('id');				
				i++;
				}
			});
		var loaded_html = $('.infscr-pages').html();
		$('.infscr-pages').remove();

		// no results
		if(loaded_html == '') {	
			}
		// append
		else {
			$('#notices_primary ol.notices').append(loaded_html);
			}
        
    });
});


// users
jQuery(document).ready(function($){
  $('profile_list').infinitescroll({
    debug: false,
    infiniteScroll  : !infinite_scroll_on_next_only,
    nextSelector    : 'body#subscribers li.nav_next a, body#subscriptions li.nav_next a',
    loadingImg      : ajax_loader_url,
    text            : "<em>Loading the next set of users...</em>",
    donetext        : "<em>Congratulations, you\'ve reached the end of the Internet.</em>",
    navSelector     : "#pagination",
    contentSelector : "#content_inner ul.profile_list",
    itemSelector    : "#content_inner ul.profile_list > li"
    },function(){
        // Reply button and attachment magic need to be set up
        // for each new notice.
        // DO NOT run SN.Init.Notices() which will duplicate stuff.
        $(this).find('.profile').each(function() {
            SN.U.NoticeReplyTo($(this));
            SN.U.NoticeWithAttachment($(this));
        });

        // moving the loaded notices out of their container
		$('#infscr-loading').remove();	
		var ids_to_append = Array(); var i=0;
		$.each($('.infscr-pages').children('.profile'),function(){
			
			// remove dupes
			if($('.profile_list > #' + $(this).attr('id')).length > 0) {
				$(this).remove();
				}
			
			// keep new unique notices
			else {
				ids_to_append[i] = $(this).attr('id');				
				i++;
				}
			});
		var loaded_html = $('.infscr-pages').html();
		$('.infscr-pages').remove();

		// no results
		if(loaded_html == '') {	
			}
		// append
		else {
			$('#content_inner ul.profile_list').append(loaded_html);
			}
        
    });
});


// user directory
jQuery(document).ready(function($){
  $('profile_list').infinitescroll({
    debug: false,
    infiniteScroll  : !infinite_scroll_on_next_only,
    nextSelector    : 'body#userdirectory li.nav_next a',
    loadingImg      : ajax_loader_url,
    text            : "<em>Loading the next set of users...</em>",
    donetext        : "<em>Congratulations, you\'ve reached the end of the Internet.</em>",
    navSelector     : "#pagination",
    contentSelector : "#profile_directory table.profile_list tbody",
    itemSelector    : "#profile_directory table.profile_list tbody tr"
    },function(){
        // Reply button and attachment magic need to be set up
        // for each new notice.
        // DO NOT run SN.Init.Notices() which will duplicate stuff.
        $(this).find('.profile').each(function() {
            SN.U.NoticeReplyTo($(this));
            SN.U.NoticeWithAttachment($(this));
        });

        // moving the loaded notices out of their container
		$('#infscr-loading').remove();	
		var ids_to_append = Array(); var i=0;
		$.each($('.infscr-pages').children('.profile'),function(){
			
			// remove dupes
			if($('.profile_list > #' + $(this).attr('id')).length > 0) {
				$(this).remove();
				}
			
			// keep new unique notices
			else {
				ids_to_append[i] = $(this).attr('id');				
				i++;
				}
			});
		var loaded_html = $('.infscr-pages').html();
		$('.infscr-pages').remove();

		// no results
		if(loaded_html == '') {	
			}
		// append
		else {
			$('#profile_directory table.profile_list tbody').append(loaded_html);
			}
        
    });
});