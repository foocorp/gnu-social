/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2009-2011, StatusNet, Inc.
 *
 * Add a notice encoded as JSON into the current timeline
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

/**
 * This is the UI portion of the Realtime plugin base class, handling
 * queueing up and displaying of notices that have been received through
 * other code in one of the subclassed plugin implementations such as
 * Meteor or Orbited.
 *
 * Notices are passed in as JSON objects formatted per the Twitter-compatible
 * API.
 *
 * @todo Currently we duplicate a lot of formatting and layout code from
 *       the PHP side of StatusNet, which makes it very difficult to maintain
 *       this package. Internationalization as well as newer features such
 *       as location data, customized source links for OStatus profiles,
 *       and image thumbnails are not yet supported in Realtime yet because
 *       they have not been implemented here.
 */
RealtimeUpdate = {
     _userid: 0,
     _showurl: '',
     _keepaliveurl: '',
     _closeurl: '',
     _updatecounter: 0,
     _maxnotices: 50,
     _windowhasfocus: true,
     _documenttitle: '',
     _paused:false,
     _queuedNotices:[],

     /**
      * Initialize the Realtime plugin UI on a page with a timeline view.
      *
      * This function is called from a JS fragment inserted by the PHP side
      * of the Realtime plugin, and provides us with base information
      * needed to build a near-replica of StatusNet's NoticeListItem output.
      *
      * Once the UI is initialized, a plugin subclass will need to actually
      * feed data into the RealtimeUpdate object!
      *
      * @param {int} userid: local profile ID of the currently logged-in user
      * @param {String} showurl: URL for shownotice action, used when fetching formatting notices.
      *                            This URL contains a stub value of 0000000000 which will be replaced with the notice ID.
      *
      * @access public
      */
     init: function(userid, showurl)
     {
        RealtimeUpdate._userid = userid;
        RealtimeUpdate._showurl = showurl;

        RealtimeUpdate._documenttitle = document.title;

        $(window).bind('focus', function() {
          RealtimeUpdate._windowhasfocus = true;

          // Clear the counter on the window title when we focus in.
          RealtimeUpdate._updatecounter = 0;
          RealtimeUpdate.removeWindowCounter();
        });

        $(window).bind('blur', function() {
          $('#notices_primary .notice').removeClass('mark-top');

          $('#notices_primary .notice:first').addClass('mark-top');

          // While we're in the background, received messages will increment
          // a counter that we put on the window title. This will cause some
          // browsers to also flash or mark the tab or window title bar until
          // you seek attention (eg Firefox 4 pinned app tabs).
          RealtimeUpdate._windowhasfocus = false;

          return false;
        });
     },

     /**
      * Accept a notice in a Twitter-API JSON style and either show it
      * or queue it up, depending on whether the realtime display is
      * active.
      *
      * The meat of a Realtime plugin subclass is to provide a substrate
      * transport to receive data and shove it into this function. :)
      *
      * Note that the JSON data is extended from the standard API return
      * with additional fields added by RealtimePlugin's PHP code.
      *
      * @param {Object} data: extended JSON API-formatted notice
      *
      * @access public
      */
     receive: function(data)
     {
          if (RealtimeUpdate.isNoticeVisible(data.id)) {
              // Probably posted by the user in this window, and so already
              // shown by the AJAX form handler. Ignore it.
              return;
          }
          if (RealtimeUpdate._paused === false) {
              RealtimeUpdate.purgeLastNoticeItem();

              RealtimeUpdate.insertNoticeItem(data);
          }
          else {
              RealtimeUpdate._queuedNotices.push(data);

              RealtimeUpdate.updateQueuedCounter();
          }

          RealtimeUpdate.updateWindowCounter();
     },

     /**
      * Add a visible representation of the given notice at the top of
      * the current timeline.
      *
      * If the notice is already in the timeline, nothing will be added.
      *
      * @param {Object} data: extended JSON API-formatted notice
      *
      * @fixme while core UI JS code is used to activate the AJAX UI controls,
      *        the actual production of HTML (in makeNoticeItem and its subs)
      *        duplicates core code without plugin hook points or i18n support.
      *
      * @access private
      */
     insertNoticeItem: function(data) {
        // Don't add it if it already exists
        if (RealtimeUpdate.isNoticeVisible(data.id)) {
            return;
        }

        RealtimeUpdate.makeNoticeItem(data, function(noticeItem) {
            // Check again in case it got shown while we were waiting for data...
            if (RealtimeUpdate.isNoticeVisible(data.id)) {
                return;
            }
            var noticeItemID = $(noticeItem).attr('id');

            var list = $("#notices_primary .notices:first")
            var prepend = true;

            var threaded = list.hasClass('threaded-notices');
            if (threaded && data.in_reply_to_status_id) {
                // aho!
                var parent = $('#notice-' + data.in_reply_to_status_id);
                if (parent.length == 0) {
                    // @todo fetch the original, insert it, and finish the rest
                } else {
                    // Check the parent notice to make sure it's not a reply itself.
                    // If so, use it's parent as the parent.
                    var parentList = parent.closest('.notices');
                    if (parentList.hasClass('threaded-replies')) {
                        parent = parentList.closest('.notice');
                    }
                    list = parent.find('.threaded-replies');
                    if (list.length == 0) {
                        list = $('<ul class="notices threaded-replies xoxo"></ul>');
                        parent.append(list);
                        SN.U.NoticeInlineReplyPlaceholder(parent);
                    }
                    prepend = false;
                }
            }

            var newNotice = $(noticeItem);
            if (prepend) {
                list.prepend(newNotice);
            } else {
                var placeholder = list.find('li.notice-reply-placeholder')
                if (placeholder.length > 0) {
                    newNotice.insertBefore(placeholder)
                } else {
                    newNotice.appendTo(list);
                }
            }
            newNotice.css({display:"none"}).fadeIn(1000);

            SN.U.NoticeReplyTo($('#'+noticeItemID));
            SN.U.NoticeWithAttachment($('#'+noticeItemID));
        });
     },

     /**
      * Check if the given notice is visible in the timeline currently.
      * Used to avoid duplicate processing of notices that have been
      * displayed by other means.
      *
      * @param {number} id: notice ID to check
      *
      * @return boolean
      *
      * @access private
      */
     isNoticeVisible: function(id) {
        return ($("#notice-"+id).length > 0);
     },

     /**
      * Trims a notice off the end of the timeline if we have more than the
      * maximum number of notices visible.
      *
      * @access private
      */
     purgeLastNoticeItem: function() {
        if ($('#notices_primary .notice').length > RealtimeUpdate._maxnotices) {
            $("#notices_primary .notice:last").remove();
        }
     },

     /**
      * If the window/tab is in background, increment the counter of newly
      * received notices and append it onto the window title.
      *
      * Has no effect if the window is in foreground.
      *
      * @access private
      */
     updateWindowCounter: function() {
          if (RealtimeUpdate._windowhasfocus === false) {
              RealtimeUpdate._updatecounter += 1;
              document.title = '('+RealtimeUpdate._updatecounter+') ' + RealtimeUpdate._documenttitle;
          }
     },

     /**
      * Clear the background update counter from the window title.
      *
      * @access private
      *
      * @fixme could interfere with anything else trying similar tricks
      */
     removeWindowCounter: function() {
          document.title = RealtimeUpdate._documenttitle;
     },

     /**
      * Builds a notice HTML block from JSON API-style data;
      * loads data from server, so runs async.
      *
      * @param {Object} data: extended JSON API-formatted notice
      * @param {function} callback: function(DOMNode) to receive new code
      *
      * @access private
      */
     makeNoticeItem: function(data, callback)
     {
         var url = RealtimeUpdate._showurl.replace('0000000000', data.id);
         $.get(url, {ajax: 1}, function(data, textStatus, xhr) {
             var notice = $('li.notice:first', data);
             if (notice.length) {
                 var node = document._importNode(notice[0], true);
                 callback(node);
             }
         });
     },

     /**
      * Creates a favorite button.
      *
      * @param {number} id: notice ID to work with
      * @param {String} session_key: session token for form CSRF protection
      * @return {String} HTML fragment
      *
      * @fixme this replicates core StatusNet code, making maintenance harder
      * @fixme sloppy HTML building (raw concat without escaping)
      * @fixme no i18n support
      *
      * @access private
      */
     makeFavoriteForm: function(id, session_key)
     {
          var ff;

          ff = "<form id=\"favor-"+id+"\" class=\"form_favor\" method=\"post\" action=\""+RealtimeUpdate._favorurl+"\">"+
                "<fieldset>"+
               "<legend>Favor this notice</legend>"+
               "<input name=\"token\" type=\"hidden\" id=\"token-"+id+"\" value=\""+session_key+"\"/>"+
               "<input name=\"notice\" type=\"hidden\" id=\"notice-n"+id+"\" value=\""+id+"\"/>"+
               "<input type=\"submit\" id=\"favor-submit-"+id+"\" name=\"favor-submit-"+id+"\" class=\"submit\" value=\"Favor\" title=\"Favor this notice\"/>"+
                "</fieldset>"+
               "</form>";
          return ff;
     },

     /**
      * Creates a reply button.
      *
      * @param {number} id: notice ID to work with
      * @param {String} nickname: nick of the user to whom we are replying
      * @return {String} HTML fragment
      *
      * @fixme this replicates core StatusNet code, making maintenance harder
      * @fixme sloppy HTML building (raw concat without escaping)
      * @fixme no i18n support
      *
      * @access private
      */
     makeReplyLink: function(id, nickname)
     {
          var rl;
          rl = "<a class=\"notice_reply\" href=\""+RealtimeUpdate._replyurl+"?replyto="+nickname+"\" title=\"Reply to this notice\">Reply <span class=\"notice_id\">"+id+"</span></a>";
          return rl;
     },

     /**
      * Creates a repeat button.
      *
      * @param {number} id: notice ID to work with
      * @param {String} session_key: session token for form CSRF protection
      * @return {String} HTML fragment
      *
      * @fixme this replicates core StatusNet code, making maintenance harder
      * @fixme sloppy HTML building (raw concat without escaping)
      * @fixme no i18n support
      *
      * @access private
      */
     makeRepeatForm: function(id, session_key)
     {
          var rf;
          rf = "<form id=\"repeat-"+id+"\" class=\"form_repeat\" method=\"post\" action=\""+RealtimeUpdate._repeaturl+"\">"+
               "<fieldset>"+
               "<legend>Repeat this notice?</legend>"+
               "<input name=\"token\" type=\"hidden\" id=\"token-"+id+"\" value=\""+session_key+"\"/>"+
               "<input name=\"notice\" type=\"hidden\" id=\"notice-"+id+"\" value=\""+id+"\"/>"+
               "<input type=\"submit\" id=\"repeat-submit-"+id+"\" name=\"repeat-submit-"+id+"\" class=\"submit\" value=\"Yes\" title=\"Repeat this notice\"/>"+
               "</fieldset>"+
               "</form>";

          return rf;
     },

     /**
      * Creates a delete button.
      *
      * @param {number} id: notice ID to create a delete link for
      * @return {String} HTML fragment
      *
      * @fixme this replicates core StatusNet code, making maintenance harder
      * @fixme sloppy HTML building (raw concat without escaping)
      * @fixme no i18n support
      *
      * @access private
      */
     makeDeleteLink: function(id)
     {
          var dl, delurl;
          delurl = RealtimeUpdate._deleteurl.replace("0000000000", id);

          dl = "<a class=\"notice_delete\" href=\""+delurl+"\" title=\"Delete this notice\">Delete</a>";

          return dl;
     },

     /**
      * Adds a control widget at the top of the timeline view, containing
      * pause/play and popup buttons.
      *
      * @param {String} url: full URL to the popup window variant of this timeline page
      * @param {String} timeline: string key for the timeline (eg 'public' or 'evan-all')
      * @param {String} path: URL to the base directory containing the Realtime plugin,
      *                       used to fetch resources if needed.
      *
      * @todo timeline and path parameters are unused and probably should be removed.
      *
      * @access private
      */
    initActions: function(url, timeline, path, keepaliveurl, closeurl)
     {
        $('#notices_primary').prepend('<ul id="realtime_actions"><li id="realtime_playpause"></li><li id="realtime_timeline"></li></ul>');

        RealtimeUpdate._pluginPath = path;
        RealtimeUpdate._keepaliveurl = keepaliveurl;
        RealtimeUpdate._closeurl = closeurl;


	 // On unload, let the server know we're no longer listening
         $(window).unload(function() {
            $.ajax({
                type: 'POST',
                url: RealtimeUpdate._closeurl});
	 });

	setInterval(function() {
            $.ajax({
                type: 'POST',
                url: RealtimeUpdate._keepaliveurl});

	}, 15 * 60 * 1000 ); // every 15 min; timeout in 30 min

        RealtimeUpdate.initPlayPause();
        RealtimeUpdate.initAddPopup(url, timeline, RealtimeUpdate._pluginPath);
     },

     /**
      * Initialize the state of the play/pause controls.
      *
      * If the browser supports the localStorage interface, we'll attempt
      * to retrieve a pause state from there; otherwise we default to paused.
      *
      * @access private
      */
     initPlayPause: function()
     {
        if (typeof(localStorage) == 'undefined') {
            RealtimeUpdate.showPause();
        }
        else {
            if (localStorage.getItem('RealtimeUpdate_paused') === 'true') {
                RealtimeUpdate.showPlay();
            }
            else {
                RealtimeUpdate.showPause();
            }
        }
     },

     /**
      * Switch the realtime UI into paused state.
      * Uses SN.msg i18n system for the button label and tooltip.
      *
      * State will be saved and re-used next time if the browser supports
      * the localStorage interface (via setPause).
      *
      * @access private
      */
     showPause: function()
     {
        RealtimeUpdate.setPause(false);
        RealtimeUpdate.showQueuedNotices();
        RealtimeUpdate.addNoticesHover();

        $('#realtime_playpause').remove();
        $('#realtime_actions').prepend('<li id="realtime_playpause"><button id="realtime_pause" class="pause"></button></li>');
        $('#realtime_pause').text(SN.msg('realtime_pause'))
                            .attr('title', SN.msg('realtime_pause_tooltip'))
                            .bind('click', function() {
            RealtimeUpdate.removeNoticesHover();
            RealtimeUpdate.showPlay();
            return false;
        });
     },

     /**
      * Switch the realtime UI into play state.
      * Uses SN.msg i18n system for the button label and tooltip.
      *
      * State will be saved and re-used next time if the browser supports
      * the localStorage interface (via setPause).
      *
      * @access private
      */
     showPlay: function()
     {
        RealtimeUpdate.setPause(true);
        $('#realtime_playpause').remove();
        $('#realtime_actions').prepend('<li id="realtime_playpause"><span id="queued_counter"></span> <button id="realtime_play" class="play"></button></li>');
        $('#realtime_play').text(SN.msg('realtime_play'))
                           .attr('title', SN.msg('realtime_play_tooltip'))
                           .bind('click', function() {
            RealtimeUpdate.showPause();
            return false;
        });
     },

     /**
      * Update the internal pause/play state.
      * Do not call directly; use showPause() and showPlay().
      *
      * State will be saved and re-used next time if the browser supports
      * the localStorage interface.
      *
      * @param {boolean} state: true = paused, false = not paused
      *
      * @access private
      */
     setPause: function(state)
     {
        RealtimeUpdate._paused = state;
        if (typeof(localStorage) != 'undefined') {
            localStorage.setItem('RealtimeUpdate_paused', RealtimeUpdate._paused);
        }
     },

     /**
      * Go through notices we have previously received while paused,
      * dumping them into the timeline view.
      *
      * @fixme long timelines are not trimmed here as they are for things received while not paused
      *
      * @access private
      */
     showQueuedNotices: function()
     {
        $.each(RealtimeUpdate._queuedNotices, function(i, n) {
            RealtimeUpdate.insertNoticeItem(n);
        });

        RealtimeUpdate._queuedNotices = [];

        RealtimeUpdate.removeQueuedCounter();
     },

     /**
      * Update the Realtime widget control's counter of queued notices to show
      * the current count. This will be called after receiving and queueing
      * a notice while paused.
      *
      * @access private
      */
     updateQueuedCounter: function()
     {
        $('#realtime_playpause #queued_counter').html('('+RealtimeUpdate._queuedNotices.length+')');
     },

     /**
      * Clear the Realtime widget control's counter of queued notices.
      *
      * @access private
      */
     removeQueuedCounter: function()
     {
        $('#realtime_playpause #queued_counter').empty();
     },

     /**
      * Set up event handlers on the timeline view to automatically pause
      * when the mouse is over the timeline, as this indicates the user's
      * desire to interact with the UI. (Which is hard to do when it's moving!)
      *
      * @access private
      */
     addNoticesHover: function()
     {
        $('#notices_primary .notices').hover(
            function() {
                if (RealtimeUpdate._paused === false) {
                    RealtimeUpdate.showPlay();
                }
            },
            function() {
                if (RealtimeUpdate._paused === true) {
                    RealtimeUpdate.showPause();
                }
            }
        );
     },

     /**
      * Tear down event handlers on the timeline view to automatically pause
      * when the mouse is over the timeline.
      *
      * @fixme this appears to remove *ALL* event handlers from the timeline,
      *        which assumes that nobody else is adding any event handlers.
      *        Sloppy -- we should only remove the ones we add.
      *
      * @access private
      */
     removeNoticesHover: function()
     {
        $('#notices_primary .notices').unbind();
     },

     /**
      * UI initialization, to be called from Realtime plugin code on regular
      * timeline pages.
      *
      * Adds a button to the control widget at the top of the timeline view,
      * allowing creation of a popup window with a more compact real-time
      * view of the current timeline.
      *
      * @param {String} url: full URL to the popup window variant of this timeline page
      * @param {String} timeline: string key for the timeline (eg 'public' or 'evan-all')
      * @param {String} path: URL to the base directory containing the Realtime plugin,
      *                       used to fetch resources if needed.
      *
      * @todo timeline and path parameters are unused and probably should be removed.
      *
      * @access public
      */
     initAddPopup: function(url, timeline, path)
     {
         $('#realtime_timeline').append('<button id="realtime_popup"></button>');
         $('#realtime_popup').text(SN.msg('realtime_popup'))
                             .attr('title', SN.msg('realtime_popup_tooltip'))
                             .bind('click', function() {
                window.open(url,
                         '',
                         'toolbar=no,resizable=yes,scrollbars=yes,status=no,menubar=no,personalbar=no,location=no,width=500,height=550');

             return false;
         });
     },

     /**
      * UI initialization, to be called from Realtime plugin code on popup
      * compact timeline pages.
      *
      * Sets up links in notices to open in a new window.
      *
      * @fixme fails to do the same for UI links like context view which will
      *        look bad in the tiny chromeless window.
      *
      * @access public
      */
     initPopupWindow: function()
     {
         $('.notices .entry-title a, .notices .e-content a').bind('click', function() {
            window.open(this.href, '');

            return false;
         });

         $('#showstream .entity_profile').css({'width':'69%'});
     }
}

