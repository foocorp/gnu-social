<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Class for activity streams
 *
 * Includes objects like notices, subscriptions and from plugins.
 *
 * We extend atomusernoticefeed since it does some nice setup for us.
 *
 */
class UserActivityStream extends AtomUserNoticeFeed
{
    public $activities = array();
    public $after = null;

    const OUTPUT_STRING = 1;
    const OUTPUT_RAW = 2;
    public $outputMode = self::OUTPUT_STRING;

    /**
     *
     * @param User $user
     * @param boolean $indent
     * @param boolean $outputMode: UserActivityStream::OUTPUT_STRING to return a string,
     *                           or UserActivityStream::OUTPUT_RAW to go to raw output.
     *                           Raw output mode will attempt to stream, keeping less
     *                           data in memory but will leave $this->activities incomplete.
     */
    function __construct($user, $indent = true, $outputMode = UserActivityStream::OUTPUT_STRING, $after = null)
    {
        parent::__construct($user, null, $indent);

        $this->outputMode = $outputMode;

        if ($this->outputMode == self::OUTPUT_STRING) {
            // String buffering? Grab all the notices now.
            $notices = $this->getNotices();
        } elseif ($this->outputMode == self::OUTPUT_RAW) {
            // Raw output... need to restructure from the stringer init.
            $this->xw = new XMLWriter();
            $this->xw->openURI('php://output');
            if(is_null($indent)) {
                $indent = common_config('site', 'indent');
            }
            $this->xw->setIndent($indent);

            // We'll fetch notices later.
            $notices = array();
        } else {
            throw new Exception('Invalid outputMode provided to ' . __METHOD__);
        }

        $this->after = $after;

        // Assume that everything but notices is feasible
        // to pull at once and work with in memory...

        $subscriptions = $this->getSubscriptions();
        $subscribers   = $this->getSubscribers();
        $groups        = $this->getGroups();

        $objs = array_merge($subscriptions, $subscribers, $groups, $notices);

        Event::handle('AppendUserActivityStreamObjects', array($this, &$objs));

        $subscriptions = null;
        $subscribers   = null;
        $groups        = null;

        unset($subscriptions);
        unset($subscribers);
        unset($groups);

        // Sort by create date

        usort($objs, 'UserActivityStream::compareObject');

        // We'll keep these around for later, and interleave them into
        // the output stream with the user's notices.

        $this->objs = $objs;
    }

