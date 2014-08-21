<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011 StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('TWITTER_SERVICE', 1); // Twitter is foreign_service ID 1

function add_twitter_user($twitter_id, $screen_name)
{
    // Clear out any bad old foreign_users with the new user's legit URL
    // This can happen when users move around or fakester accounts get
    // repoed, and things like that.
    $luser = Foreign_user::getForeignUser($twitter_id, TWITTER_SERVICE);

    if (!empty($luser)) {
        $result = $luser->delete();
        if ($result != false) {
            common_log(
                LOG_INFO,
                "Twitter bridge - removed old Twitter user: $screen_name ($twitter_id)."
            );
        }
    }

    $fuser = new Foreign_user();

    $fuser->nickname = $screen_name;
    $fuser->uri = 'http://twitter.com/' . $screen_name;
    $fuser->id = $twitter_id;
    $fuser->service = TWITTER_SERVICE;
    $fuser->created = common_sql_now();
    $result = $fuser->insert();

    if (empty($result)) {
        common_log(LOG_WARNING,
            "Twitter bridge - failed to add new Twitter user: $twitter_id - $screen_name.");
        common_log_db_error($fuser, 'INSERT', __FILE__);
    } else {
        common_log(LOG_INFO,
                   "Twitter bridge - Added new Twitter user: $screen_name ($twitter_id).");
    }

    return $result;
}

// Creates or Updates a Twitter user
function save_twitter_user($twitter_id, $screen_name)
{
    // Check to see whether the Twitter user is already in the system,
    // and update its screen name and uri if so.
    $fuser = Foreign_user::getForeignUser($twitter_id, TWITTER_SERVICE);

    if (!empty($fuser)) {
        // Delete old record if Twitter user changed screen name

        if ($fuser->nickname != $screen_name) {
            $oldname = $fuser->nickname;
            $fuser->delete();
            common_log(LOG_INFO, sprintf('Twitter bridge - Updated nickname (and URI) ' .
                                         'for Twitter user %1$d - %2$s, was %3$s.',
                                         $fuser->id,
                                         $screen_name,
                                         $oldname));
        }
    } else {
        // Kill any old, invalid records for this screen name
        $fuser = Foreign_user::getByNickname($screen_name, TWITTER_SERVICE);

        if (!empty($fuser)) {
            $fuser->delete();
            common_log(
                LOG_INFO,
                sprintf(
                    'Twitter bridge - deteted old record for Twitter ' .
                    'screen name "%s" belonging to Twitter ID %d.',
                    $screen_name,
                    $fuser->id
                )
            );
        }
    }

    return add_twitter_user($twitter_id, $screen_name);
}

function is_twitter_bound($notice, $flink) {

    // Don't send activity activities (at least for now)
    if ($notice->object_type == ActivityObject::ACTIVITY) {
        return false;
    }

    $allowedVerbs = array(ActivityVerb::POST, ActivityVerb::SHARE);

    // Don't send things that aren't posts or repeats (at least for now)
    if (!in_array($notice->verb, $allowedVerbs)) {
        return false;
    }

    // Check to see if notice should go to Twitter
    if (!empty($flink) && (($flink->noticesync & FOREIGN_NOTICE_SEND) == FOREIGN_NOTICE_SEND)) {

        // If it's not a Twitter-style reply, or if the user WANTS to send replies,
        // or if it's in reply to a twitter notice
        if ( (($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) == FOREIGN_NOTICE_SEND_REPLY) ||
              ((is_twitter_notice($notice->reply_to) || is_twitter_notice($notice->repeat_of))
              && (($flink->noticesync & FOREIGN_NOTICE_RECV) == FOREIGN_NOTICE_RECV)) ||
             (empty($notice->reply_to) && !preg_match('/^@[a-zA-Z0-9_]{1,15}\b/u', $notice->content)) ){
            return true;
        }
    }

    return false;
}

function is_twitter_notice($id)
{
    $n2s = Notice_to_status::getKV('notice_id', $id);

    return (!empty($n2s));
}

/**
 * Pull the formatted status ID number from a Twitter status object
 * returned via JSON from Twitter API.
 *
 * Encapsulates checking for the id_str attribute, which is required
 * to read 64-bit "Snowflake" ID numbers on a 32-bit system -- the
 * integer id attribute gets corrupted into a double-precision float,
 * losing a few digits of precision.
 *
 * Warning: avoid performing arithmetic or direct comparisons with
 * this number, as it may get coerced back to a double on 32-bit.
 *
 * @param object $status
 * @param string $field base field name if not 'id'
 * @return mixed id number as int or string
 */
