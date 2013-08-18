<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * PHP version 5
 *
 * @category  Action
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Takes parameters:
 *
 *    - feed: a profile ID
 *    - token: session token to prevent CSRF attacks
 *    - ajax: boolean; whether to return Ajax or full-browser results
 *
 * Only works if the current user is logged in.
 *
 * @category  Action
 * @package   StatusNet
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
class EditMirrorAction extends BaseMirrorAction
{
    /**
     * Check pre-requisites and instantiate attributes
     *
     * @param Array $args array of arguments (URL, GET, POST)
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->profile = $this->validateProfile($this->trimmed('profile'));

        $this->mirror = SubMirror::pkeyGet(array('subscriber' => $this->user->id,
                                                 'subscribed' => $this->profile->id));

        if (!$this->mirror) {
            // TRANS: Client error displayed when trying to edit an object that is not a feed mirror.
            $this->clientError(_m('Requested invalid profile to edit.'));
        }

        $this->style = $this->validateStyle($this->trimmed('style'));

        // DO NOT change to $this->boolean(), it will be wrong.
        // We're checking for the presence of the setting, not its value.
        $this->delete = (bool)$this->arg('delete');

        return true;
    }

    protected function validateStyle($style)
    {
        $allowed = array('repeat', 'copy');
        if (in_array($style, $allowed)) {
            return $style;
        } else {
            // TRANS: Client error displayed when providing invalid input when editing a mirror.
            $this->clientError(_m('Bad form data.'));
        }
    }

    function saveMirror()
    {
        $mirror = SubMirror::getMirror($this->user, $this->profile);
        if (!$mirror) {
            // TRANS: Client error thrown when a mirror request is made and no result is retrieved.
            $this->clientError(_m('The mirror request failed, because no result was retrieved.'));
        }

        if ($this->delete) {
            $mirror->delete();
            $oprofile = Ostatus_profile::getKV('profile_id', $this->profile->id);
            if ($oprofile) {
                $oprofile->garbageCollect();
            }
        } else if ($this->style != $mirror->style) {
            $orig = clone($mirror);
            $mirror->style = $this->style;
            $mirror->modified = common_sql_now();
            $mirror->update($orig);
        }
    }
}
