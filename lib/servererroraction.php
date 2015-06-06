<?php

/**
 * Server error action.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/error.php';

/**
 * Class for displaying HTTP server errors
 *
 * Note: The older util.php class simply printed a string, but the spec
 * says that 500 errors should be treated similarly to 400 errors, and
 * it's easier to give an HTML response.  Maybe we can customize these
 * to display some funny animal cartoons.  If not, we can probably role
 * these classes up into a single class.
 *
 * See: http://tools.ietf.org/html/rfc2616#section-10
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

class ServerErrorAction extends ErrorAction
{
    static $status = array(500 => 'Internal Server Error',
                           501 => 'Not Implemented',
                           502 => 'Bad Gateway',
                           503 => 'Service Unavailable',
                           504 => 'Gateway Timeout',
                           505 => 'HTTP Version Not Supported');

    function __construct($message='Error', $code=500, $ex=null)
    {
        parent::__construct($message, $code);

        $this->default = 500;

        // Server errors must be logged.
        $log = "ServerErrorAction: $code $message";
        if ($ex) {
            $log .= "\n" . $ex->getTraceAsString();
        }
        common_log(LOG_ERR, $log);
    }

    // XXX: Should these error actions even be invokable via URI?

    protected function handle()
    {
        parent::handle();

        $this->code = $this->trimmed('code');

        if (!$this->code || $code < 500 || $code > 599) {
            $this->code = $this->default;
        }

        $this->message = $this->trimmed('message');

        if (!$this->message) {
            $this->message = "Server Error $this->code";
        }

        $this->showPage();
    }

    /**
     *  To specify additional HTTP headers for the action
     *
     *  @return void
     */
    function extraHeaders()
    {
        $status_string = @self::$status[$this->code];
        header('HTTP/1.1 '.$this->code.' '.$status_string);
    }

    /**
     * Page title.
     *
     * @return page title
     */

    function title()
    {
        return @self::$status[$this->code];
    }

}
