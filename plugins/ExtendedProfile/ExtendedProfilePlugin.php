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
        $versions[] = array('name' => 'ExtendedProfile',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:ExtendedProfile',
                            'rawdescription' =>
                            _m('UI extensions for additional profile fields.'));

        return true;
    }

    /**
     * Autoloader
     *
     * Loads our classes if they're requested.
     *
     * @param string $cls Class requested
     *
     * @return boolean hook return
     */
    function onAutoload($cls)
    {
        $lower = strtolower($cls);
        switch ($lower)
        {
        case 'extendedprofile':
        case 'extendedprofilewidget':
        case 'profiledetailaction':
        case 'profiledetailsettingsaction':
            require_once dirname(__FILE__) . '/' . $lower . '.php';
            return false;
        case 'profile_detail':
            require_once dirname(__FILE__) . '/' . ucfirst($lower) . '.php';
            return false;
        default:
            return true;
        }
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
        $m->connect(':nickname/detail',
                array('action' => 'profiledetail'),
                array('nickname' => Nickname::DISPLAY_FMT));
        $m->connect('settings/profile/detail',
                array('action' => 'profiledetailsettings'));

        return true;
    }

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('profile_detail', Profile_detail::schemaDef());

        // @hack until key definition support is merged
        Profile_detail::fixIndexes($schema);
        return true;
    }

    function onEndAccountSettingsProfileMenuItem($widget, $menu)
    {
        // TRANS: Link title attribute in user account settings menu.
        $title = _('Change additional profile settings');
        // TRANS: Link description in user account settings menu.
        $widget->showMenuItem('profiledetailsettings',_m('Details'),$title);
        return true;
    }

    function onEndProfilePageProfileElements(HTMLOutputter $out, Profile $profile) {
        $user = User::staticGet('id', $profile->id);
        if ($user) {
            $url = common_local_url('profiledetail', array('nickname' => $user->nickname));
            $out->element('a', array('href' => $url), _m('More details...'));
        }
        return;
    }

}
