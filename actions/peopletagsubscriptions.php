<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * People tags subscribed to by a user
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
 * @category  Personal
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/peopletaglist.php';

class PeopletagsubscriptionsAction extends Action
{
    var $page = null;
    var $profile = null;

    function isReadOnly($args)
    {
        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            // TRANS: Title for page that displays lists subscribed to by a user.
            // TRANS: %s is a profile nickname.
            return sprintf(_('Lists subscribed to by %s'), $this->profile->nickname);
        } else {
            // TRANS: Title for page that displays lists subscribed to by a user.
            // TRANS: %1$s is a profile nickname, %2$d is a page number.
            return sprintf(_('Lists subscribed to by %1$s, page %2$d'), $this->profile->nickname, $this->page);
        }
    }

    function prepare($args)
    {
        parent::prepare($args);

        if (common_config('singleuser', 'enabled')) {
            $nickname_arg = User::singleUserNickname();
        } else {
            $nickname_arg = $this->arg('nickname');
        }

        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url('peopletagsbyuser', $args), 301);
            return false;
        }

        $user = User::getKV('nickname', $nickname);

        if (!$user) {
            // TRANS: Client error displayed trying to perform an action related to a non-existing user.
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $user->getProfile();

        if (!$this->profile) {
            // TRANS: Error message displayed when referring to a user without a profile.
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showAnonymousMessage()
    {
        $notice =
          // TRANS: Message displayed for anonymous users on page that displays lists subscribed to by a user.
          // TRANS: This message contains Markdown links in the form [description](links).
          // TRANS: %s is a profile nickname.
          sprintf(_('These are lists subscribed to by **%s**. ' .
                    'Lists are how you sort similar ' .
                    'people on %%%%site.name%%%%, a [micro-blogging]' .
                    '(http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                    'based on the Free Software [StatusNet](http://status.net/) tool. ' .
                    'You can easily keep track of what they ' .
                    'are doing by subscribing to the list\'s timeline.' ), $this->profile->nickname);
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($notice));
        $this->elementEnd('div');
    }

    function showContent()
    {
        $offset = ($this->page-1) * PEOPLETAGS_PER_PAGE;
        $limit  = PEOPLETAGS_PER_PAGE + 1;

        $ptags = $this->profile->getTagSubscriptions($offset, $limit);

        $pl = new PeopletagList($ptags, $this);
        $cnt = $pl->show();

        $this->pagination($this->page > 1, $cnt > PEOPLETAGS_PER_PAGE,
                          $this->page, 'peopletagsubscriptions', array('nickname' => $this->profile->id));
    }

    function showObjectNav()
    {
        $nav = new PeopletagNav($this, $this->profile);
        $nav->show();
    }

    function showProfileBlock()
    {
        $block = new AccountProfileBlock($this, $this->profile);
        $block->show();
    }

    function showSections()
    {
        #TODO: tags with most subscribers
        #TODO: tags with most "members"
    }
}
