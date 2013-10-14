<?php
/**
 * Disfavor action.
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

/**
 * DisfavorAction class.
 *
 * @category Action
 * @package  GNUsocial
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://www.gnu.org/software/social/
 */
class DisfavorAction extends FormAction
{
    public function showForm($msg=null, $success=false)
    {
        if ($success) {
            common_redirect(common_local_url('showfavorites',
                array('nickname' => $this->scoped->nickname)), 303);
        }
        parent::showForm($msg, $success);
    }

    protected function handlePost()
    {
        $id     = $this->trimmed('notice');
        $notice = Notice::getKV($id);
        if (!$notice instanceof Notice) {
            $this->serverError(_('Notice not found'));
        }

        $fave            = new Fave();
        $fave->user_id   = $this->scoped->id;
        $fave->notice_id = $notice->id;
        if (!$fave->find(true)) {
            throw new NoResultException($fave);
        }
        $result = $fave->delete();
        if (!$result) {
            common_log_db_error($fave, 'DELETE', __FILE__);
            // TRANS: Server error displayed when removing a favorite from the database fails.
            $this->serverError(_('Could not delete favorite.'));
        }
        $this->scoped->blowFavesCache();
        if (StatusNet::isAjax()) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Title for page on which favorites can be added.
            $this->element('title', null, _('Add to favorites'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $favor = new FavorForm($this, $notice);
            $favor->show();
            $this->elementEnd('body');
            $this->endHTML();
            exit;
        }
    }
}
