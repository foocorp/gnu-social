<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for subscribing to a search
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
 * @category  SearchSubPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Form for subscribing to a user
 *
 * @category SearchSubPlugin
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      UnsubscribeForm
 */
class SearchSubForm extends Form
{
    /**
     * Name of search to subscribe to
     */
    var $search = '';

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param string        $search     name of search to subscribe to
     */
    function __construct($out=null, $search=null)
    {
        parent::__construct($out);

        $this->search = $search;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'search-subscribe-' . $this->search;
    }


    /**
     * class of the form
     *
     * @return string of the form class
     */
    function formClass()
    {
        // class to match existing styles...
        return 'form_user_subscribe ajax';
    }


    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('searchsub', array('search' => $this->search));
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend.
        $this->out->element('legend', null, _m('Subscribe to this search'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden('subscribeto-' . $this->search,
                           $this->search,
                           'subscribeto');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('submit',
                           // TRANS: Button text for subscribing to a search.
                           _m('BUTTON','Subscribe'),
                           'submit',
                           null,
                           // TRANS: Button title for subscribing to a search.
                           _m('Subscribe to this search.'));
    }
}
