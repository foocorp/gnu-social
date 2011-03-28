<?php

class TaggedProfileNoticeStream extends CachingNoticeStream
{
    function __construct($profile, $tag)
    {
        parent::__construct(new RawTaggedProfileNoticeStream($profile, $tag),
                            'profile:notice_ids_tagged:'.$profile->id.':'.Cache::keyize($tag));
    }
}

class RawTaggedProfileNoticeStream extends NoticeStream
{
    protected $profile;
    protected $tag;

    function __construct($profile, $tag)
    {
        $this->profile = $profile;
        $this->tag     = $tag;
    }

    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        // XXX It would be nice to do this without a join
        // (necessary to do it efficiently on accounts with long history)

        $notice = new Notice();

        $query =
          "select id from notice join notice_tag on id=notice_id where tag='".
          $notice->escape($this->tag) .
          "' and profile_id=" . intval($this->profile->id);

        $since = Notice::whereSinceId($since_id, 'id', 'notice.created');
        if ($since) {
            $query .= " and ($since)";
        }

        $max = Notice::whereMaxId($max_id, 'id', 'notice.created');
        if ($max) {
            $query .= " and ($max)";
        }

        $query .= ' order by notice.created DESC, id DESC';

        if (!is_null($offset)) {
            $query .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
        }

        $notice->query($query);

        $ids = array();

        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        return $ids;
    }
}
