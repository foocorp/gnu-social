<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for forms
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
 * @category  Widget
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

require_once INSTALLDIR.'/lib/widget.php';

/**
 * Base class for forms
 *
 * We have a lot of common forms (subscribe, fave, delete) and this superclass
 * lets us abstract out the basic features of the form.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */

class Form extends Widget
{
    var $enctype = null;

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
        $attributes = array('id' => $this->id(),
            'class' => $this->formClass(),
            'method' => $this->method(),
            'action' => $this->action());

        if (!empty($this->enctype)) {
            $attributes['enctype'] = $this->enctype;
        }
        $this->out->elementStart('form', $attributes);
        $this->out->elementStart('fieldset');
        $this->formLegend();
        $this->sessionToken();
        $this->formData();
        $this->formActions();
        $this->out->elementEnd('fieldset');
        $this->out->elementEnd('form');
    }

    /**
     * Include a session token for CSRF protection
     *
     * @return void
     */

    function sessionToken()
    {
        if (strtolower($this->method()) == 'post') {
            $this->out->hidden('token-' . $this->id() ?: common_random_hexstr(3), common_session_token(), 'token');
        }
    }

    /**
     * Name of the form
     *
     * Sub-classes should overload this with the name of their form.
     *
     * @return void
     */

    function formLegend()
    {
    }

    /**
     * Visible or invisible data elements
     *
     * Display the form fields that make up the data of the form.
     * Sub-classes should overload this to show their data.
     *
     * @return void
     */

    function formData()
    {
    }

    /**
     * HTTP method used to submit the form
     *
     * Defaults to post. Subclasses can override if they need to.
     *
     * @return string the method to use for submitting
     */
     function method()
     {
         return 'post';
     }

    /**
     * Buttons for form actions
     *
     * Submit and cancel buttons (or whatever)
     * Sub-classes should overload this to show their own buttons.
     *
     * @return void
     */

    function formActions()
    {
    }

    /**
     * ID of the form
     *
     * Should be unique on the page. Sub-classes should overload this
     * to show their own IDs.
     *
     * @return int ID of the form
     */

    function id()
    {
        return null;
    }

    /**
     * Action of the form.
     *
     * URL to post to. Should be overloaded by subclasses to give
     * somewhere to post to.
     *
     * @return string URL to post to
     */

    function action()
    {
    }

    /**
     * Class of the form. May include space-separated list of multiple classes.
     *
     * If 'ajax' is included, the form will automatically be submitted with
     * an 'ajax=1' parameter added, and the resulting form or error message
     * will replace the form after submission.
     *
     * It's up to you to make sure that the target action supports this!
     *
     * @return string the form's class
     */

    function formClass()
    {
        return 'form';
    }

    function li($class=null)
    {
        $this->out->elementStart('li', $class);
    }

    function unli()
    {
        $this->out->elementEnd('li');
    }
}
