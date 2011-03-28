<?php

class GroupNoticeStream extends CachingNoticeStream
{
    function __construct($group)
    {
        parent::__construct(new RawGroupNoticeStream($group),
                            'user_group:notice_ids:' . $group->id);
    }
}

class RawGroupNoticeStream extends NoticeStream
{
    protected $group;

    function __construct($group)
    {
        $this->group = $group;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $inbox = new Group_inbox();

        $inbox->group_id = $this->group->id;

        $inbox->selectAdd();
        $inbox->selectAdd('notice_id');

        Notice::addWhereSinceId($inbox, $since_id, 'notice_id');
        Notice::addWhereMaxId($inbox, $max_id, 'notice_id');

        $inbox->orderBy('created DESC, notice_id DESC');

        if (!is_null($offset)) {
            $inbox->limit($offset, $limit);
        }

        $ids = array();

        if ($inbox->find()) {
            while ($inbox->fetch()) {
                $ids[] = $inbox->notice_id;
            }
        }

        return $ids;
    }
}
