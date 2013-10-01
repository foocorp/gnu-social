<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @package   StatusNet
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class EditMirrorForm extends Form
{
    function __construct($action, $profile)
    {
        parent::__construct($action);

        $this->profile = clone($profile);
        $this->user = common_current_user();
        $this->mirror = SubMirror::pkeyGet(array('subscriber' => $this->user->id,
                                                 'subscribed' => $this->profile->id));
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
        $this->out->elementStart('fieldset');

        $this->out->hidden('profile', $this->profile->id);

        $this->out->elementStart('div', array('style' => 'float: left; width: 80px;'));
        $img = $this->profile->avatarUrl(AVATAR_STREAM_SIZE);
        $feed = $this->getFeed($this->profile);
        $this->out->elementStart('a', array('href' => $this->profile->profileurl));
        $this->out->element('img', array('src' => $img, 'style' => 'float: left'));
        $this->out->elementEnd('a');
        $this->out->elementEnd('div');


        $this->out->elementStart('div', array('style' => 'margin-left: 80px; margin-right: 20px'));
        $this->out->elementStart('p');
        $this->out->elementStart('div');
        $this->out->element('a', array('href' => $this->profile->profileurl), $this->profile->getBestName());
        $this->out->elementEnd('div');
        $this->out->elementStart('div');
        if ($feed) {
            // XXX: Why the hard coded space?
            // TRANS: Field label (URL expectected).
            $this->out->text(_m('LABEL', 'Remote feed:') . ' ');
            //$this->out->element('a', array('href' => $feed), $feed);
            $this->out->element('input', array('value' => $feed, 'readonly' => 'readonly', 'style' => 'width: 100%'));
        } else {
            // TRANS: Field label.
            $this->out->text(_m('LABEL', 'Local user'));
        }
        $this->out->elementEnd('div');
        $this->out->elementEnd('p');

        $this->out->elementStart('fieldset', array('style' => 'margin-top: 20px'));
        // TRANS: Fieldset legend for feed mirror setting.
        $this->out->element('legend', false, _m('Mirroring style'));

        // TRANS: Feed mirror style (radio button option).
        $styles = array('repeat' => _m('Repeat: reference the original user\'s post (sometimes shows as "RT @blah")'),
                        // TRANS: Feed mirror style (radio button option).
                        'copy' => _m('Repost the content under my account'));
        foreach ($styles as $key => $label) {
            $this->out->elementStart('div');
            $attribs = array('type' => 'radio',
                             'value' => $key,
                             'name' => 'style',
                             'id' => $this->id() . '-style');
            if ($key == $this->mirror->style || ($key == 'repeat' && empty($this->mirror->style))) {
                $attribs['checked'] = 'checked';
            }
            $this->out->element('input', $attribs);
            $this->out->element('span', false, $label); // @todo FIXME: should be label, but the styles muck it up for now
            $this->out->elementEnd('div');

        }
        $this->out->elementEnd('fieldset');


        $this->out->elementStart('div');
        // TRANS: Button text to save feed mirror settings.
        $this->out->submit($this->id() . '-save', _m('BUTTON','Save'));
        $this->out->element('input', array('type' => 'submit',
                                           // TRANS: Button text to stop mirroring a feed.
                                           'value' => _m('BUTTON','Stop mirroring'),
                                           'name' => 'delete',
                                           'class' => 'submit'));
        $this->out->elementEnd('div');

        $this->out->elementEnd('div');
        $this->out->elementEnd('fieldset');
    }

    private function getFeed($profile)
    {
        // Ok this is a bit of a hack. ;)
        if (class_exists('Ostatus_profile')) {
            $oprofile = Ostatus_profile::getKV('profile_id', $profile->id);
            if ($oprofile) {
                return $oprofile->feeduri;
            }
        }
        var_dump('wtf');
        return false;
    }

    /**
     * ID of the form
     *
     * Should be unique on the page. Sub-classes should overload this
     * to show their own IDs.
     *
     * @return string ID of the form
     */
    function id()
    {
        return 'edit-mirror-form-' . $this->profile->id;
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
        return common_local_url('editmirror');
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_settings';
    }
}
