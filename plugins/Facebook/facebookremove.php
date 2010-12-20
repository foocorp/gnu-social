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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/plugins/Facebook/facebookaction.php';

class FacebookremoveAction extends FacebookAction
{
    function handle($args)
    {
        parent::handle($args);

        $secret = common_config('facebook', 'secret');

        $sig = '';

        ksort($_POST);

        foreach ($_POST as $key => $val) {
            if (substr($key, 0, 7) == 'fb_sig_') {
                $sig .= substr($key, 7) . '=' . $val;
            }
         }

        $sig .= $secret;
        $verify = md5($sig);

        if ($verify == $this->arg('fb_sig')) {

            $flink = Foreign_link::getByForeignID($this->arg('fb_sig_user'), 2);

            if (!$flink) {
                common_log(LOG_ERR, "Tried to delete missing foreign_link entry with Facebook ID " . $this->arg('fb_sig_user'));
                $this->serverError(_m('Couldn\'t remove Facebook user: already deleted.'));
                return;
            }

            common_debug("Removing foreign link to Facebook - local user ID: $flink->user_id, Facebook ID: $flink->foreign_id");

            $result = $flink->delete();

            if (!$result) {
                common_log_db_error($flink, 'DELETE', __FILE__);
                $this->serverError(_m('Couldn\'t remove Facebook user.'));
                return;
            }

        } else {
            # Someone bad tried to remove facebook link?
            common_log(LOG_ERR, "Someone from $_SERVER[REMOTE_ADDR] " .
                'unsuccessfully tried to remove a foreign link to Facebook!');
        }
    }
}
