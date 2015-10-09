<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class Peopletag extends PeopletagListItem
{
    protected $avatarSize = AVATAR_PROFILE_SIZE;

    function showStart()
    {
        $mode = $this->peopletag->private ? 'private' : 'public';
        $this->out->elementStart('div', array('class' => 'h-entry peopletag peopletag-profile mode-'.$mode,
                                             'id' => 'peopletag-' . $this->peopletag->id));
    }

    function showEnd()
    {
        $this->out->elementEnd('div');
    }
}
