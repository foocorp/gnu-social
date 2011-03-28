<?php

class RepeatsOfMeNoticeStream extends CachingNoticeStream
{
    function __construct($user)
    {
        parent::__construct(new RawRepeatsOfMeNoticeStream($user),
                            'user:repeats_of_me:'.$user->id);
    }
}

class RawRepeatsOfMeNoticeStream extends NoticeStream
{
    protected $user;

    function __construct($user)
    {
        $this->user = $user;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $qry =
          'SELECT DISTINCT original.id AS id ' .
          'FROM notice original JOIN notice rept ON original.id = rept.repeat_of ' .
          'WHERE original.profile_id = ' . $this->user->id . ' ';

        $since = Notice::whereSinceId($since_id, 'original.id', 'original.created');
        if ($since) {
            $qry .= "AND ($since) ";
        }

        $max = Notice::whereMaxId($max_id, 'original.id', 'original.created');
        if ($max) {
            $qry .= "AND ($max) ";
        }

        $qry .= 'ORDER BY original.created, original.id DESC ';

        if (!is_null($offset)) {
            $qry .= "LIMIT $limit OFFSET $offset";
        }

        $ids = array();

        $notice = new Notice();

        $notice->query($qry);

        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        $notice->free();
        $notice = NULL;

        return $ids;
    }
}
