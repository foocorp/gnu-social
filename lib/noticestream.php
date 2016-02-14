<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A stream of notices
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Class for notice streams
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
abstract class NoticeStream
{
    protected $selectVerbs   = array(ActivityVerb::POST => true,
                                     ActivityVerb::DELETE => false);

    public function __construct()
    {
        foreach ($this->selectVerbs as $key=>$val) {
            // to avoid database inconsistency issues we select both relative and absolute verbs
            $this->selectVerbs[ActivityUtils::resolveUri($key)] = $val;
            $this->selectVerbs[ActivityUtils::resolveUri($key, true)] = $val;
        }
    }

    abstract function getNoticeIds($offset, $limit, $since_id, $max_id);

    function getNotices($offset, $limit, $sinceId = null, $maxId = null)
    {
        $ids = $this->getNoticeIds($offset, $limit, $sinceId, $maxId);

        $notices = self::getStreamByIds($ids);

        return $notices;
    }

    static function getStreamByIds($ids)
    {
    	return Notice::multiGet('id', $ids);
    }

    static function filterVerbs(Notice $notice, array $selectVerbs)
    {
        $filter = array_keys(array_filter($selectVerbs));
        if (!empty($filter)) {
            // include verbs in selectVerbs with values that equate to true
            $notice->whereAddIn('verb', $filter, $notice->columnType('verb'));
        }

        $filter = array_keys(array_filter($selectVerbs, function ($v) { return !$v; }));
        if (!empty($filter)) {
            // exclude verbs in selectVerbs with values that equate to false
            $notice->whereAddIn('!verb', $filter, $notice->columnType('verb'));
        }

        unset($filter);
    }
}
