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
        return 'form_notice ajax-notice';
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
        // TRANS: Form legend for direct notice.
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
        // TRANS: Label entry in drop-down selection box in direct-message inbox/outbox.
        // TRANS: This is the default entry in the drop-down box, doubling as instructions
        // TRANS: and a brake against accidental submissions with the first user in the list.
        $mutual[0] = _('Select recipient:');

        while ($mutual_users->fetch()) {
            if ($mutual_users->id != $user->id) {
                $mutual[$mutual_users->id] = $mutual_users->nickname;
            }
        }

        $mutual_users->free();
        unset($mutual_users);

        if (count($mutual) == 1) {
            // TRANS: Entry in drop-down selection box in direct-message inbox/outbox when no one is available to message.
            $mutual[0] = _('No mutual subscribers.');
        }

        // TRANS: Dropdown label in direct notice form.
        $this->out->dropdown('to', _('To'), $mutual, null, false,
                             ($this->to) ? $this->to->id : null);

        $this->out->element('textarea', array('class' => 'notice_data-text',
                                              'cols' => 35,
                                              'rows' => 4,
                                              'name' => 'content'),
                            ($this->content) ? $this->content : '');

        $contentLimit = Message::maxContent();

        if ($contentLimit > 0) {
            $this->out->element('span',
                                array('class' => 'count'),
                                $contentLimit);
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
                                           // TRANS: Button text for sending a direct notice.
                                           'value' => _m('Send button for sending notice', 'Send')));
    }


    /**
     * Show the form
     *
     * Uses a recipe to output the form.
     *
     * @return void
     * @see Widget::show()
     */

    function show()
    {
        $this->elementStart('div', 'input_forms');
        $this->elementStart(
            'div',
            array(
                'id'    => 'input_form_direct',
                'class' => 'input_form current nonav'
            )
        );

        parent::show();

        $this->elementEnd('div');
        $this->elementEnd('div');

    }
}
