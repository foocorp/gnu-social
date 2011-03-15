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

class ProfileDetailSettingsAction extends AccountSettingsAction
{

    function title()
    {
        return _m('Extended profile settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Usage instructions for profile settings.
        return _('You can update your personal profile info here '.
                 'so people know more about you.');
    }

    function showStylesheets() {
        parent::showStylesheets();
        $this->cssLink('plugins/ExtendedProfile/profiledetail.css');
        return true;
    }

    function handle($args)
    {
        $this->showPage();
    }

    function showContent()
    {
        $cur = common_current_user();
        $profile = $cur->getProfile();

        $widget = new ExtendedProfileWidget($this, $profile, ExtendedProfileWidget::EDITABLE);
        $widget->show();
    }
}
