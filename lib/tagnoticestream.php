<?php

class TagNoticeStream extends CachingNoticeStream
{
    function __construct($tag)
    {
        parent::__construct(new RawTagNoticeStream($tag),
                            'notice_tag:notice_ids:' . Cache::keyize($tag));
    }
}

class RawTagNoticeStream extends NoticeStream
{
    protected $tag;

    function __construct($tag)
    {
        $this->tag = $tag;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $nt = new Notice_tag();

        $nt->tag = $this->tag;

        $nt->selectAdd();
        $nt->selectAdd('notice_id');

        Notice::addWhereSinceId($nt, $since_id, 'notice_id');
        Notice::addWhereMaxId($nt, $max_id, 'notice_id');

        $nt->orderBy('created DESC, notice_id DESC');

        if (!is_null($offset)) {
            $nt->limit($offset, $limit);
        }

        $ids = array();

        if ($nt->find()) {
            while ($nt->fetch()) {
                $ids[] = $nt->notice_id;
            }
        }

        return $ids;
    }
}
