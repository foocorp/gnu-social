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

/**
 * @package ApiLoggerPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class ApiLoggerPlugin extends Plugin
{
    // Lower this to do random sampling of API requests rather than all.
    // 0.1 will check about 10% of hits, etc.
    public $frequency = 1.0;

    function onArgsInitialize($args)
    {
        if (isset($args['action'])) {
            $action = strtolower($args['action']);
            if (substr($action, 0, 3) == 'api') {
                if ($this->frequency < 1.0 && $this->frequency > 0.0) {
                    $max = mt_getrandmax();
                    $n = mt_rand() / $max;
                    if ($n > $this->frequency) {
                        return true;
                    }
                }
                $uri = $_SERVER['REQUEST_URI'];

                $method = $_SERVER['REQUEST_METHOD'];
                $ssl = empty($_SERVER['HTTPS']) ? 'no' : 'yes';
                $cookie = empty($_SERVER['HTTP_COOKIE']) ? 'no' : 'yes';
                $etag = empty($_SERVER['HTTP_IF_MATCH']) ? 'no' : 'yes';
                $last = empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? 'no' : 'yes';
                $auth = empty($_SERVER['HTTP_AUTHORIZATION']) ? 'no' : 'yes';
                if ($auth == 'no' && function_exists('apache_request_headers')) {
                    // Sometimes Authorization doesn't make it into $_SERVER.
                    // Probably someone thought it was scary.
                    $headers = apache_request_headers();
                    if (isset($headers['Authorization'])) {
                        $auth = 'yes';
                    }
                }
                $agent = empty($_SERVER['HTTP_USER_AGENT']) ? 'no' : $_SERVER['HTTP_USER_AGENT'];

                $query = (strpos($uri, '?') === false) ? 'no' : 'yes';
                if ($query == 'yes') {
                    if (preg_match('/\?since_id=\d+$/', $uri)) {
                        $query = 'since_id';
                    }
                }

                common_log(LOG_INFO, "STATLOG action:$action method:$method ssl:$ssl query:$query cookie:$cookie auth:$auth ifmatch:$etag ifmod:$last agent:$agent");
            }
        }
        return true;
    }

    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'ApiLogger',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/ApiLogger',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Allows random sampling of API requests.'));
        return true;
    }
}
