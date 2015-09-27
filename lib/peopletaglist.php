<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a list of peopletags
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
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Widget to show a list of peopletags
 *
 * @category Public
 * @package  StatusNet
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class PeopletagList extends Widget
{
    /** Current peopletag, peopletag query. */
    var $peopletag = null;
    /** current user **/
    var $user = null;

    function __construct($peopletag, $action=null)
    {
        parent::__construct($action);

        $this->peopletag = $peopletag;

        if (!empty($owner)) {
            $this->user = $owner;
        } else {
            $this->user = common_current_user();
        }
    }

    function show()
    {
        $this->out->elementStart('ul', 'peopletags xoxo hfeed');

        $cnt = 0;

        while ($this->peopletag->fetch()) {
            $cnt++;
            if($cnt > PEOPLETAGS_PER_PAGE) {
                break;
            }
            $this->showPeopletag();
        }

        $this->out->elementEnd('ul');

        return $cnt;
    }

    function showPeopletag()
    {
        $ptag = new PeopletagListItem($this->peopletag, $this->user, $this->out);
        $ptag->show();
    }
}
