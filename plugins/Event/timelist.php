<?php
/**
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
 *
 * @category  Event
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Callback handler to populate end time dropdown
 */
class TimelistAction extends Action {
    private $start;
    private $duration;

    /**
     * Get ready
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     */
    function prepare($args) {
        parent::prepare($args);
        $this->start = $this->arg('start');
        $this->duration = $this->boolean('duration', false);
        return true;
    }

    /**
     * Handle input and ouput something
     *
     * @param array $args $_REQUEST arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_m('Not logged in.'));
            return;
        }

        if (!empty($this->start)) {
            $times = EventTimeList::getTimes($this->start, $this->duration);
        } else {
            // TRANS: Client error when submitting a form with unexpected information.
            $this->clientError(_m('Unexpected form submission.'));
            return;
        }

        if ($this->boolean('ajax')) {
            header('Content-Type: application/json; charset=utf-8');
            print json_encode($times);
        } else {
            // TRANS: Client error displayed when using an action in a non-AJAX way.
            $this->clientError(_m('This action is AJAX only.'));
        }
    }

    /**
     * Override the regular error handler to show something more
     * ajaxy
     *
     * @param string $msg   error message
     * @param int    $code  error code
     */
    function clientError($msg, $code = 400) {
        if ($this->boolean('ajax')) {
            header('Content-Type: application/json; charset=utf-8');
            print json_encode(
                array(
                    'success' => false,
                    'code'    => $code,
                    'message' => $msg
                )
            );
        } else {
            parent::clientError($msg, $code);
        }
    }
}
