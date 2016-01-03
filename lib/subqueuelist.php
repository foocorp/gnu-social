<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class SubQueueList extends ProfileList
{
    public function newListItem(Profile $target)
    {
        return new SubQueueListItem($target, $this->action);
    }
}
