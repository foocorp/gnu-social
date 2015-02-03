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
 
        $fave            = new Fave(); 
        $fave->user_id   = $this->user->id; 
        $fave->notice_id = $notice->id; 
        $fave->find(); 
 
        if ($fave->fetch()) { 
            // TRANS: Error message text shown when a favorite could not be set because it has already been favorited. 
            $channel->error($this->user, _('Could not create favorite: Already favorited.')); 
            return; 
        } 
 
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
