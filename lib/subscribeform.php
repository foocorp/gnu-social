<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for subscribing to a user
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
 * Form for subscribing to a user
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      UnsubscribeForm
 */
class SubscribeForm extends Form
{
    /**
     * Profile of user to subscribe to
     */
    var $profile = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param Profile       $profile profile of user to subscribe to
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
        return 'subscribe-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */
    function formClass()
    {
        return 'form_user_subscribe ajax';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('subscribe');
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form of form to subscribe to a user.
        $this->out->element('legend', null, _('Subscribe to this user'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden('subscribeto-' . $this->profile->id,
                           $this->profile->id,
                           'subscribeto');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button text to subscribe to a user.
        $this->out->submit('submit', _m('BUTTON','Subscribe'), 'submit', null,
                           // TRANS: Button title to subscribe to a user.
                           _('Subscribe to this user.'));
    }
}
