<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * An activity
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Feed
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * An activity in the ActivityStrea.ms world
 *
 * An activity is kind of like a sentence: someone did something
 * to something else.
 *
 * 'someone' is the 'actor'; 'did something' is the verb;
 * 'something else' is the object.
 *
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
class Activity
{
    const SPEC   = 'http://activitystrea.ms/spec/1.0/';
    const SCHEMA = 'http://activitystrea.ms/schema/1.0/';
    const MEDIA  = 'http://purl.org/syndication/atommedia';

    const VERB       = 'verb';
    const OBJECT     = 'object';
    const ACTOR      = 'actor';
    const SUBJECT    = 'subject';
    const OBJECTTYPE = 'object-type';
    const CONTEXT    = 'context';
    const TARGET     = 'target';

    const ATOM = 'http://www.w3.org/2005/Atom';

    const AUTHOR    = 'author';
    const PUBLISHED = 'published';
    const UPDATED   = 'updated';

    const RSS = null; // no namespace!

    const PUBDATE     = 'pubDate';
    const DESCRIPTION = 'description';
    const GUID        = 'guid';
    const SELF        = 'self';
    const IMAGE       = 'image';
    const URL         = 'url';

    const DC = 'http://purl.org/dc/elements/1.1/';

    const CREATOR = 'creator';

    const CONTENTNS = 'http://purl.org/rss/1.0/modules/content/';
    const ENCODED = 'encoded';

    public $actor;   // an ActivityObject
    public $verb;    // a string (the URL)
    public $objects = array();  // an array of ActivityObjects
    public $target;  // an ActivityObject
    public $context; // an ActivityObject
    public $time;    // Time of the activity
    public $link;    // an ActivityObject
    public $entry;   // the source entry
    public $feed;    // the source feed

    public $summary; // summary of activity
    public $content; // HTML content of activity
    public $id;      // ID of the activity
    public $title;   // title of the activity
    public $categories = array(); // list of AtomCategory objects
    public $enclosures = array(); // list of enclosure URL references

    public $extra = array(); // extra elements as array(tag, attrs, content)
    public $source;  // ActivitySource object representing 'home feed'
    public $selfLink; // <link rel='self' type='application/atom+xml'>
    public $editLink; // <link rel='edit' type='application/atom+xml'>

    /**
     * Turns a regular old Atom <entry> into a magical activity
     *
     * @param DOMElement $entry Atom entry to poke at
     * @param DOMElement $feed  Atom feed, for context
     */
    function __construct($entry = null, $feed = null)
    {
        if (is_null($entry)) {
            return;
        }

        // Insist on a feed's root DOMElement; don't allow a DOMDocument
        if ($feed instanceof DOMDocument) {
            throw new ClientException(
                // TRANS: Client exception thrown when a feed instance is a DOMDocument.
                _('Expecting a root feed element but got a whole XML document.')
            );
        }

        $this->entry = $entry;
        $this->feed  = $feed;

        if ($entry->namespaceURI == Activity::ATOM &&
            $entry->localName == 'entry') {
            $this->_fromAtomEntry($entry, $feed);
        } else if ($entry->namespaceURI == Activity::RSS &&
                   $entry->localName == 'item') {
            $this->_fromRssItem($entry, $feed);
        } else if ($entry->namespaceURI == Activity::SPEC &&
                   $entry->localName == 'object') {
            $this->_fromAtomEntry($entry, $feed);
        } else {
            // Low level exception. No need for i18n.
            throw new Exception("Unknown DOM element: {$entry->namespaceURI} {$entry->localName}");
        }
    }

