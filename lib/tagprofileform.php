<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for tagging a profile with a peopletag
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
 * Form for tagging a profile with a peopletag
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
class TagProfileForm extends Form
{
    protected $target = null;

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
    function __construct(Action $action, Profile $target)
    {
        parent::__construct($action);

        $this->target = $target;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        return 'form_tagprofile_'.$target->id;
    }

   /**
     * Class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_tagprofile form_settings';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('tagprofile', array('id' => $this->target->id));
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend for notice form.
        $this->out->element('legend', null, sprintf(_m('ADDTOLIST', 'List %s'), $this->target->getNickname()));

    }

    /**
     * Data elements
     *
     * @return void
     */
    function formData()
    {
        if (Event::handle('StartShowTagProfileFormData', array($this))) {
            $this->hidden('token', common_session_token());  
            $this->hidden('id', $this->target->id);

            $this->elementStart('ul', 'form_data');
            $this->elementStart('li');

            $tags = Profile_tag::getTagsArray($this->out->getScoped()->id, $this->target->id, $this->out->getScoped()->id);
            // TRANS: Field label on list form.
            $this->input('tags', _m('LABEL','Lists'),
                         ($this->arg('tags')) ? $this->arg('tags') : implode(' ', $tags),
                         // TRANS: Field title on list form.
                         _('Lists for this user (letters, numbers, -, ., and _), comma- or space- separated.'));
            $this->elementEnd('li');
            $this->elementEnd('ul');
            Event::handle('EndShowTagProfileFormData', array($this));
        }
    }

    function formActions()
    {
        // TRANS: Button text to save lists.
        $this->out->submit('save', _m('BUTTON', 'Save'));
    }
}
