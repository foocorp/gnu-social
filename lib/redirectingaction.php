<?php
/**
 * Superclass for actions that redirect to a given return-to page on completion.
 *
 * PHP version 5
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Superclass for actions that redirect to a given return-to page on completion.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class RedirectingAction extends Action
{
    /**
     * Redirect browser to the page our hidden parameters requested,
     * or if none given, to the url given by $this->defaultReturnTo().
     *
     * To be called only after successful processing.
     *
     * Note: this was named returnToArgs() up through 0.9.2, which
     * caused problems because there's an Action::returnToArgs()
     * already which does something different.
     *
     * @return void
     */
    function returnToPrevious()
    {
        // Now, gotta figure where we go back to
        $action = false;
        $args = array();
        $params = array();
        foreach ($this->args as $k => $v) {
            if ($k == 'returnto-action') {
                $action = $v;
            } else if (substr($k, 0, 15) == 'returnto-param-') {
                $params[substr($k, 15)] = $v;
            } elseif (substr($k, 0, 9) == 'returnto-') {
                $args[substr($k, 9)] = $v;
            }
        }

        if ($action) {
            common_redirect(common_local_url($action, $args, $params), 303);
        } else {
            $url = $this->defaultReturnTo();
        }
        common_redirect($url, 303);
    }

    /**
     * If we reached this form without returnto arguments, where should
     * we go? May be overridden by subclasses to a reasonable destination
     * for that action; default implementation throws an exception.
     *
     * @return string URL
     */
    function defaultReturnTo()
    {
        // TRANS: Client error displayed when return-to was defined without a target.
        $this->clientError(_('No return-to arguments.'));
    }
}
