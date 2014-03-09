<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Lists by a user
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
 * PHP version 5
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Personal
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/peopletaglist.php';
// cache 3 pages
define('PEOPLETAG_CACHE_WINDOW', PEOPLETAGS_PER_PAGE*3 + 1);

class PeopletagAction extends Action
{
    var $page = null;
    var $tag = null;

    function isReadOnly($args)
    {
        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            // TRANS: Title for list page.
            // TRANS: %s is a list.
            return sprintf(_('Public list %s'), $this->tag);
        } else {
            // TRANS: Title for list page.
            // TRANS: %1$s is a list, %2$d is a page number.
            return sprintf(_('Public list %1$s, page %2$d'), $this->tag, $this->page);
        }
    }

    function prepare($args)
    {
        parent::prepare($args);
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        $tag_arg = $this->arg('tag');
        $tag = common_canonical_tag($tag_arg);

        // Permanent redirect on non-canonical nickname

        if ($tag_arg != $tag) {
            $args = array('tag' => $nickname);
            if ($this->page && $this->page != 1) {
                $args['page'] = $this->page;
            }
            common_redirect(common_local_url('peopletag', $args), 301);
        }
        $this->tag = $tag;

        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showLocalNav()
    {
        $nav = new PublicGroupNav($this);
        $nav->show();
    }

    function showAnonymousMessage()
    {
        $notice =
          // TRANS: Message for anonymous users on list page.
          // TRANS: This message contains Markdown links in the form [description](link).
          _('Lists are how you sort similar ' .
            'people on %%site.name%%, a [micro-blogging]' .
            '(http://en.wikipedia.org/wiki/Micro-blogging) service ' .
            'based on the Free Software [StatusNet](http://status.net/) tool. ' .
            'You can then easily keep track of what they ' .
            'are doing by subscribing to the list\'s timeline.' );
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($notice));
        $this->elementEnd('div');
    }

    function showContent()
    {
        $offset = ($this->page-1) * PEOPLETAGS_PER_PAGE;
        $limit  = PEOPLETAGS_PER_PAGE + 1;

        $ptags = new Profile_list();
        $ptags->tag = $this->tag;

        $user = common_current_user();

        if (empty($user)) {
            $ckey = sprintf('profile_list:tag:%s', $this->tag);
            $ptags->private = false;
            $ptags->orderBy('profile_list.modified DESC');

            $c = Cache::instance();
            if ($offset+$limit <= PEOPLETAG_CACHE_WINDOW && !empty($c)) {
                $cached_ptags = Profile_list::getCached($ckey, $offset, $limit);
                if ($cached_ptags === false) {
                    $ptags->limit(0, PEOPLETAG_CACHE_WINDOW);
                    $ptags->find();

                    Profile_list::setCache($ckey, $ptags, $offset, $limit);
                } else {
                    $ptags = clone($cached_ptags);
                }
            } else {
                $ptags->limit($offset, $limit);
                $ptags->find();
            }
        } else {
            $ptags->whereAdd('(profile_list.private = false OR (' .
                             ' profile_list.tagger =' . $user->id .
                             ' AND profile_list.private = true) )');

            $ptags->orderBy('profile_list.modified DESC');
            $ptags->find();
        }

        $pl = new PeopletagList($ptags, $this);
        $cnt = $pl->show();

        $this->pagination($this->page > 1, $cnt > PEOPLETAGS_PER_PAGE,
                          $this->page, 'peopletag', array('tag' => $this->tag));
    }

    function showSections()
    {
    }
}
