<?php
class FeedSubException extends Exception
{
    function __construct($msg=null)
    {
        $type = get_class($this);
        if ($msg) {
            parent::__construct("$type: $msg");
        } else {
            parent::__construct($type);
        }
    }
}
