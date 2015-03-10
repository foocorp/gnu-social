<?php

class FavCommand extends Command 
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
            $fave = Fave::addNew($this->user->getProfile(), $notice); 
        } catch (Exception $e) {
            $channel->error($this->user, $e->getMessage());
            return;
        }
 
        // TRANS: Text shown when a notice has been marked as favourite successfully. 
        $channel->output($this->user, _('Notice marked as fave.')); 
    } 
}
