<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ThreadingPublicNoticeStream extends ThreadingNoticeStream
{
    function __construct($scoped)
    {
        parent::__construct(new PublicNoticeStream($scoped));
    }
}
