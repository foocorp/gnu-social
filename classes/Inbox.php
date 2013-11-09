<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Data class for user location preferences
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
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class Inbox extends Managed_DataObject {
    const BOXCAR = 128;
    const MAX_NOTICES = 1024;

    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'inbox';                           // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $notice_ids;                      // blob

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'user_id' => array('type' => 'int', 'not null' => true, 'description' => 'user receiving the notice'),
                'notice_ids' => array('type' => 'blob', 'description' => 'packed list of notice ids'),
            ),
            'primary key' => array('user_id'),
            'foreign keys' => array(
                'inbox_user_id_fkey' => array('user', array('user_id' => 'id')),
            ),
        );
    }

    /**
     * Append the given notice to the given user's inbox.
     * Caching updates are managed for the inbox itself.
     *
     * If the notice is already in this inbox, the second
     * add will be silently dropped.
     *
     * @param int @user_id
     * @param int $notice_id
     * @return boolean success
     */
    static function insertNotice(Notice $notice, $user_id)
    {
        // Going straight to the DB rather than trusting our caching
        // during an update. Note: not using DB_DataObject::staticGet,
        // which is unsafe to use directly (in-process caching causes
        // memory leaks, which accumulate in queue processes).
        $inbox = new Inbox();
        $inbox->get('user_id', $user_id);

        if (empty($inbox)) {
            return false;
        }

        $ids = $inbox->unpack();
        if (in_array(intval($notice->id), $ids)) {
            // Already in there, we probably re-ran some inbox adds
            // due to an error. Skip the dupe silently.
            return true;
        }

        $result = $inbox->query(sprintf('UPDATE inbox '.
                                        'SET notice_ids = concat(cast(0x%08x as binary(4)), '.
                                        'SUBSTR(notice_ids, 1, %d)) '.
                                        'WHERE user_id = %d',
                                        $notice->id,
                                        4 * (self::MAX_NOTICES - 1),
                                        $user_id));

        if ($result !== false) {
            self::blow('inbox:user_id:%d', $user_id);
        }

        return $result;
    }

    static function bulkInsert(Notice $notice, array $user_ids)
    {
        foreach ($user_ids as $user_id)
        {
            self::insertNotice($notice, $user_id);
        }
    }

    /**
     * Saves a list of integer notice_ids into a packed blob in this object.
     * @param array $ids list of integer notice_ids
     */
    function pack(array $ids)
    {
        $this->notice_ids = call_user_func_array('pack', array_merge(array('N*'), $ids));
    }

    /**
     * @return array of integer notice_ids
     */
    function unpack()
    {
        return unpack('N*', $this->notice_ids);
    }
}
