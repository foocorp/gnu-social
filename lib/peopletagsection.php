<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of peopletags
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
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/peopletaglist.php';

define('PEOPLETAGS_PER_SECTION', 6);

/**
 * Base class for sections
 *
 * These are the widgets that show interesting data about a person
 * peopletag, or site.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class PeopletagSection extends Section
{
    protected $avatarSize = AVATAR_MINI_SIZE;

    function showContent()
    {
        $tags = $this->getPeopletags();

        if (!$tags) {
            return false;
        }

        $cnt = 0;

        $this->out->elementStart('table', 'peopletag_section');

        while ($tags->fetch() && ++$cnt <= PEOPLETAGS_PER_SECTION) {
            $this->showPeopletag($tags);
        }

        $this->out->elementEnd('table');

        return ($cnt > PEOPLETAGS_PER_SECTION);
    }

    function getPeopletags()
    {
        return null;
    }

    function showPeopletag($peopletag)
    {
        $tag = new PeopletagSectionItem($peopletag, null, $this->out);
        $tag->show();
    }
}

class PeopletagSectionItem extends PeopletagListItem
{
    function showStart()
    {
    }

    function showEnd()
    {
    }

    function showPeopletag()
    {
        $this->showCreator();
        $this->showTag();
        $this->showPrivacy();
    }

    function show()
    {
        if (empty($this->peopletag)) {
            common_log(LOG_WARNING, "Trying to show missing peopletag; skipping.");
            return;
        }
        $mode = ($this->peopletag->private) ? 'private' : 'public';

        $this->out->elementStart('tr');

        $this->out->elementStart('td', 'peopletag mode-' . $mode);
        $this->showPeopletag();
        $this->out->elementEnd('td');

        if (isset($this->peopletag->value)) {
            $this->out->element('td', 'value', $this->peopletag->value);
        }
        $this->out->elementEnd('tr');
    }

    function showTag()
    {
        // TRANS: List summary. %1$d is the number of users in the list,
        // TRANS: %2$d is the number of subscribers to the list.
        $title = sprintf(_('Listed: %1$d Subscribers: %2$d'),
                         $this->peopletag->taggedCount(),
                         $this->peopletag->subscriberCount());

        $this->out->elementStart('span', 'entry-title tag');

        $this->out->element('a',
            array('rel'   => 'bookmark',
                  'href'  => $this->url(),
                  'title' => $title),
            htmlspecialchars($this->peopletag->tag));
        $this->out->elementEnd('span');
    }
}
