<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Lists a user has created
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

/**
 * Peopletags a user has subscribed to
 *
 * @category Widget
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ListsNav extends Menu
{
    var $profile=null;
    var $lists=null;

    function __construct($out, Profile $profile)
    {
        parent::__construct($out);
        $this->profile = $profile;

        $user = common_current_user();

        $this->lists = $profile->getLists($user);
    }

    function show()
    {
        $action = $this->actionName;

        $this->out->elementStart('ul', array('class' => 'nav'));

        if (Event::handle('StartListsNav', array($this))) {

            while ($this->lists->fetch()) {
                $mode = $this->lists->private ? 'private' : 'public';
                $this->out->menuItem(($this->lists->mainpage) ?
                                     $this->lists->mainpage :
                                     common_local_url('showprofiletag',
                                                      array('tagger' => $this->profile->nickname,
                                                            'tag'    => $this->lists->tag)),
                                     $this->lists->tag,
                                     '',
                                     $action == 'showprofiletag' &&
                                     $this->action->arg('tagger') == $this->profile->nickname &&
                                     $this->action->arg('tag')    == $this->lists->tag,
                                     'nav_timeline_list_'.$this->lists->id,
                                     'mode-' . $mode);
            }
            Event::handle('EndListsNav', array($this));
        }

        $this->out->elementEnd('ul');
    }

    function hasLists()
    {
        return (!empty($this->lists) && $this->lists->N > 0);
    }
}
