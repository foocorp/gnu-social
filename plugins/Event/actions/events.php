<?php
/**
 * List events
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class EventsAction extends ShowstreamAction
{
    public function getStream()
    {
                                     /* whose events */   /* are these the user's own events? */
        $stream = new EventsNoticeStream($this->target, $this->scoped);
        return $stream;
    }

    function title()
    {
        // TRANS: Page title for sample plugin. %s is a user nickname.
        return sprintf(_m('%s\'s happenings'), $this->target->getNickname());
    }

    function getFeeds()
    {
        return array(
                    );
    }

    function showEmptyList() {
        $message = sprintf(_('This is %1$s\'s event stream, but %1$s hasn\'t received any events yet.'), $this->target->getNickname()) . ' ';

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    /**
     * Return true if read only.
     *
     * Some actions only read from the database; others read and write.
     * The simple database load-balancer built into StatusNet will
     * direct read-only actions to database mirrors (if they are configured),
     * and read-write actions to the master database.
     *
     * This defaults to false to avoid data integrity issues, but you
     * should make sure to overload it for performance gains.
     *
     * @param array $args other arguments, if RO/RW status depends on them.
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }
}
