<?php

class FaveNoticeStream extends CachingNoticeStream
{
    function __construct($user_id, $own)
    {
        $stream = new RawFaveNoticeStream($user_id, $own);
        if ($own) {
            $key = 'fave:ids_by_user_own:'.$user_id;
        } else {
            $key = 'fave:ids_by_user:'.$user_id;
        }
        parent::__construct($stream, $key);
    }
}

class RawFaveNoticeStream extends NoticeStream
{
    protected $user_id;
    protected $own;

    function __construct($user_id, $own)
    {
        $this->user_id = $user_id;
        $this->own     = $own;
    }

    /**
     * Note that the sorting for this is by order of *fave* not order of *notice*.
     *
     * @fixme add since_id, max_id support?
     *
     * @param <type> $user_id
     * @param <type> $own
     * @param <type> $offset
     * @param <type> $limit
     * @param <type> $since_id
     * @param <type> $max_id
     * @return <type>
     */
    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $fav = new Fave();
        $qry = null;

        if ($this->own) {
            $qry  = 'SELECT fave.* FROM fave ';
            $qry .= 'WHERE fave.user_id = ' . $this->user_id . ' ';
        } else {
             $qry =  'SELECT fave.* FROM fave ';
             $qry .= 'INNER JOIN notice ON fave.notice_id = notice.id ';
             $qry .= 'WHERE fave.user_id = ' . $this->user_id . ' ';
             $qry .= 'AND notice.is_local != ' . Notice::GATEWAY . ' ';
        }

        if ($since_id != 0) {
            $qry .= 'AND notice_id > ' . $since_id . ' ';
        }

        if ($max_id != 0) {
            $qry .= 'AND notice_id <= ' . $max_id . ' ';
        }

        // NOTE: we sort by fave time, not by notice time!

        $qry .= 'ORDER BY modified DESC ';

        if (!is_null($offset)) {
            $qry .= "LIMIT $limit OFFSET $offset";
        }

        $fav->query($qry);

        $ids = array();

        while ($fav->fetch()) {
            $ids[] = $fav->notice_id;
        }

        $fav->free();
        unset($fav);

        return $ids;
    }
}

