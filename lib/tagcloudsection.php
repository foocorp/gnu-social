<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections showing tag clouds
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
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('TAGS_PER_SECTION', 10);

/**
 * Base class for sections
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class TagCloudSection extends Section
{
    function showContent()
    {
        $tags = $this->getTags();

        if (!$tags) {
            // TRANS: Content displayed in a tag cloud section if there are no tags.
            $this->out->element('p', null, _m('NOTAGS','None'));
            return false;
        }

        $cnt = 0;

        $tw = array();
        $sum = 0;

        while ($tags->fetch() && ++$cnt <= TAGS_PER_SECTION) {
            $tw[$tags->tag] = $tags->weight;
            $sum += $tags->weight;
        }

        if ($cnt == 0) {
            // TRANS: Content displayed in a tag cloud section if there are no tags.
            $this->out->element('p', null, _m('NOTAGS','None'));
            return false;
        }

        ksort($tw);

        $this->out->elementStart('ul', 'tags xoxo tag-cloud');
        foreach ($tw as $tag => $weight) {
            $this->showTag($tag, $weight, ($sum == 0) ? 0 : $weight/$sum);
        }
        $this->out->elementEnd('ul');

        return ($cnt > TAGS_PER_SECTION);
    }

    function getTags()
    {
        return null;
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

        $this->out->elementStart('li', $rel);
        $this->out->element('a', array('href' => $this->tagUrl($tag)),
                       $tag);
        $this->out->elementEnd('li');
    }

    function tagUrl($tag)
    {
        if ($this->out instanceof ShowstreamAction) {
            return common_local_url('showstream', array('nickname' => $this->out->getTarget()->getNickname(), 'tag' => $tag));
        }
        return common_local_url('tag', array('tag' => $tag));
    }

    function divId()
    {
        return 'tagcloud';
    }
}
