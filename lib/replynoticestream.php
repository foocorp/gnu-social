<?php

class ReplyNoticeStream extends CachingNoticeStream
{
    function __construct($userId)
    {
        parent::__construct(new RawReplyNoticeStream($userId),
                            'reply:stream:' . $userId);
    }
}

class RawReplyNoticeStream extends NoticeStream
{
    protected $userId;

    function __construct($userId)
    {
        $this->userId = $userId;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $reply = new Reply();
        $reply->profile_id = $this->userId;

        Notice::addWhereSinceId($reply, $since_id, 'notice_id', 'modified');
        Notice::addWhereMaxId($reply, $max_id, 'notice_id', 'modified');

        $reply->orderBy('modified DESC, notice_id DESC');

        if (!is_null($offset)) {
            $reply->limit($offset, $limit);
        }

        $ids = array();

        if ($reply->find()) {
            while ($reply->fetch()) {
                $ids[] = $reply->notice_id;
            }
        }

        return $ids;
    }
}