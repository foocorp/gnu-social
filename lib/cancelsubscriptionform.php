<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for leaving a group
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
 * Form for leaving a group
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
class CancelSubscriptionForm extends Form
{
    /**
     * user being subscribed to
     */

    var $profile = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out   output channel
     * @param Profile       $profile being subscribed to
     */
    function __construct($out=null, $profile=null)
    {
        parent::__construct($out);

        $this->profile = $profile;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */
    function id()
    {
        return 'subscription-cancel-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */
    function formClass()
    {
        return 'form_unsubscribe ajax';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('cancelsubscription',
                                array(),
                                array('id' => $this->profile->id));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden('unsubscribeto-' . $this->profile->id,
                           $this->profile->id,
                           'unsubscribeto');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button text for form action to cancel a subscription request.
        $this->out->submit('submit', _m('BUTTON','Cancel subscription request'));
    }
}
