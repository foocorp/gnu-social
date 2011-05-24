<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List users for autocompletion
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2008-2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * List users for autocompletion
 *
 * This is the form for adding a new g
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class AutocompleteAction extends Action
{
    private $result;

    /**
     * Last-modified date for page
     *
     * When was the content of this page last modified? Based on notice,
     * profile, avatar.
     *
     * @return int last-modified date as unix timestamp
     */
    function lastModified()
    {
        $max=0;
        foreach($this->users as $user){
            $max = max($max,strtotime($user->modified),strtotime($user->getProfile()->modified));
        }
        foreach($this->groups as $group){
            $max = max($max,strtotime($group->modified));
        }
        return $max;
    }

    /**
     * An entity tag for this page
     *
     * Shows the ETag for the page, based on the notice ID and timestamps
     * for the notice, profile, and avatar. It's weak, since we change
     * the date text "one hour ago", etc.
     *
     * @return string etag
     */
    function etag()
    {
        return '"' . implode(':', array($this->arg('action'),
            common_user_cache_hash(),
            crc32($this->arg('q')), //the actual string can have funny characters in we don't want showing up in the etag
            $this->arg('limit'),
            $this->lastModified())) . '"';
    }

    function prepare($args)
    {
        // If we die, show short error messages.
        StatusNet::setApi(true);

        parent::prepare($args);

        $cur = common_current_user();
        if (!$cur) {
            // TRANS: Client exception in autocomplete plugin.
            throw new ClientException(_m('Access forbidden.'), true);
        }
        $this->groups=array();
        $this->users=array();
        $q = $this->arg('q');
        $limit = $this->arg('limit');
        if($limit > 200) $limit=200; //prevent DOS attacks
        if(substr($q,0,1)=='@'){
            //user search
            $q=substr($q,1);
            $user = new User();
            $user->limit($limit);
            $user->whereAdd('nickname like \'' . trim($user->escape($q), '\'') . '%\'');
            if($user->find()){
                while($user->fetch()) {
                    $this->users[]=clone($user);
                }
            }
        }
        if(substr($q,0,1)=='!'){
            //group search
            $q=substr($q,1);
            $group = new User_group();
            $group->limit($limit);
            $group->whereAdd('nickname like \'' . trim($group->escape($q), '\'') . '%\'');
            if($group->find()){
                while($group->fetch()) {
                    $this->groups[]=clone($group);
                }
            }
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $results = array();
        foreach($this->users as $user){
            $profile = $user->getProfile();
            $avatar = $profile->getAvatar(AVATAR_MINI_SIZE);
            // sigh.... encapsulate this upstream!
            if ($avatar) {
                $avatar = $avatar->displayUrl();
            } else {
                $avatar = Avatar::defaultImage(AVATAR_MINI_SIZE);
            }
            $results[] = array(
                'nickname' => $user->nickname,
                'fullname'=> $profile->fullname,
                'avatar' => $avatar,
                'type' => 'user'
            );
        }
        foreach($this->groups as $group){
            // sigh.... encapsulate this upstream!
            if ($group->mini_logo) {
                $avatar = $group->mini_logo;
            } else {
                $avatar = User_group::defaultLogo(AVATAR_MINI_SIZE);
            }
            $results[] = array(
                'nickname' => $group->nickname,
                'fullname'=> $group->fullname,
                'avatar' => $avatar,
                'type' => 'group');
        }
        foreach($results as $result) {
            print json_encode($result) . "\n";
        }
    }

    /**
     * Is this action read-only?
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }
}
