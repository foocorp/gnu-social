<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ThreadingGroupNoticeStream extends ThreadingNoticeStream
{
    function __construct($group, $profile)
    {
        parent::__construct(new GroupNoticeStream($group, $profile));
    }
}
