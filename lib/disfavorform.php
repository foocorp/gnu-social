<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for disfavoring a notice
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
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Form for disfavoring a notice
 *
 * @category Form
 * @package  GNUsocial
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://www.gnu.org/software/social/
 *
 * @see      FavorForm
 */
class DisfavorForm extends Form
{
    /**
     * Notice to disfavor
     */
    var $notice = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out    output channel
     * @param Notice        $notice notice to disfavor
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
        return 'disfavor-' . $this->notice->id;
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('disfavor');
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend for removing the favourite status for a favourite notice.
        $this->out->element('legend', null, _('Disfavor this notice'));
    }

    /**
     * Data elements
     *
     * @return void
     */

    function formData()
    {
        if (Event::handle('StartDisFavorNoticeForm', array($this, $this->notice))) {
            $this->out->hidden('notice-n'.$this->notice->id,
                               $this->notice->id,
                               'notice');
            Event::handle('EndDisFavorNoticeForm', array($this, $this->notice));
        }
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $this->out->submit('disfavor-submit-' . $this->notice->id,
                           // TRANS: Button text for removing the favourite status for a favourite notice.
                           _m('BUTTON','Disfavor favorite'),
                           'submit',
                           null,
                           // TRANS: Button title for removing the favourite status for a favourite notice.
                           _('Remove this notice from your list of favorite notices.'));
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_disfavor ajax';
    }
}
