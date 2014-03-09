<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List of peopletag subscribers
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
 * @copyright 2008-2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once(INSTALLDIR.'/lib/profilelist.php');

/**
 * List of peopletag subscribers
 *
 * @category Peopletag
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class PeopletagsubscribersAction extends Action
{
    var $page = null;
    var $peopletag = null;
    var $tagger = null;

    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        if (common_config('singleuser', 'enabled')) {
            $tagger_arg = User::singleUserNickname();
        } else {
            $tagger_arg = $this->arg('tagger');
        }

        $tag_arg = $this->arg('tag');
        $tagger = common_canonical_nickname($tagger_arg);
        $tag = common_canonical_tag($tag_arg);

        // Permanent redirect on non-canonical nickname

        if ($tagger_arg != $tagger || $tag_arg != $tag) {
            $args = array('tagger' => $nickname, 'tag' => $tag);
            if ($this->page != 1) {
                $args['page'] = $this->page;
            }
            common_redirect(common_local_url('peopletagged', $args), 301);
        }

        if (!$tagger) {
            // TRANS: Client error displayed when a tagger is expected but not provided.
            $this->clientError(_('No tagger.'), 404);
        }

        $user = User::getKV('nickname', $tagger);

        if (!$user) {
            // TRANS: Client error displayed trying to perform an action related to a non-existing user.
            $this->clientError(_('No such user.'), 404);
        }

        $this->tagger = $user->getProfile();
        $this->peopletag = Profile_list::pkeyGet(array('tagger' => $user->id, 'tag' => $tag));

        if (!$this->peopletag) {
            // TRANS: Client error displayed trying to reference a non-existing list.
            $this->clientError(_('No such list.'), 404);
        }

        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            // TRANS: Page title for list of list subscribers.
            // TRANS: %1$s is a list, %2$s is a user nickname.
            return sprintf(_('Subscribers to list %1$s by %2$s'),
                           $this->peopletag->tag, $this->tagger->nickname);
        } else {
            // TRANS: Page title for list of list subscribers.
            // TRANS: %1$s is a list, %2$s is a user nickname, %3$d is a page number.
            return sprintf(_('Subscribers to list %1$s by %2$s, page %3$d'),
                           $this->peopletag->tag, $this->tagger->nickname,
                           $this->page);
        }
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showPageNotice()
    {
    }

    function showLocalNav()
    {
        $nav = new PeopletagGroupNav($this);
        $nav->show();
    }

    function showContent()
    {
        $offset = ($this->page-1) * PROFILES_PER_PAGE;
        $limit =  PROFILES_PER_PAGE + 1;

        $cnt = 0;

        $subs = $this->peopletag->getSubscribers($offset, $limit);

        if ($subs) {
            $subscriber_list = new PeopletagSubscriberList($subs, $this->peopletag, $this);
            $cnt = $subscriber_list->show();
        }

        $subs->free();

        $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                          $this->page, 'peopletagsubscribers',
                          array('tagger' => $this->tagger->nickname,
                                'tag'    => $this->peopletag->tag));
    }
}

class PeopletagSubscriberList extends ProfileList
{
    var $peopletag = null;

    function __construct($profile, $peopletag, $action)
    {
        parent::__construct($profile, $action);

        $this->peopletag = $peopletag;
    }

    function newListItem($profile)
    {
        return new PeopletagSubscriberListItem($profile, $this->peopletag, $this->action);
    }
}

class PeopletagSubscriberListItem extends ProfileListItem
{
    var $peopletag = null;

    function __construct($profile, $peopletag, $action)
    {
        parent::__construct($profile, $action);

        $this->peopletag = $peopletag;
    }

    function showFullName()
    {
        parent::showFullName();
        if ($this->profile->id == $this->peopletag->tagger) {
            $this->out->text(' ');
            // TRANS: Addition in tag subscribers list for creator of a tag.
            $this->out->element('span', 'role', _('Creator'));
        }
    }

    function showActions()
    {
        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->showSubscribeButton();
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'members')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }

    function homepageAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'members')) {
            $aAttrs['rel'] = 'nofollow';
        }

        return $aAttrs;
    }

    /**
     * Fetch necessary return-to arguments for the profile forms
     * to return to this list when they're done.
     *
     * @return array
     */
    protected function returnToArgs()
    {
        $args = array('action' => 'peopletagsubscribers',
                      'tag' => $this->peopletag->tag,
                      'tagger' => $this->profile->nickname);
        $page = $this->out->arg('page');
        if ($page) {
            $args['param-page'] = $page;
        }
        return $args;
    }
}
