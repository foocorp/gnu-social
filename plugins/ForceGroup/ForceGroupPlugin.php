<?php
/*
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
 */

/**
 * @package ForceGroupPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) { exit(1); }

class ForceGroupPlugin extends Plugin
{
    /**
     * Members of these groups will have all their posts mirrored into
     * the group even if they don't explicitly mention it.
     *
     * List by local nickname.
     */
    public $post = array();

    /**
     * New user registrations will automatically join these groups on
     * registration. They're not prevented from leaving, however.
     *
     * List by local nickname.
     */
    public $join = array();

    /**
     * If poster is in one of the forced groups, make sure their notice
     * gets saved into that group even if not explicitly mentioned.
     *
     * @param Notice $notice
     * @return boolean event hook return
     */
    function onStartNoticeDistribute($notice)
    {
        $profile = $notice->getProfile();
        foreach ($this->post as $nickname) {
            $group = User_group::getForNickname($nickname);
            if ($group && $profile->isMember($group)) {
                $notice->addToGroupInbox($group);
            }
        }
        return true;
    }

    public function onEndUserRegister(Profile $profile)
    {
        foreach ($this->join as $nickname) {
            $group = User_group::getForNickname($nickname);
            if ($group && !$profile->isMember($group)) {
                try {
                    $profile->joinGroup($group);
                } catch (Exception $e) {
                    // TRANS: Server exception.
                    // TRANS: %1$s is a user nickname, %2$s is a group nickname.
                    throw new ServerException(sprintf(_m('Could not join user %1$s to group %2$s.'),
                                               $profile->nickname, $group->nickname));
                }
            }
        }
    }

    /**
     * Provide plugin version information.
     *
     * This data is used when showing the version page.
     *
     * @param array &$versions array of version data arrays; see EVENTS.txt
     *
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $url = 'http://status.net/wiki/Plugin:ForceGroup';

        $versions[] = array('name' => 'ForceGroup',
            'version' => STATUSNET_VERSION,
            'author' => 'Brion Vibber',
            'homepage' => $url,
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('Allows forced group memberships and forces all notices to appear in groups that users were forced in.'));

        return true;
    }
}
