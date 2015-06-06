<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Debugging helper plugin -- records detailed data on POSTs to log
 *
 * PHP version 5
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
 * @category  Sample
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PostDebugPlugin extends Plugin
{
    /**
     * Set to a directory to dump individual items instead of
     * sending to the debug log
     */
    public $dir=false;

    public function onArgsInitialize(&$args)
    {
        if (isset($_SERVER['REQUEST_METHOD']) &&
            $_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->doDebug();
        }
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'PostDebug',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:PostDebug',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Debugging tool to record request details on POST.'));
        return true;
    }

    protected function doDebug()
    {
        $data = array('timestamp' => gmdate('r'),
                      'remote_addr' => @$_SERVER['REMOTE_ADDR'],
                      'url' => @$_SERVER['REQUEST_URI'],
                      'have_session' => common_have_session(),
                      'logged_in' => common_logged_in(),
                      'is_real_login' => common_is_real_login(),
                      'user' => common_logged_in() ? common_current_user()->nickname : null,
                      'headers' => $this->getHttpHeaders(),
                      'post_data' => $this->sanitizePostData($_POST));
        $this->saveDebug($data);
    }

    protected function saveDebug($data)
    {
        $output = var_export($data, true);
        if ($this->dir) {
            $file = $this->dir . DIRECTORY_SEPARATOR . $this->logFileName();
            file_put_contents($file, $output);
        } else {
            common_log(LOG_DEBUG, "PostDebug: $output");
        }
    }

    protected function logFileName()
    {
        $base = common_request_id();
        $base = preg_replace('/^(.+?) .*$/', '$1', $base);
        $base = str_replace(':', '-', $base);
        $base = rawurlencode($base);
        return $base;
    }

    protected function getHttpHeaders()
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = array();
            $prefix = 'HTTP_';
            $prefixLen = strlen($prefix);
            foreach ($_SERVER as $key => $val) {
                if (substr($key, 0, $prefixLen) == $prefix) {
                    $header = $this->normalizeHeader(substr($key, $prefixLen));
                    $headers[$header] = $val;
                }
            }
        }
        foreach ($headers as $header => $val) {
            if (strtolower($header) == 'cookie') {
                $headers[$header] = $this->sanitizeCookies($val);
            }
        }
        return $headers;
    }

    protected function normalizeHeader($key)
    {
        return implode('-',
                       array_map('ucfirst',
                                 explode("_",
                                         strtolower($key))));
    }

    function sanitizeCookies($val)
    {
        $blacklist = array(session_name(), 'rememberme');
        foreach ($blacklist as $name) {
            $val = preg_replace("/(^|;\s*)({$name}=)(.*?)(;|$)/",
                                "$1$2########$4",
                                $val);
        }
        return $val;
    }

    function sanitizePostData($data)
    {
        $blacklist = array('password', 'confirm', 'token');
        foreach ($data as $key => $val) {
            if (in_array($key, $blacklist)) {
                $data[$key] = '########';
            }
        }
        return $data;
    }
}
