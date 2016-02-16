<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Class for notice streams that does not filter anything out.
 */
class FullNoticeStream extends NoticeStream
{
    protected $selectVerbs = [];
}
