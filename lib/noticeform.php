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

if (!defined('GNUSOCIAL')) { exit(1); }

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
    var $actionName = null;

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

    /** select this group from the drop-down by default. */
    var $to_group;

    /** select this user from the drop-down by default. */
    var $to_profile;

    /** Pre-click the private checkbox. */
    var $private;

    /**
     * Constructor
     *
     * @param Action $action  Action we're being embedded into
     * @param array  $options Array of optional parameters
     *                        'user' a user instead of current
     *                        'content' notice content
     *                        'inreplyto' ID of notice to reply to
     *                        'lat' Latitude
     *                        'lon' Longitude
     *                        'location_id' ID of location
     *                        'location_ns' Namespace of location
     */
    function __construct($action, $options=null)
    {
        // XXX: ??? Is this to keep notice forms distinct?
        // Do we have to worry about sub-second race conditions?
        // XXX: Needs to be above the parent::__construct() call...?

        $this->id_suffix = rand();

        parent::__construct($action);

        if (is_null($options)) {
            $options = array();
        }

        $this->actionName  = $action->trimmed('action');

        $prefill = array('content', 'inreplyto', 'lat', 
                         'lon', 'location_id', 'location_ns',
                         'to_group', 'to_profile', 'private');

        foreach ($prefill as $fieldName) {
            if (array_key_exists($fieldName, $options)) {
                $this->$fieldName = $options[$fieldName];
            }
        }

        // Prefill the profile if we're replying

        if (empty($this->to_profile) &&
            !empty($this->inreplyto)) {
            $notice = Notice::getKV('id', $this->inreplyto);
            if (!empty($notice)) {
                $this->to_profile = $notice->getProfile();
            }
        }

        if (array_key_exists('user', $options)) {
            $this->user = $options['user'];
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
        return 'form_notice_' . $this->id_suffix;
    }

   /**
     * Class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_notice ajax-notice';
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
        // TRANS: Form legend for notice form.
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
                                // TRANS: Title for notice label. %s is the user's nickname.
                                sprintf(_('What\'s up, %s?'), $this->user->nickname));
            // XXX: vary by defined max size
            $this->out->element('textarea', array('class' => 'notice_data-text',
                                                  'required' => 'required',
                                                  'placeholder' => _('Share your status...'),
                                                  'cols' => 35,
                                                  'rows' => 4,
                                                  'name' => 'status_textarea'),
                                ($this->content) ? $this->content : '');

            $contentLimit = Notice::maxContent();

            if ($contentLimit > 0) {
                $this->out->element('span',
                                    array('class' => 'count'),
                                    $contentLimit);
            }

            if (common_config('attachments', 'uploads')) {
                $this->out->hidden('MAX_FILE_SIZE', common_config('attachments', 'file_quota'));
                $this->out->elementStart('label', array('class' => 'notice_data-attach'));
                // TRANS: Input label in notice form for adding an attachment.
                $this->out->text(_('Attach'));
                $this->out->element('input', array('class' => 'notice_data-attach',
                                                   'type' => 'file',
                                                   'name' => 'attach',
                                                   // TRANS: Title for input field to attach a file to a notice.
                                                   'title' => _('Attach a file.')));
                $this->out->elementEnd('label');
            }
            if (!empty($this->actionName)) {
                $this->out->hidden('notice_return-to', $this->actionName, 'returnto');
            }
            $this->out->hidden('notice_in-reply-to', $this->inreplyto, 'inreplyto');

            $this->out->elementStart('div', 'to-selector');
            $toWidget = new ToSelector($this->out,
                                       $this->user,
                                       (!empty($this->to_group) ? $this->to_group : $this->to_profile));

            $toWidget->show();
            $this->out->elementEnd('div');

            if ($this->profile->shareLocation()) {
                $this->out->hidden('notice_data-lat', empty($this->lat) ? (empty($this->profile->lat) ? null : $this->profile->lat) : $this->lat, 'lat');
                $this->out->hidden('notice_data-lon', empty($this->lon) ? (empty($this->profile->lon) ? null : $this->profile->lon) : $this->lon, 'lon');
                $this->out->hidden('notice_data-location_id', empty($this->location_id) ? (empty($this->profile->location_id) ? null : $this->profile->location_id) : $this->location_id, 'location_id');
                $this->out->hidden('notice_data-location_ns', empty($this->location_ns) ? (empty($this->profile->location_ns) ? null : $this->profile->location_ns) : $this->location_ns, 'location_ns');

                $this->out->elementStart('div', array('class' => 'notice_data-geo_wrap',
                                                      'data-api' => common_local_url('geocode')));

                // @fixme checkbox method allows no way to change the id without changing the name
                //// TRANS: Checkbox label to allow sharing geo location in notices.
                //$this->out->checkbox('notice_data-geo', _('Share my location'), true);
                $this->out->elementStart('label', 'notice_data-geo');
                $this->out->element('input', array(
                    'name' => 'notice_data-geo',
                    'type' => 'checkbox',
                    'class' => 'checkbox',
                    'id' => $this->id() . '-notice_data-geo',
                    'checked' => true, // ?
                ));
                $this->out->text(' ');
                // TRANS: Field label to add location to a notice.
                $this->out->text(_('Share my location'));
                $this->out->elementEnd('label');
                               
                $this->out->elementEnd('div');
                // TRANS: Text to not share location for a notice in notice form.
                $share_disable_text = _('Do not share my location');
                // TRANS: Timeout error text for location retrieval in notice form.
                $error_timeout_text = _('Sorry, retrieving your geo location is taking longer than expected, please try again later');
                $this->out->inlineScript(' var NoticeDataGeo_text = {'.
                    'ShareDisable: ' .json_encode($share_disable_text).','.
                    'ErrorTimeout: ' .json_encode($error_timeout_text).
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
                                           // TRANS: Button text for sending notice.
                                           'value' => _m('BUTTON', 'Send')));
    }
}
