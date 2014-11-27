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
                'uri' => array('type' => 'varchar', 'not null'=>true, 'length' => 255, 'description' => 'URI of the conversation'),
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
     * Factory method for creating a new conversation.
     *
     * Use this for locally initiated conversations. Remote notices should
     * preferrably supply their own conversation URIs in the OStatus feed.
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
        $conv->uri = sprintf('%s%s=%d:%s=%s',
                             TagURI::mint(),
                             'noticeId', $notice->id,
                             'objectType', 'thread');
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
        $notice->whereAddIn('verb', array(ActivityVerb::POST, ActivityUtils::resolveUri(ActivityVerb::POST, true)), $notice->columnType('verb'));
        $cnt                  = $notice->count();

        self::cacheSet($keypart, $cnt);

        return $cnt;
    }

    static public function getUrlFromNotice(Notice $notice, $anchor=true)
    {
        $conv = self::getKV('id', $notice->conversation);
        return $conv->getUrl($anchor ? $notice->id : null);
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getUrl($noticeId=null)
    {
        // FIXME: the URL router should take notice-id as an argument...
        return common_local_url('conversation', array('id' => $this->id)) .
                ($noticeId===null ? '' : "#notice-{$noticeId}");
    }

    // FIXME: ...will 500 ever be too low? Taken from ConversationAction::MAX_NOTICES
    public function getNotices($offset=0, $limit=500, Profile $scoped=null)
    {
        if ($scoped === null) {
            $scoped = Profile::current();
        }
        $stream = new ConversationNoticeStream($this->id, $scoped);
        $notices = $stream->getNotices($offset, $limit);
        return $notices;
    }
}
