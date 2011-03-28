<?php

class ConversationNoticeStream extends CachingNoticeStream
{
    function __construct($id)
    {
        parent::__construct(new RawConversationNoticeStream($id),
                            'notice:conversation_ids:'.$id);
    }
}

class RawConversationNoticeStream extends NoticeStream
{
    protected $id;

    function __construct($id)
    {
        $this->id = $id;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();

        $notice->selectAdd(); // clears it
        $notice->selectAdd('id');

        $notice->conversation = $this->id;

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