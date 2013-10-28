<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class IMChannel extends Channel
{

    var $imPlugin;

    function source()
    {
        return $imPlugin->transport;
    }

    function __construct($imPlugin)
    {
        $this->imPlugin = $imPlugin;
    }

    function on($user)
    {
        return $this->setNotify($user, 1);
    }

    function off($user)
    {
        return $this->setNotify($user, 0);
    }

    function output($user, $text)
    {
        $text = '['.common_config('site', 'name') . '] ' . $text;
        $this->imPlugin->sendMessage($this->imPlugin->getScreenname($user), $text);
    }

    function error($user, $text)
    {
        $text = '['.common_config('site', 'name') . '] ' . $text;

        $screenname = $this->imPlugin->getScreenname($user);
        if($screenname){
            $this->imPlugin->sendMessage($screenname, $text);
            return true;
        }else{
            common_log(LOG_ERR,
                'Could not send error message to user ' . common_log_objstring($user) .
                ' on transport ' . $this->imPlugin->transport .' : user preference does not exist');
            return false;
        }
    }

    function setNotify($user, $notify)
    {
        global $_PEAR;

        $user_im_prefs = new User_im_prefs();
        $user_im_prefs->transport = $this->imPlugin->transport;
        $user_im_prefs->user_id = $user->id;
        if($user_im_prefs->find() && $user_im_prefs->fetch()){
            if($user_im_prefs->notify == $notify){
                //notify is already set the way they want
                return true;
            }else{
                $original = clone($user_im_prefs);
                $user_im_prefs->notify = $notify;
                $result = $user_im_prefs->update($original);

                if (!$result) {
                    $last_error = &$_PEAR->getStaticProperty('DB_DataObject','lastError');
                    common_log(LOG_ERR,
                               'Could not set notify flag to ' . $notify .
                               ' for user ' . common_log_objstring($user) .
                               ' on transport ' . $this->imPlugin->transport .' : ' . $last_error->message);
                    return false;
                } else {
                    common_log(LOG_INFO,
                               'User ' . $user->nickname . ' set notify flag to ' . $notify);
                    return true;
                }
            }
        }else{
                common_log(LOG_ERR,
                           'Could not set notify flag to ' . $notify .
                           ' for user ' . common_log_objstring($user) .
                           ' on transport ' . $this->imPlugin->transport .' : user preference does not exist');
                return false;
        }
    }
}
