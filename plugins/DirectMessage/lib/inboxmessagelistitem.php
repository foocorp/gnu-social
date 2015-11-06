<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class InboxMessageListItem extends MessageListItem
{
    /**
     * Returns the profile we want to show with the message
     *
     * @return Profile The profile that matches the message
     */
    function getMessageProfile()
    {
        return $this->message->getFrom();
    }
}
