<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Shows social activities in the output feed
 *
 * PHP version 5
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
 *
 * @category  Activity
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Activity plugin main class
 *
 * @category  Activity
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ActivityPlugin extends Plugin
{
    const VERSION = '0.1';

    /**
     * Database schema setup
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing the activity part of a notice

        $schema->ensureTable('notice_activity',
                             array(new ColumnDef('notice_id', 'integer', null,
                                                 false, 'PRI'),
                                   new ColumnDef('verb', 'varchar', 255,
                                                 false, 'MUL'),
                                   new ColumnDef('object', 'varchar', 255,
                                                 true, 'MUL')));

        return true;
    }

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'Notice_activity':
            include_once $dir . '/'.$cls.'.php';
            return false;
        default:
            return true;
        }
    }

    function onEndSubscribe($subscriber, $other)
    {
        $user = User::staticGet('id', $subscriber->id);
        if (!empty($user)) {
            $rendered = sprintf(_m('<em>Started following <a href="%s">%s</a></em>.'),
                                $other->profileurl,
                                $other->getBestName());
            $content  = sprintf(_m('Started following %s : %s'),
                                $other->getBestName(),
				$other->profileurl);

            $notice = Notice::saveNew($user->id,
                                      $content,
                                      'activity',
                                      array('rendered' => $rendered));

            Notice_activity::setActivity($notice->id,
                                         ActivityVerb::FOLLOW,
                                         $other->getUri());
        }
        return true;
    }

    function onEndUnsubscribe($subscriber, $other)
    {
        $user = User::staticGet('id', $subscriber->id);
        if (!empty($user)) {
            $rendered = sprintf(_m('<em>Stopped following <a href="%s">%s</a></em>.'),
                                $other->profileurl,
                                $other->getBestName());
            $content  = sprintf(_m('Stopped following %s : %s'),
                                $other->getBestName(),
				$other->profileurl);

            $notice = Notice::saveNew($user->id,
                                      $content,
                                      'activity',
                                      array('rendered' => $rendered));

            Notice_activity::setActivity($notice->id,
                                         ActivityVerb::UNFOLLOW,
                                         $other->getUri());
        }
        return true;
    }

    function onEndFavorNotice($profile, $notice)
    {
        $user = User::staticGet('id', $profile->id);

        if (!empty($user)) {
            $author = Profile::staticGet('id', $notice->profile_id);
            $rendered = sprintf(_m('<em>Liked <a href="%s">%s\'s status</a></em>.'),
                                $notice->bestUrl(),
                                $author->getBestName());
            $content  = sprintf(_m('Liked %s\'s status: %s'),
                                $author->getBestName(), 
				$notice->bestUrl());

            $notice = Notice::saveNew($user->id,
                                      $content,
                                      'activity',
                                      array('rendered' => $rendered));

            Notice_activity::setActivity($notice->id,
                                         ActivityVerb::FAVORITE,
                                         $notice->uri);
        }
        return true;
    }

    function onEndDisfavorNotice($profile, $notice)
    {
        $user = User::staticGet('id', $profile->id);

        if (!empty($user)) {
            $author = Profile::staticGet('id', $notice->profile_id);
            $rendered = sprintf(_m('<em>Stopped liking <a href="%s">%s\'s status</a></em>.'),
                                $notice->bestUrl(),
                                $author->getBestName());
            $content  = sprintf(_m('Stopped liking %s\'s status: %s'),
                                $author->getBestName(),
				$notice->bestUrl());

            $notice = Notice::saveNew($user->id,
                                      $content,
                                      'activity',
                                      array('rendered' => $rendered));

            Notice_activity::setActivity($notice->id,
                                         ActivityVerb::UNFAVORITE,
                                         $notice->uri);
        }
        return true;
    }

    function onEndJoinGroup($group, $user)
    {
        $rendered = sprintf(_m('<em>Joined the group &quot;<a href="%s">%s</a>&quot;</em>.'),
                            $group->homeUrl(),
                            $group->getBestName());
        $content  = sprintf(_m('Joined the group %s : %s'),
                            $group->getBestName(),
			    $group->homeUrl());

        $notice = Notice::saveNew($user->id,
                                  $content,
                                  'activity',
                                  array('rendered' => $rendered));

        Notice_activity::setActivity($notice->id,
                                     ActivityVerb::JOIN,
                                     $group->getUri());
        return true;
    }

    function onEndLeaveGroup($group, $user)
    {
        $rendered = sprintf(_m('<em>Left the group &quot;<a href="%s">%s</a>&quot;</em>.'),
                            $group->homeUrl(),
                            $group->getBestName());
        $content  = sprintf(_m('Left the group "%s" : %s'),
                            $group->getBestName(),
			    $group->homeUrl());

        $notice = Notice::saveNew($user->id,
                                  $content,
                                  'activity',
                                  array('rendered' => $rendered));

        Notice_activity::setActivity($notice->id,
                                     ActivityVerb::LEAVE,
                                     $group->getUri());
        return true;
    }

    function onEndNoticeAsActivity($notice, &$activity)
    {
        $na = Notice_activity::staticGet('notice_id', $notice->id);

        if (!empty($na)) {

            $activity->verb = $na->verb;

            // wipe the old object!

            $activity->objects = array();

            switch ($na->verb)
            {
            case ActivityVerb::FOLLOW:
            case ActivityVerb::UNFOLLOW:
                $profile = Profile::fromURI($na->object);
                if (!empty($profile)) {
                    $activity->objects[] = ActivityObject::fromProfile($profile);
                }
                break;
            case ActivityVerb::FAVORITE:
            case ActivityVerb::UNFAVORITE:
                $target = Notice::staticGet('uri', $na->object);
                if (!empty($target)) {
                    $activity->objects[] = ActivityObject::fromNotice($target);
                }
                break;
            case ActivityVerb::JOIN:
            case ActivityVerb::LEAVE:
                $group = User_group::staticGet('uri', $na->object);
                if (!empty($notice)) {
                    $activity->objects[] = ActivityObject::fromGroup($group);
                }
                break;
            default:
                break;
            }
        }

        return true;
    }


    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Activity',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Activity',
                            'rawdescription' =>
                            _m('Emits notices when social activities happen.'));
        return true;
    }
}
