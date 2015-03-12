<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Placeholder for showing repeats...
 */
class ThreadedNoticeListRepeatsItem extends NoticeListActorsItem
{
    function getProfiles()
    {
        $repeats = Notice::listGet('repeat_of', array($this->notice->getID()));

        $profiles = array();
        foreach ($repeats[$this->notice->getID()] as $rep) {
            $profiles[] = $rep->profile_id;
        }

        return $profiles;
    }

    function magicList($items)
    {
        if (count($items) > 4) {
            return parent::magicList(array_slice($items, 0, 3));
        } else {
            return parent::magicList($items);
        }
    }

    function getListMessage($count, $you)
    {
        if ($count == 1 && $you) {
            // darn first person being different from third person!
            // TRANS: List message for notice repeated by logged in user.
            return _m('REPEATLIST', 'You repeated this.');
        } else if ($count > 4) {
            // TRANS: List message for when more than 4 people repeat something.
            // TRANS: %%s is a list of users liking a notice, %d is the number over 4 that like the notice.
            // TRANS: Plural is decided on the total number of users liking the notice (count of %%s + %d).
            return sprintf(_m('%%s and %d other repeated this.',
                              '%%s and %d others repeated this.',
                              $count - 3),
                           $count - 3);
        } else {
            // TRANS: List message for repeated notices.
            // TRANS: %%s is a list of users who have repeated a notice.
            // TRANS: Plural is based on the number of of users that have repeated a notice.
            return sprintf(_m('%%s repeated this.',
                              '%%s repeated this.',
                              $count),
                           $count);
        }
    }

    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'notice-data notice-repeats'));
    }

    function showEnd()
    {
        $this->out->elementEnd('li');
    }
}
