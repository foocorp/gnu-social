<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class Ostatus_profile extends Managed_DataObject
{
    public $__table = 'ostatus_profile';

    public $uri;

    public $profile_id;
    public $group_id;
    public $peopletag_id;

    public $feeduri;
    public $salmonuri;
    public $avatar; // remote URL of the last avatar we saved

    public $created;
    public $modified;

    /**
     * Return table definition for Schema setup and DB_DataObject usage.
     *
     * @return array array of column definitions
     */
    static function schemaDef()
    {
        return array(
            'fields' => array(
                'uri' => array('type' => 'varchar', 'length' => 255, 'not null' => true),
                'profile_id' => array('type' => 'integer'),
                'group_id' => array('type' => 'integer'),
                'peopletag_id' => array('type' => 'integer'),
                'feeduri' => array('type' => 'varchar', 'length' => 255),
                'salmonuri' => array('type' => 'varchar', 'length' => 255),
                'avatar' => array('type' => 'text'),
                'created' => array('type' => 'datetime', 'not null' => true),
                'modified' => array('type' => 'datetime', 'not null' => true),
            ),
            'primary key' => array('uri'),
            'unique keys' => array(
                'ostatus_profile_profile_id_idx' => array('profile_id'),
                'ostatus_profile_group_id_idx' => array('group_id'),
                'ostatus_profile_peopletag_id_idx' => array('peopletag_id'),
                'ostatus_profile_feeduri_idx' => array('feeduri'),
            ),
            'foreign keys' => array(
                'ostatus_profile_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
                'ostatus_profile_group_id_fkey' => array('user_group', array('group_id' => 'id')),
                'ostatus_profile_peopletag_id_fkey' => array('profile_list', array('peopletag_id' => 'id')),
            ),
        );
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localProfile()
    {
        if ($this->profile_id) {
            return Profile::staticGet('id', $this->profile_id);
        }
        return null;
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function localGroup()
    {
        if ($this->group_id) {
            return User_group::staticGet('id', $this->group_id);
        }
        return null;
    }

    /**
     * Fetch the StatusNet-side peopletag for this feed
     * @return Profile
     */
    public function localPeopletag()
    {
        if ($this->peopletag_id) {
            return Profile_list::staticGet('id', $this->peopletag_id);
        }
        return null;
    }

    /**
     * Returns an ActivityObject describing this remote user or group profile.
     * Can then be used to generate Atom chunks.
     *
     * @return ActivityObject
     */
    function asActivityObject()
    {
        if ($this->isGroup()) {
            return ActivityObject::fromGroup($this->localGroup());
        } else if ($this->isPeopletag()) {
            return ActivityObject::fromPeopletag($this->localPeopletag());
        } else {
            return ActivityObject::fromProfile($this->localProfile());
        }
    }

    /**
     * Returns an XML string fragment with profile information as an
     * Activity Streams noun object with the given element type.
     *
     * Assumes that 'activity' namespace has been previously defined.
     *
     * @todo FIXME: Replace with wrappers on asActivityObject when it's got everything.
     *
     * @param string $element one of 'actor', 'subject', 'object', 'target'
     * @return string
     */
    function asActivityNoun($element)
    {
        if ($this->isGroup()) {
            $noun = ActivityObject::fromGroup($this->localGroup());
            return $noun->asString('activity:' . $element);
        } else if ($this->isPeopletag()) {
            $noun = ActivityObject::fromPeopletag($this->localPeopletag());
            return $noun->asString('activity:' . $element);
        } else {
            $noun = ActivityObject::fromProfile($this->localProfile());
            return $noun->asString('activity:' . $element);
        }
    }

    /**
     * @return boolean true if this is a remote group
     */
    function isGroup()
    {
        if ($this->profile_id || $this->peopletag_id && !$this->group_id) {
            return false;
        } else if ($this->group_id && !$this->profile_id && !$this->peopletag_id) {
            return true;
        } else if ($this->group_id && ($this->profile_id || $this->peopletag_id)) {
            // TRANS: Server exception. %s is a URI
            throw new ServerException(sprintf(_m('Invalid ostatus_profile state: Two or more IDs set for %s.'), $this->uri));
        } else {
            // TRANS: Server exception. %s is a URI
            throw new ServerException(sprintf(_m('Invalid ostatus_profile state: All IDs empty for %s.'), $this->uri));
        }
    }

    /**
     * @return boolean true if this is a remote peopletag
     */
    function isPeopletag()
    {
        if ($this->profile_id || $this->group_id && !$this->peopletag_id) {
            return false;
        } else if ($this->peopletag_id && !$this->profile_id && !$this->group_id) {
            return true;
        } else if ($this->peopletag_id && ($this->profile_id || $this->group_id)) {
            // TRANS: Server exception. %s is a URI
            throw new ServerException(sprintf(_m('Invalid ostatus_profile state: Two or more IDs set for %s.'), $this->uri));
        } else {
            // TRANS: Server exception. %s is a URI
            throw new ServerException(sprintf(_m('Invalid ostatus_profile state: All IDs empty for %s.'), $this->uri));
        }
    }

    /**
     * Send a subscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /main/push/callback.
     *
     * @return bool true on success, false on failure
     * @throws ServerException if feed state is not valid
     */
    public function subscribe()
    {
        $feedsub = FeedSub::ensureFeed($this->feeduri);
        if ($feedsub->sub_state == 'active') {
            // Active subscription, we don't need to do anything.
            return true;
        } else {
            // Inactive or we got left in an inconsistent state.
            // Run a subscription request to make sure we're current!
            return $feedsub->subscribe();
        }
    }

    /**
     * Check if this remote profile has any active local subscriptions, and
     * if not drop the PuSH subscription feed.
     *
     * @return bool true on success, false on failure
     */
    public function unsubscribe() {
        $this->garbageCollect();
    }

    /**
     * Check if this remote profile has any active local subscriptions, and
     * if not drop the PuSH subscription feed.
     *
     * @return boolean
     */
    public function garbageCollect()
    {
        $feedsub = FeedSub::staticGet('uri', $this->feeduri);
        return $feedsub->garbageCollect();
    }

    /**
     * Check if this remote profile has any active local subscriptions, so the
     * PuSH subscription layer can decide if it can drop the feed.
     *
     * This gets called via the FeedSubSubscriberCount event when running
     * FeedSub::garbageCollect().
     *
     * @return int
     */
    public function subscriberCount()
    {
        if ($this->isGroup()) {
            $members = $this->localGroup()->getMembers(0, 1);
            $count = $members->N;
        } else if ($this->isPeopletag()) {
            $subscribers = $this->localPeopletag()->getSubscribers(0, 1);
            $count = $subscribers->N;
        } else {
            $profile = $this->localProfile();
            $count = $profile->subscriberCount();
            if ($profile->hasLocalTags()) {
                $count = 1;
            }
        }
        common_log(LOG_INFO, __METHOD__ . " SUB COUNT BEFORE: $count");

        // Other plugins may be piggybacking on OStatus without having
        // an active group or user-to-user subscription we know about.
        Event::handle('Ostatus_profileSubscriberCount', array($this, &$count));
        common_log(LOG_INFO, __METHOD__ . " SUB COUNT AFTER: $count");

        return $count;
    }

    /**
     * Send an Activity Streams notification to the remote Salmon endpoint,
     * if so configured.
     *
     * @param Profile $actor  Actor who did the activity
     * @param string  $verb   Activity::SUBSCRIBE or Activity::JOIN
     * @param Object  $object object of the action; must define asActivityNoun($tag)
     */
    public function notify($actor, $verb, $object=null, $target=null)
    {
        if (!($actor instanceof Profile)) {
            $type = gettype($actor);
            if ($type == 'object') {
                $type = get_class($actor);
            }
            // TRANS: Server exception.
            // TRANS: %1$s is the method name the exception occured in, %2$s is the actor type.
            throw new ServerException(sprintf(_m('Invalid actor passed to %1$s: %2$s.'),__METHOD__,$type));
        }
        if ($object == null) {
            $object = $this;
        }
        if ($this->salmonuri) {
            $text = 'update';
            $id = TagURI::mint('%s:%s:%s',
                               $verb,
                               $actor->getURI(),
                               common_date_iso8601(time()));

            // @todo FIXME: Consolidate all these NS settings somewhere.
            $attributes = array('xmlns' => Activity::ATOM,
                                'xmlns:activity' => 'http://activitystrea.ms/spec/1.0/',
                                'xmlns:thr' => 'http://purl.org/syndication/thread/1.0',
                                'xmlns:georss' => 'http://www.georss.org/georss',
                                'xmlns:ostatus' => 'http://ostatus.org/schema/1.0',
                                'xmlns:poco' => 'http://portablecontacts.net/spec/1.0',
                                'xmlns:media' => 'http://purl.org/syndication/atommedia');

            $entry = new XMLStringer();
            $entry->elementStart('entry', $attributes);
            $entry->element('id', null, $id);
            $entry->element('title', null, $text);
            $entry->element('summary', null, $text);
            $entry->element('published', null, common_date_w3dtf(common_sql_now()));

            $entry->element('activity:verb', null, $verb);
            $entry->raw($actor->asAtomAuthor());
            $entry->raw($actor->asActivityActor());
            $entry->raw($object->asActivityNoun('object'));
            if ($target != null) {
                $entry->raw($target->asActivityNoun('target'));
            }
            $entry->elementEnd('entry');

            $xml = $entry->getString();
            common_log(LOG_INFO, "Posting to Salmon endpoint $this->salmonuri: $xml");

            $salmon = new Salmon(); // ?
            return $salmon->post($this->salmonuri, $xml, $actor);
        }
        return false;
    }

    /**
     * Send a Salmon notification ping immediately, and confirm that we got
     * an acceptable response from the remote site.
     *
     * @param mixed $entry XML string, Notice, or Activity
     * @param Profile $actor
     * @return boolean success
     */
    public function notifyActivity($entry, $actor)
    {
        if ($this->salmonuri) {
            $salmon = new Salmon();
            return $salmon->post($this->salmonuri, $this->notifyPrepXml($entry), $actor);
        }

        return false;
    }

    /**
     * Queue a Salmon notification for later. If queues are disabled we'll
     * send immediately but won't get the return value.
     *
     * @param mixed $entry XML string, Notice, or Activity
     * @return boolean success
     */
    public function notifyDeferred($entry, $actor)
    {
        if ($this->salmonuri) {
            $data = array('salmonuri' => $this->salmonuri,
                          'entry' => $this->notifyPrepXml($entry),
                          'actor' => $actor->id);

            $qm = QueueManager::get();
            return $qm->enqueue($data, 'salmon');
        }

        return false;
    }

    protected function notifyPrepXml($entry)
    {
        $preamble = '<?xml version="1.0" encoding="UTF-8" ?' . '>';
        if (is_string($entry)) {
            return $entry;
        } else if ($entry instanceof Activity) {
            return $preamble . $entry->asString(true);
        } else if ($entry instanceof Notice) {
            return $preamble . $entry->asAtomEntry(true, true);
        } else {
            // TRANS: Server exception.
            throw new ServerException(_m('Invalid type passed to Ostatus_profile::notify. It must be XML string or Activity entry.'));
        }
    }

    function getBestName()
    {
        if ($this->isGroup()) {
            return $this->localGroup()->getBestName();
        } else if ($this->isPeopletag()) {
            return $this->localPeopletag()->getBestName();
        } else {
            return $this->localProfile()->getBestName();
        }
    }

    /**
     * Read and post notices for updates from the feed.
     * Currently assumes that all items in the feed are new,
     * coming from a PuSH hub.
     *
     * @param DOMDocument $doc
     * @param string $source identifier ("push")
     */
    public function processFeed(DOMDocument $doc, $source)
    {
        $feed = $doc->documentElement;

        if ($feed->localName == 'feed' && $feed->namespaceURI == Activity::ATOM) {
            $this->processAtomFeed($feed, $source);
        } else if ($feed->localName == 'rss') { // @todo FIXME: Check namespace.
            $this->processRssFeed($feed, $source);
        } else {
            // TRANS: Exception.
            throw new Exception(_m('Unknown feed format.'));
        }
    }

    public function processAtomFeed(DOMElement $feed, $source)
    {
        $entries = $feed->getElementsByTagNameNS(Activity::ATOM, 'entry');
        if ($entries->length == 0) {
            common_log(LOG_ERR, __METHOD__ . ": no entries in feed update, ignoring");
            return;
        }

        for ($i = 0; $i < $entries->length; $i++) {
            $entry = $entries->item($i);
            $this->processEntry($entry, $feed, $source);
        }
    }

    public function processRssFeed(DOMElement $rss, $source)
    {
        $channels = $rss->getElementsByTagName('channel');

        if ($channels->length == 0) {
            // TRANS: Exception.
            throw new Exception(_m('RSS feed without a channel.'));
        } else if ($channels->length > 1) {
            common_log(LOG_WARNING, __METHOD__ . ": more than one channel in an RSS feed");
        }

        $channel = $channels->item(0);

        $items = $channel->getElementsByTagName('item');

        for ($i = 0; $i < $items->length; $i++) {
            $item = $items->item($i);
            $this->processEntry($item, $channel, $source);
        }
    }

    /**
     * Process a posted entry from this feed source.
     *
     * @param DOMElement $entry
     * @param DOMElement $feed for context
     * @param string $source identifier ("push" or "salmon")
     *
     * @return Notice Notice representing the new (or existing) activity
     */
    public function processEntry($entry, $feed, $source)
    {
        $activity = new Activity($entry, $feed);
        return $this->processActivity($activity, $source);
    }

    public function processActivity($activity, $source)
    {
        $notice = null;

        // The "WithProfile" events were added later.

        if (Event::handle('StartHandleFeedEntryWithProfile', array($activity, $this, &$notice)) &&
            Event::handle('StartHandleFeedEntry', array($activity))) {

            switch ($activity->verb) {
            case ActivityVerb::POST:
                // @todo process all activity objects
                switch ($activity->objects[0]->type) {
                case ActivityObject::ARTICLE:
                case ActivityObject::BLOGENTRY:
                case ActivityObject::NOTE:
                case ActivityObject::STATUS:
                case ActivityObject::COMMENT:
                case null:
                    $notice = $this->processPost($activity, $source);
                    break;
                default:
                    // TRANS: Client exception.
                    throw new ClientException(_m('Cannot handle that kind of post.'));
                }
                break;
            case ActivityVerb::SHARE:
                $notice = $this->processShare($activity, $source);
                break;
            default:
                common_log(LOG_INFO, "Ignoring activity with unrecognized verb $activity->verb");
            }

            Event::handle('EndHandleFeedEntry', array($activity));
            Event::handle('EndHandleFeedEntryWithProfile', array($activity, $this, $notice));
        }

        return $notice;
    }

    public function processShare($activity, $method)
    {
        $notice = null;

        $oprofile = $this->checkAuthorship($activity);

        if (empty($oprofile)) {
            common_log(LOG_INFO, "No author matched share activity");
            return null;
        }

        if (count($activity->objects) != 1) {
            // TRANS: Client exception thrown when trying to share multiple activities at once.
            throw new ClientException(_m('Can only handle share activities with exactly one object.'));
        }

        $shared = $activity->objects[0];

        if (!($shared instanceof Activity)) {
            // TRANS: Client exception thrown when trying to share a non-activity object.
            throw new ClientException(_m('Can only handle shared activities.'));
        }

        $other = Ostatus_profile::ensureActivityObjectProfile($shared->actor);

        // Save the item (or check for a dupe)

        $sharedNotice = $other->processActivity($shared, $method);

        if (empty($sharedNotice)) {
            $sharedId = ($shared->id) ? $shared->id : $shared->objects[0]->id;
            // TRANS: Client exception thrown when saving an activity share fails.
            // TRANS: %s is a share ID.
            throw new ClientException(sprintf(_m('Failed to save activity %s.'),
                                              $sharedId));
        }

        // The id URI will be used as a unique identifier for for the notice,
        // protecting against duplicate saves. It isn't required to be a URL;
        // tag: URIs for instance are found in Google Buzz feeds.

        $sourceUri = $activity->id;

        $dupe = Notice::staticGet('uri', $sourceUri);
        if ($dupe) {
            common_log(LOG_INFO, "OStatus: ignoring duplicate post: $sourceUri");
            return $dupe;
        }

        // We'll also want to save a web link to the original notice, if provided.

        $sourceUrl = null;
        if ($activity->link) {
            $sourceUrl = $activity->link;
        } else if ($activity->link) {
            $sourceUrl = $activity->link;
        } else if (preg_match('!^https?://!', $activity->id)) {
            $sourceUrl = $activity->id;
        }

        // Use summary as fallback for content

        if (!empty($activity->content)) {
            $sourceContent = $activity->content;
        } else if (!empty($activity->summary)) {
            $sourceContent = $activity->summary;
        } else if (!empty($activity->title)) {
            $sourceContent = $activity->title;
        } else {
            // @todo FIXME: Fetch from $sourceUrl?
            // TRANS: Client exception. %s is a source URI.
            throw new ClientException(sprintf(_m('No content for notice %s.'),$sourceUri));
        }

        // Get (safe!) HTML and text versions of the content

        $rendered = $this->purify($sourceContent);
        $content = html_entity_decode(strip_tags($rendered), ENT_QUOTES, 'UTF-8');

        $shortened = common_shorten_links($content);

        // If it's too long, try using the summary, and make the
        // HTML an attachment.

        $attachment = null;

        if (Notice::contentTooLong($shortened)) {
            $attachment = $this->saveHTMLFile($activity->title, $rendered);
            $summary = html_entity_decode(strip_tags($activity->summary), ENT_QUOTES, 'UTF-8');
            if (empty($summary)) {
                $summary = $content;
            }
            $shortSummary = common_shorten_links($summary);
            if (Notice::contentTooLong($shortSummary)) {
                $url = common_shorten_url($sourceUrl);
                $shortSummary = substr($shortSummary,
                                       0,
                                       Notice::maxContent() - (mb_strlen($url) + 2));
                $content = $shortSummary . ' ' . $url;

                // We mark up the attachment link specially for the HTML output
                // so we can fold-out the full version inline.

                // @todo FIXME i18n: This tooltip will be saved with the site's default language
                // TRANS: Shown when a notice is longer than supported and/or when attachments are present. At runtime
                // TRANS: this will usually be replaced with localised text from StatusNet core messages.
                $showMoreText = _m('Show more');
                $attachUrl = common_local_url('attachment',
                                              array('attachment' => $attachment->id));
                $rendered = common_render_text($shortSummary) .
                            '<a href="' . htmlspecialchars($attachUrl) .'"'.
                            ' class="attachment more"' .
                            ' title="'. htmlspecialchars($showMoreText) . '">' .
                            '&#8230;' .
                            '</a>';
            }
        }

        $options = array('is_local' => Notice::REMOTE,
                         'url' => $sourceUrl,
                         'uri' => $sourceUri,
                         'rendered' => $rendered,
                         'replies' => array(),
                         'groups' => array(),
                         'peopletags' => array(),
                         'tags' => array(),
                         'urls' => array(),
                         'repeat_of' => $sharedNotice->id,
                         'scope' => $sharedNotice->scope);

        // Check for optional attributes...

        if (!empty($activity->time)) {
            $options['created'] = common_sql_date($activity->time);
        }

        if ($activity->context) {
            // Any individual or group attn: targets?
            $replies = $activity->context->attention;
            $options['groups'] = $this->filterReplies($oprofile, $replies);
            $options['replies'] = $replies;

            // Maintain direct reply associations
            // @todo FIXME: What about conversation ID?
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

        if ($this->isPeopletag()) {
            $options['peopletags'][] = $this->localPeopletag();
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
            // @todo FIXME: Save these locally or....?
            $options['urls'][] = $href;
        }

        $notice = Notice::saveNew($oprofile->profile_id,
                                  $content,
                                  'ostatus',
                                  $options);

        return $notice;
    }

    /**
     * Process an incoming post activity from this remote feed.
     * @param Activity $activity
     * @param string $method 'push' or 'salmon'
     * @return mixed saved Notice or false
     * @todo FIXME: Break up this function, it's getting nasty long
     */
    public function processPost($activity, $method)
    {
        $notice = null;

        $oprofile = $this->checkAuthorship($activity);

        if (empty($oprofile)) {
            return null;
        }

        // It's not always an ActivityObject::NOTE, but... let's just say it is.

        $note = $activity->objects[0];

        // The id URI will be used as a unique identifier for for the notice,
        // protecting against duplicate saves. It isn't required to be a URL;
        // tag: URIs for instance are found in Google Buzz feeds.
        $sourceUri = $note->id;
        $dupe = Notice::staticGet('uri', $sourceUri);
        if ($dupe) {
            common_log(LOG_INFO, "OStatus: ignoring duplicate post: $sourceUri");
            return $dupe;
        }

        // We'll also want to save a web link to the original notice, if provided.
        $sourceUrl = null;
        if ($note->link) {
            $sourceUrl = $note->link;
        } else if ($activity->link) {
            $sourceUrl = $activity->link;
        } else if (preg_match('!^https?://!', $note->id)) {
            $sourceUrl = $note->id;
        }

        // Use summary as fallback for content

        if (!empty($note->content)) {
            $sourceContent = $note->content;
        } else if (!empty($note->summary)) {
            $sourceContent = $note->summary;
        } else if (!empty($note->title)) {
            $sourceContent = $note->title;
        } else {
            // @todo FIXME: Fetch from $sourceUrl?
            // TRANS: Client exception. %s is a source URI.
            throw new ClientException(sprintf(_m('No content for notice %s.'),$sourceUri));
        }

        // Get (safe!) HTML and text versions of the content

        $rendered = $this->purify($sourceContent);
        $content = html_entity_decode(strip_tags($rendered), ENT_QUOTES, 'UTF-8');

        $shortened = common_shorten_links($content);

        // If it's too long, try using the summary, and make the
        // HTML an attachment.

        $attachment = null;

        if (Notice::contentTooLong($shortened)) {
            $attachment = $this->saveHTMLFile($note->title, $rendered);
            $summary = html_entity_decode(strip_tags($note->summary), ENT_QUOTES, 'UTF-8');
            if (empty($summary)) {
                $summary = $content;
            }
            $shortSummary = common_shorten_links($summary);
            if (Notice::contentTooLong($shortSummary)) {
                $url = common_shorten_url($sourceUrl);
                $shortSummary = substr($shortSummary,
                                       0,
                                       Notice::maxContent() - (mb_strlen($url) + 2));
                $content = $shortSummary . ' ' . $url;

                // We mark up the attachment link specially for the HTML output
                // so we can fold-out the full version inline.

                // @todo FIXME i18n: This tooltip will be saved with the site's default language
                // TRANS: Shown when a notice is longer than supported and/or when attachments are present. At runtime
                // TRANS: this will usually be replaced with localised text from StatusNet core messages.
                $showMoreText = _m('Show more');
                $attachUrl = common_local_url('attachment',
                                              array('attachment' => $attachment->id));
                $rendered = common_render_text($shortSummary) .
                            '<a href="' . htmlspecialchars($attachUrl) .'"'.
                            ' class="attachment more"' .
                            ' title="'. htmlspecialchars($showMoreText) . '">' .
                            '&#8230;' .
                            '</a>';
            }
        }

        $options = array('is_local' => Notice::REMOTE,
                        'url' => $sourceUrl,
                        'uri' => $sourceUri,
                        'rendered' => $rendered,
                        'replies' => array(),
                        'groups' => array(),
                        'peopletags' => array(),
                        'tags' => array(),
                        'urls' => array());

        // Check for optional attributes...

        if (!empty($activity->time)) {
            $options['created'] = common_sql_date($activity->time);
        }

        if ($activity->context) {
            // Any individual or group attn: targets?
            $replies = $activity->context->attention;
            $options['groups'] = $this->filterReplies($oprofile, $replies);
            $options['replies'] = $replies;

            // Maintain direct reply associations
            // @todo FIXME: What about conversation ID?
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

        if ($this->isPeopletag()) {
            $options['peopletags'][] = $this->localPeopletag();
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
            // @todo FIXME: Save these locally or....?
            $options['urls'][] = $href;
        }

        try {
            $saved = Notice::saveNew($oprofile->profile_id,
                                     $content,
                                     'ostatus',
                                     $options);
            if ($saved) {
                Ostatus_source::saveNew($saved, $this, $method);
                if (!empty($attachment)) {
                    File_to_post::processNew($attachment->id, $saved->id);
                }
            }
        } catch (Exception $e) {
            common_log(LOG_ERR, "OStatus save of remote message $sourceUri failed: " . $e->getMessage());
            throw $e;
        }
        common_log(LOG_INFO, "OStatus saved remote message $sourceUri as notice id $saved->id");
        return $saved;
    }

    /**
     * Clean up HTML
     */
    protected function purify($html)
    {
        require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';
        $config = array('safe' => 1,
                        'deny_attribute' => 'id,style,on*');
        return htmLawed($html, $config);
    }

    /**
     * Filters a list of recipient ID URIs to just those for local delivery.
     * @param Ostatus_profile local profile of sender
     * @param array in/out &$attention_uris set of URIs, will be pruned on output
     * @return array of group IDs
     */
    protected function filterReplies($sender, &$attention_uris)
    {
        common_log(LOG_DEBUG, "Original reply recipients: " . implode(', ', $attention_uris));
        $groups = array();
        $replies = array();
        foreach (array_unique($attention_uris) as $recipient) {
            // Is the recipient a local user?
            $user = User::staticGet('uri', $recipient);
            if ($user) {
                // @todo FIXME: Sender verification, spam etc?
                $replies[] = $recipient;
                continue;
            }

            // Is the recipient a local group?
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
                        common_log(LOG_DEBUG, "Skipping reply to local group $group->nickname as sender $profile->id is not a member");
                    }
                    continue;
                } else {
                    common_log(LOG_DEBUG, "Skipping reply to bogus group $recipient");
                }
            }

            // Is the recipient a remote user or group?
            try {
                $oprofile = Ostatus_profile::ensureProfileURI($recipient);
                if ($oprofile->isGroup()) {
                    // Deliver to local members of this remote group.
                    // @todo FIXME: Sender verification?
                    $groups[] = $oprofile->group_id;
                } else {
                    // may be canonicalized or something
                    $replies[] = $oprofile->uri;
                }
                continue;
            } catch (Exception $e) {
                // Neither a recognizable local nor remote user!
                common_log(LOG_DEBUG, "Skipping reply to unrecognized profile $recipient: " . $e->getMessage());
            }

        }
        $attention_uris = $replies;
        common_log(LOG_DEBUG, "Local reply recipients: " . implode(', ', $replies));
        common_log(LOG_DEBUG, "Local group recipients: " . implode(', ', $groups));
        return $groups;
    }

    /**
     * Look up and if necessary create an Ostatus_profile for the remote entity
     * with the given profile page URL. This should never return null -- you
     * will either get an object or an exception will be thrown.
     *
     * @param string $profile_url
     * @return Ostatus_profile
     * @throws Exception on various error conditions
     * @throws OStatusShadowException if this reference would obscure a local user/group
     */
    public static function ensureProfileURL($profile_url, $hints=array())
    {
        $oprofile = self::getFromProfileURL($profile_url);

        if (!empty($oprofile)) {
            return $oprofile;
        }

        $hints['profileurl'] = $profile_url;

        // Fetch the URL
        // XXX: HTTP caching

        $client = new HTTPClient();
        $client->setHeader('Accept', 'text/html,application/xhtml+xml');
        $response = $client->get($profile_url);

        if (!$response->isOk()) {
            // TRANS: Exception. %s is a profile URL.
            throw new Exception(sprintf(_m('Could not reach profile page %s.'),$profile_url));
        }

        // Check if we have a non-canonical URL

        $finalUrl = $response->getUrl();

        if ($finalUrl != $profile_url) {

            $hints['profileurl'] = $finalUrl;

            $oprofile = self::getFromProfileURL($finalUrl);

            if (!empty($oprofile)) {
                return $oprofile;
            }
        }

        // Try to get some hCard data

        $body = $response->getBody();

        $hcardHints = DiscoveryHints::hcardHints($body, $finalUrl);

        if (!empty($hcardHints)) {
            $hints = array_merge($hints, $hcardHints);
        }

        // Check if they've got an LRDD header

        $lrdd = LinkHeader::getLink($response, 'lrdd', 'application/xrd+xml');

        if (!empty($lrdd)) {

            $xrd = Discovery::fetchXrd($lrdd);
            $xrdHints = DiscoveryHints::fromXRD($xrd);

            $hints = array_merge($hints, $xrdHints);
        }

        // If discovery found a feedurl (probably from LRDD), use it.

        if (array_key_exists('feedurl', $hints)) {
            return self::ensureFeedURL($hints['feedurl'], $hints);
        }

        // Get the feed URL from HTML

        $discover = new FeedDiscovery();

        $feedurl = $discover->discoverFromHTML($finalUrl, $body);

        if (!empty($feedurl)) {
            $hints['feedurl'] = $feedurl;
            return self::ensureFeedURL($feedurl, $hints);
        }

        // TRANS: Exception. %s is a URL.
        throw new Exception(sprintf(_m('Could not find a feed URL for profile page %s.'),$finalUrl));
    }

    /**
     * Look up the Ostatus_profile, if present, for a remote entity with the
     * given profile page URL. Will return null for both unknown and invalid
     * remote profiles.
     *
     * @return mixed Ostatus_profile or null
     * @throws OStatusShadowException for local profiles
     */
    static function getFromProfileURL($profile_url)
    {
        $profile = Profile::staticGet('profileurl', $profile_url);

        if (empty($profile)) {
            return null;
        }

        // Is it a known Ostatus profile?

        $oprofile = Ostatus_profile::staticGet('profile_id', $profile->id);

        if (!empty($oprofile)) {
            return $oprofile;
        }

        // Is it a local user?

        $user = User::staticGet('id', $profile->id);

        if (!empty($user)) {
            // @todo i18n FIXME: use sprintf and add i18n (?)
            throw new OStatusShadowException($profile, "'$profile_url' is the profile for local user '{$user->nickname}'.");
        }

        // Continue discovery; it's a remote profile
        // for OMB or some other protocol, may also
        // support OStatus

        return null;
    }

    /**
     * Look up and if necessary create an Ostatus_profile for remote entity
     * with the given update feed. This should never return null -- you will
     * either get an object or an exception will be thrown.
     *
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureFeedURL($feed_url, $hints=array())
    {
        $discover = new FeedDiscovery();

        $feeduri = $discover->discoverFromFeedURL($feed_url);
        $hints['feedurl'] = $feeduri;

        $huburi = $discover->getHubLink();
        $hints['hub'] = $huburi;
        $salmonuri = $discover->getAtomLink(Salmon::NS_REPLIES);
        $hints['salmon'] = $salmonuri;

        if (!$huburi && !common_config('feedsub', 'fallback_hub')) {
            // We can only deal with folks with a PuSH hub
            throw new FeedSubNoHubException();
        }

        $feedEl = $discover->root;

        if ($feedEl->tagName == 'feed') {
            return self::ensureAtomFeed($feedEl, $hints);
        } else if ($feedEl->tagName == 'channel') {
            return self::ensureRssChannel($feedEl, $hints);
        } else {
            throw new FeedSubBadXmlException($feeduri);
        }
    }

    /**
     * Look up and, if necessary, create an Ostatus_profile for the remote
     * profile with the given Atom feed - actually loaded from the feed.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param DOMElement $feedEl root element of a loaded Atom feed
     * @param array $hints additional discovery information passed from higher levels
     * @todo FIXME: Should this be marked public?
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureAtomFeed($feedEl, $hints)
    {
        $author = ActivityUtils::getFeedAuthor($feedEl);

        if (empty($author)) {
            // XXX: make some educated guesses here
            // TRANS: Feed sub exception.
            throw new FeedSubException(_m('Cannot find enough profile '.
                                          'information to make a feed.'));
        }

        return self::ensureActivityObjectProfile($author, $hints);
    }

    /**
     * Look up and, if necessary, create an Ostatus_profile for the remote
     * profile with the given RSS feed - actually loaded from the feed.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param DOMElement $feedEl root element of a loaded RSS feed
     * @param array $hints additional discovery information passed from higher levels
     * @todo FIXME: Should this be marked public?
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureRssChannel($feedEl, $hints)
    {
        // Special-case for Posterous. They have some nice metadata in their
        // posterous:author elements. We should use them instead of the channel.

        $items = $feedEl->getElementsByTagName('item');

        if ($items->length > 0) {
            $item = $items->item(0);
            $authorEl = ActivityUtils::child($item, ActivityObject::AUTHOR, ActivityObject::POSTEROUS);
            if (!empty($authorEl)) {
                $obj = ActivityObject::fromPosterousAuthor($authorEl);
                // Posterous has multiple authors per feed, and multiple feeds
                // per author. We check if this is the "main" feed for this author.
                if (array_key_exists('profileurl', $hints) &&
                    !empty($obj->poco) &&
                    common_url_to_nickname($hints['profileurl']) == $obj->poco->preferredUsername) {
                    return self::ensureActivityObjectProfile($obj, $hints);
                }
            }
        }

        // @todo FIXME: We should check whether this feed has elements
        // with different <author> or <dc:creator> elements, and... I dunno.
        // Do something about that.

        $obj = ActivityObject::fromRssChannel($feedEl);

        return self::ensureActivityObjectProfile($obj, $hints);
    }

    /**
     * Download and update given avatar image
     *
     * @param string $url
     * @throws Exception in various failure cases
     */
    protected function updateAvatar($url)
    {
        if ($url == $this->avatar) {
            // We've already got this one.
            return;
        }
        if (!common_valid_http_url($url)) {
            // TRANS: Server exception. %s is a URL.
            throw new ServerException(sprintf(_m('Invalid avatar URL %s.'), $url));
        }

        if ($this->isGroup()) {
            $self = $this->localGroup();
        } else {
            $self = $this->localProfile();
        }
        if (!$self) {
            throw new ServerException(sprintf(
                // TRANS: Server exception. %s is a URI.
                _m('Tried to update avatar for unsaved remote profile %s.'),
                $this->uri));
        }

        // @todo FIXME: This should be better encapsulated
        // ripped from oauthstore.php (for old OMB client)
        $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
        try {
            if (!copy($url, $temp_filename)) {
                // TRANS: Server exception. %s is a URL.
                throw new ServerException(sprintf(_m('Unable to fetch avatar from %s.'), $url));
            }

            if ($this->isGroup()) {
                $id = $this->group_id;
            } else {
                $id = $this->profile_id;
            }
            // @todo FIXME: Should we be using different ids?
            $imagefile = new ImageFile($id, $temp_filename);
            $filename = Avatar::filename($id,
                                         image_type_to_extension($imagefile->type),
                                         null,
                                         common_timestamp());
            rename($temp_filename, Avatar::path($filename));
        } catch (Exception $e) {
            unlink($temp_filename);
            throw $e;
        }
        // @todo FIXME: Hardcoded chmod is lame, but seems to be necessary to
        // keep from accidentally saving images from command-line (queues)
        // that can't be read from web server, which causes hard-to-notice
        // problems later on:
        //
        // http://status.net/open-source/issues/2663
        chmod(Avatar::path($filename), 0644);

        $profile = $this->localProfile();

        if (!empty($profile)) {
            $profile->setOriginal($filename);
        }

        $orig = clone($this);
        $this->avatar = $url;
        $this->update($orig);
    }

    /**
     * Pull avatar URL from ActivityObject or profile hints
     *
     * @param ActivityObject $object
     * @param array $hints
     * @return mixed URL string or false
     */
    public static function getActivityObjectAvatar($object, $hints=array())
    {
        if ($object->avatarLinks) {
            $best = false;
            // Take the exact-size avatar, or the largest avatar, or the first avatar if all sizeless
            foreach ($object->avatarLinks as $avatar) {
                if ($avatar->width == AVATAR_PROFILE_SIZE && $avatar->height = AVATAR_PROFILE_SIZE) {
                    // Exact match!
                    $best = $avatar;
                    break;
                }
                if (!$best || $avatar->width > $best->width) {
                    $best = $avatar;
                }
            }
            return $best->url;
        } else if (array_key_exists('avatar', $hints)) {
            return $hints['avatar'];
        }
        return false;
    }

    /**
     * Get an appropriate avatar image source URL, if available.
     *
     * @param ActivityObject $actor
     * @param DOMElement $feed
     * @return string
     */
    protected static function getAvatar($actor, $feed)
    {
        $url = '';
        $icon = '';
        if ($actor->avatar) {
            $url = trim($actor->avatar);
        }
        if (!$url) {
            // Check <atom:logo> and <atom:icon> on the feed
            $els = $feed->childNodes();
            if ($els && $els->length) {
                for ($i = 0; $i < $els->length; $i++) {
                    $el = $els->item($i);
                    if ($el->namespaceURI == Activity::ATOM) {
                        if (empty($url) && $el->localName == 'logo') {
                            $url = trim($el->textContent);
                            break;
                        }
                        if (empty($icon) && $el->localName == 'icon') {
                            // Use as a fallback
                            $icon = trim($el->textContent);
                        }
                    }
                }
            }
            if ($icon && !$url) {
                $url = $icon;
            }
        }
        if ($url) {
            $opts = array('allowed_schemes' => array('http', 'https'));
            if (Validate::uri($url, $opts)) {
                return $url;
            }
        }

        return Plugin::staticPath('OStatus', 'images/96px-Feed-icon.svg.png');
    }

    /**
     * Fetch, or build if necessary, an Ostatus_profile for the actor
     * in a given Activity Streams activity.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param Activity $activity
     * @param string $feeduri if we already know the canonical feed URI!
     * @param string $salmonuri if we already know the salmon return channel URI
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureActorProfile($activity, $hints=array())
    {
        return self::ensureActivityObjectProfile($activity->actor, $hints);
    }

    /**
     * Fetch, or build if necessary, an Ostatus_profile for the profile
     * in a given Activity Streams object (can be subject, actor, or object).
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param ActivityObject $object
     * @param array $hints additional discovery information passed from higher levels
     * @return Ostatus_profile
     * @throws Exception
     */
    public static function ensureActivityObjectProfile($object, $hints=array())
    {
        $profile = self::getActivityObjectProfile($object);
        if ($profile) {
            $profile->updateFromActivityObject($object, $hints);
        } else {
            $profile = self::createActivityObjectProfile($object, $hints);
        }
        return $profile;
    }

    /**
     * @param Activity $activity
     * @return mixed matching Ostatus_profile or false if none known
     * @throws ServerException if feed info invalid
     */
    public static function getActorProfile($activity)
    {
        return self::getActivityObjectProfile($activity->actor);
    }

    /**
     * @param ActivityObject $activity
     * @return mixed matching Ostatus_profile or false if none known
     * @throws ServerException if feed info invalid
     */
    protected static function getActivityObjectProfile($object)
    {
        $uri = self::getActivityObjectProfileURI($object);
        return Ostatus_profile::staticGet('uri', $uri);
    }

    /**
     * Get the identifier URI for the remote entity described
     * by this ActivityObject. This URI is *not* guaranteed to be
     * a resolvable HTTP/HTTPS URL.
     *
     * @param ActivityObject $object
     * @return string
     * @throws ServerException if feed info invalid
     */
    protected static function getActivityObjectProfileURI($object)
    {
        if ($object->id) {
            if (ActivityUtils::validateUri($object->id)) {
                return $object->id;
            }
        }

        // If the id is missing or invalid (we've seen feeds mistakenly listing
        // things like local usernames in that field) then we'll use the profile
        // page link, if valid.
        if ($object->link && common_valid_http_url($object->link)) {
            return $object->link;
        }
        // TRANS: Server exception.
        throw new ServerException(_m('No author ID URI found.'));
    }

    /**
     * @todo FIXME: Validate stuff somewhere.
     */

    /**
     * Create local ostatus_profile and profile/user_group entries for
     * the provided remote user or group.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param ActivityObject $object
     * @param array $hints
     *
     * @return Ostatus_profile
     */
    protected static function createActivityObjectProfile($object, $hints=array())
    {
        $homeuri = $object->id;
        $discover = false;

        if (!$homeuri) {
            common_log(LOG_DEBUG, __METHOD__ . " empty actor profile URI: " . var_export($activity, true));
            // TRANS: Exception.
            throw new Exception(_m('No profile URI.'));
        }

        $user = User::staticGet('uri', $homeuri);
        if ($user) {
            // TRANS: Exception.
            throw new Exception(_m('Local user cannot be referenced as remote.'));
        }

        if (OStatusPlugin::localGroupFromUrl($homeuri)) {
            // TRANS: Exception.
            throw new Exception(_m('Local group cannot be referenced as remote.'));
        }

        $ptag = Profile_list::staticGet('uri', $homeuri);
        if ($ptag) {
            $local_user = User::staticGet('id', $ptag->tagger);
            if (!empty($local_user)) {
                // TRANS: Exception.
                throw new Exception(_m('Local list cannot be referenced as remote.'));
            }
        }

        if (array_key_exists('feedurl', $hints)) {
            $feeduri = $hints['feedurl'];
        } else {
            $discover = new FeedDiscovery();
            $feeduri = $discover->discoverFromURL($homeuri);
        }

        if (array_key_exists('salmon', $hints)) {
            $salmonuri = $hints['salmon'];
        } else {
            if (!$discover) {
                $discover = new FeedDiscovery();
                $discover->discoverFromFeedURL($hints['feedurl']);
            }
            $salmonuri = $discover->getAtomLink(Salmon::NS_REPLIES);
        }

        if (array_key_exists('hub', $hints)) {
            $huburi = $hints['hub'];
        } else {
            if (!$discover) {
                $discover = new FeedDiscovery();
                $discover->discoverFromFeedURL($hints['feedurl']);
            }
            $huburi = $discover->getHubLink();
        }

        if (!$huburi && !common_config('feedsub', 'fallback_hub')) {
            // We can only deal with folks with a PuSH hub
            throw new FeedSubNoHubException();
        }

        $oprofile = new Ostatus_profile();

        $oprofile->uri        = $homeuri;
        $oprofile->feeduri    = $feeduri;
        $oprofile->salmonuri  = $salmonuri;

        $oprofile->created    = common_sql_now();
        $oprofile->modified   = common_sql_now();

        if ($object->type == ActivityObject::PERSON) {
            $profile = new Profile();
            $profile->created = common_sql_now();
            self::updateProfile($profile, $object, $hints);

            $oprofile->profile_id = $profile->insert();
            if (!$oprofile->profile_id) {
                // TRANS: Server exception.
                throw new ServerException(_m('Cannot save local profile.'));
            }
        } else if ($object->type == ActivityObject::GROUP) {
            $group = new User_group();
            $group->uri = $homeuri;
            $group->created = common_sql_now();
            self::updateGroup($group, $object, $hints);

            $oprofile->group_id = $group->insert();
            if (!$oprofile->group_id) {
                // TRANS: Server exception.
                throw new ServerException(_m('Cannot save local profile.'));
            }
        } else if ($object->type == ActivityObject::_LIST) {
            $ptag = new Profile_list();
            $ptag->uri = $homeuri;
            $ptag->created = common_sql_now();
            self::updatePeopletag($ptag, $object, $hints);

            $oprofile->peopletag_id = $ptag->insert();
            if (!$oprofile->peopletag_id) {
            // TRANS: Server exception.
                throw new ServerException(_m('Cannot save local list.'));
            }
        }

        $ok = $oprofile->insert();

        if (!$ok) {
            // TRANS: Server exception.
            throw new ServerException(_m('Cannot save OStatus profile.'));
        }

        $avatar = self::getActivityObjectAvatar($object, $hints);

        if ($avatar) {
            try {
                $oprofile->updateAvatar($avatar);
            } catch (Exception $ex) {
                // Profile is saved, but Avatar is messed up. We're
                // just going to continue.
                common_log(LOG_WARNING, "Exception saving OStatus profile avatar: ". $ex->getMessage());
            }
        }

        return $oprofile;
    }

    /**
     * Save any updated profile information to our local copy.
     * @param ActivityObject $object
     * @param array $hints
     */
    public function updateFromActivityObject($object, $hints=array())
    {
        if ($this->isGroup()) {
            $group = $this->localGroup();
            self::updateGroup($group, $object, $hints);
        } else if ($this->isPeopletag()) {
            $ptag = $this->localPeopletag();
            self::updatePeopletag($ptag, $object, $hints);
        } else {
            $profile = $this->localProfile();
            self::updateProfile($profile, $object, $hints);
        }

        $avatar = self::getActivityObjectAvatar($object, $hints);
        if ($avatar && !isset($ptag)) {
            try {
                $this->updateAvatar($avatar);
            } catch (Exception $ex) {
                common_log(LOG_WARNING, "Exception saving OStatus profile avatar: " . $ex->getMessage());
            }
        }
    }

    public static function updateProfile($profile, $object, $hints=array())
    {
        $orig = clone($profile);

        // Existing nickname is better than nothing.

        if (!array_key_exists('nickname', $hints)) {
            $hints['nickname'] = $profile->nickname;
        }

        $nickname = self::getActivityObjectNickname($object, $hints);

        if (!empty($nickname)) {
            $profile->nickname = $nickname;
        }

        if (!empty($object->title)) {
            $profile->fullname = $object->title;
        } else if (array_key_exists('fullname', $hints)) {
            $profile->fullname = $hints['fullname'];
        }

        if (!empty($object->link)) {
            $profile->profileurl = $object->link;
        } else if (array_key_exists('profileurl', $hints)) {
            $profile->profileurl = $hints['profileurl'];
        } else if (Validate::uri($object->id, array('allowed_schemes' => array('http', 'https')))) {
            $profile->profileurl = $object->id;
        }

        $bio = self::getActivityObjectBio($object, $hints);

        if (!empty($bio)) {
            $profile->bio = $bio;
        }

        $location = self::getActivityObjectLocation($object, $hints);

        if (!empty($location)) {
            $profile->location = $location;
        }

        $homepage = self::getActivityObjectHomepage($object, $hints);

        if (!empty($homepage)) {
            $profile->homepage = $homepage;
        }

        if (!empty($object->geopoint)) {
            $location = ActivityContext::locationFromPoint($object->geopoint);
            if (!empty($location)) {
                $profile->lat = $location->lat;
                $profile->lon = $location->lon;
            }
        }

        // @todo FIXME: tags/categories
        // @todo tags from categories

        if ($profile->id) {
            common_log(LOG_DEBUG, "Updating OStatus profile $profile->id from remote info $object->id: " . var_export($object, true) . var_export($hints, true));
            $profile->update($orig);
        }
    }

    protected static function updateGroup($group, $object, $hints=array())
    {
        $orig = clone($group);

        $group->nickname = self::getActivityObjectNickname($object, $hints);
        $group->fullname = $object->title;

        if (!empty($object->link)) {
            $group->mainpage = $object->link;
        } else if (array_key_exists('profileurl', $hints)) {
            $group->mainpage = $hints['profileurl'];
        }

        // @todo tags from categories
        $group->description = self::getActivityObjectBio($object, $hints);
        $group->location = self::getActivityObjectLocation($object, $hints);
        $group->homepage = self::getActivityObjectHomepage($object, $hints);

        if ($group->id) {
            common_log(LOG_DEBUG, "Updating OStatus group $group->id from remote info $object->id: " . var_export($object, true) . var_export($hints, true));
            $group->update($orig);
        }
    }

    protected static function updatePeopletag($tag, $object, $hints=array()) {
        $orig = clone($tag);

        $tag->tag = $object->title;

        if (!empty($object->link)) {
            $tag->mainpage = $object->link;
        } else if (array_key_exists('profileurl', $hints)) {
            $tag->mainpage = $hints['profileurl'];
        }

        $tag->description = $object->summary;
        $tagger = self::ensureActivityObjectProfile($object->owner);
        $tag->tagger = $tagger->profile_id;

        if ($tag->id) {
            common_log(LOG_DEBUG, "Updating OStatus peopletag $tag->id from remote info $object->id: " . var_export($object, true) . var_export($hints, true));
            $tag->update($orig);
        }
    }

    protected static function getActivityObjectHomepage($object, $hints=array())
    {
        $homepage = null;
        $poco     = $object->poco;

        if (!empty($poco)) {
            $url = $poco->getPrimaryURL();
            if ($url && $url->type == 'homepage') {
                $homepage = $url->value;
            }
        }

        // @todo Try for a another PoCo URL?

        return $homepage;
    }

    protected static function getActivityObjectLocation($object, $hints=array())
    {
        $location = null;

        if (!empty($object->poco) &&
            isset($object->poco->address->formatted)) {
            $location = $object->poco->address->formatted;
        } else if (array_key_exists('location', $hints)) {
            $location = $hints['location'];
        }

        if (!empty($location)) {
            if (mb_strlen($location) > 255) {
                $location = mb_substr($note, 0, 255 - 3) . ' … ';
            }
        }

        // @todo Try to find location some othe way? Via goerss point?

        return $location;
    }

    protected static function getActivityObjectBio($object, $hints=array())
    {
        $bio  = null;

        if (!empty($object->poco)) {
            $note = $object->poco->note;
        } else if (array_key_exists('bio', $hints)) {
            $note = $hints['bio'];
        }

        if (!empty($note)) {
            if (Profile::bioTooLong($note)) {
                // XXX: truncate ok?
                $bio = mb_substr($note, 0, Profile::maxBio() - 3) . ' … ';
            } else {
                $bio = $note;
            }
        }

        // @todo Try to get bio info some other way?

        return $bio;
    }

    public static function getActivityObjectNickname($object, $hints=array())
    {
        if ($object->poco) {
            if (!empty($object->poco->preferredUsername)) {
                return common_nicknamize($object->poco->preferredUsername);
            }
        }

        if (!empty($object->nickname)) {
            return common_nicknamize($object->nickname);
        }

        if (array_key_exists('nickname', $hints)) {
            return $hints['nickname'];
        }

        // Try the profile url (like foo.example.com or example.com/user/foo)
        if (!empty($object->link)) {
            $profileUrl = $object->link;
        } else if (!empty($hints['profileurl'])) {
            $profileUrl = $hints['profileurl'];
        }

        if (!empty($profileUrl)) {
            $nickname = self::nicknameFromURI($profileUrl);
        }

        // Try the URI (may be a tag:, http:, acct:, ...

        if (empty($nickname)) {
            $nickname = self::nicknameFromURI($object->id);
        }

        // Try a Webfinger if one was passed (way) down

        if (empty($nickname)) {
            if (array_key_exists('webfinger', $hints)) {
                $nickname = self::nicknameFromURI($hints['webfinger']);
            }
        }

        // Try the name

        if (empty($nickname)) {
            $nickname = common_nicknamize($object->title);
        }

        return $nickname;
    }

    protected static function nicknameFromURI($uri)
    {
        if (preg_match('/(\w+):/', $uri, $matches)) {
            $protocol = $matches[1];
        } else {
            return null;
        }

        switch ($protocol) {
        case 'acct':
        case 'mailto':
            if (preg_match("/^$protocol:(.*)?@.*\$/", $uri, $matches)) {
                return common_canonical_nickname($matches[1]);
            }
            return null;
        case 'http':
            return common_url_to_nickname($uri);
            break;
        default:
            return null;
        }
    }

    /**
     * Look up, and if necessary create, an Ostatus_profile for the remote
     * entity with the given webfinger address.
     * This should never return null -- you will either get an object or
     * an exception will be thrown.
     *
     * @param string $addr webfinger address
     * @return Ostatus_profile
     * @throws Exception on error conditions
     * @throws OStatusShadowException if this reference would obscure a local user/group
     */
    public static function ensureWebfinger($addr)
    {
        // First, try the cache

        $uri = self::cacheGet(sprintf('ostatus_profile:webfinger:%s', $addr));

        if ($uri !== false) {
            if (is_null($uri)) {
                // Negative cache entry
                // TRANS: Exception.
                throw new Exception(_m('Not a valid webfinger address.'));
            }
            $oprofile = Ostatus_profile::staticGet('uri', $uri);
            if (!empty($oprofile)) {
                return $oprofile;
            }
        }

        // Try looking it up
        $oprofile = Ostatus_profile::staticGet('uri', 'acct:'.$addr);

        if (!empty($oprofile)) {
            self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), $oprofile->uri);
            return $oprofile;
        }

        // Now, try some discovery

        $disco = new Discovery();

        try {
            $xrd = $disco->lookup($addr);
        } catch (Exception $e) {
            // Save negative cache entry so we don't waste time looking it up again.
            // @todo FIXME: Distinguish temporary failures?
            self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), null);
            // TRANS: Exception.
            throw new Exception(_m('Not a valid webfinger address.'));
        }

        $hints = array('webfinger' => $addr);

        $dhints = DiscoveryHints::fromXRD($xrd);

        $hints = array_merge($hints, $dhints);

        // If there's an Hcard, let's grab its info
        if (array_key_exists('hcard', $hints)) {
            if (!array_key_exists('profileurl', $hints) ||
                $hints['hcard'] != $hints['profileurl']) {
                $hcardHints = DiscoveryHints::fromHcardUrl($hints['hcard']);
                $hints = array_merge($hcardHints, $hints);
            }
        }

        // If we got a feed URL, try that
        if (array_key_exists('feedurl', $hints)) {
            try {
                common_log(LOG_INFO, "Discovery on acct:$addr with feed URL " . $hints['feedurl']);
                $oprofile = self::ensureFeedURL($hints['feedurl'], $hints);
                self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), $oprofile->uri);
                return $oprofile;
            } catch (Exception $e) {
                common_log(LOG_WARNING, "Failed creating profile from feed URL '$feedUrl': " . $e->getMessage());
                // keep looking
            }
        }

        // If we got a profile page, try that!
        if (array_key_exists('profileurl', $hints)) {
            try {
                common_log(LOG_INFO, "Discovery on acct:$addr with profile URL $profileUrl");
                $oprofile = self::ensureProfileURL($hints['profileurl'], $hints);
                self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), $oprofile->uri);
                return $oprofile;
            } catch (OStatusShadowException $e) {
                // We've ended up with a remote reference to a local user or group.
                // @todo FIXME: Ideally we should be able to say who it was so we can
                // go back and refer to it the regular way
                throw $e;
            } catch (Exception $e) {
                common_log(LOG_WARNING, "Failed creating profile from profile URL '$profileUrl': " . $e->getMessage());
                // keep looking
                //
                // @todo FIXME: This means an error discovering from profile page
                // may give us a corrupt entry using the webfinger URI, which
                // will obscure the correct page-keyed profile later on.
            }
        }

        // XXX: try hcard
        // XXX: try FOAF

        if (array_key_exists('salmon', $hints)) {
            $salmonEndpoint = $hints['salmon'];

            // An account URL, a salmon endpoint, and a dream? Not much to go
            // on, but let's give it a try

            $uri = 'acct:'.$addr;

            $profile = new Profile();

            $profile->nickname = self::nicknameFromUri($uri);
            $profile->created  = common_sql_now();

            if (isset($profileUrl)) {
                $profile->profileurl = $profileUrl;
            }

            $profile_id = $profile->insert();

            if (!$profile_id) {
                common_log_db_error($profile, 'INSERT', __FILE__);
                // TRANS: Exception. %s is a webfinger address.
                throw new Exception(sprintf(_m('Could not save profile for "%s".'),$addr));
            }

            $oprofile = new Ostatus_profile();

            $oprofile->uri        = $uri;
            $oprofile->salmonuri  = $salmonEndpoint;
            $oprofile->profile_id = $profile_id;
            $oprofile->created    = common_sql_now();

            if (isset($feedUrl)) {
                $profile->feeduri = $feedUrl;
            }

            $result = $oprofile->insert();

            if (!$result) {
                common_log_db_error($oprofile, 'INSERT', __FILE__);
                // TRANS: Exception. %s is a webfinger address.
                throw new Exception(sprintf(_m('Could not save OStatus profile for "%s".'),$addr));
            }

            self::cacheSet(sprintf('ostatus_profile:webfinger:%s', $addr), $oprofile->uri);
            return $oprofile;
        }

        // TRANS: Exception. %s is a webfinger address.
        throw new Exception(sprintf(_m('Could not find a valid profile for "%s".'),$addr));
    }

    /**
     * Store the full-length scrubbed HTML of a remote notice to an attachment
     * file on our server. We'll link to this at the end of the cropped version.
     *
     * @param string $title plaintext for HTML page's title
     * @param string $rendered HTML fragment for HTML page's body
     * @return File
     */
    function saveHTMLFile($title, $rendered)
    {
        $final = sprintf("<!DOCTYPE html>\n" .
                         '<html><head>' .
                         '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' .
                         '<title>%s</title>' .
                         '</head>' .
                         '<body>%s</body></html>',
                         htmlspecialchars($title),
                         $rendered);

        $filename = File::filename($this->localProfile(),
                                   'ostatus', // ignored?
                                   'text/html');

        $filepath = File::path($filename);

        file_put_contents($filepath, $final);

        $file = new File;

        $file->filename = $filename;
        $file->url      = File::url($filename);
        $file->size     = filesize($filepath);
        $file->date     = time();
        $file->mimetype = 'text/html';

        $file_id = $file->insert();

        if ($file_id === false) {
            common_log_db_error($file, "INSERT", __FILE__);
            // TRANS: Server exception.
            throw new ServerException(_m('Could not store HTML content of long post as file.'));
        }

        return $file;
    }

    static function ensureProfileURI($uri)
    {
        $oprofile = null;

        // First, try to query it

        $oprofile = Ostatus_profile::staticGet('uri', $uri);

        // If unfound, do discovery stuff

        if (empty($oprofile)) {
            if (preg_match("/^(\w+)\:(.*)/", $uri, $match)) {
                $protocol = $match[1];
                switch ($protocol) {
                case 'http':
                case 'https':
                    $oprofile = Ostatus_profile::ensureProfileURL($uri);
                    break;
                case 'acct':
                case 'mailto':
                    $rest = $match[2];
                    $oprofile = Ostatus_profile::ensureWebfinger($rest);
                    break;
                default:
                    // TRANS: Server exception.
                    // TRANS: %1$s is a protocol, %2$s is a URI.
                    throw new ServerException(sprintf(_m('Unrecognized URI protocol for profile: %1$s (%2$s).'),
                                                      $protocol,
                                                      $uri));
                    break;
                }
            } else {
                // TRANS: Server exception. %s is a URI.
                throw new ServerException(sprintf(_m('No URI protocol for profile: %s.'),$uri));
            }
        }

        return $oprofile;
    }

    function checkAuthorship($activity)
    {
        if ($this->isGroup() || $this->isPeopletag()) {
            // A group or propletag feed will contain posts from multiple authors.
            $oprofile = self::ensureActorProfile($activity);
            if ($oprofile->isGroup() || $oprofile->isPeopletag()) {
                // Groups can't post notices in StatusNet.
                common_log(LOG_WARNING,
                    "OStatus: skipping post with group listed ".
                    "as author: $oprofile->uri in feed from $this->uri");
                return false;
            }
        } else {
            $actor = $activity->actor;

            if (empty($actor)) {
                // OK here! assume the default
            } else if ($actor->id == $this->uri || $actor->link == $this->uri) {
                $this->updateFromActivityObject($actor);
            } else if ($actor->id) {
                // We have an ActivityStreams actor with an explicit ID that doesn't match the feed owner.
                // This isn't what we expect from mainline OStatus person feeds!
                // Group feeds go down another path, with different validation...
                // Most likely this is a plain ol' blog feed of some kind which
                // doesn't match our expectations. We'll take the entry, but ignore
                // the <author> info.
                common_log(LOG_WARNING, "Got an actor '{$actor->title}' ({$actor->id}) on single-user feed for {$this->uri}");
            } else {
                // Plain <author> without ActivityStreams actor info.
                // We'll just ignore this info for now and save the update under the feed's identity.
            }

            $oprofile = $this;
        }

        return $oprofile;
    }
}

/**
 * Exception indicating we've got a remote reference to a local user,
 * not a remote user!
 *
 * If we can ue a local profile after all, it's available as $e->profile.
 */
class OStatusShadowException extends Exception
{
    public $profile;

    /**
     * @param Profile $profile
     * @param string $message
     */
    function __construct($profile, $message) {
        $this->profile = $profile;
        parent::__construct($message);
    }
}
