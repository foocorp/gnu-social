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
 
        $fave = Fave::addNew($this->user->getProfile(), $notice); 
 
        if (!$fave) { 
            // TRANS: Error message text shown when a favorite could not be set. 
            $channel->error($this->user, _('Could not create favorite.')); 
            return; 
        } 
 
        // @fixme favorite notification should be triggered 
        // at a lower level 
 
        $other = User::getKV('id', $notice->profile_id); 
 
        if ($other && $other->id != $this->user->id && !empty($other->email)) { 
            require_once INSTALLDIR.'/lib/mail.php';

            mail_notify_fave($other, $this->user->getProfile(), $notice);
        } 
 
        Fave::blowCacheForProfileId($this->user->id);
 
        // TRANS: Text shown when a notice has been marked as favourite successfully. 
        $channel->output($this->user, _('Notice marked as fave.')); 
    } 
}
