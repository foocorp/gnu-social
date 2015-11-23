<?php

class RawEventsNoticeStream extends NoticeStream
{
    protected $target;
    protected $own;

    function __construct(Profile $target)
    {
        $this->target   = $target;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();
        $qry = null;

        $qry =  'SELECT notice.* FROM notice ';
        $qry .= 'INNER JOIN happening ON happening.uri = notice.uri ';
        $qry .= 'WHERE happening.profile_id = ' . $this->target->getID() . ' ';
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

class EventsNoticeStream extends ScopingNoticeStream
{
    function __construct(Profile $target, Profile $scoped=null)
    {
        $stream = new RawEventsNoticeStream($target);

        if ($target->sameAs($scoped)) {
            $key = 'bookmark:ids_by_user_own:'.$target->getID();
        } else {
            $key = 'bookmark:ids_by_user:'.$target->getID();
        }

        parent::__construct(new CachingNoticeStream($stream, $key), $scoped);
    }
}
