<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for posting a notice from within the Facebook App.
 *
 * This is a stripped down version of the normal NoticeForm (sans
 * location stuff and media upload stuff). I'm not sure we can share the
 * location (from FB) and they don't allow posting multipart form data
 * to Facebook canvas pages, so that won't work anyway. --Zach
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
 * @category  Form
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/form.php';

/**
 * Form for posting a notice from within the Facebook app
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Zach Copey <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */
class FacebookNoticeForm extends Form
{
    /**
     * Current action, used for returning to this page.
     */
    var $action = null;

    /**
     * Pre-filled content of the form
     */
    var $content = null;

    /**
     * The current user
     */
    var $user = null;

    /**
     * The notice being replied to
     */
    var $inreplyto = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param string        $action  action to return to, if any
     * @param string        $content content to pre-fill
     */
    function __construct($out=null, $action=null, $content=null, $post_action=null, $user=null, $inreplyto=null)
    {
        parent::__construct($out);

        $this->action  = $action;
        $this->post_action = $post_action;
        $this->content = $content;
        $this->inreplyto = $inreplyto;

        if ($user) {
            $this->user = $user;
        } else {
            $this->user = common_current_user();
        }

        // Note: Facebook doesn't allow multipart/form-data posting to
        // canvas pages, so don't try to set it--no file uploads, at
        // least not this way.  It can be done using multiple servers
        // and iFrames, but it's a pretty hacky process.
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */
    function id()
    {
        return 'form_notice';
    }

   /**
     * Class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_notice';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return $this->post_action;
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Legend.
        $this->out->element('legend', null, _m('Send a notice'));
    }

    /**
     * Data elements
     *
     * @return void
     */
    function formData()
    {
        if (Event::handle('StartShowNoticeFormData', array($this))) {
            $this->out->element('label', array('for' => 'notice_data-text'),
                                // TRANS: Field label.
                                sprintf(_m('What\'s up, %s?'), $this->user->nickname));
            // XXX: vary by defined max size
            $this->out->element('textarea', array('id' => 'notice_data-text',
                                                  'cols' => 35,
                                                  'rows' => 4,
                                                  'name' => 'status_textarea'),
                                ($this->content) ? $this->content : '');

            $contentLimit = Notice::maxContent();

            if ($contentLimit > 0) {
                $this->out->elementStart('dl', 'form_note');
                $this->out->element('dt', null, _m('Available characters'));
                $this->out->element('dd', array('id' => 'notice_text-count'),
                                    $contentLimit);
                $this->out->elementEnd('dl');
            }

            if ($this->action) {
                $this->out->hidden('notice_return-to', $this->action, 'returnto');
            }
            $this->out->hidden('notice_in-reply-to', $this->inreplyto, 'inreplyto');

            Event::handle('StartShowNoticeFormData', array($this));
        }
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->element('input', array('id' => 'notice_action-submit',
                                           'class' => 'submit',
                                           'name' => 'status_submit',
                                           'type' => 'submit',
                                           // TRANS: Button text.
                                           'value' => _m('BUTTON','Send')));
    }
}
