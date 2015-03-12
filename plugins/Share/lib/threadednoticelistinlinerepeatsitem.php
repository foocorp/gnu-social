<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class ThreadedNoticeListInlineRepeatsItem extends ThreadedNoticeListRepeatsItem
{
    function showStart()
    {
        $this->out->elementStart('div', array('class' => 'notice-repeats'));
    }

    function showEnd()
    {
        $this->out->elementEnd('div');
    }
}
