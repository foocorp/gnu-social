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
class ThreadedNoticeListFavesItem extends NoticeListActorsItem
{
    function getProfiles()
    {
        $faves = Fave::byNotice($this->notice);
        $profiles = array();
        foreach ($faves as $fave) {
            $profiles[] = $fave->user_id;
        }   
        return $profiles;
    }

    function magicList($items)
    {
        if (count($items) > 4) {
            return parent::magicList(array_slice($items, 0, 3));
        } else {
            return parent::magicList($items);
        }   
    }

    function getListMessage($count, $you)
    {
        if ($count == 1 && $you) {
            // darn first person being different from third person!
            // TRANS: List message for notice favoured by logged in user.
            return _m('FAVELIST', 'You like this.');
        } else if ($count > 4) {
            // TRANS: List message for when more than 4 people like something.
            // TRANS: %%s is a list of users liking a notice, %d is the number over 4 that like the notice.
            // TRANS: Plural is decided on the total number of users liking the notice (count of %%s + %d).
            return sprintf(_m('%%s and %d others like this.',
                              '%%s and %d others like this.',
                              $count),
                           $count - 3);
        } else {           
            // TRANS: List message for favoured notices.
            // TRANS: %%s is a list of users liking a notice.
            // TRANS: Plural is based on the number of of users that have favoured a notice.
            return sprintf(_m('%%s likes this.',
                              '%%s like this.',
                              $count),
                           $count);
        }                  
    }

    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'notice-data notice-faves'));
    }   

    function showEnd()
    {
        $this->out->elementEnd('li');
    }   
}
