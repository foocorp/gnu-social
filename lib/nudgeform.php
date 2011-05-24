<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for nudging a user
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
 * Form for nudging a user
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      DisfavorForm
 */
class NudgeForm extends Form
{
    /**
     * Profile of user to nudge
     */
    var $profile = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param Profile       $profile profile of user to nudge
     */
    function __construct($out=null, $profile=null)
    {
        parent::__construct($out);

        $this->profile = $profile;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'form_user_nudge';
    }


    /**
     * class of the form
     *
     * @return string of the form class
     */
    function formClass()
    {
        return 'form_user_nudge ajax';
    }


    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('nudge',
                                array('nickname' => $this->profile->nickname));
    }


    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend of form to nudge/ping another user.
        $this->out->element('legend', null, _('Nudge this user'));
    }


    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('submit',
                           // TRANS: Button text to nudge/ping another user.
                           _m('BUTTON','Nudge'),
                           'submit',
                           null,
                           // TRANS: Button title to nudge/ping another user.
                           _('Send a nudge to this user.'));
    }
}
