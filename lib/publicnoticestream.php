<?php

class PublicNoticeStream extends CachingNoticeStream
{
    function __construct()
    {
        parent::__construct(new RawPublicNoticeStream(), 'public');
    }
}

class RawPublicNoticeStream extends NoticeStream
{
    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();

        $notice->selectAdd(); // clears it
        $notice->selectAdd('id');

        $notice->orderBy('created DESC, id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        if (common_config('public', 'localonly')) {
            $notice->whereAdd('is_local = ' . Notice::LOCAL_PUBLIC);
        } else {
            // -1 == blacklisted, -2 == gateway (i.e. Twitter)
            $notice->whereAdd('is_local !='. Notice::LOCAL_NONPUBLIC);
            $notice->whereAdd('is_local !='. Notice::GATEWAY);
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