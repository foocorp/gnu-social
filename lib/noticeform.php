<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for posting a notice
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
 * Form for posting a notice
 *
 * Frequently-used form for posting a notice
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

class NoticeForm extends Form
{
    /**
     * Current action, used for returning to this page.
     */

    var $action = null;

    /**
     * Pre-filled content of the form
     */

    var $content = null;

    /**
     * The current user
     */

    var $user = null;

    /**
     * The notice being replied to
     */

    var $inreplyto = null;

    /**
     * Pre-filled location content of the form
     */

    var $lat;
    var $lon;
    var $location_id;
    var $location_ns;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param string        $action  action to return to, if any
     * @param string        $content content to pre-fill
     */

    function __construct($out=null, $action=null, $content=null, $user=null, $inreplyto=null, $lat=null, $lon=null, $location_id=null, $location_ns=null)
    {
        parent::__construct($out);

        $this->action  = $action;
        $this->content = $content;
        $this->inreplyto = $inreplyto;
        $this->lat = $lat;
        $this->lon = $lon;
        $this->location_id = $location_id;
        $this->location_ns = $location_ns;

        if ($user) {
            $this->user = $user;
        } else {
            $this->user = common_current_user();
        }

        $this->profile = $this->user->getProfile();

        if (common_config('attachments', 'uploads')) {
            $this->enctype = 'multipart/form-data';
        }
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        return 'form_notice';
    }

   /**
     * Class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_notice';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('newnotice');
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        $this->out->element('legend', null, _('Send a notice'));
    }

    /**
     * Data elements
     *
     * @return void
     */

    function formData()
    {
        if (Event::handle('StartShowNoticeFormData', array($this))) {
            $this->out->element('label', array('for' => 'notice_data-text',
                                               'id' => 'notice_data-text-label'),
                                sprintf(_('What\'s up, %s?'), $this->user->nickname));
            // XXX: vary by defined max size
            $this->out->element('textarea', array('id' => 'notice_data-text',
                                                  'cols' => 35,
                                                  'rows' => 4,
                                                  'name' => 'status_textarea'),
                                ($this->content) ? $this->content : '');

            $contentLimit = Notice::maxContent();

            if ($contentLimit > 0) {
                $this->out->elementStart('dl', 'form_note');
                $this->out->element('dt', null, _('Available characters'));
                $this->out->element('dd', array('id' => 'notice_text-count'),
                                    $contentLimit);
                $this->out->elementEnd('dl');
            }

            if (common_config('attachments', 'uploads')) {
                $this->out->element('label', array('for' => 'notice_data-attach'),_('Attach'));
                $this->out->element('input', array('id' => 'notice_data-attach',
                                                   'type' => 'file',
                                                   'name' => 'attach',
                                                   'title' => _('Attach a file')));
                $this->out->hidden('MAX_FILE_SIZE', common_config('attachments', 'file_quota'));
            }
            if ($this->action) {
                $this->out->hidden('notice_return-to', $this->action, 'returnto');
            }
            $this->out->hidden('notice_in-reply-to', $this->inreplyto, 'inreplyto');

            if ($this->user->shareLocation()) {
                $this->out->hidden('notice_data-lat', empty($this->lat) ? (empty($this->profile->lat) ? null : $this->profile->lat) : $this->lat, 'lat');
                $this->out->hidden('notice_data-lon', empty($this->lon) ? (empty($this->profile->lon) ? null : $this->profile->lon) : $this->lon, 'lon');
                $this->out->hidden('notice_data-location_id', empty($this->location_id) ? (empty($this->profile->location_id) ? null : $this->profile->location_id) : $this->location_id, 'location_id');
                $this->out->hidden('notice_data-location_ns', empty($this->location_ns) ? (empty($this->profile->location_ns) ? null : $this->profile->location_ns) : $this->location_ns, 'location_ns');

                $this->out->elementStart('div', array('id' => 'notice_data-geo_wrap',
                                                      'title' => common_local_url('geocode')));
                $this->out->checkbox('notice_data-geo', _('Share my location'), true);
                $this->out->elementEnd('div');
                $this->out->inlineScript(' var NoticeDataGeo_text = {'.
                    'ShareDisable: ' .json_encode(_('Do not share my location')).','.
                    'ErrorTimeout: ' .json_encode(_('Sorry, retrieving your geo location is taking longer than expected, please try again later')).
                    '}');
            }

            Event::handle('EndShowNoticeFormData', array($this));
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
                                           'name' => 'status_submit',
                                           'type' => 'submit',
                                           'value' => _m('Send button for sending notice', 'Send')));
    }
}
