<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class RawBookmarksNoticeStream extends NoticeStream
{
    protected $user_id;
    protected $own;

    function __construct($user_id, $own)
    {
        $this->user_id = $user_id;
        $this->own     = $own;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();
        $qry = null;

        $qry =  'SELECT notice.* FROM notice ';
        $qry .= 'INNER JOIN bookmark ON bookmark.uri = notice.uri ';
        $qry .= 'WHERE bookmark.profile_id = ' . $this->user_id . ' ';
        $qry .= 'AND notice.is_local != ' . Notice::GATEWAY . ' ';

        if ($since_id != 0) {
            $qry .= 'AND notice.id > ' . $since_id . ' ';
        }

        if ($max_id != 0) {
            $qry .= 'AND notice.id <= ' . $max_id . ' ';
        }

        // NOTE: we sort by bookmark time, not by notice time!
        $qry .= 'ORDER BY created DESC ';
        if (!is_null($offset)) {
            $qry .= "LIMIT $limit OFFSET $offset";
        }

        $notice->query($qry);
        $ids = array();
        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        $notice->free();
        unset($notice);
        return $ids;
    }
}

/**
 * Notice stream for bookmarks
 *
 * @category  Stream
 * @package   StatusNet
 * @author    Stephane Berube <chimo@chromic.org>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class BookmarksNoticeStream extends ScopingNoticeStream
{
    function __construct($user_id, $own, Profile $scoped=null)
    {
        $stream = new RawBookmarksNoticeStream($user_id, $own);

        if ($own) {
            $key = 'bookmark:ids_by_user_own:'.$user_id;
        } else {
            $key = 'bookmark:ids_by_user:'.$user_id;
        }

        parent::__construct(new CachingNoticeStream($stream, $key), $scoped);
    }
}
