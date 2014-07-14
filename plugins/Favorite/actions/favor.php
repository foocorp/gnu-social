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

    protected $object = null;

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $this->target = Notice::getKV($this->trimmed('notice'));
        if (!$this->target instanceof Notice) {
            throw new ServerException(_m('No such notice.'));
        }
        if (!$this->target->inScope($this->scoped)) {
            // TRANS: Client error displayed when trying to reply to a notice a the target has no access to.
            // TRANS: %1$s is a user nickname, %2$d is a notice ID (number).
            throw new ClientException(sprintf(_m('%1$s has no right to reply to notice %2$d.'), $this->scoped->getNickname(), $this->target->id), 403);
        }

        return true;
    }

    protected function handlePost()
    {
        parent::handlePost();

        if (Fave::existsForProfile($this->target, $this->scoped)) {
            // TRANS: Client error displayed when trying to mark a notice as favorite that already is a favorite.
            throw new AlreadyFulfilledException(_('You have already favorited this!'));
        }

        $now = common_sql_now();

        $act = new Activity();
        $act->id = Fave::newUri($this->scoped, $this->target, $now);
        $act->type = Fave::getObjectType();
        $act->actor = $this->scoped->asActivityObject();
        $act->target = $this->target->asActivityObject();
        $act->objects = array(clone($act->target));
        $act->verb = ActivityVerb::FAVORITE;
        $act->title = ActivityUtils::verbToTitle($act->verb);
        $act->time = strtotime($now);
        // TRANS: Notification given when a user marks a notice as favorite.
        // TRANS: %1$s is a user name or full name, %2$s is a notice URI, %3$s the link to the user's profile.
        $act->content = sprintf(_('<a href="%3$s">%1$s</a> marked notice <a href="%2$s">%2$s</a> as a favorite.'),
                                htmlspecialchars($this->scoped->getBestName()),
                                htmlspecialchars($this->target->getUrl()),
                                htmlspecialchars($this->scoped->getUrl()));


        $stored = Notice::saveActivity($act, $this->scoped,
                                        array('uri'=>$act->id));

        Fave::blowCacheForProfileId($this->scoped->id);

        return _('Favorited the notice');
    }

    protected function showContent()
    {
        if ($this->target instanceof Notice) {
            $disfavor = new DisfavorForm($this, $this->target);
            $disfavor->show();
        }
    }
}