function twitter_id($status, $field='id')
{
    $field_str = "{$field}_str";
    if (isset($status->$field_str)) {
        // String version of the id -- required on 32-bit systems
        // since the 64-bit numbers get corrupted as ints.
        return $status->$field_str;
    } else {
        return $status->$field;
    }
}

/**
 * Check if we need to broadcast a notice over the Twitter bridge, and
 * do so if necessary. Will determine whether to do a straight post or
 * a repeat/retweet
 *
 * This function is meant to be called directly from TwitterQueueHandler.
 *
 * @param Notice $notice
 * @return boolean true if complete or successful, false if we should retry
 */
function broadcast_twitter($notice)
{
    $flink = Foreign_link::getByUserID($notice->profile_id,
                                       TWITTER_SERVICE);

    // Don't bother with basic auth, since it's no longer allowed
    if (!empty($flink) && TwitterOAuthClient::isPackedToken($flink->credentials)) {
        if (is_twitter_bound($notice, $flink)) {
            if (!empty($notice->repeat_of) && is_twitter_notice($notice->repeat_of)) {
                $retweet = retweet_notice($flink, Notice::getKV('id', $notice->repeat_of));
                if (is_object($retweet)) {
                    Notice_to_status::saveNew($notice->id, twitter_id($retweet));
                    return true;
                } else {
                    // Our error processing will have decided if we need to requeue
                    // this or can discard safely.
                    return $retweet;
                }
            } else {
                return broadcast_oauth($notice, $flink);
            }
        }
    }

    return true;
}

/**
 * Send a retweet to Twitter for a notice that has been previously bridged
 * in or out.
 *
 * Warning: the return value is not guaranteed to be an object; some error
 * conditions will return a 'true' which should be passed on to a calling
 * queue handler.
 *
 * No local information about the resulting retweet is saved: it's up to
 * caller to save new mappings etc if appropriate.
 *
 * @param Foreign_link $flink
 * @param Notice $notice
 * @return mixed object with resulting Twitter status data on success, or true/false/null on error conditions.
 */
function retweet_notice($flink, $notice)
{
    $token = TwitterOAuthClient::unpackToken($flink->credentials);
    $client = new TwitterOAuthClient($token->key, $token->secret);

    $id = twitter_status_id($notice);

    if (empty($id)) {
        common_log(LOG_WARNING, "Trying to retweet notice {$notice->id} with no known status id.");
        return null;
    }

    try {
        $status = $client->statusesRetweet($id);
        return $status;
    } catch (OAuthClientException $e) {
        return process_error($e, $flink, $notice);
    }
}

function twitter_status_id($notice)
{
    $n2s = Notice_to_status::getKV('notice_id', $notice->id);
    if (empty($n2s)) {
        return null;
    } else {
        return $n2s->status_id;
    }
}

/**
 * Pull any extra information from a notice that we should transfer over
 * to Twitter beyond the notice text itself.
 *
 * @param Notice $notice
 * @return array of key-value pairs for Twitter update submission
 * @access private
 */
function twitter_update_params($notice)
{
    $params = array();
    if ($notice->lat || $notice->lon) {
        $params['lat'] = $notice->lat;
        $params['long'] = $notice->lon;
    }
    if (!empty($notice->reply_to) && is_twitter_notice($notice->reply_to)) {
        $reply = Notice::getKV('id', $notice->reply_to);
        $params['in_reply_to_status_id'] = twitter_status_id($reply);
    }
    return $params;
}

function broadcast_oauth($notice, $flink) {
    $user = $flink->getUser();
    $statustxt = format_status($notice);
    $params = twitter_update_params($notice);

    $token = TwitterOAuthClient::unpackToken($flink->credentials);
    $client = new TwitterOAuthClient($token->key, $token->secret);
    $status = null;

    try {
        $status = $client->statusesUpdate($statustxt, $params);
        if (!empty($status)) {
            Notice_to_status::saveNew($notice->id, twitter_id($status));
        }
    } catch (OAuthClientException $e) {
        return process_error($e, $flink, $notice);
    }

    if (empty($status)) {
        // This could represent a failure posting,
        // or the Twitter API might just be behaving flakey.
        $errmsg = sprintf('Twitter bridge - No data returned by Twitter API when ' .
                          'trying to post notice %d for User %s (user id %d).',
                          $notice->id,
                          $user->nickname,
                          $user->id);

        common_log(LOG_WARNING, $errmsg);

        return false;
    }

    // Notice crossed the great divide
    $msg = sprintf('Twitter bridge - posted notice %d to Twitter using ' .
                   'OAuth for User %s (user id %d).',
                   $notice->id,
                   $user->nickname,
                   $user->id);

    common_log(LOG_INFO, $msg);

    return true;
}

