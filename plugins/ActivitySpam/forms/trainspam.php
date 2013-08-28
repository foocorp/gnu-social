<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Toggle indicating ham, click to train as spam
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
 * @category  Spam
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
 * Form 
 *
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class TrainSpamForm extends Form {

    var $notice  = null;

    function __construct($out, $notice) {
        parent::__construct($out);
        $this->notice = $notice;
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
        return _("Train spam");
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
        $this->hidden('notice', $this->notice->id);
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
        $this->submit('train-spam-submit-' . $this->notice->id,
                      _('Train spam'),
                      'submit',
                      null,
                      _("Mark as spam"));
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
        return 'train-spam-' . $this->notice->id;
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
        return common_local_url('train', array('category' => 'spam'));
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
        return 'form-train-spam ajax';
    }
}
