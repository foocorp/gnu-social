<?php

if (!defined('GNUSOCIAL')) { exit(1); }

interface NoticestreamAction
{
    // this fetches the NoticeStream
    public function getStream();
}
