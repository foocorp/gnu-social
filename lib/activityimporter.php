<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * class to import activities as part of a user's timeline
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
 * @category  Cache
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
 * Class comment
 *
 * @category  General
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ActivityImporter extends QueueHandler
{
    private $trusted = false;

    /**
     * Function comment
     *
     * @param
     *
     * @return
     */
    function handle($data)
    {
        list($user, $author, $activity, $trusted) = $data;

        $this->trusted = $trusted;

        $done = null;

        try {
            if (Event::handle('StartImportActivity',
                              array($user, $author, $activity, $trusted, &$done))) {
                switch ($activity->verb) {
                case ActivityVerb::FOLLOW:
                    $this->subscribeProfile($user, $author, $activity);
                    break;
                case ActivityVerb::JOIN:
                    $this->joinGroup($user, $activity);
                    break;
                case ActivityVerb::POST:
                    $this->postNote($user, $author, $activity);
                    break;
                default:
                    // TRANS: Client exception thrown when using an unknown verb for the activity importer.
                    throw new ClientException(sprintf(_("Unknown verb: \"%s\"."),$activity->verb));
                }
                Event::handle('EndImportActivity',
                              array($user, $author, $activity, $trusted));
                $done = true;
            }
        } catch (Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
            $done = true;
        }
        return $done;
    }

    function subscribeProfile($user, $author, $activity)
    {
        $profile = $user->getProfile();

        if ($activity->objects[0]->id == $author->id) {
            if (!$this->trusted) {
                // TRANS: Client exception thrown when trying to force a subscription for an untrusted user.
                throw new ClientException(_('Cannot force subscription for untrusted user.'));
            }

            $other = $activity->actor;
            $otherUser = User::staticGet('uri', $other->id);

            if (!empty($otherUser)) {
                $otherProfile = $otherUser->getProfile();
            } else {
                // TRANS: Client exception thrown when trying to force a remote user to subscribe.
                throw new Exception(_('Cannot force remote user to subscribe.'));
            }

            // XXX: don't do this for untrusted input!

            Subscription::start($otherProfile, $profile);
        } else if (empty($activity->actor)
                   || $activity->actor->id == $author->id) {

            $other = $activity->objects[0];

            $otherProfile = Profile::fromUri($other->id);

            if (empty($otherProfile)) {
                // TRANS: Client exception thrown when trying to subscribe to an unknown profile.
                throw new ClientException(_('Unknown profile.'));
            }

            Subscription::start($profile, $otherProfile);
        } else {
            // TRANS: Client exception thrown when trying to import an event not related to the importing user.
            throw new Exception(_('This activity seems unrelated to our user.'));
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
                // TRANS: Client exception thrown when trying to join a remote group that is not a group.
                throw new ClientException(_('Remote profile is not a group!'));
            }
            $group = $oprofile->localGroup();
        }

        assert(!empty($group));

        if ($user->isMember($group)) {
            // TRANS: Client exception thrown when trying to join a group the importing user is already a member of.
            throw new ClientException(_("User is already a member of this group."));
        }

        $user->joinGroup($group);
    }

    // XXX: largely cadged from Ostatus_profile::processNote()

    function postNote($user, $author, $activity)
    {
        $note = $activity->objects[0];

        $sourceUri = $note->id;

        $notice = Notice::staticGet('uri', $sourceUri);

        if (!empty($notice)) {

            common_log(LOG_INFO, "Notice {$sourceUri} already exists.");

            if ($this->trusted) {

                $profile = $notice->getProfile();

                $uri = $profile->getUri();

                if ($uri == $author->id) {
                    common_log(LOG_INFO, "Updating notice author from $author->id to $user->uri");
                    $orig = clone($notice);
                    $notice->profile_id = $user->id;
                    $notice->update($orig);
                    return;
                } else {
                    // TRANS: Client exception thrown when trying to import a notice by another user.
                    // TRANS: %1$s is the source URI of the notice, %2$s is the URI of the author.
                    throw new ClientException(sprintf(_('Already know about notice %1$s and '.
                                                        ' it has a different author %2$s.'),
                                                      $sourceUri, $uri));
                }
            } else {
                // TRANS: Client exception thrown when trying to overwrite the author information for a non-trusted user during import.
                throw new ClientException(_('Not overwriting author info for non-trusted user.'));
            }
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
            // TRANS: Client exception thrown when trying to import a notice without content.
            // TRANS: %s is the notice URI.
            throw new ClientException(sprintf(_('No content for notice %s.'),$sourceUri));
        }

        // Get (safe!) HTML and text versions of the content

        $rendered = $this->purify($sourceContent);
        $content = html_entity_decode(strip_tags($rendered), ENT_QUOTES, 'UTF-8');

        $shortened = $user->shortenLinks($content);

        $options = array('is_local' => Notice::LOCAL_PUBLIC,
                         'uri' => $sourceUri,
                         'rendered' => $rendered,
                         'replies' => array(),
                         'groups' => array(),
                         'tags' => array(),
                         'urls' => array(),
                         'distribute' => false);

        // Check for optional attributes...

        if (!empty($activity->time)) {
            $options['created'] = common_sql_date($activity->time);
        }

        if ($activity->context) {
            // Any individual or group attn: targets?

            list($options['groups'], $options['replies']) = $this->filterAttention($activity->context->attention);

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

        common_log(LOG_INFO, "Saving notice {$options['uri']}");

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


    function purify($content)
    {
        require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';

        $config = array('safe' => 1,
                        'deny_attribute' => 'id,style,on*');

        return htmLawed($content, $config);
    }
}
