<?php
/**
 * Disfavor action.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
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

require_once INSTALLDIR.'/lib/favorform.php';

/**
 * Disfavor class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class DisfavorAction extends Action
{
    /**
     * Class handler.
     *
     * @param array $args query arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_('Not logged in.'));
            return;
        }
        $user = common_current_user();
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            common_redirect(common_local_url('showfavorites',
                array('nickname' => $user->nickname)));
            return;
        }
        $id     = $this->trimmed('notice');
        $notice = Notice::getKV($id);
        $token  = $this->trimmed('token-'.$notice->id);
        if (!$token || $token != common_session_token()) {
            // TRANS: Client error displayed when the session token does not match or is not given.
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
            return;
        }
        $fave            = new Fave();
        $fave->user_id   = $user->id;
        $fave->notice_id = $notice->id;
        if (!$fave->find(true)) {
            // TRANS: Client error displayed when trying to remove favorite status for a notice that is not a favorite.
            $this->clientError(_('This notice is not a favorite!'));
            return;
        }
        $result = $fave->delete();
        if (!$result) {
            common_log_db_error($fave, 'DELETE', __FILE__);
            // TRANS: Server error displayed when removing a favorite from the database fails.
            $this->serverError(_('Could not delete favorite.'));
            return;
        }
        $user->blowFavesCache();
        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title for page on which favorites can be added.
            $this->element('title', null, _('Add to favorites'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $favor = new FavorForm($this, $notice);
            $favor->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect(common_local_url('showfavorites',
                                             array('nickname' => $user->nickname)),
                            303);
        }
    }
}
