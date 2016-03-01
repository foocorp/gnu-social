<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ThreadingGroupNoticeStream extends ThreadingNoticeStream
{
    function __construct($group, Profile $scoped=null)
    {
        parent::__construct(new GroupNoticeStream($group, $scoped));
    }
}
