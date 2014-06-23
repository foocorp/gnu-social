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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Some UI extras for now...
 *
 * @package ModPlusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class ModPlusPlugin extends Plugin
{
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'ModPlus',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:ModPlus',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('UI extension for profile moderation actions.'));

        return true;
    }

    /**
     * Load JS at runtime.
     *
     * @param Action $action
     * @return boolean hook result
     */
    function onEndShowScripts(Action $action)
    {
        $action->script($this->path('js/modplus.js'));
        return true;
    }

    public function onEndShowStylesheets(Action $action) {
        $action->cssLink($this->path('css/modplus.css'));
        return true;
    }

    /**
     * Add ModPlus-related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m URL mapper
     *
     * @return boolean hook return
     */
    function onStartInitializeRouter($m)
    {
        $m->connect('user/remote/:id',
                array('action' => 'remoteprofile'),
                array('id' => '[\d]+'));

        return true;
    }

    /**
     * Add per-profile info popup menu for author on notice lists.
     *
     * @param NoticeListItem $item
     * @return boolean hook value
     */
    function onStartShowNoticeItem(NoticeListItem $item)
    {
        $this->showProfileOptions($item->out, $item->profile);
        return true;
    }

    /**
     * Add per-profile info popup menu on profile lists.
     *
     * @param ProfileListItem $item
     */
    function onStartProfileListItemProfile($item)
    {
        $this->showProfileOptions($item->out, $item->profile->getProfile());
        return true;
    }

    /**
     * Build common remote-profile options structure.
     * Currently only adds output for remote profiles, nothing for local users.
     *
     * @param HTMLOutputter $out
     * @param Profile $profile
     */
    protected function showProfileOptions(HTMLOutputter $out, Profile $profile)
    {
        if (!$profile->isGroup() && !$profile->isLocal()) {
            $target = common_local_url('remoteprofile', array('id' => $profile->id));
            // TRANS: Label for access to remote profile options.
            $label = _m('Remote profile options...');
            $out->elementStart('div', 'remote-profile-options');
            $out->element('a', array('href' => $target), $label);
            $out->elementEnd('div');
        }
    }
}
