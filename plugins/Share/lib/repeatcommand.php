<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class RepeatCommand extends Command 
{ 
    var $other = null; 
    function __construct($user, $other) 
    { 
        parent::__construct($user); 
        $this->other = $other; 
    } 
 
    function handle($channel) 
    { 
        $notice = $this->getNotice($this->other); 
 
        try {
            $repeat = $notice->repeat($this->scoped->id, $channel->source());
            $recipient = $notice->getProfile();

            // TRANS: Message given having repeated a notice from another user.
            // TRANS: %s is the name of the user for which the notice was repeated.
            $channel->output($this->user, sprintf(_('Notice from %s repeated.'), $recipient->nickname));
        } catch (Exception $e) {
            $channel->error($this->user, $e->getMessage());
        }
    } 
}
