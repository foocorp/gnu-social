<?php
/**
 * Favor action.
 *
 * PHP version 5
 *
 * @category Action
 * @package  GNUsocial
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://www.gnu.org/software/social/
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

if (!defined('GNUSOCIAL')) { exit(1); }

require_once INSTALLDIR.'/lib/mail.php';

/**
 * FavorAction class.
 *
 * @category Action
 * @package  GNUsocial
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://www.gnu.org/software/social/
 */
class FavorAction extends FormAction
{
    protected $needPost = true;

    protected function handlePost()
    {
        $id     = $this->trimmed('notice');
        $notice = Notice::getKV($id);
        if (!($notice instanceof Notice)) {
            $this->serverError(_('Notice not found'));
        }
        if ($this->scoped->hasFave($notice)) {
            // TRANS: Client error displayed when trying to mark a notice as favorite that already is a favorite.
            $this->clientError(_('This notice is already a favorite!'));
        }
        $fave = Fave::addNew($this->scoped, $notice);
        if (!$fave) {
            // TRANS: Server error displayed when trying to mark a notice as favorite fails in the database.
            $this->serverError(_('Could not create favorite.'));
        }
        $this->notify($notice, $this->scoped->getUser());
        $this->scoped->blowFavesCache();
        if (StatusNet::isAjax()) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title for page on which favorite notices can be unfavourited.
            $this->element('title', null, _('Disfavor favorite.'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $disfavor = new DisFavorForm($this, $notice);
            $disfavor->show();
            $this->elementEnd('body');
            $this->endHTML();
            exit;
        }
        common_redirect(common_local_url('showfavorites',
                                         array('nickname' => $this->scoped->nickname)),
                            303);
    }

    /**
     * Notifies a user when their notice is favorited.
     *
     * @param class $notice favorited notice
     * @param class $user   user declaring a favorite
     *
     * @return void
     */
    function notify($notice, $user)
    {
        $other = User::getKV('id', $notice->profile_id);
        if ($other && $other->id != $user->id) {
            if ($other->email && $other->emailnotifyfav) {
                mail_notify_fave($other, $user, $notice);
            }
            // XXX: notify by IM
            // XXX: notify by SMS
        }
    }
}