function process_error($e, $flink, $notice)
{
    $user = $flink->getUser();
    $code = $e->getCode();

    $logmsg = sprintf('Twitter bridge - %d posting notice %d for ' .
                      'User %s (user id: %d): %s.',
                      $code,
                      $notice->id,
                      $user->nickname,
                      $user->id,
                      $e->getMessage());

    common_log(LOG_WARNING, $logmsg);

    // http://dev.twitter.com/pages/responses_errors
    switch($code) {
     case 400:
         // Probably invalid data (bad Unicode chars or coords) that
         // cannot be resolved by just sending again.
         //
         // It could also be rate limiting, but retrying immediately
         // won't help much with that, so we'll discard for now.
         // If a facility for retrying things later comes up in future,
         // we can detect the rate-limiting headers and use that.
         //
         // Discard the message permanently.
         return true;
         break;
     case 401:
        // Probably a revoked or otherwise bad access token - nuke!
        remove_twitter_link($flink);
        return true;
        break;
     case 403:
        // User has exceeder her rate limit -- toss the notice
        return true;
        break;
     case 404:
         // Resource not found. Shouldn't happen much on posting,
         // but just in case!
         //
         // Consider it a matter for tossing the notice.
         return true;
         break;
     default:

        // For every other case, it's probably some flakiness so try
        // sending the notice again later (requeue).

        return false;
        break;
    }
}

function format_status($notice)
{
    // Start with the plaintext source of this notice...
    $statustxt = $notice->content;

    // Convert !groups to #hashes
    // XXX: Make this an optional setting?
    $statustxt = preg_replace('/(^|\s)!([A-Za-z0-9]{1,64})/', "\\1#\\2", $statustxt);

    // Twitter still has a 140-char hardcoded max.
    if (mb_strlen($statustxt) > 140) {
        $noticeUrl = common_shorten_url($notice->getUrl());
        $urlLen = mb_strlen($noticeUrl);
        $statustxt = mb_substr($statustxt, 0, 140 - ($urlLen + 3)) . ' … ' . $noticeUrl;
    }

    return $statustxt;
}

function remove_twitter_link($flink)
{
    $user = $flink->getUser();

    common_log(LOG_INFO, 'Removing Twitter bridge Foreign link for ' .
               "user $user->nickname (user id: $user->id).");

    $result = $flink->safeDelete();

    if (empty($result)) {
        common_log(LOG_ERR, 'Could not remove Twitter bridge ' .
                   "Foreign_link for $user->nickname (user id: $user->id)!");
        common_log_db_error($flink, 'DELETE', __FILE__);
    }

    // Notify the user that her Twitter bridge is down

    if (isset($user->email)) {
        $result = mail_twitter_bridge_removed($user);

        if (!$result) {
            $msg = 'Unable to send email to notify ' .
              "$user->nickname (user id: $user->id) " .
              'that their Twitter bridge link was ' .
              'removed!';

            common_log(LOG_WARNING, $msg);
        }
    }
}

/**
 * Send a mail message to notify a user that her Twitter bridge link
 * has stopped working, and therefore has been removed.  This can
 * happen when the user changes her Twitter password, or otherwise
 * revokes access.
 *
 * @param User $user   user whose Twitter bridge link has been removed
 *
 * @return boolean success flag
 */
function mail_twitter_bridge_removed($user)
{
    $profile = $user->getProfile();

    common_switch_locale($user->language);

    // TRANS: Mail subject after forwarding notices to Twitter has stopped working.
    $subject = sprintf(_m('Your Twitter bridge has been disabled'));

    $site_name = common_config('site', 'name');

    // TRANS: Mail body after forwarding notices to Twitter has stopped working.
    // TRANS: %1$ is the name of the user the mail is sent to, %2$s is a URL to the
    // TRANS: Twitter settings, %3$s is the StatusNet sitename.
    $body = sprintf(_m('Hi, %1$s. We\'re sorry to inform you that your ' .
        'link to Twitter has been disabled. We no longer seem to have ' .
    'permission to update your Twitter status. Did you maybe revoke ' .
    '%3$s\'s access?' . "\n\n" .
    'You can re-enable your Twitter bridge by visiting your ' .
    "Twitter settings page:\n\n\t%2\$s\n\n" .
        "Regards,\n%3\$s"),
        $profile->getBestName(),
        common_local_url('twittersettings'),
        common_config('site', 'name'));

    common_switch_locale();
    return mail_to_user($user, $subject, $body);
}
