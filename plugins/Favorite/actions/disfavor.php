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
    protected $needPost = true;

    protected function doPreparation()
    {
        $this->target = Notice::getKV($this->trimmed('notice'));
        if (!$this->target instanceof Notice) {
            throw new ServerException(_m('No such notice.'));
        }
    }

    protected function doPost()
    {
        $fave            = new Fave();
        $fave->user_id   = $this->scoped->getID();
        $fave->notice_id = $this->target->getID();
        if (!$fave->find(true)) {
            // TRANS: Client error displayed when trying to remove a 'favor' when there is none in the first place.
            throw new AlreadyFulfilledException(_('This is already not favorited.'));
        }
        $result = $fave->delete();
        if ($result === false) {
            common_log_db_error($fave, 'DELETE', __FILE__);
            // TRANS: Server error displayed when removing a favorite from the database fails.
            throw new ServerException(_('Could not delete favorite.'));
        }
        Fave::blowCacheForProfileId($this->scoped->getID());

        // TRANS: Message when a disfavor action has been taken for a notice.
        return _('Disfavored the notice.');
    }

    protected function showContent()
    {
        // We show the 'Favor' form because right now all calls to Disfavor will directly disfavor a notice.
        $disfavor = new FavorForm($this, $this->target);
        $disfavor->show();
    }
}
