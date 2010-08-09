<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Data class for user IM preferences
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
 * @category  Data
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class User_im_prefs extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user_im_prefs';       // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $screenname;                      // varchar(255)  not_null
    public $transport;                       // varchar(255)  not_null
    public $notify;                          // tinyint(1)
    public $replies;                         // tinyint(1)
    public $microid;                         // tinyint(1)
    public $updatefrompresence;              // tinyint(1)
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('User_im_prefs',$k,$v); }

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('User_im_prefs', $kv);
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /*
    DB_DataObject calculates the sequence key(s) by taking the first key returned by the keys() function.
    In this case, the keys() function returns user_id as the first key. user_id is not a sequence, but
    DB_DataObject's sequenceKey() will incorrectly think it is. Then, since the sequenceKey() is a numeric
    type, but is not set to autoincrement in the database, DB_DataObject will create a _seq table and
    manage the sequence itself. This is not the correct behavior for the user_id in this class.
    So we override that incorrect behavior, and simply say there is no sequence key.
    */
    function sequenceKey()
    {
        return array(false,false);
    }

    /**
     * We have two compound keys with unique constraints:
     * (transport, user_id) which is our primary key, and
     * (transport, screenname) which is an additional constraint.
     * 
     * Currently there's not a way to represent that second key
     * in the general keys list, so we're adding it here to the
     * list of keys to use for caching, ensuring that it gets
     * cleared as well when we change.
     * 
     * @return array of cache keys
     */
    function _allCacheKeys()
    {
        $ukeys = 'transport,screenname';
        $uvals = $this->transport . ',' . $this->screenname;

        $ckeys = parent::_allCacheKeys();
        $ckeys[] = $this->cacheKey($this->tableName(), $ukeys, $uvals);
        return $ckeys;
    }

}
