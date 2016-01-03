<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List of group members
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Group
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * List of group members
 *
 * @category Group
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SubqueueAction extends GalleryAction
{
    protected $needLogin = true;

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        if (!$this->target->sameAs($this->scoped)) {
            // TRANS: Client error displayed when trying to approve group applicants without being a group administrator.
            throw new ClientException(_('You may only approve your own pending subscriptions.'));
        }
        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            // TRANS: Title of the first page showing pending subscribers still awaiting approval.
            // TRANS: %s is the name of the user.
            return sprintf(_('%s subscribers awaiting approval'),
                           $this->target->getNickname());
        } else {
            // TRANS: Title of all but the first page showing pending subscribersmembers still awaiting approval.
            // TRANS: %1$s is the name of the user, %2$d is the page number of the members list.
            return sprintf(_('%1$s subscribers awaiting approval, page %2$d'),
                           $this->target->getNickname(),
                           $this->page);
        }
    }

    function showPageNotice()
    {
        $this->element('p', 'instructions',
                       // TRANS: Page notice for group members page.
                       _('A list of users awaiting approval to subscribe to you.'));
    }


    function showContent()
    {
        $offset = ($this->page-1) * PROFILES_PER_PAGE;
        $limit =  PROFILES_PER_PAGE + 1;

        $cnt = 0;

        try {
            $subqueue = $this->target->getRequests($offset, $limit);
        } catch (NoResultException $e) {
            // TRANS: If no pending subscription requests are found
            $this->element('div', null, _m('You have no pending subscription requests.'));
            return;
        }

        $list = new SubQueueList($subqueue, $this);
        $cnt = $list->show();

        $subqueue->free();

        $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                          $this->page, 'subqueue',
                          array('nickname' => $this->target->getNickname())); // urgh
    }
}