    function _fromAtomEntry($entry, $feed)
    {
        $pubEl = $this->_child($entry, self::PUBLISHED, self::ATOM);

        if (!empty($pubEl)) {
            $this->time = strtotime($pubEl->textContent);
        } else {
            // XXX technically an error; being liberal. Good idea...?
            $updateEl = $this->_child($entry, self::UPDATED, self::ATOM);
            if (!empty($updateEl)) {
                $this->time = strtotime($updateEl->textContent);
            } else {
                $this->time = null;
            }
        }

        $this->link = ActivityUtils::getPermalink($entry);

        $verbEl = $this->_child($entry, self::VERB);

        if (!empty($verbEl)) {
            $this->verb = trim($verbEl->textContent);
        } else {
            $this->verb = ActivityVerb::POST;
            // XXX: do other implied stuff here
        }

        // get immediate object children

        $objectEls = ActivityUtils::children($entry, self::OBJECT, self::SPEC);

        if (count($objectEls) > 0) {
            foreach ($objectEls as $objectEl) {
                // Special case for embedded activities
                $objectType = ActivityUtils::childContent($objectEl, self::OBJECTTYPE, self::SPEC);
                if (!empty($objectType) && $objectType == ActivityObject::ACTIVITY) {
                    $this->objects[] = new Activity($objectEl);
                } else {
                    $this->objects[] = new ActivityObject($objectEl);
                }
            }
        } else {
            // XXX: really?
            $this->objects[] = new ActivityObject($entry);
        }

        $actorEl = $this->_child($entry, self::ACTOR);

        if (!empty($actorEl)) {
            // Standalone <activity:actor> elements are a holdover from older
            // versions of ActivityStreams. Newer feeds should have this data
            // integrated straight into <atom:author>.

            $this->actor = new ActivityObject($actorEl);

            // Cliqset has bad actor IDs (just nickname of user). We
            // work around it by getting the author data and using its
            // id instead

            if (!preg_match('/^\w+:/', $this->actor->id)) {
                $authorEl = ActivityUtils::child($entry, 'author');
                if (!empty($authorEl)) {
                    $authorObj = new ActivityObject($authorEl);
                    $this->actor->id = $authorObj->id;
                }
            }
        } else if ($authorEl = $this->_child($entry, self::AUTHOR, self::ATOM)) {

            // An <atom:author> in the entry overrides any author info on
            // the surrounding feed.
            $this->actor = new ActivityObject($authorEl);

        } else if (!empty($feed) &&
                   $subjectEl = $this->_child($feed, self::SUBJECT)) {

            // Feed subject is used for things like groups.
            // Should actually possibly not be interpreted as an actor...?
            $this->actor = new ActivityObject($subjectEl);

        } else if (!empty($feed) && $authorEl = $this->_child($feed, self::AUTHOR,
                                                              self::ATOM)) {

            // If there's no <atom:author> on the entry, it's safe to assume
            // the containing feed's authorship info applies.
            $this->actor = new ActivityObject($authorEl);
        }

        $contextEl = $this->_child($entry, self::CONTEXT);

        if (!empty($contextEl)) {
            $this->context = new ActivityContext($contextEl);
        } else {
            $this->context = new ActivityContext($entry);
        }

        $targetEl = $this->_child($entry, self::TARGET);

        if (!empty($targetEl)) {
            $this->target = new ActivityObject($targetEl);
        }

        $this->summary = ActivityUtils::childContent($entry, 'summary');
        $this->id      = ActivityUtils::childContent($entry, 'id');
        $this->content = ActivityUtils::getContent($entry);

        $catEls = $entry->getElementsByTagNameNS(self::ATOM, 'category');
        if ($catEls) {
            for ($i = 0; $i < $catEls->length; $i++) {
                $catEl = $catEls->item($i);
                $this->categories[] = new AtomCategory($catEl);
            }
        }

        foreach (ActivityUtils::getLinks($entry, 'enclosure') as $link) {
            $this->enclosures[] = $link->getAttribute('href');
        }

        // From APP. Might be useful.

        $this->selfLink = ActivityUtils::getLink($entry, 'self', 'application/atom+xml');
        $this->editLink = ActivityUtils::getLink($entry, 'edit', 'application/atom+xml');
    }

