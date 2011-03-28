<?php

class FileNoticeStream extends CachingNoticeStream
{
    function __construct($file)
    {
        parent::__construct(new RawFileNoticeStream($file),
                            'file:notice-ids:'.$this->url);
    }
}

class RawFileNoticeStream extends NoticeStream
{
    protected $file = null;

    function __construct($file)
    {
        $this->file = $file;
        parent::__construct();
    }

    /**
     * Stream of notices linking to this URL
     *
     * @param integer $offset   Offset to show; default is 0
     * @param integer $limit    Limit of notices to show
     * @param integer $since_id Since this notice
     * @param integer $max_id   Before this notice
     *
     * @return array ids of notices that link to this file
     */
    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $f2p = new File_to_post();

        $f2p->selectAdd();
        $f2p->selectAdd('post_id');

        $f2p->file_id = $this->file->id;

        Notice::addWhereSinceId($f2p, $since_id, 'post_id', 'modified');
        Notice::addWhereMaxId($f2p, $max_id, 'post_id', 'modified');

        $f2p->orderBy('modified DESC, post_id DESC');

        if (!is_null($offset)) {
            $f2p->limit($offset, $limit);
        }

        $ids = array();

        if ($f2p->find()) {
            while ($f2p->fetch()) {
                $ids[] = $f2p->post_id;
            }
        }

        return $ids;
    }
}
