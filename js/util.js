/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, StatusNet, Inc.
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
 * @category  UI interaction
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009,2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

var SN = { // StatusNet
    C: { // Config
        I: { // Init
            CounterBlackout: false,
            MaxLength: 140,
            PatternUsername: /^[0-9a-zA-Z\-_.]*$/,
            HTTP20x30x: [200, 201, 202, 203, 204, 205, 206, 300, 301, 302, 303, 304, 305, 306, 307]
        },

        /**
         * @fixme are these worth the trouble? They seem to mostly just duplicate
         * themselves while slightly obscuring the actual selector, so it's hard
         * to pop over to the HTML and find something.
         *
         * In theory, minification could reduce them to shorter variable names,
         * but at present that doesn't happen with yui-compressor.
         */
        S: { // Selector
            Disabled: 'disabled',
            Warning: 'warning',
            Error: 'error',
            Success: 'success',
            Processing: 'processing',
            CommandResult: 'command_result',
            FormNotice: 'form_notice',
            NoticeDataText: 'notice_data-text',
            NoticeTextCount: 'notice_text-count',
            NoticeInReplyTo: 'notice_in-reply-to',
            NoticeDataAttach: 'notice_data-attach',
            NoticeDataAttachSelected: 'notice_data-attach_selected',
            NoticeActionSubmit: 'notice_action-submit',
            NoticeLat: 'notice_data-lat',
            NoticeLon: 'notice_data-lon',
            NoticeLocationId: 'notice_data-location_id',
            NoticeLocationNs: 'notice_data-location_ns',
            NoticeGeoName: 'notice_data-geo_name',
            NoticeDataGeo: 'notice_data-geo',
            NoticeDataGeoCookie: 'NoticeDataGeo',
            NoticeDataGeoSelected: 'notice_data-geo_selected',
            StatusNetInstance:'StatusNetInstance'
        }
    },

    /**
     * Map of localized message strings exported to script from the PHP
     * side via Action::getScriptMessages().
     *
     * Retrieve them via SN.msg(); this array is an implementation detail.
     *
     * @access private
     */
    messages: {},

    /**
     * Grabs a localized string that's been previously exported to us
     * from server-side code via Action::getScriptMessages().
     *
     * @example alert(SN.msg('coolplugin-failed'));
     *
     * @param {String} key: string key name to pull from message index
     * @return matching localized message string
     */
    msg: function(key) {
        if (typeof SN.messages[key] == "undefined") {
            return '[' + key + ']';
        } else {
            return SN.messages[key];
        }
    },

    U: { // Utils
        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers on the new notice form.
         *
         * @param {jQuery} form: jQuery object whose first matching element is the form
         * @access private
         */
        FormNoticeEnhancements: function(form) {
            if (jQuery.data(form[0], 'ElementData') === undefined) {
                MaxLength = form.find('#'+SN.C.S.NoticeTextCount).text();
                if (typeof(MaxLength) == 'undefined') {
                     MaxLength = SN.C.I.MaxLength;
                }
                jQuery.data(form[0], 'ElementData', {MaxLength:MaxLength});

                SN.U.Counter(form);

                NDT = form.find('#'+SN.C.S.NoticeDataText);

                NDT.bind('keyup', function(e) {
                    SN.U.Counter(form);
                });

                var delayedUpdate= function(e) {
                    // Cut and paste events fire *before* the operation,
                    // so we need to trigger an update in a little bit.
                    // This would be so much easier if the 'change' event
                    // actually fired every time the value changed. :P
                    window.setTimeout(function() {
                        SN.U.Counter(form);
                    }, 50);
                };
                // Note there's still no event for mouse-triggered 'delete'.
                NDT.bind('cut', delayedUpdate)
                   .bind('paste', delayedUpdate);

                NDT.bind('keydown', function(e) {
                    SN.U.SubmitOnReturn(e, form);
                });
            }
            else {
                form.find('#'+SN.C.S.NoticeTextCount).text(jQuery.data(form[0], 'ElementData').MaxLength);
            }

            if ($('body')[0].id != 'conversation' && window.location.hash.length === 0 && $(window).scrollTop() == 0) {
                form.find('textarea').focus();
            }
        },

        /**
         * To be called from keydown event handler on the notice import form.
         * Checks if return or enter key was pressed, and if so attempts to
         * submit the form and cancel standard processing of the enter key.
         *
         * @param {Event} event
         * @param {jQuery} el: jQuery object whose first element is the notice posting form
         *
         * @return {boolean} whether to cancel the event? Does this actually pass through?
         * @access private
         */
        SubmitOnReturn: function(event, el) {
            if (event.keyCode == 13 || event.keyCode == 10) {
                el.submit();
                event.preventDefault();
                event.stopPropagation();
                $('#'+el[0].id+' #'+SN.C.S.NoticeDataText).blur();
                $('body').focus();
                return false;
            }
            return true;
        },

        /**
         * To be called from event handlers on the notice import form.
         * Triggers an update of the remaining-characters counter.
         *
         * Additional counter updates will be suppressed during the
         * next half-second to avoid flooding the layout engine with
         * updates, followed by another automatic check.
         *
         * The maximum length is pulled from data established by
         * FormNoticeEnhancements.
         *
         * @param {jQuery} form: jQuery object whose first element is the notice posting form
         * @access private
         */
        Counter: function(form) {
            SN.C.I.FormNoticeCurrent = form;

            var MaxLength = jQuery.data(form[0], 'ElementData').MaxLength;

            if (MaxLength <= 0) {
                return;
            }

            var remaining = MaxLength - SN.U.CharacterCount(form);
            var counter = form.find('#'+SN.C.S.NoticeTextCount);

            if (remaining.toString() != counter.text()) {
                if (!SN.C.I.CounterBlackout || remaining === 0) {
                    if (counter.text() != String(remaining)) {
                        counter.text(remaining);
                    }
                    if (remaining < 0) {
                        form.addClass(SN.C.S.Warning);
                    } else {
                        form.removeClass(SN.C.S.Warning);
                    }
                    // Skip updates for the next 500ms.
                    // On slower hardware, updating on every keypress is unpleasant.
                    if (!SN.C.I.CounterBlackout) {
                        SN.C.I.CounterBlackout = true;
                        SN.C.I.FormNoticeCurrent = form;
                        window.setTimeout("SN.U.ClearCounterBlackout(SN.C.I.FormNoticeCurrent);", 500);
                    }
                }
            }
        },

        /**
         * Pull the count of characters in the current edit field.
         * Plugins replacing the edit control may need to override this.
         *
         * @param {jQuery} form: jQuery object whose first element is the notice posting form
         * @return number of chars
         */
        CharacterCount: function(form) {
            return form.find('#'+SN.C.S.NoticeDataText).val().length;
        },

        /**
         * Called internally after the counter update blackout period expires;
         * runs another update to make sure we didn't miss anything.
         *
         * @param {jQuery} form: jQuery object whose first element is the notice posting form
         * @access private
         */
        ClearCounterBlackout: function(form) {
            // Allow keyup events to poke the counter again
            SN.C.I.CounterBlackout = false;
            // Check if the string changed since we last looked
            SN.U.Counter(form);
        },

        /**
         * Helper function to rewrite default HTTP form action URLs to HTTPS
         * so we can actually fetch them when on an SSL page in ssl=sometimes
         * mode.
         *
         * It would be better to output URLs that didn't hardcode protocol
         * and hostname in the first place...
         *
         * @param {String} url
         * @return string
         */
        RewriteAjaxAction: function(url) {
            // Quick hack: rewrite AJAX submits to HTTPS if they'd fail otherwise.
            if (document.location.protocol == 'https:' && url.substr(0, 5) == 'http:') {
                return url.replace(/^http:\/\/[^:\/]+/, 'https://' + document.location.host);
            } else {
                return url;
            }
        },

        /**
         * Grabs form data and submits it asynchronously, with 'ajax=1'
         * parameter added to the rest.
         *
         * If a successful response includes another form, that form
         * will be extracted and copied in, replacing the original form.
         * If there's no form, the first paragraph will be used.
         *
         * @fixme can sometimes explode confusingly if returnd data is bogus
         * @fixme error handling is pretty vague
         * @fixme can't submit file uploads
         *
         * @param {jQuery} form: jQuery object whose first element is a form
         *
         * @access public
         */
        FormXHR: function(form) {
            $.ajax({
                type: 'POST',
                dataType: 'xml',
                url: SN.U.RewriteAjaxAction(form.attr('action')),
                data: form.serialize() + '&ajax=1',
                beforeSend: function(xhr) {
                    form
                        .addClass(SN.C.S.Processing)
                        .find('.submit')
                            .addClass(SN.C.S.Disabled)
                            .attr(SN.C.S.Disabled, SN.C.S.Disabled);
                },
                error: function (xhr, textStatus, errorThrown) {
                    alert(errorThrown || textStatus);
                },
                success: function(data, textStatus) {
                    if (typeof($('form', data)[0]) != 'undefined') {
                        form_new = document._importNode($('form', data)[0], true);
                        form.replaceWith(form_new);
                    }
                    else {
                        form.replaceWith(document._importNode($('p', data)[0], true));
                    }
                }
            });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers for special-cased async submission of the
         * notice-posting form, including some pre-post validation.
         *
         * Unlike FormXHR() this does NOT submit the form immediately!
         * It sets up event handlers so that any method of submitting the
         * form (click on submit button, enter, submit() etc) will trigger
         * it properly.
         *
         * Also unlike FormXHR(), this system will use a hidden iframe
         * automatically to handle file uploads via <input type="file">
         * controls.
         *
         * @fixme tl;dr
         * @fixme vast swaths of duplicate code and really long variable names clutter this function up real bad
         * @fixme error handling is unreliable
         * @fixme cookieValue is a global variable, but probably shouldn't be
         * @fixme saving the location cache cookies should be split out
         * @fixme some error messages are hardcoded english: needs i18n
         * @fixme special-case for bookmarklet is confusing and uses a global var "self". Is this ok?
         *
         * @param {jQuery} form: jQuery object whose first element is a form
         *
         * @access public
         */
        FormNoticeXHR: function(form) {
            SN.C.I.NoticeDataGeo = {};
            form.append('<input type="hidden" name="ajax" value="1"/>');

            // Make sure we don't have a mixed HTTP/HTTPS submission...
            form.attr('action', SN.U.RewriteAjaxAction(form.attr('action')));

            /**
             * Show a response feedback bit under the new-notice dialog.
             *
             * @param {String} cls: CSS class name to use ('error' or 'success')
             * @param {String} text
             * @access private
             */
            var showFeedback = function(cls, text) {
                form.append(
                    $('<p class="form_response"></p>')
                        .addClass(cls)
                        .text(text)
                );
            };

            /**
             * Hide the previous response feedback, if any.
             */
            var removeFeedback = function() {
                form.find('.form_response').remove();
            };

            form.ajaxForm({
                dataType: 'xml',
                timeout: '60000',
                beforeSend: function(formData) {
                    if (form.find('#'+SN.C.S.NoticeDataText)[0].value.length === 0) {
                        form.addClass(SN.C.S.Warning);
                        return false;
                    }
                    form
                        .addClass(SN.C.S.Processing)
                        .find('#'+SN.C.S.NoticeActionSubmit)
                            .addClass(SN.C.S.Disabled)
                            .attr(SN.C.S.Disabled, SN.C.S.Disabled);

                    SN.C.I.NoticeDataGeo.NLat = $('#'+SN.C.S.NoticeLat).val();
                    SN.C.I.NoticeDataGeo.NLon = $('#'+SN.C.S.NoticeLon).val();
                    SN.C.I.NoticeDataGeo.NLNS = $('#'+SN.C.S.NoticeLocationNs).val();
                    SN.C.I.NoticeDataGeo.NLID = $('#'+SN.C.S.NoticeLocationId).val();
                    SN.C.I.NoticeDataGeo.NDG = $('#'+SN.C.S.NoticeDataGeo).attr('checked');

                    cookieValue = $.cookie(SN.C.S.NoticeDataGeoCookie);

                    if (cookieValue !== null && cookieValue != 'disabled') {
                        cookieValue = JSON.parse(cookieValue);
                        SN.C.I.NoticeDataGeo.NLat = $('#'+SN.C.S.NoticeLat).val(cookieValue.NLat).val();
                        SN.C.I.NoticeDataGeo.NLon = $('#'+SN.C.S.NoticeLon).val(cookieValue.NLon).val();
                        if ($('#'+SN.C.S.NoticeLocationNs).val(cookieValue.NLNS)) {
                            SN.C.I.NoticeDataGeo.NLNS = $('#'+SN.C.S.NoticeLocationNs).val(cookieValue.NLNS).val();
                            SN.C.I.NoticeDataGeo.NLID = $('#'+SN.C.S.NoticeLocationId).val(cookieValue.NLID).val();
                        }
                    }
                    if (cookieValue == 'disabled') {
                        SN.C.I.NoticeDataGeo.NDG = $('#'+SN.C.S.NoticeDataGeo).attr('checked', false).attr('checked');
                    }
                    else {
                        SN.C.I.NoticeDataGeo.NDG = $('#'+SN.C.S.NoticeDataGeo).attr('checked', true).attr('checked');
                    }

                    return true;
                },
                error: function (xhr, textStatus, errorThrown) {
                    form
                        .removeClass(SN.C.S.Processing)
                        .find('#'+SN.C.S.NoticeActionSubmit)
                            .removeClass(SN.C.S.Disabled)
                            .removeAttr(SN.C.S.Disabled, SN.C.S.Disabled);
                    removeFeedback();
                    if (textStatus == 'timeout') {
                        // @fixme i18n
                        showFeedback('error', 'Sorry! We had trouble sending your notice. The servers are overloaded. Please try again, and contact the site administrator if this problem persists.');
                    }
                    else {
                        var response = SN.U.GetResponseXML(xhr);
                        if ($('.'+SN.C.S.Error, response).length > 0) {
                            form.append(document._importNode($('.'+SN.C.S.Error, response)[0], true));
                        }
                        else {
                            if (parseInt(xhr.status) === 0 || jQuery.inArray(parseInt(xhr.status), SN.C.I.HTTP20x30x) >= 0) {
                                form
                                    .resetForm()
                                    .find('#'+SN.C.S.NoticeDataAttachSelected).remove();
                                SN.U.FormNoticeEnhancements(form);
                            }
                            else {
                                // @fixme i18n
                                showFeedback('error', '(Sorry! We had trouble sending your notice ('+xhr.status+' '+xhr.statusText+'). Please report the problem to the site administrator if this happens again.');
                            }
                        }
                    }
                },
                success: function(data, textStatus) {
                    removeFeedback();
                    var errorResult = $('#'+SN.C.S.Error, data);
                    if (errorResult.length > 0) {
                        showFeedback('error', errorResult.text());
                    }
                    else {
                        if($('body')[0].id == 'bookmarklet') {
                            // @fixme self is not referenced anywhere?
                            self.close();
                        }

                        var commandResult = $('#'+SN.C.S.CommandResult, data);
                        if (commandResult.length > 0) {
                            showFeedback('success', commandResult.text());
                        }
                        else {
                            // New notice post was successful. If on our timeline, show it!
                            var notice = document._importNode($('li', data)[0], true);
                            var notices = $('#notices_primary .notices');
                            if (notices.length > 0 && SN.U.belongsOnTimeline(notice)) {
                                if ($('#'+notice.id).length === 0) {
                                    var notice_irt_value = $('#'+SN.C.S.NoticeInReplyTo).val();
                                    var notice_irt = '#notices_primary #notice-'+notice_irt_value;
                                    if($('body')[0].id == 'conversation') {
                                        if(notice_irt_value.length > 0 && $(notice_irt+' .notices').length < 1) {
                                            $(notice_irt).append('<ul class="notices"></ul>');
                                        }
                                        $($(notice_irt+' .notices')[0]).append(notice);
                                    }
                                    else {
                                        notices.prepend(notice);
                                    }
                                    $('#'+notice.id)
                                        .css({display:'none'})
                                        .fadeIn(2500);
                                    SN.U.NoticeWithAttachment($('#'+notice.id));
                                    SN.U.NoticeReplyTo($('#'+notice.id));
                                }
                            }
                            else {
                                // Not on a timeline that this belongs on?
                                // Just show a success message.
                                showFeedback('success', $('title', data).text());
                            }
                        }
                        form.resetForm();
                        form.find('#'+SN.C.S.NoticeInReplyTo).val('');
                        form.find('#'+SN.C.S.NoticeDataAttachSelected).remove();
                        SN.U.FormNoticeEnhancements(form);
                    }
                },
                complete: function(xhr, textStatus) {
                    form
                        .removeClass(SN.C.S.Processing)
                        .find('#'+SN.C.S.NoticeActionSubmit)
                            .removeAttr(SN.C.S.Disabled)
                            .removeClass(SN.C.S.Disabled);

                    $('#'+SN.C.S.NoticeLat).val(SN.C.I.NoticeDataGeo.NLat);
                    $('#'+SN.C.S.NoticeLon).val(SN.C.I.NoticeDataGeo.NLon);
                    if ($('#'+SN.C.S.NoticeLocationNs)) {
                        $('#'+SN.C.S.NoticeLocationNs).val(SN.C.I.NoticeDataGeo.NLNS);
                        $('#'+SN.C.S.NoticeLocationId).val(SN.C.I.NoticeDataGeo.NLID);
                    }
                    $('#'+SN.C.S.NoticeDataGeo).attr('checked', SN.C.I.NoticeDataGeo.NDG);
                }
            });
        },

        /**
         * Fetch an XML DOM from an XHR's response data.
         *
         * Works around unavailable responseXML when document.domain
         * has been modified by Meteor or other tools, in some but not
         * all browsers.
         *
         * @param {XMLHTTPRequest} xhr
         * @return DOMDocument
         */
        GetResponseXML: function(xhr) {
            try {
                return xhr.responseXML;
            } catch (e) {
                return (new DOMParser()).parseFromString(xhr.responseText, "text/xml");
            }
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers on all visible notice's reply buttons to
         * tweak the new-notice form with needed variables and focus it
         * when pushed.
         *
         * (This replaces the default reply button behavior to submit
         * directly to a form which comes back with a specialized page
         * with the form data prefilled.)
         *
         * @access private
         */
        NoticeReply: function() {
            if ($('#'+SN.C.S.NoticeDataText).length > 0 && $('#content .notice_reply').length > 0) {
                $('#content .notice').each(function() { SN.U.NoticeReplyTo($(this)); });
            }
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers on the given notice's reply button to
         * tweak the new-notice form with needed variables and focus it
         * when pushed.
         *
         * (This replaces the default reply button behavior to submit
         * directly to a form which comes back with a specialized page
         * with the form data prefilled.)
         *
         * @param {jQuery} notice: jQuery object containing one or more notices
         * @access private
         */
        NoticeReplyTo: function(notice) {
            notice.find('.notice_reply').live('click', function() {
                var nickname = ($('.author .nickname', notice).length > 0) ? $($('.author .nickname', notice)[0]) : $('.author .nickname.uid');
                SN.U.NoticeReplySet(nickname.text(), $($('.notice_id', notice)[0]).text());
                return false;
            });
        },

        /**
         * Updates the new notice posting form with bits for replying to the
         * given user. Adds replyto parameter to the form, and a "@foo" to the
         * text area.
         *
         * @fixme replyto is a global variable, but probably shouldn't be
         *
         * @param {String} nick
         * @param {String} id
         */
        NoticeReplySet: function(nick,id) {
            if (nick.match(SN.C.I.PatternUsername)) {
                var text = $('#'+SN.C.S.NoticeDataText);
                if (text.length > 0) {
                    replyto = '@' + nick + ' ';
                    text.val(replyto + text.val().replace(RegExp(replyto, 'i'), ''));
                    $('#'+SN.C.S.FormNotice+' #'+SN.C.S.NoticeInReplyTo).val(id);

                    text[0].focus();
                    if (text[0].setSelectionRange) {
                        var len = text.val().length;
                        text[0].setSelectionRange(len,len);
                    }
                }
            }
        },

        /**
         * Setup function -- DOES NOT apply immediately.
         *
         * Sets up event handlers for favor/disfavor forms to submit via XHR.
         * Uses 'live' rather than 'bind', so applies to future as well as present items.
         */
        NoticeFavor: function() {
            $('.form_favor').live('click', function() { SN.U.FormXHR($(this)); return false; });
            $('.form_disfavor').live('click', function() { SN.U.FormXHR($(this)); return false; });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers for repeat forms to toss up a confirmation
         * popout before submitting.
         *
         * Uses 'live' rather than 'bind', so applies to future as well as present items.
         */
        NoticeRepeat: function() {
            $('.form_repeat').live('click', function(e) {
                e.preventDefault();

                SN.U.NoticeRepeatConfirmation($(this));
                return false;
            });
        },

        /**
         * Shows a confirmation dialog box variant of the repeat button form.
         * This seems to use a technique where the repeat form contains
         * _both_ a standalone button _and_ text and buttons for a dialog.
         * The dialog will close after its copy of the form is submitted,
         * or if you click its 'close' button.
         *
         * The dialog is created by duplicating the original form and changing
         * its style; while clever, this is hard to generalize and probably
         * duplicates a lot of unnecessary HTML output.
         *
         * @fixme create confirmation dialogs through a generalized interface
         * that can be reused instead of hardcoded text and styles.
         *
         * @param {jQuery} form
         */
        NoticeRepeatConfirmation: function(form) {
            var submit_i = form.find('.submit');

            var submit = submit_i.clone();
            submit
                .addClass('submit_dialogbox')
                .removeClass('submit');
            form.append(submit);
            submit.bind('click', function() { SN.U.FormXHR(form); return false; });

            submit_i.hide();

            form
                .addClass('dialogbox')
                .append('<button class="close">&#215;</button>')
                .closest('.notice-options')
                    .addClass('opaque');

            form.find('button.close').click(function(){
                $(this).remove();

                form
                    .removeClass('dialogbox')
                    .closest('.notice-options')
                        .removeClass('opaque');

                form.find('.submit_dialogbox').remove();
                form.find('.submit').show();

                return false;
            });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Goes through all notices currently displayed and sets up attachment
         * handling if needed.
         */
        NoticeAttachments: function() {
            $('.notice a.attachment').each(function() {
                SN.U.NoticeWithAttachment($(this).closest('.notice'));
            });
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up special attachment link handling if needed. Currently this
         * consists only of making the "more" button used for OStatus message
         * cropping turn into an auto-expansion button that loads the full
         * text from an attachment file.
         *
         * @param {jQuery} notice
         */
        NoticeWithAttachment: function(notice) {
            if (notice.find('.attachment').length === 0) {
                return;
            }

            var attachment_more = notice.find('.attachment.more');
            if (attachment_more.length > 0) {
                $(attachment_more[0]).click(function() {
                    var m = $(this);
                    m.addClass(SN.C.S.Processing);
                    $.get(m.attr('href')+'/ajax', null, function(data) {
                        m.parent('.entry-content').html($(data).find('#attachment_view .entry-content').html());
                    });

                    return false;
                }).attr('title', SN.msg('showmore_tooltip'));
            }
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Sets up event handlers for the file-attachment widget in the
         * new notice form. When a file is selected, a box will be added
         * below the text input showing the filename and, if supported
         * by the browser, a thumbnail preview.
         *
         * This preview box will also allow removing the attachment
         * prior to posting.
         */
        NoticeDataAttach: function() {
            NDA = $('#'+SN.C.S.NoticeDataAttach);
            NDA.change(function() {
                S = '<div id="'+SN.C.S.NoticeDataAttachSelected+'" class="'+SN.C.S.Success+'"><code>'+$(this).val()+'</code> <button class="close">&#215;</button></div>';
                NDAS = $('#'+SN.C.S.NoticeDataAttachSelected);
                if (NDAS.length > 0) {
                    NDAS.replaceWith(S);
                }
                else {
                    $('#'+SN.C.S.FormNotice).append(S);
                }
                $('#'+SN.C.S.NoticeDataAttachSelected+' button').click(function(){
                    $('#'+SN.C.S.NoticeDataAttachSelected).remove();
                    NDA.val('');

                    return false;
                });
                if (typeof this.files == "object") {
                    // Some newer browsers will let us fetch the files for preview.
                    for (var i = 0; i < this.files.length; i++) {
                        SN.U.PreviewAttach(this.files[i]);
                    }
                }
            });
        },

        /**
         * For browsers with FileAPI support: make a thumbnail if possible,
         * and append it into the attachment display widget.
         *
         * Known good:
         * - Firefox 3.6.6, 4.0b7
         * - Chrome 8.0.552.210
         *
         * Known ok metadata, can't get contents:
         * - Safari 5.0.2
         *
         * Known fail:
         * - Opera 10.63, 11 beta (no input.files interface)
         *
         * @param {File} file
         *
         * @todo use configured thumbnail size
         * @todo detect pixel size?
         * @todo should we render a thumbnail to a canvas and then use the smaller image?
         */
        PreviewAttach: function(file) {
            var tooltip = file.type + ' ' + Math.round(file.size / 1024) + 'KB';
            var preview = true;

            var blobAsDataURL;
            if (typeof window.createObjectURL != "undefined") {
                /**
                 * createObjectURL lets us reference the file directly from an <img>
                 * This produces a compact URL with an opaque reference to the file,
                 * which we can reference immediately.
                 *
                 * - Firefox 3.6.6: no
                 * - Firefox 4.0b7: no
                 * - Safari 5.0.2: no
                 * - Chrome 8.0.552.210: works!
                 */
                blobAsDataURL = function(blob, callback) {
                    callback(window.createObjectURL(blob));
                }
            } else if (typeof window.FileReader != "undefined") {
                /**
                 * FileAPI's FileReader can build a data URL from a blob's contents,
                 * but it must read the file and build it asynchronously. This means
                 * we'll be passing a giant data URL around, which may be inefficient.
                 *
                 * - Firefox 3.6.6: works!
                 * - Firefox 4.0b7: works!
                 * - Safari 5.0.2: no
                 * - Chrome 8.0.552.210: works!
                 */
                blobAsDataURL = function(blob, callback) {
                    var reader = new FileReader();
                    reader.onload = function(event) {
                        callback(reader.result);
                    }
                    reader.readAsDataURL(blob);
                }
            } else {
                preview = false;
            }

            var imageTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'];
            if ($.inArray(file.type, imageTypes) == -1) {
                // We probably don't know how to show the file.
                preview = false;
            }

            var maxSize = 8 * 1024 * 1024;
            if (file.size > maxSize) {
                // Don't kill the browser trying to load some giant image.
                preview = false;
            }

            if (preview) {
                blobAsDataURL(file, function(url) {
                    var img = $('<img>')
                        .attr('title', tooltip)
                        .attr('alt', tooltip)
                        .attr('src', url)
                        .attr('style', 'height: 120px');
                    $('#'+SN.C.S.NoticeDataAttachSelected).append(img);
                });
            } else {
                var img = $('<div></div>').text(tooltip);
                $('#'+SN.C.S.NoticeDataAttachSelected).append(img);
            }
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Initializes state for the location-lookup features in the
         * new-notice form. Seems to set up some event handlers for
         * triggering lookups and using the new values.
         *
         * @fixme tl;dr
         * @fixme there's not good visual state update here, so users have a
         *        hard time figuring out if it's working or fixing if it's wrong.
         *
         */
        NoticeLocationAttach: function() {
            var NLat = $('#'+SN.C.S.NoticeLat).val();
            var NLon = $('#'+SN.C.S.NoticeLon).val();
            var NLNS = $('#'+SN.C.S.NoticeLocationNs).val();
            var NLID = $('#'+SN.C.S.NoticeLocationId).val();
            var NLN = $('#'+SN.C.S.NoticeGeoName).text();
            var NDGe = $('#'+SN.C.S.NoticeDataGeo);

            function removeNoticeDataGeo() {
                $('label[for='+SN.C.S.NoticeDataGeo+']')
                    .attr('title', jQuery.trim($('label[for='+SN.C.S.NoticeDataGeo+']').text()))
                    .removeClass('checked');

                $('#'+SN.C.S.NoticeLat).val('');
                $('#'+SN.C.S.NoticeLon).val('');
                $('#'+SN.C.S.NoticeLocationNs).val('');
                $('#'+SN.C.S.NoticeLocationId).val('');
                $('#'+SN.C.S.NoticeDataGeo).attr('checked', false);

                $.cookie(SN.C.S.NoticeDataGeoCookie, 'disabled', { path: '/' });
            }

            function getJSONgeocodeURL(geocodeURL, data) {
                $.getJSON(geocodeURL, data, function(location) {
                    var lns, lid;

                    if (typeof(location.location_ns) != 'undefined') {
                        $('#'+SN.C.S.NoticeLocationNs).val(location.location_ns);
                        lns = location.location_ns;
                    }

                    if (typeof(location.location_id) != 'undefined') {
                        $('#'+SN.C.S.NoticeLocationId).val(location.location_id);
                        lid = location.location_id;
                    }

                    if (typeof(location.name) == 'undefined') {
                        NLN_text = data.lat + ';' + data.lon;
                    }
                    else {
                        NLN_text = location.name;
                    }

                    $('label[for='+SN.C.S.NoticeDataGeo+']')
                        .attr('title', NoticeDataGeo_text.ShareDisable + ' (' + NLN_text + ')');

                    $('#'+SN.C.S.NoticeLat).val(data.lat);
                    $('#'+SN.C.S.NoticeLon).val(data.lon);
                    $('#'+SN.C.S.NoticeLocationNs).val(lns);
                    $('#'+SN.C.S.NoticeLocationId).val(lid);
                    $('#'+SN.C.S.NoticeDataGeo).attr('checked', true);

                    var cookieValue = {
                        NLat: data.lat,
                        NLon: data.lon,
                        NLNS: lns,
                        NLID: lid,
                        NLN: NLN_text,
                        NLNU: location.url,
                        NDG: true
                    };

                    $.cookie(SN.C.S.NoticeDataGeoCookie, JSON.stringify(cookieValue), { path: '/' });
                });
            }

            if (NDGe.length > 0) {
                if ($.cookie(SN.C.S.NoticeDataGeoCookie) == 'disabled') {
                    NDGe.attr('checked', false);
                }
                else {
                    NDGe.attr('checked', true);
                }

                var NGW = $('#notice_data-geo_wrap');
                var geocodeURL = NGW.attr('title');
                NGW.removeAttr('title');

                $('label[for='+SN.C.S.NoticeDataGeo+']')
                    .attr('title', jQuery.trim($('label[for='+SN.C.S.NoticeDataGeo+']').text()));

                NDGe.change(function() {
                    if ($('#'+SN.C.S.NoticeDataGeo).attr('checked') === true || $.cookie(SN.C.S.NoticeDataGeoCookie) === null) {
                        $('label[for='+SN.C.S.NoticeDataGeo+']')
                            .attr('title', NoticeDataGeo_text.ShareDisable)
                            .addClass('checked');

                        if ($.cookie(SN.C.S.NoticeDataGeoCookie) === null || $.cookie(SN.C.S.NoticeDataGeoCookie) == 'disabled') {
                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(
                                    function(position) {
                                        $('#'+SN.C.S.NoticeLat).val(position.coords.latitude);
                                        $('#'+SN.C.S.NoticeLon).val(position.coords.longitude);

                                        var data = {
                                            lat: position.coords.latitude,
                                            lon: position.coords.longitude,
                                            token: $('#token').val()
                                        };

                                        getJSONgeocodeURL(geocodeURL, data);
                                    },

                                    function(error) {
                                        switch(error.code) {
                                            case error.PERMISSION_DENIED:
                                                removeNoticeDataGeo();
                                                break;
                                            case error.TIMEOUT:
                                                $('#'+SN.C.S.NoticeDataGeo).attr('checked', false);
                                                break;
                                        }
                                    },

                                    {
                                        timeout: 10000
                                    }
                                );
                            }
                            else {
                                if (NLat.length > 0 && NLon.length > 0) {
                                    var data = {
                                        lat: NLat,
                                        lon: NLon,
                                        token: $('#token').val()
                                    };

                                    getJSONgeocodeURL(geocodeURL, data);
                                }
                                else {
                                    removeNoticeDataGeo();
                                    $('#'+SN.C.S.NoticeDataGeo).remove();
                                    $('label[for='+SN.C.S.NoticeDataGeo+']').remove();
                                }
                            }
                        }
                        else {
                            var cookieValue = JSON.parse($.cookie(SN.C.S.NoticeDataGeoCookie));

                            $('#'+SN.C.S.NoticeLat).val(cookieValue.NLat);
                            $('#'+SN.C.S.NoticeLon).val(cookieValue.NLon);
                            $('#'+SN.C.S.NoticeLocationNs).val(cookieValue.NLNS);
                            $('#'+SN.C.S.NoticeLocationId).val(cookieValue.NLID);
                            $('#'+SN.C.S.NoticeDataGeo).attr('checked', cookieValue.NDG);

                            $('label[for='+SN.C.S.NoticeDataGeo+']')
                                .attr('title', NoticeDataGeo_text.ShareDisable + ' (' + cookieValue.NLN + ')')
                                .addClass('checked');
                        }
                    }
                    else {
                        removeNoticeDataGeo();
                    }
                }).change();
            }
        },

        /**
         * Setup function -- DOES NOT trigger actions immediately.
         *
         * Initializes event handlers for the "Send direct message" link on
         * profile pages, setting it up to display a dialog box when clicked.
         *
         * Unlike the repeat confirmation form, this appears to fetch
         * the form _from the original link target_, so the form itself
         * doesn't need to be in the current document.
         *
         * @fixme breaks ability to open link in new window?
         */
        NewDirectMessage: function() {
            NDM = $('.entity_send-a-message a');
            NDM.attr({'href':NDM.attr('href')+'&ajax=1'});
            NDM.bind('click', function() {
                var NDMF = $('.entity_send-a-message form');
                if (NDMF.length === 0) {
                    $(this).addClass(SN.C.S.Processing);
                    $.get(NDM.attr('href'), null, function(data) {
                        $('.entity_send-a-message').append(document._importNode($('form', data)[0], true));
                        NDMF = $('.entity_send-a-message .form_notice');
                        SN.U.FormNoticeXHR(NDMF);
                        SN.U.FormNoticeEnhancements(NDMF);
                        NDMF.append('<button class="close">&#215;</button>');
                        $('.entity_send-a-message button').click(function(){
                            NDMF.hide();
                            return false;
                        });
                        NDM.removeClass(SN.C.S.Processing);
                    });
                }
                else {
                    NDMF.show();
                    $('.entity_send-a-message textarea').focus();
                }
                return false;
            });
        },

        /**
         * Return a date object with the current local time on the
         * given year, month, and day.
         *
         * @param {number} year: 4-digit year
         * @param {number} month: 0 == January
         * @param {number} day: 1 == 1
         * @return {Date}
         */
        GetFullYear: function(year, month, day) {
            var date = new Date();
            date.setFullYear(year, month, day);

            return date;
        },

        /**
         * Some sort of object interface for storing some structured
         * information in a cookie.
         *
         * Appears to be used to save the last-used login nickname?
         * That's something that browsers usually take care of for us
         * these days, do we really need to do it? Does anything else
         * use this interface?
         *
         * @fixme what is this?
         * @fixme should this use non-cookie local storage when available?
         */
        StatusNetInstance: {
            /**
             * @fixme what is this?
             */
            Set: function(value) {
                var SNI = SN.U.StatusNetInstance.Get();
                if (SNI !== null) {
                    value = $.extend(SNI, value);
                }

                $.cookie(
                    SN.C.S.StatusNetInstance,
                    JSON.stringify(value),
                    {
                        path: '/',
                        expires: SN.U.GetFullYear(2029, 0, 1)
                    });
            },

            /**
             * @fixme what is this?
             */
            Get: function() {
                var cookieValue = $.cookie(SN.C.S.StatusNetInstance);
                if (cookieValue !== null) {
                    return JSON.parse(cookieValue);
                }
                return null;
            },

            /**
             * @fixme what is this?
             */
            Delete: function() {
                $.cookie(SN.C.S.StatusNetInstance, null);
            }
        },

        /**
         * Check if the current page is a timeline where the current user's
         * posts should be displayed immediately on success.
         *
         * @fixme this should be done in a saner way, with machine-readable
         * info about what page we're looking at.
         *
         * @param {DOMElement} notice: HTML chunk with formatted notice
         * @return boolean
         */
        belongsOnTimeline: function(notice) {
            var action = $("body").attr('id');
            if (action == 'public') {
                return true;
            }

            var profileLink = $('#nav_profile a').attr('href');
            if (profileLink) {
                var authorUrl = $(notice).find('.entry-title .author a.url').attr('href');
                if (authorUrl == profileLink) {
                    if (action == 'all' || action == 'showstream') {
                        // Posts always show on your own friends and profile streams.
                        return true;
                    }
                }
            }

            // @fixme tag, group, reply timelines should be feasible as well.
            // Mismatch between id-based and name-based user/group links currently complicates
            // the lookup, since all our inline mentions contain the absolute links but the
            // UI links currently on the page use malleable names.

            return false;
        }
    },

    Init: {
        /**
         * If user is logged in, run setup code for the new notice form:
         *
         *  - char counter
         *  - AJAX submission
         *  - location events
         *  - file upload events
         */
        NoticeForm: function() {
            if ($('body.user_in').length > 0) {
                SN.U.NoticeLocationAttach();

                $('.'+SN.C.S.FormNotice).each(function() {
                    SN.U.FormNoticeXHR($(this));
                    SN.U.FormNoticeEnhancements($(this));
                });

                SN.U.NoticeDataAttach();
            }
        },

        /**
         * Run setup code for notice timeline views items:
         *
         * - AJAX submission for fave/repeat/reply (if logged in)
         * - Attachment link extras ('more' links)
         */
        Notices: function() {
            if ($('body.user_in').length > 0) {
                SN.U.NoticeFavor();
                SN.U.NoticeRepeat();
                SN.U.NoticeReply();
            }

            SN.U.NoticeAttachments();
        },

        /**
         * Run setup code for user & group profile page header area if logged in:
         *
         * - AJAX submission for sub/unsub/join/leave/nudge
         * - AJAX form popup for direct-message
         */
        EntityActions: function() {
            if ($('body.user_in').length > 0) {
                $('.form_user_subscribe').live('click', function() { SN.U.FormXHR($(this)); return false; });
                $('.form_user_unsubscribe').live('click', function() { SN.U.FormXHR($(this)); return false; });
                $('.form_group_join').live('click', function() { SN.U.FormXHR($(this)); return false; });
                $('.form_group_leave').live('click', function() { SN.U.FormXHR($(this)); return false; });
                $('.form_user_nudge').live('click', function() { SN.U.FormXHR($(this)); return false; });

                SN.U.NewDirectMessage();
            }
        },

        /**
         * Run setup code for login form:
         *
         * - loads saved last-used-nickname from cookie
         * - sets event handler to save nickname to cookie on submit
         *
         * @fixme is this necessary? Browsers do their own form saving these days.
         */
        Login: function() {
            if (SN.U.StatusNetInstance.Get() !== null) {
                var nickname = SN.U.StatusNetInstance.Get().Nickname;
                if (nickname !== null) {
                    $('#form_login #nickname').val(nickname);
                }
            }

            $('#form_login').bind('submit', function() {
                SN.U.StatusNetInstance.Set({Nickname: $('#form_login #nickname').val()});
                return true;
            });
        }
    }
};

/**
 * Run initialization functions on DOM-ready.
 *
 * Note that if we're waiting on other scripts to load, this won't happen
 * until that's done. To load scripts asynchronously without delaying setup,
 * don't start them loading until after DOM-ready time!
 */
$(document).ready(function(){
    if ($('.'+SN.C.S.FormNotice).length > 0) {
        SN.Init.NoticeForm();
    }
    if ($('#content .notices').length > 0) {
        SN.Init.Notices();
    }
    if ($('#content .entity_actions').length > 0) {
        SN.Init.EntityActions();
    }
    if ($('#form_login').length > 0) {
        SN.Init.Login();
    }
});

// Formerly in xbImportNode.js
// @fixme put it back there -- since we're minifying we can concat in the makefile now

/* is this stuff defined? */
if (!document.ELEMENT_NODE) {
	document.ELEMENT_NODE = 1;
	document.ATTRIBUTE_NODE = 2;
	document.TEXT_NODE = 3;
	document.CDATA_SECTION_NODE = 4;
	document.ENTITY_REFERENCE_NODE = 5;
	document.ENTITY_NODE = 6;
	document.PROCESSING_INSTRUCTION_NODE = 7;
	document.COMMENT_NODE = 8;
	document.DOCUMENT_NODE = 9;
	document.DOCUMENT_TYPE_NODE = 10;
	document.DOCUMENT_FRAGMENT_NODE = 11;
	document.NOTATION_NODE = 12;
}

document._importNode = function(node, allChildren) {
	/* find the node type to import */
	switch (node.nodeType) {
		case document.ELEMENT_NODE:
			/* create a new element */
			var newNode = document.createElement(node.nodeName);
			/* does the node have any attributes to add? */
			if (node.attributes && node.attributes.length > 0)
				/* add all of the attributes */
				for (var i = 0, il = node.attributes.length; i < il;) {
					if (node.attributes[i].nodeName == 'class') {
						newNode.className = node.getAttribute(node.attributes[i++].nodeName);
					} else {
						newNode.setAttribute(node.attributes[i].nodeName, node.getAttribute(node.attributes[i++].nodeName));
					}
				}
			/* are we going after children too, and does the node have any? */
			if (allChildren && node.childNodes && node.childNodes.length > 0)
				/* recursively get all of the child nodes */
				for (var i = 0, il = node.childNodes.length; i < il;)
					newNode.appendChild(document._importNode(node.childNodes[i++], allChildren));
			return newNode;
			break;
		case document.TEXT_NODE:
		case document.CDATA_SECTION_NODE:
		case document.COMMENT_NODE:
			return document.createTextNode(node.nodeValue);
			break;
	}
};

// @fixme put this next bit back too -- since we're minifying we can concat in the makefile now
// A shim to implement the W3C Geolocation API Specification using Gears or the Ajax API
if (typeof navigator.geolocation == "undefined" || navigator.geolocation.shim ) { (function(){

// -- BEGIN GEARS_INIT
(function() {
  // We are already defined. Hooray!
  if (window.google && google.gears) {
    return;
  }

  var factory = null;

  // Firefox
  if (typeof GearsFactory != 'undefined') {
    factory = new GearsFactory();
  } else {
    // IE
    try {
      factory = new ActiveXObject('Gears.Factory');
      // privateSetGlobalObject is only required and supported on WinCE.
      if (factory.getBuildInfo().indexOf('ie_mobile') != -1) {
        factory.privateSetGlobalObject(this);
      }
    } catch (e) {
      // Safari
      if ((typeof navigator.mimeTypes != 'undefined') && navigator.mimeTypes["application/x-googlegears"]) {
        factory = document.createElement("object");
        factory.style.display = "none";
        factory.width = 0;
        factory.height = 0;
        factory.type = "application/x-googlegears";
        document.documentElement.appendChild(factory);
      }
    }
  }

  // *Do not* define any objects if Gears is not installed. This mimics the
  // behavior of Gears defining the objects in the future.
  if (!factory) {
    return;
  }

  // Now set up the objects, being careful not to overwrite anything.
  //
  // Note: In Internet Explorer for Windows Mobile, you can't add properties to
  // the window object. However, global objects are automatically added as
  // properties of the window object in all browsers.
  if (!window.google) {
    google = {};
  }

  if (!google.gears) {
    google.gears = {factory: factory};
  }
})();
// -- END GEARS_INIT

var GearsGeoLocation = (function() {
    // -- PRIVATE
    var geo = google.gears.factory.create('beta.geolocation');

    var wrapSuccess = function(callback, self) { // wrap it for lastPosition love
        return function(position) {
            callback(position);
            self.lastPosition = position;
        };
    };

    // -- PUBLIC
    return {
        shim: true,

        type: "Gears",

        lastPosition: null,

        getCurrentPosition: function(successCallback, errorCallback, options) {
            var self = this;
            var sc = wrapSuccess(successCallback, self);
            geo.getCurrentPosition(sc, errorCallback, options);
        },

        watchPosition: function(successCallback, errorCallback, options) {
            geo.watchPosition(successCallback, errorCallback, options);
        },

        clearWatch: function(watchId) {
            geo.clearWatch(watchId);
        },

        getPermission: function(siteName, imageUrl, extraMessage) {
            geo.getPermission(siteName, imageUrl, extraMessage);
        }

    };
});

var AjaxGeoLocation = (function() {
    // -- PRIVATE
    var loading = false;
    var loadGoogleLoader = function() {
        if (!hasGoogleLoader() && !loading) {
            loading = true;
            var s = document.createElement('script');
            s.src = (document.location.protocol == "https:"?"https://":"http://") + 'www.google.com/jsapi?callback=_google_loader_apiLoaded';
            s.type = "text/javascript";
            document.getElementsByTagName('body')[0].appendChild(s);
        }
    };

    var queue = [];
    var addLocationQueue = function(callback) {
        queue.push(callback);
    };

    var runLocationQueue = function() {
        if (hasGoogleLoader()) {
            while (queue.length > 0) {
                var call = queue.pop();
                call();
            }
        }
    };

    window['_google_loader_apiLoaded'] = function() {
        runLocationQueue();
    };

    var hasGoogleLoader = function() {
        return (window['google'] && google['loader']);
    };

    var checkGoogleLoader = function(callback) {
        if (hasGoogleLoader()) { return true; }

        addLocationQueue(callback);

        loadGoogleLoader();

        return false;
    };

    loadGoogleLoader(); // start to load as soon as possible just in case

    // -- PUBLIC
    return {
        shim: true,

        type: "ClientLocation",

        lastPosition: null,

        getCurrentPosition: function(successCallback, errorCallback, options) {
            var self = this;
            if (!checkGoogleLoader(function() {
                self.getCurrentPosition(successCallback, errorCallback, options);
            })) { return; }

            if (google.loader.ClientLocation) {
                var cl = google.loader.ClientLocation;

                var position = {
                    coords: {
                        latitude: cl.latitude,
                        longitude: cl.longitude,
                        altitude: null,
                        accuracy: 43000, // same as Gears accuracy over wifi?
                        altitudeAccuracy: null,
                        heading: null,
                        speed: null
                    },
                    // extra info that is outside of the bounds of the core API
                    address: {
                        city: cl.address.city,
                        country: cl.address.country,
                        country_code: cl.address.country_code,
                        region: cl.address.region
                    },
                    timestamp: new Date()
                };

                successCallback(position);

                this.lastPosition = position;
            } else if (errorCallback === "function")  {
                errorCallback({ code: 3, message: "Using the Google ClientLocation API and it is not able to calculate a location."});
            }
        },

        watchPosition: function(successCallback, errorCallback, options) {
            this.getCurrentPosition(successCallback, errorCallback, options);

            var self = this;
            var watchId = setInterval(function() {
                self.getCurrentPosition(successCallback, errorCallback, options);
            }, 10000);

            return watchId;
        },

        clearWatch: function(watchId) {
            clearInterval(watchId);
        },

        getPermission: function(siteName, imageUrl, extraMessage) {
            // for now just say yes :)
            return true;
        }

    };
});

// If you have Gears installed use that, else use Ajax ClientLocation
navigator.geolocation = (window.google && google.gears) ? GearsGeoLocation() : AjaxGeoLocation();

})();
}