    function _fromRssItem($item, $channel)
    {
        $verbEl = $this->_child($item, self::VERB);

        if (!empty($verbEl)) {
            $this->verb = trim($verbEl->textContent);
        } else {
            $this->verb = ActivityVerb::POST;
            // XXX: do other implied stuff here
        }

        $pubDateEl = $this->_child($item, self::PUBDATE, self::RSS);

        if (!empty($pubDateEl)) {
            $this->time = strtotime($pubDateEl->textContent);
        }

        if ($authorEl = $this->_child($item, self::AUTHOR, self::RSS)) {
            $this->actor = ActivityObject::fromRssAuthor($authorEl);
        } else if ($dcCreatorEl = $this->_child($item, self::CREATOR, self::DC)) {
            $this->actor = ActivityObject::fromDcCreator($dcCreatorEl);
        } else if ($posterousEl = $this->_child($item, ActivityObject::AUTHOR, ActivityObject::POSTEROUS)) {
            // Special case for Posterous.com
            $this->actor = ActivityObject::fromPosterousAuthor($posterousEl);
        } else if (!empty($channel)) {
            $this->actor = ActivityObject::fromRssChannel($channel);
        } else {
            // No actor!
        }

        $this->title = ActivityUtils::childContent($item, ActivityObject::TITLE, self::RSS);

        $contentEl = ActivityUtils::child($item, self::ENCODED, self::CONTENTNS);

        if (!empty($contentEl)) {
            // <content:encoded> XML node's text content is HTML; no further processing needed.
            $this->content = $contentEl->textContent;
        } else {
            $descriptionEl = ActivityUtils::child($item, self::DESCRIPTION, self::RSS);
            if (!empty($descriptionEl)) {
                // Per spec, <description> must be plaintext.
                // In practice, often there's HTML... but these days good
                // feeds are using <content:encoded> which is explicitly
                // real HTML.
                // We'll treat this following spec, and do HTML escaping
                // to convert from plaintext to HTML.
                $this->content = htmlspecialchars($descriptionEl->textContent);
            }
        }

        $this->link = ActivityUtils::childContent($item, ActivityUtils::LINK, self::RSS);

        // @fixme enclosures
        // @fixme thumbnails... maybe

        $guidEl = ActivityUtils::child($item, self::GUID, self::RSS);

        if (!empty($guidEl)) {
            $this->id = $guidEl->textContent;

            if ($guidEl->hasAttribute('isPermaLink') && $guidEl->getAttribute('isPermaLink') != 'false') {
                // overwrites <link>
                $this->link = $this->id;
            }
        }

        $this->objects[] = new ActivityObject($item);
        $this->context   = new ActivityContext($item);
    }

    /**
     * Returns an Atom <entry> based on this activity
     *
     * @return DOMElement Atom entry
     */

    function toAtomEntry()
    {
        return null;
    }

    /**
     * Returns an array based on this activity suitable
     * for encoding as a JSON object
     *
     * @return array $activity
     */

    function asArray()
    {
        $activity = array();

        // actor
        $activity['actor'] = $this->actor->asArray();

        // content
        $activity['content'] = $this->content;

        // generator <-- We could use this when we know a notice is created
        //               locally. Or if we know the upstream Generator.

        // icon <-- possibly a mini object representing verb?

        // id
        $activity['id'] = $this->id;

        // object
        if ($this->verb == ActivityVerb::POST && count($this->objects) == 1) {
            $activity['object'] = $this->objects[0]->asArray();

            // Context stuff. For now I'm just sticking most of it
            // in a property called "context"

            if (!empty($this->context)) {

                if (!empty($this->context->location)) {
                    $loc = $this->context->location;

                    // GeoJSON

                    $activity['geopoint'] = array(
                        'type'        => 'Point',
                        'coordinates' => array($loc->lat, $loc->lon)
                    );

                }

                $activity['to']      = $this->context->getToArray();
                $activity['context'] = $this->context->asArray();
            }

            // Instead of adding enclosures as an extension to JSON
            // Activities, it seems like we should be using the
            // attachements property of ActivityObject

            $attachments = array();

            // XXX: OK, this is kinda cheating. We should probably figure out
            // what kind of objects these are based on mime-type and then
            // create specific object types. Right now this rely on
            // duck-typing.  Also, we should include an embed code for
            // video attachments.

            foreach ($this->enclosures as $enclosure) {

                if (is_string($enclosure)) {

                    $attachments[]['id']  = $enclosure;

                } else {

                    $attachments[]['id']  = $enclosure->url;

                    $mediaLink = new ActivityStreamsMediaLink(
                        $enclosure->url,
                        null,
                        null,
                        $enclosure->mimetype
                        // XXX: Add 'size' as an extension to MediaLink?
                    );

                    $attachments[]['mediaLink'] = $mediaLink->asArray(); // extension

                    if ($enclosure->title) {
                        $attachments[]['displayName'] = $enclosure->title;
                    }
               }
            }

            if (!empty($attachments)) {
                $activity['object']['attachments'] = $attachments;
            }

        } else {
            $activity['object'] = array();
            foreach($this->objects as $object) {
                $oa = $object->asArray();
                if ($object instanceof Activity) {
                    // throw in a type
                    // XXX: hackety-hack
                    $oa['objectType'] = 'activity';
                }
                $activity['object'][] = $oa;
            }
        }

        // published
        $activity['published'] = self::iso8601Date($this->time);

        // provider
        $provider = array(
            'objectType' => 'service',
            'displayName' => common_config('site', 'name'),
            'url' => common_root_url()
        );

        $activity['provider'] = $provider;

        // target
        if (!empty($this->target)) {
            $activity['target'] = $this->target->asArray();
        }

        // title
        $activity['title'] = $this->title;

        // updated <-- Optional. Should we use this to indicate the time we r
        //             eceived a remote notice? Probably not.

        // verb
        //
        // We can probably use the whole schema URL here but probably the
        // relative simple name is easier to parse
        $activity['verb'] = substr($this->verb, strrpos($this->verb, '/') + 1);

        // url
        $activity['url'] = $this->id;

        /* Purely extensions hereafter */

        $tags = array();

        // Use an Activity Object for term? Which object? Note?
        foreach ($this->categories as $cat) {
            $tags[] = $cat->term;
        }

        $activity['tags'] = $tags;

        // XXX: a bit of a hack... Since JSON isn't namespaced we probably
        // shouldn't be using 'statusnet:notice_info', but this will work
        // for the moment.

        foreach ($this->extra as $e) {
            list($objectName, $props, $txt) = $e;
            if (!empty($objectName)) {
                $activity[$objectName] = $props;
            }
        }

        return array_filter($activity);
    }

