var QnA = {

    // @fixme: Should use ID
    close: function (form, best) {
        var notice = $(form).closest('li.hentry.notice.question');

        notice.find('input#qna-best-answer,#qna-question-close').hide();
        notice.find('textarea').hide();

        var list = notice.find('ul');

        notice.find('ul > li.notice-answer-placeholder').remove();
        notice.find('ul > li.notice-answer').remove();

        if (best) {
            var p = notice.parent().find('div.question-description > form > fieldset > p');
            if (p.length != 0) {
                p.append($('<span class="question-closed">This question is closed.</span>'));
            }
        }
    },

    init: function () {
        QnA.NoticeInlineAnswerSetup();

        $(document).on('submit', 'form.form_question_show', function () {
            QnA.close(this);
        });
        $(document).on('submit', 'form.form_answer_show', function () {
            QnA.close(this, true);
        });
    },

    /**
     * Open up a question's inline answer box.
     *
     * @param {jQuery} notice: jQuery object containing one notice
     */
    NoticeInlineAnswerTrigger: function (notice) {
        // Find the notice we're replying to...
        var id = $($('.notice_id', notice)[0]).text();
        var parentNotice = notice;

        // Find the threaded replies view we'll be adding to...
        var list = notice.closest('.notices');
        if (list.hasClass('threaded-replies')) {

            // We're replying to a reply; use reply form on the end of this list.
            // We'll add our form at the end of this; grab the root notice.
            parentNotice = list.closest('.notice');

        } else {

            // We're replying to a parent notice; pull its threaded list
            // and we'll add on the end of it. Will add if needed.
            list = $('ul.threaded-replies', notice);
        }

        // See if the form's already open...
        var answerForm = $('.qna_answer_form', list);

        var hideReplyPlaceholders = function (notice) {
            // Do we still have a dummy answer placeholder? If so get rid of
            // reply place holders for this question. If the current user hasn't
            // answered the question we want to direct her to providing an
            // answer. She can still reply by hitting the reply button if she
            // really wants to.
            var dummyAnswer = $('ul.qna-dummy', notice);
            if (dummyAnswer.length > 0) {
                notice.find('li.notice-reply-placeholder').hide();
            }
        }

        var nextStep = function () {
            var dummyAnswer = $('ul.qna-dummy', notice);
            dummyAnswer.hide();

             // Set focus...
            var text = answerForm.find('textarea');

            if (text.length == 0) {
                throw "No textarea";
            }

            text.focus();

            $('body').click(function (e) {
                var dummyAnswer = $('ul.qna-dummy', notice);
                var style = dummyAnswer.attr('style');
                var ans = $(notice).find('li.hentry.notice.anwer', notice)
                if (ans > 0) {
                    hideReplyPlaceholders(notice);
                }

                var openAnswers = $('li.notice-answer');
                    if (openAnswers.length > 0) {
                        var target = $(e.target);
                        openAnswers.each(function () {

                            // Did we click outside this one?
                            var answerItem = $(this);
                            var parentNotice = answerItem.closest('li.notice');

                            if (answerItem.has(e.target).length == 0) {
                                var textarea = answerItem.find('.notice_data-text:first');
                                var cur = $.trim(textarea.val());
                                // Only close if there's been no edit.
                                if (cur == '' || cur == textarea.data('initialText')) {
                                    answerItem.remove();
                                    dummyAnswer.show();
                                }
                            }
                        });
                    }
                });
        };

        // See if the form's already open...
        if (answerForm.length > 0 ) {
            nextStep();
        } else {
            var placeholder = list.find('li.qna-dummy-placeholder').hide();

            // Create the answer form entry at the end
            var answerItem = $('li.notice-answer', list);

            if (answerItem.length == 0) {
                 answerItem = $('<li class="notice-answer"></li>');
                 var intermediateStep = function (formMaster) {
                    // @todo cache the form if we can (worth it?)
                    var formEl = document._importNode(formMaster, true);
                    $(formEl).data('NoticeFormSetup', true);
                    answerItem.append(formEl);
                    list.prepend(answerItem); // *before* the placeholder
                    var form = answerForm = $(formEl);
                    QnA.AnswerFormSetup(form);
                    nextStep();
                };

                if (QnA.AnswerFormMaster) {
                    // @todo if we had a cached for here's where we'd use it'
                    intermediateStep(QnA.AnswerFormMaster);
                } else {
                    // Fetch a fresh copy of the answer form over AJAX.
                    // Warning: this can have a delay, which looks bad.
                    // @fixme this fallback may or may not work
                    var url = $('#answer-action').attr('value');
                     $.get(url, {ajax: 1}, function (data, textStatus, xhr) {
                         intermediateStep($('form', data)[0]);
                     });
                 }
             }
         }
     },

    /**
     * Setup function -- DOES NOT apply immediately.
     *
     * Sets up event handlers for inline reply mini-form placeholders.
     * Uses 'live' rather than 'bind', so applies to future as well as present items.
     */
    NoticeInlineAnswerSetup: function () {

        $(document).on('focus',
            'li.qna-dummy-placeholder input.placeholder',
            function () {
                var notice = $(this).closest('li.notice');
                QnA.NoticeInlineAnswerTrigger(notice);
                return false;
            });

    },

    AnswerFormSetup: function (form) {

        form.find('textarea').focus();

        if (!form.data('NoticeFormSetup')) {
            alert('gargargar');
        }

        if (!form.data('AnswerFormSetup')) {
            //SN.U.NoticeLocationAttach(form);
            QnA.FormAnswerXHR(form);
            //SN.U.FormNoticeEnhancements(form);
            //SN.U.NoticeDataAttach(form);
            form.data('NoticeFormSetup', true);
        }
    },

   /**
     * Setup function -- DOES NOT trigger actions immediately.
     *
     * Sets up event handlers for special-cased async submission of the
     * answer-posting form, including some pre-post validation.
     *
     * @fixme geodata
     * @fixme refactor and unify with FormNoticeXHR in util.js
     *
     * @param {jQuery} form: jQuery object whose first element is a form
     *
     * @access public
     */
    FormAnswerXHR: function (form) {

        //SN.C.I.NoticeDataGeo = {};
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
        var showFeedback = function (cls, text) {
            form.append(
                $('<p class="form_response"></p>')
                    .addClass(cls)
                    .text(text)
            );
        };

        /**
         * Hide the previous response feedback, if any.
         */
        var removeFeedback = function () {
            form.find('.form_response').remove();
        };

        form.ajaxForm({
            dataType: 'xml',
            timeout: '60000',

            beforeSend: function (formData) {

                if (form.find('.notice_data-text:first').val() == '') {
                    form.addClass(SN.C.S.Warning);
                    return false;
                }
                form
                    .addClass(SN.C.S.Processing)
                    .find('.submit')
                        .addClass(SN.C.S.Disabled)
                        .attr(SN.C.S.Disabled, SN.C.S.Disabled);
                return true;
            },
            error: function (xhr, textStatus, errorThrown) {
                form
                    .removeClass(SN.C.S.Processing)
                    .find('.submit')
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
                                .find('.attach-status').remove();
                            SN.U.FormNoticeEnhancements(form);
                        }
                        else {
                            // @fixme i18n
                            showFeedback('error', '(Sorry! We had trouble sending your notice ('+xhr.status+' '+xhr.statusText+'). Please report the problem to the site administrator if this happens again.');
                        }
                    }
                }
            },
            success: function (data, textStatus) {

                removeFeedback();
                var errorResult = $('#'+SN.C.S.Error, data);
                if (errorResult.length > 0) {
                    showFeedback('error', errorResult.text());
                }
                else {

                    // New notice post was successful. If on our timeline, show it!
                    var notice = document._importNode($('li', data)[0], true);
                    var notices = $('#notices_primary .notices:first');
                    var answerItem = form.closest('li.notice-answer');
                    var questionItem = form.closest('li.question');

                    var dummyAnswer = form.find('ul.qna-dummy', questionItem).remove();
                    if (answerItem.length > 0) {

                    // If this is an inline answer, remove the form...
                    var list = form.closest('.threaded-replies');

                    // if the inserted notice's parent question needs it give it a placeholder
                    var ans = questionItem.find('ul > li.hentry.notice.answer');
                    if (ans.length == 0) {
                        SN.U.NoticeInlineReplyPlaceholder(questionItem);
                    }

                    var id = $(notice).attr('id');
                    if ($("#"+id).length == 0) {
                        $(notice).insertBefore(answerItem);
                            answerItem.remove();
                        } else {
                            // NOP
                            // Realtime came through before us...
                        }

                    } else if (notices.length > 0 && SN.U.belongsOnTimeline(notice)) {

                        // Not a reply. If on our timeline, show it at the
                        if ($('#'+notice.id).length === 0) {
                            var notice_irt_value = form.find('#inreplyto').val();
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
                        }

                        // realtime injected the notice first

                    } else {
                        // Not on a timeline that this belongs on?
                        // Just show a success message.
                        // @fixme inline
                        showFeedback('success', $('title', data).text());
                    }
                }
            },
            complete: function (xhr, textStatus) {
                form
                    .removeClass(SN.C.S.Processing)
                    .find('.submit')
                        .removeAttr(SN.C.S.Disabled)
                        .removeClass(SN.C.S.Disabled);
            }
        });
    }
};

$(document).ready(function () {
    QnA.init();
});
