<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Extra profile bio-like fields
 *
 * @package ExtendedProfilePlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class ExtendedProfilePlugin extends Plugin
{
    function onPluginVersion(&$versions)
    {
        $versions[] = array(
            'name' => 'ExtendedProfile',
            'version' => STATUSNET_VERSION,
            'author' => 'Brion Vibber, Samantha Doherty, Zach Copley',
            'homepage' => 'http://status.net/wiki/Plugin:ExtendedProfile',
            // TRANS: Plugin description.
            'rawdescription' => _m('UI extensions for additional profile fields.')
        );

        return true;
    }

    /**
     * Add paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m URL mapper
     *
     * @return boolean hook return
     */
    function onStartInitializeRouter($m)
    {
        $m->connect(
            ':nickname/detail',
            array('action' => 'profiledetail'),
            array('nickname' => Nickname::DISPLAY_FMT)
        );
        $m->connect(
            '/settings/profile/finduser',
            array('action' => 'Userautocomplete')
        );
        $m->connect(
            'settings/profile/detail',
            array('action' => 'profiledetailsettings')
        );

        return true;
    }

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('profile_detail', Profile_detail::schemaDef());

        return true;
    }

    function onEndShowAccountProfileBlock(HTMLOutputter $out, Profile $profile) {
        $user = User::getKV('id', $profile->id);
        if ($user) {
            $url = common_local_url('profiledetail', array('nickname' => $user->nickname));
            // TRANS: Link text on user profile page leading to extended profile page.
            $out->element('a', array('href' => $url, 'class' => 'profiledetail'), _m('More details...'));
        }
    }
}
