<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ThreadingNetworkPublicNoticeStream extends ThreadingNoticeStream
{
    public function __construct(Profile $scoped=null)
    {
        parent::__construct(new NetworkPublicNoticeStream($scoped));
    }
}
