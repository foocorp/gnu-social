<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Public tag cloud for notices
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
 * @category  Public
 * @package   StatusNet
 * @author    Mike Cochrane <mikec@mikenz.geek.nz>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 Mike Cochrane
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

define('TAGS_PER_PAGE', 100);

/**
 * Public tag cloud for notices
 *
 * @category Personal
 * @package  StatusNet
 * @author    Mike Cochrane <mikec@mikenz.geek.nz>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 Mike Cochrane
 * @copyright 2008-2009 StatusNet, Inc.
 * @link     http://status.net/
 */
class PublicpeopletagcloudAction extends Action
{
    function isReadOnly($args)
    {
        return true;
    }

    function title()
    {
        // TRANS: Title for page with public list cloud.
        return _('Public list cloud');
    }

    function showPageNotice()
    {
        $this->element('p', 'instructions',
                       // TRANS: Page notice for page with public list cloud.
                       // TRANS: %s is a StatusNet sitename.
                       sprintf(_('These are largest lists on %s'),
                               common_config('site', 'name')));
    }

    function showEmptyList()
    {
        // TRANS: Empty list message on page with public list cloud.
        // TRANS: This message contains Markdown links in the form [description](link).
        $message = _('No one has [listed](%%doc.tags%%) anyone yet.') . ' ';

        if (common_logged_in()) {
            // TRANS: Additional empty list message on page with public list cloud for logged in users.
            $message .= _('Be the first to list someone!');
        }
        else {
            // TRANS: Additional empty list message on page with public list cloud for anonymous users.
        // TRANS: This message contains Markdown links in the form [description](link).
            $message .= _('Why not [register an account](%%action.register%%) and be the first to list someone!');
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showLocalNav()
    {
        $nav = new PublicGroupNav($this);
        $nav->show();
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showContent()
    {
        // XXX: cache this

        $tags = new Profile_tag();
        $plist = new Profile_list();
        $plist->private = false;

        $tags->joinAdd($plist);
        $tags->selectAdd();
        $tags->selectAdd('profile_tag.tag');
        $tags->selectAdd('count(profile_tag.tag) as weight');
        $tags->groupBy('profile_tag.tag');
        $tags->orderBy('weight DESC');

        $tags->limit(TAGS_PER_PAGE);

        $cnt = $tags->find();

        if ($cnt > 0) {
            $this->elementStart('div', array('id' => 'tagcloud',
                                             'class' => 'section'));

            $tw = array();
            $sum = 0;
            while ($tags->fetch()) {
                $tw[$tags->tag] = $tags->weight;
                $sum += $tags->weight;
            }

            ksort($tw);

            $this->elementStart('dl');
            // TRANS: DT element on on page with public list cloud.
            $this->element('dt', null, _('List cloud'));
            $this->elementStart('dd');
            $this->elementStart('ul', 'tags xoxo tag-cloud');
            foreach ($tw as $tag => $weight) {
                if ($sum) {
                    $weightedSum = $weight/$sum;
                } else {
                    $weightedSum = 0.5;
                }
                $this->showTag($tag, $weight, $weightedSum);
            }
            $this->elementEnd('ul');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
            $this->elementEnd('div');
        } else {
            $this->showEmptyList();
        }
    }

    function showTag($tag, $weight, $relative)
    {
        if ($relative > 0.1) {
            $rel =  'tag-cloud-7';
        } else if ($relative > 0.05) {
            $rel = 'tag-cloud-6';
        } else if ($relative > 0.02) {
            $rel = 'tag-cloud-5';
        } else if ($relative > 0.01) {
            $rel = 'tag-cloud-4';
        } else if ($relative > 0.005) {
            $rel = 'tag-cloud-3';
        } else if ($relative > 0.002) {
            $rel = 'tag-cloud-2';
        } else {
            $rel = 'tag-cloud-1';
        }

        $this->elementStart('li', $rel);

        // TRANS: Link title for number of listed people. %d is the number of listed people.
        $title = sprintf(_m('1 person listed','%d people listed',$weight),$weight);
        $this->element('a', array('href'  => common_local_url('peopletag', array('tag' => $tag)),
                                  'title' => $title), $tag);
        $this->elementEnd('li');
    }
}
