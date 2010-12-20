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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'i:n:f:';
$longoptions = array('id=', 'nickname=', 'file=');

$helptext = <<<END_OF_RESTOREUSER_HELP
restoreuser.php [options]
Restore a backed-up user file to the database. If
neither ID or name provided, will create a new user.

  -i --id       ID of user to export
  -n --nickname nickname of the user to export
  -f --file     file to read from (STDIN by default)

END_OF_RESTOREUSER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';
require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';

function getActivityStreamDocument()
{
    $filename = get_option_value('f', 'file');

    if (empty($filename)) {
        show_help();
        exit(1);
    }

    if (!file_exists($filename)) {
        throw new Exception("No such file '$filename'.");
    }

    if (!is_file($filename)) {
        throw new Exception("Not a regular file: '$filename'.");
    }

    if (!is_readable($filename)) {
        throw new Exception("File '$filename' not readable.");
    }

    // TRANS: Commandline script output. %s is the filename that contains a backup for a user.
    printfv(_("Getting backup from file '%s'.")."\n",$filename);

    $xml = file_get_contents($filename);

    $dom = DOMDocument::loadXML($xml);

    if ($dom->documentElement->namespaceURI != Activity::ATOM ||
        $dom->documentElement->localName != 'feed') {
        throw new Exception("'$filename' is not an Atom feed.");
    }

    return $dom;
}

function importActivityStream($user, $doc)
{
    $feed = $doc->documentElement;

    $subjectEl = ActivityUtils::child($feed, Activity::SUBJECT, Activity::SPEC);

    if (!empty($subjectEl)) {
        $subject = new ActivityObject($subjectEl);
        // TRANS: Commandline script output. %1$s is the subject ID, %2$s is the subject nickname.
        printfv(_("Backup file for user %1$s (%2$s)")."\n", $subject->id, Ostatus_profile::getActivityObjectNickname($subject));
    } else {
        throw new Exception("Feed doesn't have an <activity:subject> element.");
    }

    if (is_null($user)) {
        // TRANS: Commandline script output.
        printfv(_("No user specified; using backup user.")."\n");
        $user = userFromSubject($subject);
    }

    $entries = $feed->getElementsByTagNameNS(Activity::ATOM, 'entry');

    // TRANS: Commandline script output. %d is the number of entries in the activity stream in backup; used for plural.
    printfv(_m("%d entry in backup.","%d entries in backup.",$entries->length)."\n", $entries->length);

    for ($i = $entries->length - 1; $i >= 0; $i--) {
        try {
            $entry = $entries->item($i);

            $activity = new Activity($entry, $feed);

            switch ($activity->verb) {
            case ActivityVerb::FOLLOW:
                subscribeProfile($user, $subject, $activity);
                break;
            case ActivityVerb::JOIN:
                joinGroup($user, $activity);
                break;
            case ActivityVerb::POST:
                postNote($user, $activity);
                break;
            default:
                throw new Exception("Unknown verb: {$activity->verb}");
            }
        } catch (Exception $e) {
            print $e->getMessage()."\n";
            continue;
        }
    }
}

function subscribeProfile($user, $subject, $activity)
{
    $profile = $user->getProfile();

    if ($activity->objects[0]->id == $subject->id) {

        $other = $activity->actor;
        $otherUser = User::staticGet('uri', $other->id);

        if (!empty($otherUser)) {
            $otherProfile = $otherUser->getProfile();
        } else {
            throw new Exception("Can't force remote user to subscribe.");
        }
        // XXX: don't do this for untrusted input!
        Subscription::start($otherProfile, $profile);

    } else if (empty($activity->actor) || $activity->actor->id == $subject->id) {

        $other = $activity->objects[0];
        $otherUser = User::staticGet('uri', $other->id);

        if (!empty($otherUser)) {
            $otherProfile = $otherUser->getProfile();
        } else {
            $oprofile = Ostatus_profile::ensureActivityObjectProfile($other);
            $otherProfile = $oprofile->localProfile();
        }

        Subscription::start($profile, $otherProfile);
    } else {
        throw new Exception("This activity seems unrelated to our user.");
    }
}

function joinGroup($user, $activity)
{
    // XXX: check that actor == subject

    $uri = $activity->objects[0]->id;

    $group = User_group::staticGet('uri', $uri);

    if (empty($group)) {
        $oprofile = Ostatus_profile::ensureActivityObjectProfile($activity->objects[0]);
        if (!$oprofile->isGroup()) {
            throw new Exception("Remote profile is not a group!");
        }
        $group = $oprofile->localGroup();
    }

    assert(!empty($group));

    if (Event::handle('StartJoinGroup', array($group, $user))) {
        Group_member::join($group->id, $user->id);
        Event::handle('EndJoinGroup', array($group, $user));
    }
}

// XXX: largely cadged from Ostatus_profile::processNote()

