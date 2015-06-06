<?php
/**
 * ChooseTheme - GNU social plugin enabling user to select a preferred theme
 * Copyright (C) 2015, kollektivet0x242.
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
 * @author   Knut Erik Hollund <knut.erik@unlike.no>
 * @copyright 2015 kollektivet0x242. http://www.kollektivet0x242.no
 *
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 */

class ChooseThemePlugin extends Plugin {

    public function onRouterInitialized(URLMapper $m) {
        $m->connect('main/choosethemesettings', array('action' => 'choosethemesettings'));
    }

    public function onPluginVersion(array &$versions) {
		
        $versions[] = array('name' => 'ChooseTheme',
                            'version' => '0.1',
                            'author' => 'Knut Erik "abjectio" Hollund',
                            'homepage' => 'https://gitlab.com/kollektivet0x242/gsp-choosetheme',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Allowing user to select the preferred theme.'));
        return true;
    }
    
    /**
     * Menu item for ChooseTheme
     *
     * @param Action $action action being executed
     *
     * @return boolean hook return
     */
    function onEndAccountSettingsNav(Action $action) {
        $action_name = $action->getActionName();

        $action->menuItem(common_local_url('choosethemesettings'),
                          // TRANS: Poll plugin menu item on user settings page.
                          _m('MENU', 'Theme'),
                          // TRANS: Poll plugin tooltip for user settings menu item.
                          _m('Choose Theme'),
                          $action_name === 'themesettings');

        return true;
    }


    function onStartShowStylesheets(Action $action) {

		//get the theme and set the current config for site and theme.
		if($action->getScoped() instanceof Profile) {
			$site_theme = common_config('site','theme');
			$user_theme = $action->getScoped()->getPref('chosen_theme', 'theme', $site_theme);
			common_config_set('site', 'theme', $user_theme);
		}		
		return true;
	}
}
