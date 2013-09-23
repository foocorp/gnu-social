<?php
/**
 * Clear all flags for a profile
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Action to clear flags for a profile
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class ClearflagAction extends ProfileFormAction
{
    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        if (!parent::prepare($args)) {
            return false;
        }

        $user = common_current_user();

        assert(!empty($user)); // checked above
        assert(!empty($this->profile)); // checked above

        return true;
    }

    /**
     * Handle request
     *
     * Overriding the base Action's handle() here to deal check
     * for Ajax and return an HXR response if necessary
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
            if (!$this->boolean('ajax')) {
                $this->returnToPrevious();
            }
        }
    }

    /**
     * Handle POST
     *
     * Executes the actions; deletes all flags
     *
     * @return void
     */
    function handlePost()
    {
        $ufp = new User_flag_profile();

        $result = $ufp->query('UPDATE user_flag_profile ' .
                              'SET cleared = now() ' .
                              'WHERE cleared is null ' .
                              'AND profile_id = ' . $this->profile->id);

        if ($result == false) {
            // TRANS: Server exception given when flags could not be cleared.
            // TRANS: %s is a profile nickname.
            $msg = sprintf(_m('Could not clear flags for profile "%s".'),
                           $this->profile->nickname);
            throw new ServerException($msg);
        }

        $ufp->free();

        if ($this->boolean('ajax')) {
            $this->ajaxResults();
        }
    }

    /**
     * Return results in ajax form
     *
     * @return void
     */
    function ajaxResults()
    {
        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        // TRANS: Title for AJAX form to indicated that flags were removed.
        $this->element('title', null, _m('Flags cleared'));
        $this->elementEnd('head');
        $this->elementStart('body');
        // TRANS: Body element for "flags cleared" form.
        $this->element('p', 'cleared', _m('Cleared'));
        $this->elementEnd('body');
        $this->endHTML();
    }
}