    /**
     * Interleave the pre-sorted objects with the user's
     * notices, all in reverse chron order.
     */
    function renderEntries($format=Feed::ATOM, $handle=null)
    {
        $haveOne = false;

        $end = time() + 1;
        foreach ($this->objs as $obj) {
            try {
                $act = $obj->asActivity();
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }

            $start = $act->time;

            if ($this->outputMode == self::OUTPUT_RAW && $start != $end) {
                // In raw mode, we haven't pre-fetched notices.
                // Grab the chunks of notices between other activities.
                try {
                    $notices = $this->getNoticesBetween($start, $end);
                    foreach ($notices as $noticeAct) {
                        try {
                            $nact = $noticeAct->asActivity($this->user);
                            if ($format == Feed::ATOM) {
                                $nact->outputTo($this, false, false);
                            } else {
                                if ($haveOne) {
                                    fwrite($handle, ",");
                                }
                                fwrite($handle, json_encode($nact->asArray()));
                                $haveOne = true;
                            }
                        } catch (Exception $e) {
                            common_log(LOG_ERR, $e->getMessage());
                            continue;
                        }
                        $nact = null;
                        unset($nact);
                    }
                } catch (Exception $e) {
                    common_log(LOG_ERR, $e->getMessage());
                }
            }

            $notices = null;
            unset($notices);

            try {
                if ($format == Feed::ATOM) {
                    // Only show the author sub-element if it's different from default user
                    $act->outputTo($this, false, ($act->actor->id != $this->user->getUri()));
                } else {
                    if ($haveOne) {
                        fwrite($handle, ",");
                    }
                    fwrite($handle, json_encode($act->asArray()));
                    $haveOne = true;
                }
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
            }

            $act = null;
            unset($act);

            $end = $start;
        }

        if ($this->outputMode == self::OUTPUT_RAW) {
            // Grab anything after the last pre-sorted activity.
            try {
                if (!empty($this->after)) {
                    $notices = $this->getNoticesBetween($this->after, $end);
                } else {
                    $notices = $this->getNoticesBetween(0, $end);
                }
                foreach ($notices as $noticeAct) {
                    try {
                        $nact = $noticeAct->asActivity($this->user);
                        if ($format == Feed::ATOM) {
                            $nact->outputTo($this, false, false);
                        } else {
                            if ($haveOne) {
                                fwrite($handle, ",");
                            }
                            fwrite($handle, json_encode($nact->asArray()));
                            $haveOne = true;
                        }
                    } catch (Exception $e) {
                        common_log(LOG_ERR, $e->getMessage());
                        continue;
                    }
                }
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
            }
        }

        if (empty($this->after) || strtotime($this->user->created) > $this->after) {
            // We always add the registration activity at the end, even if
            // they have older activities (from restored backups) in their stream.

            try {
                $ract = $this->user->registrationActivity();
                if ($format == Feed::ATOM) {
                    $ract->outputTo($this, false, false);
                } else {
                    if ($haveOne) {
                        fwrite($handle, ",");
                    }
                    fwrite($handle, json_encode($ract->asArray()));
                    $haveOne = true;
                }
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
        }
    }

    function compareObject($a, $b)
    {
        $ac = strtotime((empty($a->created)) ? $a->modified : $a->created);
        $bc = strtotime((empty($b->created)) ? $b->modified : $b->created);

        return (($ac == $bc) ? 0 : (($ac < $bc) ? 1 : -1));
    }

    function getSubscriptions()
    {
        $subs = array();

        $sub = new Subscription();

        $sub->subscriber = $this->user->id;

        if (!empty($this->after)) {
            $sub->whereAdd("created > '" . common_sql_date($this->after) . "'");
        }

        if ($sub->find()) {
            while ($sub->fetch()) {
                if ($sub->subscribed != $this->user->id) {
                    $subs[] = clone($sub);
                }
            }
        }

        return $subs;
    }

    function getSubscribers()
    {
        $subs = array();

        $sub = new Subscription();

        $sub->subscribed = $this->user->id;

        if (!empty($this->after)) {
            $sub->whereAdd("created > '" . common_sql_date($this->after) . "'");
        }

        if ($sub->find()) {
            while ($sub->fetch()) {
                if ($sub->subscriber != $this->user->id) {
                    $subs[] = clone($sub);
                }
            }
        }

        return $subs;
    }

    /**
     *
     * @param int $start unix timestamp for earliest
     * @param int $end unix timestamp for latest
     * @return array of Notice objects
     */
    function getNoticesBetween($start=0, $end=0)
    {
        $notices = array();

        $notice = new Notice();

        $notice->profile_id = $this->user->id;

        // Only stuff after $this->after

        if (!empty($this->after)) {
            if ($start) {
                $start = max($start, $this->after);
            }
            if ($end) {
                $end = max($end, $this->after);
            }
        }

        if ($start) {
            $tsstart = common_sql_date($start);
            $notice->whereAdd("created >= '$tsstart'");
        }
        if ($end) {
            $tsend = common_sql_date($end);
            $notice->whereAdd("created < '$tsend'");
        }

        $notice->orderBy('created DESC');

        if ($notice->find()) {
            while ($notice->fetch()) {
                $notices[] = clone($notice);
            }
        }

        return $notices;
    }

    function getNotices()
    {
        if (!empty($this->after)) {
            return $this->getNoticesBetween($this->after);
        } else {
            return $this->getNoticesBetween();
        }
    }

    function getGroups()
    {
        $groups = array();

        $gm = new Group_member();

        $gm->profile_id = $this->user->id;

        if (!empty($this->after)) {
            $gm->whereAdd("created > '" . common_sql_date($this->after) . "'");
        }

        if ($gm->find()) {
            while ($gm->fetch()) {
                $groups[] = clone($gm);
            }
        }

        return $groups;
    }

    function createdAfter($item) {
        $created = strtotime((empty($item->created)) ? $item->modified : $item->created);
        return ($created >= $this->after);
    }

    function writeJSON($handle)
    {
        require_once INSTALLDIR.'/lib/activitystreamjsondocument.php';
        fwrite($handle, '{"items": [');
        $this->renderEntries(Feed::JSON, $handle);
        fwrite($handle, ']}');
    }
}