function postNote($user, $activity)
{
    $note = $activity->objects[0];

    $sourceUri = $note->id;

    $notice = Notice::staticGet('uri', $sourceUri);

    if (!empty($notice)) {
        // This is weird.
        $orig = clone($notice);
        $notice->profile_id = $user->id;
        $notice->update($orig);
        return;
    }

    // Use summary as fallback for content

    if (!empty($note->content)) {
        $sourceContent = $note->content;
    } else if (!empty($note->summary)) {
        $sourceContent = $note->summary;
    } else if (!empty($note->title)) {
        $sourceContent = $note->title;
    } else {
        // @fixme fetch from $sourceUrl?
        // @todo i18n FIXME: use sprintf and add i18n.
        throw new ClientException("No content for notice {$sourceUri}.");
    }

    // Get (safe!) HTML and text versions of the content

    $rendered = purify($sourceContent);
    $content = html_entity_decode(strip_tags($rendered), ENT_QUOTES, 'UTF-8');

    $shortened = $user->shortenLinks($content);

    $options = array('is_local' => Notice::LOCAL_PUBLIC,
                     'uri' => $sourceUri,
                     'rendered' => $rendered,
                     'replies' => array(),
                     'groups' => array(),
                     'tags' => array(),
                     'urls' => array());

    // Check for optional attributes...

    if (!empty($activity->time)) {
        $options['created'] = common_sql_date($activity->time);
    }

    if ($activity->context) {
        // Any individual or group attn: targets?

        list($options['groups'], $options['replies']) = filterAttention($activity->context->attention);

        // Maintain direct reply associations
        // @fixme what about conversation ID?
        if (!empty($activity->context->replyToID)) {
            $orig = Notice::staticGet('uri',
                                      $activity->context->replyToID);
            if (!empty($orig)) {
                $options['reply_to'] = $orig->id;
            }
        }

        $location = $activity->context->location;

        if ($location) {
            $options['lat'] = $location->lat;
            $options['lon'] = $location->lon;
            if ($location->location_id) {
                $options['location_ns'] = $location->location_ns;
                $options['location_id'] = $location->location_id;
            }
        }
    }

    // Atom categories <-> hashtags

    foreach ($activity->categories as $cat) {
        if ($cat->term) {
            $term = common_canonical_tag($cat->term);
            if ($term) {
                $options['tags'][] = $term;
            }
        }
    }

    // Atom enclosures -> attachment URLs
    foreach ($activity->enclosures as $href) {
        // @fixme save these locally or....?
        $options['urls'][] = $href;
    }

    $saved = Notice::saveNew($user->id,
                             $content,
                             'restore', // TODO: restore the actual source
                             $options);

    return $saved;
}

function filterAttention($attn)
{
    $groups = array();
    $replies = array();

    foreach (array_unique($attn) as $recipient) {

        // Is the recipient a local user?

        $user = User::staticGet('uri', $recipient);

        if ($user) {
            // @fixme sender verification, spam etc?
            $replies[] = $recipient;
            continue;
        }

        // Is the recipient a remote group?
        $oprofile = Ostatus_profile::ensureProfileURI($recipient);

        if ($oprofile) {
            if (!$oprofile->isGroup()) {
                // may be canonicalized or something
                $replies[] = $oprofile->uri;
            }
            continue;
        }

        // Is the recipient a local group?
        // @fixme uri on user_group isn't reliable yet
        // $group = User_group::staticGet('uri', $recipient);
        $id = OStatusPlugin::localGroupFromUrl($recipient);

        if ($id) {
            $group = User_group::staticGet('id', $id);
            if ($group) {
                // Deliver to all members of this local group if allowed.
                $profile = $sender->localProfile();
                if ($profile->isMember($group)) {
                    $groups[] = $group->id;
                } else {
                    common_log(LOG_INFO, "Skipping reply to local group {$group->nickname} as sender {$profile->id} is not a member");
                }
                continue;
            } else {
                common_log(LOG_INFO, "Skipping reply to bogus group $recipient");
            }
        }
    }

    return array($groups, $replies);
}

function userFromSubject($subject)
{
    $user = User::staticGet('uri', $subject->id);

    if (empty($user)) {
        $attrs =
          array('nickname' => Ostatus_profile::getActivityObjectNickname($subject),
                'uri' => $subject->id);

        $user = User::register($attrs);
    }

    $profile = $user->getProfile();
    Ostatus_profile::updateProfile($profile, $subject);

    // FIXME: Update avatar
    return $user;
}

function purify($content)
{
    $config = array('safe' => 1,
                    'deny_attribute' => 'id,style,on*');
    return htmLawed($content, $config);
}

try {
    try {
        $user = getUser();
    } catch (NoUserArgumentException $noae) {
        $user = null;
    }
    $doc  = getActivityStreamDocument();
    importActivityStream($user, $doc);
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}
