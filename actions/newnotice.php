<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Handler for posting new notices
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Personal
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Action for posting new notices
 *
 * @category Personal
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class NewnoticeAction extends FormAction
{
    /**
     * Title of the page
     *
     * Note that this usually doesn't get called unless something went wrong
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Page title for sending a new notice.
        return _m('TITLE','New notice');
    }

    /**
     * This handlePost saves a new notice, based on arguments
     *
     * If successful, will show the notice, or return an Ajax-y result.
     * If not, it will show an error message -- possibly Ajax-y.
     *
     * Also, if the notice input looks like a command, it will run the
     * command and show the results -- again, possibly ajaxy.
     *
     * @return void
     */
    protected function handlePost()
    {
        parent::handlePost();

        assert($this->scoped); // XXX: maybe an error instead...
        $user = $this->scoped->getUser();
        $content = $this->trimmed('status_textarea');
        $options = array();
        Event::handle('StartSaveNewNoticeWeb', array($this, $user, &$content, &$options));

        if (!$content) {
            // TRANS: Client error displayed trying to send a notice without content.
            $this->clientError(_('No content!'));
        }

        $inter = new CommandInterpreter();

        $cmd = $inter->handle_command($user, $content);

        if ($cmd) {
            if (StatusNet::isAjax()) {
                $cmd->execute(new AjaxWebChannel($this));
            } else {
                $cmd->execute(new WebChannel($this));
            }
            return;
        }

        $content_shortened = $user->shortenLinks($content);
        if (Notice::contentTooLong($content_shortened)) {
            // TRANS: Client error displayed when the parameter "status" is missing.
            // TRANS: %d is the maximum number of character for a notice.
            $this->clientError(sprintf(_m('That\'s too long. Maximum notice size is %d character.',
                                          'That\'s too long. Maximum notice size is %d characters.',
                                          Notice::maxContent()),
                                       Notice::maxContent()));
        }

        $replyto = intval($this->trimmed('inreplyto'));
        if ($replyto) {
            $options['reply_to'] = $replyto;
        }

        $upload = null;
        $upload = MediaFile::fromUpload('attach');

        if (isset($upload)) {

            if (Event::handle('StartSaveNewNoticeAppendAttachment', array($this, $upload, &$content_shortened, &$options))) {
                $content_shortened .= ' ' . $upload->shortUrl();
            }
            Event::handle('EndSaveNewNoticeAppendAttachment', array($this, $upload, &$content_shortened, &$options));

            if (Notice::contentTooLong($content_shortened)) {
                $upload->delete();
                // TRANS: Client error displayed exceeding the maximum notice length.
                // TRANS: %d is the maximum length for a notice.
                $this->clientError(sprintf(_m('Maximum notice size is %d character, including attachment URL.',
                                              'Maximum notice size is %d characters, including attachment URL.',
                                              Notice::maxContent()),
                                           Notice::maxContent()));
            }
        }

        if ($user->shareLocation()) {
            // use browser data if checked; otherwise profile data
            if ($this->arg('notice_data-geo')) {
                $locOptions = Notice::locationOptions($this->trimmed('lat'),
                                                      $this->trimmed('lon'),
                                                      $this->trimmed('location_id'),
                                                      $this->trimmed('location_ns'),
                                                      $this->scoped);
            } else {
                $locOptions = Notice::locationOptions(null,
                                                      null,
                                                      null,
                                                      null,
                                                      $this->scoped);
            }

            $options = array_merge($options, $locOptions);
        }

        $author_id = $this->scoped->id;
        $text      = $content_shortened;

        // Does the heavy-lifting for getting "To:" information

        ToSelector::fillOptions($this, $options);

        if (Event::handle('StartNoticeSaveWeb', array($this, &$author_id, &$text, &$options))) {

            $notice = Notice::saveNew($this->scoped->id, $content_shortened, 'web', $options);

            if (isset($upload)) {
                $upload->attachToNotice($notice);
            }

            Event::handle('EndNoticeSaveWeb', array($this, $notice));
        }
        Event::handle('EndSaveNewNoticeWeb', array($this, $user, &$content_shortened, &$options));

        if (StatusNet::isAjax()) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title after sending a notice.
            $this->element('title', null, _('Notice posted'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showNotice($notice);
            $this->elementEnd('body');
            $this->endHTML();
            exit;
        } else {
            $returnto = $this->trimmed('returnto');

            if ($returnto) {
                $url = common_local_url($returnto,
                                        array('nickname' => $this->scoped->nickname));
            } else {
                $url = common_local_url('shownotice',
                                        array('notice' => $notice->id));
            }
            common_redirect($url, 303);
        }
    }

    /**
     * Show an Ajax-y error message
     *
     * Goes back to the browser, where it's shown in a popup.
     *
     * @param string $msg Message to show
     *
     * @return void
     */
    function ajaxErrorMsg($msg)
    {
        $this->startHTML('text/xml;charset=utf-8', true);
        $this->elementStart('head');
        // TRANS: Page title after an AJAX error occurs on the send notice page.
        $this->element('title', null, _('Ajax Error'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $this->element('p', array('id' => 'error'), $msg);
        $this->elementEnd('body');
        $this->elementEnd('html');
    }

    /**
     * Show an Ajax-y notice form
     *
     * Goes back to the browser, where it's shown in a popup.
     *
     * @param string $msg Message to show
     *
     * @return void
     */
    function ajaxShowForm()
    {
        $this->startHTML('text/xml;charset=utf-8', true);
        $this->elementStart('head');
        // TRANS: Title for form to send a new notice.
        $this->element('title', null, _m('TITLE','New notice'));
        $this->elementEnd('head');
        $this->elementStart('body');

        $form = new NoticeForm($this);
        $form->show();

        $this->elementEnd('body');
        $this->elementEnd('html');
    }

    /**
     * Formerly page output
     *
     * This used to be the whole page output; now that's been largely
     * subsumed by showPage. So this just stores an error message, if
     * it was passed, and calls showPage.
     *
     * Note that since we started doing Ajax output, this page is rarely
     * seen.
     *
     * @param string  $msg     An error/info message, if any
     * @param boolean $success false for error indication, true for info
     *
     * @return void
     */
    function showForm($msg=null, $success=false)
    {
        if (StatusNet::isAjax()) {
            if ($msg) {
                $this->ajaxErrorMsg($msg);
            } else {
                $this->ajaxShowForm();
            }
            return;
        }

        parent::showForm($msg, $success);
    }

    /**
     * // XXX: Should we be showing the notice form with microapps here?
     *
     * Overload for replies or bad results
     *
     * We show content in the notice form if there were replies or results.
     *
     * @return void
     */
    function showNoticeForm()
    {
        $content = $this->trimmed('status_textarea');
        if (!$content) {
            $replyto = $this->trimmed('replyto');
            $inreplyto = $this->trimmed('inreplyto');
            $profile = Profile::getKV('nickname', $replyto);
            if ($profile) {
                $content = '@' . $profile->nickname . ' ';
            }
        } else {
            // @fixme most of these bits above aren't being passed on above
            $inreplyto = null;
        }

        $this->elementStart('div', 'input_forms');
        $this->elementStart(
            'div',
            array(
                'id'    => 'input_form_status',
                'class' => 'input_form current nonav'
            )
        );

        $notice_form = new NoticeForm(
            $this,
            array(
                'content' => $content,
                'inreplyto' => $inreplyto
            )
        );

        $notice_form->show();

        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    /**
     * Show an error message
     *
     * Shows an error message if there is one.
     *
     * @return void
     *
     * @todo maybe show some instructions?
     */
    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', array('id' => 'error'), $this->msg);
        }
    }

    /**
     * Output a notice
     *
     * Used to generate the notice code for Ajax results.
     *
     * @param Notice $notice Notice that was saved
     *
     * @return void
     */
    function showNotice($notice)
    {
        $nli = new NoticeListItem($notice, $this);
        $nli->show();
    }
}
