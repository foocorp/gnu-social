<?php
/**
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
 * Placeholder for showing faves...
 */
abstract class NoticeListActorsItem extends NoticeListItem
{
    /**
     * @return array of profile IDs
     */
    abstract function getProfiles();

    abstract function getListMessage($count, $you);

    function show()
    {
        $links = array();
        $you = false;
        $cur = common_current_user();
        foreach ($this->getProfiles() as $id) {
            if ($cur && $cur->id == $id) {
                $you = true;
                // TRANS: Reference to the logged in user in favourite list.
                array_unshift($links, _m('FAVELIST', 'You'));
            } else {
                $profile = Profile::getKV('id', $id);
                if ($profile instanceof Profile) {
                    $links[] = sprintf('<a class="h-card" href="%s">%s</a>',
                                       htmlspecialchars($profile->getUrl()),
                                       htmlspecialchars($profile->getBestName()));
                }
            }
        }

        if ($links) {
            $count = count($links);
            $msg = $this->getListMessage($count, $you);
            $out = sprintf($msg, $this->magicList($links));

            $this->showStart();
            $this->out->raw($out);
            $this->showEnd();
            return $count;
        } else {
            return 0;
        }
    }

    function magicList($items)
    {
        if (count($items) == 0) {
            return '';
        } else if (count($items) == 1) {
            return $items[0];
        } else {
            $first = array_slice($items, 0, -1);
            $last = array_slice($items, -1, 1);
            // TRANS: Separator in list of user names like "Jim, Bob, Mary".
            $separator = _(', ');
            // TRANS: For building a list such as "Jim, Bob, Mary and 5 others like this".
            // TRANS: %1$s is a list of users, separated by a separator (default: ", "), %2$s is the last user in the list.
            return sprintf(_m('FAVELIST', '%1$s and %2$s'), implode($separator, $first), implode($separator, $last));
        }
    }
}
