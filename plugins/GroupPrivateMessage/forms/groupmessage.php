<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Form for posting a group message
 *
 * PHP version 5
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
 * @category  GroupPrivateMessage
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Form for posting a group message
 *
 * @category  GroupPrivateMessage
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GroupMessageForm extends Form
{
    var $group;
    var $content;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out   Output context
     * @param User_group    $group Group to post to
     *
     * @todo add a drop-down list to post to any group
     */
    function __construct($out, $group, $content=null)
    {
        parent::__construct($out);

        $this->group   = $group;
        $this->content = $content;
    }

    /**
     * Action for the form
     */
    function action()
    {
        return common_local_url('newgroupmessage',
                                array('nickname' => $this->group->nickname));
    }

    /**
     * Legend for the form
     *
     * @param
     *
     * @return
     */
    function formLegend()
    {
        $this->out->element('legend',
                            null,
                            // TRANS: Form legend for sending private message to group %s.
                            sprintf(_m('Message to %s'), $this->group->nickname));
    }

    /**
     * id for the form
     *
     * @param
     *
     * @return
     */
    function id()
    {
        return 'form_notice-group-message';
    }

    /**
     * class for the form
     *
     * @param
     *
     * @return
     */
    function formClass()
    {
        return 'form_notice';
    }

    /**
     * Entry data
     *
     * @param
     *
     * @return
     */
    function formData()
    {
        $this->out->element('label', array('for' => 'notice_data-text',
                                           'id' => 'notice_data-text-label'),
                            // TRANS: Field label for private group message to group %s.
                            sprintf(_m('Direct message to %s'), $this->group->nickname));

        $this->out->element('textarea', array('id' => 'notice_data-text',
                                              'cols' => 35,
                                              'rows' => 4,
                                              'name' => 'content'),
                            ($this->content) ? $this->content : '');

        $contentLimit = Message::maxContent();

        if ($contentLimit > 0) {
            $this->out->elementStart('dl', 'form_note');
            // TRANS: Indicator for number of chatacters still available for notice.
            $this->out->element('dt', null, _m('Available characters'));
            $this->out->element('dd', array('class' => 'count'),
                                $contentLimit);
            $this->out->elementEnd('dl');
        }
    }

    /**
     * Legend for the form
     *
     * @param
     *
     * @return
     */
    function formActions()
    {
        $this->out->element('input', array('id' => 'notice_action-submit',
                                           'class' => 'submit',
                                           'name' => 'message_send',
                                           'type' => 'submit',
                                           // TRANS: Send button text for sending private group notice.
                                           'value' => _m('Send button for sending notice', 'Send')));
    }
}