    function asString($namespace=false, $author=true, $source=false)
    {
        $xs = new XMLStringer(true);
        $this->outputTo($xs, $namespace, $author, $source);
        return $xs->getString();
    }

    function outputTo($xs, $namespace=false, $author=true, $source=false, $tag='entry')
    {
        if ($namespace) {
            $attrs = array('xmlns' => 'http://www.w3.org/2005/Atom',
                           'xmlns:thr' => 'http://purl.org/syndication/thread/1.0',
                           'xmlns:activity' => 'http://activitystrea.ms/spec/1.0/',
                           'xmlns:georss' => 'http://www.georss.org/georss',
                           'xmlns:ostatus' => 'http://ostatus.org/schema/1.0',
                           'xmlns:poco' => 'http://portablecontacts.net/spec/1.0',
                           'xmlns:media' => 'http://purl.org/syndication/atommedia',
                           'xmlns:statusnet' => 'http://status.net/schema/api/1/');
        } else {
            $attrs = array();
        }

        $xs->elementStart($tag, $attrs);

        if ($tag != 'entry') {
            $xs->element('activity:object-type', null, ActivityObject::ACTIVITY);
        }

        if ($this->verb == ActivityVerb::POST && count($this->objects) == 1 && $tag == 'entry') {

            $obj = $this->objects[0];
			$obj->outputTo($xs, null);

        } else {
            $xs->element('id', null, $this->id);
            $xs->element('title', null, $this->title);

            $xs->element('content', array('type' => 'html'), $this->content);

            if (!empty($this->summary)) {
                $xs->element('summary', null, $this->summary);
            }

            if (!empty($this->link)) {
                $xs->element('link', array('rel' => 'alternate',
                                           'type' => 'text/html'),
                             $this->link);
            }

        }

        $xs->element('activity:verb', null, $this->verb);

        $published = self::iso8601Date($this->time);

        $xs->element('published', null, $published);
        $xs->element('updated', null, $published);

        if ($author) {
            $this->actor->outputTo($xs, 'author');

            // XXX: Remove <activity:actor> ASAP! Author information
            // has been moved to the author element in the Activity
            // Streams spec. We're outputting actor only for backward
            // compatibility with clients that can only parse
            // activities based on older versions of the spec.

            $depMsg = 'Deprecation warning: activity:actor is present '
                . 'only for backward compatibility. It will be '
                . 'removed in the next version of StatusNet.';
            $xs->comment($depMsg);
            $this->actor->outputTo($xs, 'activity:actor');
        }

        if ($this->verb != ActivityVerb::POST || count($this->objects) != 1 || $tag != 'entry') {
            foreach($this->objects as $object) {
                if ($object instanceof Activity) {
                    $object->outputTo($xs, false, true, true, 'activity:object');
                } else {
                    $object->outputTo($xs, 'activity:object');
                }
            }
        }

        if (!empty($this->context)) {

            if (!empty($this->context->replyToID)) {
                if (!empty($this->context->replyToUrl)) {
                    $xs->element('thr:in-reply-to',
                                 array('ref' => $this->context->replyToID,
                                       'href' => $this->context->replyToUrl));
                } else {
                    $xs->element('thr:in-reply-to',
                                 array('ref' => $this->context->replyToID));
                }
            }

            if (!empty($this->context->replyToUrl)) {
                $xs->element('link', array('rel' => 'related',
                                           'href' => $this->context->replyToUrl));
            }

            if (!empty($this->context->conversation)) {
                $xs->element('link', array('rel' => 'ostatus:conversation',
                                           'href' => $this->context->conversation));
            }

            foreach ($this->context->attention as $attnURI) {
                $xs->element('link', array('rel' => 'ostatus:attention',
                                           'href' => $attnURI));
                $xs->element('link', array('rel' => 'mentioned',
                                           'href' => $attnURI));
            }

            // XXX: shoulda used ActivityVerb::SHARE

            if (!empty($this->context->forwardID)) {
                if (!empty($this->context->forwardUrl)) {
                    $xs->element('ostatus:forward',
                                 array('ref' => $this->context->forwardID,
                                       'href' => $this->context->forwardUrl));
                } else {
                    $xs->element('ostatus:forward',
                                 array('ref' => $this->context->forwardID));
                }
            }

            if (!empty($this->context->location)) {
                $loc = $this->context->location;
                $xs->element('georss:point', null, $loc->lat . ' ' . $loc->lon);
            }
        }

        if ($this->target) {
            $this->target->outputTo($xs, 'activity:target');
        }

        foreach ($this->categories as $cat) {
            $cat->outputTo($xs);
        }

        // can be either URLs or enclosure objects

        foreach ($this->enclosures as $enclosure) {
            if (is_string($enclosure)) {
                $xs->element('link', array('rel' => 'enclosure',
                                           'href' => $enclosure));
            } else {
                $attributes = array('rel' => 'enclosure',
                                    'href' => $enclosure->url,
                                    'type' => $enclosure->mimetype,
                                    'length' => $enclosure->size);
                if ($enclosure->title) {
                    $attributes['title'] = $enclosure->title;
                }
                $xs->element('link', $attributes);
            }
        }

        // Info on the source feed

        if ($source && !empty($this->source)) {
            $xs->elementStart('source');

            $xs->element('id', null, $this->source->id);
            $xs->element('title', null, $this->source->title);

            if (array_key_exists('alternate', $this->source->links)) {
                $xs->element('link', array('rel' => 'alternate',
                                           'type' => 'text/html',
                                           'href' => $this->source->links['alternate']));
            }

            if (array_key_exists('self', $this->source->links)) {
                $xs->element('link', array('rel' => 'self',
                                           'type' => 'application/atom+xml',
                                           'href' => $this->source->links['self']));
            }

            if (array_key_exists('license', $this->source->links)) {
                $xs->element('link', array('rel' => 'license',
                                           'href' => $this->source->links['license']));
            }

            if (!empty($this->source->icon)) {
                $xs->element('icon', null, $this->source->icon);
            }

            if (!empty($this->source->updated)) {
                $xs->element('updated', null, $this->source->updated);
            }

            $xs->elementEnd('source');
        }

        if (!empty($this->selfLink)) {
            $xs->element('link', array('rel' => 'self',
                                       'type' => 'application/atom+xml',
                                       'href' => $this->selfLink));
        }

        if (!empty($this->editLink)) {
            $xs->element('link', array('rel' => 'edit',
                                       'type' => 'application/atom+xml',
                                       'href' => $this->editLink));
        }

        // For throwing in extra elements; used for statusnet:notice_info

        foreach ($this->extra as $el) {
            list($tag, $attrs, $content) = $el;
            $xs->element($tag, $attrs, $content);
        }

        $xs->elementEnd($tag);

        return;
    }

    private function _child($element, $tag, $namespace=self::SPEC)
    {
        return ActivityUtils::child($element, $tag, $namespace);
    }

    /**
     * For consistency, we'll always output UTC rather than local time.
     * Note that clients *should* accept any timezone we give them as long
     * as it's properly formatted.
     *
     * @param int $tm Unix timestamp
     * @return string
     */
    static function iso8601Date($tm)
    {
        $dateStr = date('d F Y H:i:s', $tm);
        $d = new DateTime($dateStr, new DateTimeZone('UTC'));
        return $d->format('c');
    }
}
