<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for editing a group
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
 * Form for editing a group
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
class GroupEditForm extends Form
{
    /**
     * group for user to join
     */
    var $group = null;

    /**
     * Constructor
     *
     * @param Action     $out   output channel
     * @param User_group $group group to join
     */
    function __construct($out=null, $group=null)
    {
        parent::__construct($out);

        $this->group = $group;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */
    function id()
    {
        if ($this->group) {
            return 'form_group_edit-' . $this->group->id;
        } else {
            return 'form_group_add';
        }
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */
    function formClass()
    {
        return 'form_settings';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        if ($this->group) {
            return common_local_url('editgroup',
                                    array('nickname' => $this->group->nickname));
        } else {
            return common_local_url('newgroup');
        }
    }

    /**
     * Name of the form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend for group edit form.
        $this->out->element('legend', null, _('Create a new group'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        if ($this->group) {
            $id = $this->group->id;
            $nickname = $this->group->nickname;
            $fullname = $this->group->fullname;
            $homepage = $this->group->homepage;
            $description = $this->group->description;
            $location = $this->group->location;
        } else {
            $id = '';
            $nickname = '';
            $fullname = '';
            $homepage = '';
            $description = '';
            $location = '';
        }

        $this->out->elementStart('ul', 'form_data');
        if (Event::handle('StartGroupEditFormData', array($this))) {
            $this->out->elementStart('li');
            $this->out->hidden('groupid', $id);
            // TRANS: Field label on group edit form.
            $this->out->input('newnickname', _('Nickname'),
                              ($this->out->arg('newnickname')) ? $this->out->arg('newnickname') : $nickname,
                              // TRANS: Field title on group edit form.
                              _('1-64 lowercase letters or numbers, no punctuation or spaces.'));
            $this->out->elementEnd('li');
            $this->out->elementStart('li');
            // TRANS: Field label on group edit form.
            $this->out->input('fullname', _('Full name'),
                              ($this->out->arg('fullname')) ? $this->out->arg('fullname') : $fullname);
            $this->out->elementEnd('li');
            $this->out->elementStart('li');
            // TRANS: Field label on group edit form; points to "more info" for a group.
            $this->out->input('homepage', _('Homepage'),
                              ($this->out->arg('homepage')) ? $this->out->arg('homepage') : $homepage,
                              // TRANS: Field title on group edit form.
                              _('URL of the homepage or blog of the group or topic.'));
            $this->out->elementEnd('li');
            $this->out->elementStart('li');
            $desclimit = User_group::maxDescription();
            if ($desclimit == 0) {
                // TRANS: Text area title for group description when there is no text limit.
                $descinstr = _('Describe the group or topic.');
            } else {
                // TRANS: Text area title for group description.
                // TRANS: %d is the number of characters available for the description.
                $descinstr = sprintf(_m('Describe the group or topic in %d character or less.',
                                        'Describe the group or topic in %d characters or less.',
                                        $desclimit),
                                     $desclimit);
            }
            // TRANS: Text area label on group edit form; contains description of group.
            $this->out->textarea('description', _('Description'),
                                 ($this->out->arg('description')) ? $this->out->arg('description') : $description,
                                 $descinstr);
            $this->out->elementEnd('li');
            $this->out->elementStart('li');
            // TRANS: Field label on group edit form.
            $this->out->input('location', _('Location'),
                              ($this->out->arg('location')) ? $this->out->arg('location') : $location,
                              // TRANS: Field title on group edit form.
                              _('Location for the group, if any, like "City, State (or Region), Country".'));
            $this->out->elementEnd('li');
            if (common_config('group', 'maxaliases') > 0) {
                $aliases = (empty($this->group)) ? array() : $this->group->getAliases();
                $this->out->elementStart('li');
                // TRANS: Field label on group edit form.
                $this->out->input('aliases', _('Aliases'),
                                  ($this->out->arg('aliases')) ? $this->out->arg('aliases') :
                                  (!empty($aliases)) ? implode(' ', $aliases) : '',
                                  // TRANS: Input field title for group aliases.
                                  // TRANS: %d is the maximum number of group aliases available.
                                  sprintf(_m('Extra nicknames for the group, separated with commas or spaces. Maximum %d alias allowed.',
                                             'Extra nicknames for the group, separated with commas or spaces. Maximum %d aliases allowed.',
                                             common_config('group', 'maxaliases')),
                                          common_config('group', 'maxaliases')));;
                $this->out->elementEnd('li');
            }
            $this->out->elementStart('li');
            // TRANS: Checkbox field label on group edit form to mark a group private.
            $this->out->checkbox('private', _m('LABEL','Private'), 
                                  ($this->out->arg('private')) ? $this->out->arg('private') :
                                 ((!empty($this->group)) ? $this->group->isPrivate() : false),
                                 // TRANS: Checkbox field title on group edit form to mark a group private.
                                 _('New members must be approved by admin and all posts are forced to be private.'));
            $this->out->elementEnd('li');
            Event::handle('EndGroupEditFormData', array($this));
        }
        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Text for save button on group edit form.
        $this->out->submit('submit', _m('BUTTON','Save'));
    }
}
