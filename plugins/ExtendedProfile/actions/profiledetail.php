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

if (!defined('GNUSOCIAL')) { exit(1); }

class ProfileDetailAction extends ShowstreamAction
{
    function isReadOnly($args)
    {
        return true;
    }

    function title()
    {
        return $this->target->getFancyName();
    }

    function showStylesheets() {
        parent::showStylesheets();
        $this->cssLink('plugins/ExtendedProfile/css/profiledetail.css');
        return true;
    }

    function showContent()
    {
        $cur = common_current_user();
        if ($this->scoped instanceof Profile && $this->scoped->sameAs($this->target)) {
            $this->elementStart('div', 'entity_actions');
            $this->elementStart('ul');
            $this->elementStart('li', 'entity_edit');
            $this->element('a', array('href' => common_local_url('profiledetailsettings'),
                                      // TRANS: Link title for link on user profile.
                                      'title' => _m('Edit extended profile settings')),
                           // TRANS: Link text for link on user profile.
                           _m('Edit'));
            $this->elementEnd('li');
            $this->elementEnd('ul');
            $this->elementEnd('div');
        }

        $widget = new ExtendedProfileWidget($this, $this->target);
        $widget->show();
    }
}
