<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2014, Free Software Foundation, Inc.
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

/**
 * @package     UI
 * @maintainer  Mikael Nordfeldth <mmn@hethane.se>
 */
class ConversationTreePlugin extends Plugin
{
    public function onStartShowConversation(Action $action, Conversation $conv, Profile $scoped=null) {
        $nl = new ConversationTree($conv->getNotices($action->getScoped()), $action);
        $cnt = $nl->show();
        return false;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'ConversationTree',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://gnu.io/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Enables conversation tree view.'));

        return true;
    }
}
