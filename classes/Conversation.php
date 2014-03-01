<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Data class for Conversations
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
 * @author    Zach Copley <zach@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2010 StatusNet Inc.
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class Conversation extends Managed_DataObject
{
    public $__table = 'conversation';        // table name
    public $id;                              // int(4)  primary_key not_null
    public $uri;                             // varchar(255)  unique_key
    public $created;                         // datetime   not_null
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'int', 'not null' => true, 'description' => 'should be set from root notice id (since 2014-03-01 commit)'),
                'uri' => array('type' => 'varchar', 'length' => 255, 'description' => 'URI of the conversation'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'conversation_uri_key' => array('uri'),
            ),
        );
    }

    /**
     * Factory method for creating a new conversation
     *
     * @return Conversation the new conversation DO
     */
    static function create(Notice $notice)
    {
        if (empty($notice->id)) {
            throw new ServerException(_('Tried to create conversation for not yet inserted notice'));
        }
        $conv = new Conversation();
        $conv->created = common_sql_now();
        $conv->id = $notice->id;
        $conv->uri = common_local_url('conversation', array('id' => $notice->id), null, null, false);
        $result = $conv->insert();

        if ($result === false) {
            common_log_db_error($conv, 'INSERT', __FILE__);
            throw new ServerException(_('Failed to create conversation for notice'));
        }

        return $conv;
    }

    static function noticeCount($id)
    {
        $keypart = sprintf('conversation:notice_count:%d', $id);

        $cnt = self::cacheGet($keypart);

        if ($cnt !== false) {
            return $cnt;
        }

        $notice               = new Notice();
        $notice->conversation = $id;
        $cnt                  = $notice->count();

        self::cacheSet($keypart, $cnt);

        return $cnt;
    }
}
