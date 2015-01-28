<?php

if (!defined('GNUSOCIAL')) { exit(1); } 

class NetworkPublicNoticeStream extends ScopingNoticeStream
{
    function __construct(Profile $scoped=null)
    {
        parent::__construct(new CachingNoticeStream(new RawNetworkPublicNoticeStream(),
                                                    'networkpublic'),
                            $scoped);
    }
}

/**
 * Raw public stream
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class RawNetworkPublicNoticeStream extends NoticeStream
{
    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();

        $notice->selectAdd(); // clears it
        $notice->selectAdd('id');

        $notice->orderBy('created DESC, id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        $notice->whereAdd('is_local ='. Notice::REMOTE);
        // -1 == blacklisted, -2 == gateway (i.e. Twitter)
        $notice->whereAdd('is_local !='. Notice::LOCAL_NONPUBLIC);
        $notice->whereAdd('is_local !='. Notice::GATEWAY);

        Notice::addWhereSinceId($notice, $since_id);
        Notice::addWhereMaxId($notice, $max_id);

        if (!empty($this->selectVerbs)) {
            $notice->whereAddIn('verb', $this->selectVerbs, $notice->columnType('verb'));
        }

        $ids = array();

        if ($notice->find()) {
            while ($notice->fetch()) {
                $ids[] = $notice->id;
            }
        }

        $notice->free();
        $notice = NULL;

        return $ids;
    }
}
