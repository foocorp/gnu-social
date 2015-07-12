<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for repeating a notice
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Form for repeating a notice
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class RepeatForm extends Form
{
    /**
     * Notice to repeat
     */
    var $notice = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out    output channel
     * @param Notice        $notice notice to repeat
     */
    function __construct($out=null, $notice=null)
    {
        parent::__construct($out);

        $this->notice = $notice;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'repeat-' . $this->notice->id;
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('repeat');
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: For legend for notice repeat form.
        $this->out->element('legend', null, _('Repeat this notice?'));
    }

    /**
     * Data elements
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden('notice-n'.$this->notice->id,
                           $this->notice->id,
                           'notice');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('repeat-submit-' . $this->notice->id,
                           // TRANS: Button text to repeat a notice on notice repeat form.
                           _m('BUTTON','Confirm repeat'), 'submit', null,
                           // TRANS: Button title to repeat a notice on notice repeat form.
                           _('Repeat this notice.'));
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_repeat';
    }
}
