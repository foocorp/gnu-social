<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of notices
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

if (!defined('GNUSOCIAL') && !defined('GNUSOCIAL')) { exit(1); }

define('NOTICES_PER_SECTION', 6);

/**
 * Base class for sections showing lists of notices
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @todo migrate this to use a variant of NoticeList
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

abstract class NoticeSection extends Section
{
    protected $addressees = false;
    protected $attachments = false;
    protected $maxchars = 140;
    protected $options = false;
    protected $show_n = NOTICES_PER_SECTION;

    function showContent()
    {
        $prefs = array();
        foreach (array('addressees', 'attachments', 'maxchars', 'options', 'show_n') as $key) {
            $prefs[$key] = $this->$key;
        }
        $prefs['id_prefix'] = $this->divId();

        // args: notice object, html outputter, preference array for notice lists and their items
        $list = new NoticeList($this->getNotices(), $this->out, $prefs);
        $total = $list->show(); // returns total amount of notices available
        return ($total > $this->show_n);  // do we have more to show?
    }

    function getNotices()
    {
        return null;
    }
}
