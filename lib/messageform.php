<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for posting a direct message
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/form.php';

/**
 * Form for posting a direct message
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */

class MessageForm extends Form
{
    /**
     * User to send a direct message to
     */

    var $to = null;

    /**
     * Pre-filled content of the form
     */

    var $content = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param User          $to      user to send a message to
     * @param string        $content content to pre-fill
     */

    function __construct($out=null, $to=null, $content=null)
    {
        parent::__construct($out);

        $this->to      = $to;
        $this->content = $content;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        return 'form_notice-direct';
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
        return common_local_url('newmessage');
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        $this->out->element('legend', null, _('Send a direct notice'));
    }

    /**
     * Data elements
     *
     * @return void
     */

    function formData()
    {
        $user = common_current_user();

        $mutual_users = $user->mutuallySubscribedUsers();

        $mutual = array();
        // TRANS Label entry in drop-down selection box in direct-message inbox/outbox. This is the default entry in the drop-down box, doubling as instructions and a brake against accidental submissions with the first user in the list.
        $mutual[0] = _('Select recipient:');

        while ($mutual_users->fetch()) {
            if ($mutual_users->id != $user->id) {
                $mutual[$mutual_users->id] = $mutual_users->nickname;
            }
        }

        $mutual_users->free();
        unset($mutual_users);

        if (count($mutual) == 1) {
            // TRANS Entry in drop-down selection box in direct-message inbox/outbox when no one is available to message.
            $mutual[0] = _('No mutual subscribers.');
        }

        $this->out->dropdown('to', _('To'), $mutual, null, false,
                             ($this->to) ? $this->to->id : null);

        $this->out->element('textarea', array('id' => 'notice_data-text',
                                              'cols' => 35,
                                              'rows' => 4,
                                              'name' => 'content'),
                            ($this->content) ? $this->content : '');

        $contentLimit = Message::maxContent();

        if ($contentLimit > 0) {
            $this->out->elementStart('dl', 'form_note');
            $this->out->element('dt', null, _('Available characters'));
            $this->out->element('dd', array('id' => 'notice_text-count'),
                                $contentLimit);
            $this->out->elementEnd('dl');
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
                                           'name' => 'message_send',
                                           'type' => 'submit',
                                           'value' => _m('Send button for sending notice', 'Send')));
    }
}
