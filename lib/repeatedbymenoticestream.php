<?php

class RepeatedByMeNoticeStream extends CachingNoticeStream
{
    function __construct($user)
    {
        parent::__construct(new RawRepeatedByMeNoticeStream($user),
                            'user:repeated_by_me:'.$user->id);
    }
}

class RawRepeatedByMeNoticeStream extends NoticeStream
{
    protected $user;

    function __construct($user)
    {
        $this->user = $user;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();

        $notice->selectAdd(); // clears it
        $notice->selectAdd('id');

        $notice->profile_id = $this->user->id;
        $notice->whereAdd('repeat_of IS NOT NULL');

        $notice->orderBy('created DESC, id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        Notice::addWhereSinceId($notice, $since_id);
        Notice::addWhereMaxId($notice, $max_id);

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