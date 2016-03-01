<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class RawEventsNoticeStream extends NoticeStream
{
    function getNoticeIds($offset, $limit, $since_id, $max_id)
    {
        $notice = new Notice();
        $qry = null;

        $qry =  'SELECT notice.* FROM notice ';
        $qry .= 'INNER JOIN happening ON happening.uri = notice.uri ';
        $qry .= 'AND notice.is_local != ' . Notice::GATEWAY . ' ';

        if ($since_id != 0) {
            $qry .= 'AND notice.id > ' . $since_id . ' ';
        }

        if ($max_id != 0) {
            $qry .= 'AND notice.id <= ' . $max_id . ' ';
        }

        // NOTE: we sort by event time, not by notice time!
        $qry .= 'ORDER BY happening.created DESC ';
        if (!is_null($offset)) {
            $qry .= "LIMIT $limit OFFSET $offset";
        }

        $notice->query($qry);
        $ids = array();
        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        $notice->free();
        unset($notice);
        return $ids;
    }
}

class EventsNoticeStream extends ScopingNoticeStream
{
    // possible values of RSVP in our database
    protected $rsvp = ['Y', 'N', '?'];
    protected $target = null;

    function __construct(Profile $target, Profile $scoped=null, array $rsvp=array())
    {
        $stream = new RawEventsNoticeStream();

        if ($target->sameAs($scoped)) {
            $key = 'happening:ids_for_user_own:'.$target->getID();
        } else {
            $key = 'happening:ids_for_user:'.$target->getID();
        }

        // Match RSVP against our possible values, given in the class variable
        // and if no RSVPs are given is empty, assume we want all events, even
        // without RSVPs from this profile.
        $this->rsvp = array_intersect($this->rsvp, $rsvp);
        $this->target = $target;

        parent::__construct(new CachingNoticeStream($stream, $key), $scoped);
    }

    function filter($notice)
    {
        if (!parent::filter($notice)) {
            // if not in our scope, return false
            return false;
        }

        if (empty($this->rsvp)) {
            // Don't filter on RSVP (for only events with RSVP if no responses
            // are given (give ['Y', 'N', '?'] for only RSVP'd events!).
            return true;
        }

        $rsvp = new RSVP();
        $rsvp->profile_id = $this->target->getID();
        $rsvp->event_uri  = $notice->getUri();
        $rsvp->whereAddIn('response', $this->rsvp, $rsvp->columnType('response'));

        // filter out if no RSVP match was found
        return $rsvp->N > 0;
    }
}
