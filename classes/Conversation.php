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
    public $id;                              // int(4)  primary_key not_null auto_increment
    public $uri;                             // varchar(191)  unique_key   not 255 because utf8mb4 takes more space
    public $created;                         // datetime   not_null
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'serial', 'not null' => true, 'description' => 'Unique identifier, (again) unrelated to notice id since 2016-01-06'),
                'uri' => array('type' => 'varchar', 'not null'=>true, 'length' => 191, 'description' => 'URI of the conversation'),
                'created' => array('type' => 'datetime', 'not null' => true, 'description' => 'date this record was created'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'conversation_uri_key' => array('uri'),
            ),
        );
    }

    static public function beforeSchemaUpdate()
    {
        $table = strtolower(get_called_class());
        $schema = Schema::get();
        $schemadef = $schema->getTableDef($table);

        // 2016-01-06 We have to make sure there is no conversation with id==0 since it will screw up auto increment resequencing
        if ($schemadef['fields']['id']['auto_increment']) {
            // since we already have auto incrementing ('serial') we can continue
            return;
        }

        // The conversation will be recreated in upgrade.php, which will
        // generate a new URI, but that's collateral damage for you.
        $conv = new Conversation();
        $conv->id = 0;
        if ($conv->find()) {
            while ($conv->fetch()) {
                // Since we have filtered on 0 this only deletes such entries
                // which I have been afraid wouldn't work, but apparently does!
                // (I thought it would act as null or something and find _all_ conversation entries)
                $conv->delete();
            }
        }
    }

    /**
     * Factory method for creating a new conversation.
     *
     * Use this for locally initiated conversations. Remote notices should
     * preferrably supply their own conversation URIs in the OStatus feed.
     *
     * @return Conversation the new conversation DO
     */
    static function create($uri=null, $created=null)
    {
        // Be aware that the Notice does not have an id yet since it's not inserted!
        $conv = new Conversation();
        $conv->created = $created ?: common_sql_now();
        $conv->uri = $uri ?: sprintf('%s%s=%s:%s=%s',
                             TagURI::mint(),
                             'objectType', 'thread',
                             'nonce', common_random_hexstr(8));
        // This insert throws exceptions on failure
        $conv->insert();

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
        $notice->whereAddIn('verb', array(ActivityVerb::POST, ActivityUtils::resolveUri(ActivityVerb::POST, true)), $notice->columnType('verb'));
        $cnt                  = $notice->count();

        self::cacheSet($keypart, $cnt);

        return $cnt;
    }

    static public function getUrlFromNotice(Notice $notice, $anchor=true)
    {
        $conv = Conversation::getByID($notice->conversation);
        return $conv->getUrl($anchor ? $notice->getID() : null);
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getUrl($noticeId=null)
    {
        // FIXME: the URL router should take notice-id as an argument...
        return common_local_url('conversation', array('id' => $this->getID())) .
                ($noticeId===null ? '' : "#notice-{$noticeId}");
    }

    // FIXME: ...will 500 ever be too low? Taken from ConversationAction::MAX_NOTICES
    public function getNotices(Profile $scoped=null, $offset=0, $limit=500)
    {
        $stream = new ConversationNoticeStream($this->getID(), $scoped);
        $notices = $stream->getNotices($offset, $limit);
        return $notices;
    }

    public function insert()
    {
        $result = parent::insert();
        if ($result === false) {
            common_log_db_error($this, 'INSERT', __FILE__);
            throw new ServerException(_('Failed to insert Conversation into database'));
        }
        return $result;
    }
}
