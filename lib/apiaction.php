<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base API action
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
 * @category  API
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Dan Moore <dan@moore.cx>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @author    Toby Inkster <mail@tobyinkster.co.uk>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

/* External API usage documentation. Please update when you change how the API works. */

/*! @mainpage StatusNet REST API

    @section Introduction

    Some explanatory text about the API would be nice.

    @section API Methods

    @subsection timelinesmethods_sec Timeline Methods

    @li @ref publictimeline
    @li @ref friendstimeline

    @subsection statusmethods_sec Status Methods

    @li @ref statusesupdate

    @subsection usermethods_sec User Methods

    @subsection directmessagemethods_sec Direct Message Methods (now a plugin)

    @subsection friendshipmethods_sec Friendship Methods

    @subsection socialgraphmethods_sec Social Graph Methods

    @subsection accountmethods_sec Account Methods

    @subsection favoritesmethods_sec Favorites Methods

    @subsection blockmethods_sec Block Methods

    @subsection oauthmethods_sec OAuth Methods

    @subsection helpmethods_sec Help Methods

    @subsection groupmethods_sec Group Methods

    @page apiroot API Root

    The URLs for methods referred to in this API documentation are
    relative to the StatusNet API root. The API root is determined by the
    site's @b server and @b path variables, which are generally specified
    in config.php. For example:

    @code
    $config['site']['server'] = 'example.org';
    $config['site']['path'] = 'statusnet'
    @endcode

    The pattern for a site's API root is: @c protocol://server/path/api E.g:

    @c http://example.org/statusnet/api

    The @b path can be empty.  In that case the API root would simply be:

    @c http://example.org/api

*/

if (!defined('STATUSNET')) {
    exit(1);
}

class ApiValidationException extends Exception { }

/**
 * Contains most of the Twitter-compatible API output functions.
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Dan Moore <dan@moore.cx>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Toby Inkster <mail@tobyinkster.co.uk>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiAction extends Action
{
    const READ_ONLY  = 1;
    const READ_WRITE = 2;

    var $user      = null;
    var $auth_user = null;
    var $page      = null;
    var $count     = null;
    var $offset    = null;
    var $limit     = null;
    var $max_id    = null;
    var $since_id  = null;
    var $source    = null;
    var $callback  = null;
    var $format    = null;

    var $access    = self::READ_ONLY;  // read (default) or read-write

    static $reserved_sources = array('web', 'omb', 'ostatus', 'mail', 'xmpp', 'api');

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if user doesn't exist
     */
    protected function prepare(array $args=array())
    {
        GNUsocial::setApi(true); // reduce exception reports to aid in debugging
        parent::prepare($args);

        $this->format   = $this->arg('format');
        $this->callback = $this->arg('callback');
        $this->page     = (int)$this->arg('page', 1);
        $this->count    = (int)$this->arg('count', 20);
        $this->max_id   = (int)$this->arg('max_id', 0);
        $this->since_id = (int)$this->arg('since_id', 0);

        // These two are not used everywhere, mainly just AtompubAction extensions
        $this->offset   = ($this->page-1) * $this->count;
        $this->limit    = $this->count + 1;

        if ($this->arg('since')) {
            header('X-GNUsocial-Warning: since parameter is disabled; use since_id');
        }

        $this->source = $this->trimmed('source');

        if (empty($this->source) || in_array($this->source, self::$reserved_sources)) {
            $this->source = 'api';
        }

        return true;
    }

    /**
     * Handle a request
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return void
     */
    protected function handle()
    {
        header('Access-Control-Allow-Origin: *');
        parent::handle();
    }

    /**
     * Overrides XMLOutputter::element to write booleans as strings (true|false).
     * See that method's documentation for more info.
     *
     * @param string $tag     Element type or tagname
     * @param array  $attrs   Array of element attributes, as
     *                        key-value pairs
     * @param string $content string content of the element
     *
     * @return void
     */
    function element($tag, $attrs=null, $content=null)
    {
        if (is_bool($content)) {
            $content = ($content ? 'true' : 'false');
        }

        return parent::element($tag, $attrs, $content);
    }

    function twitterUserArray($profile, $get_notice=false)
    {
        $twitter_user = array();

        try {
            $user = $profile->getUser();
        } catch (NoSuchUserException $e) {
            $user = null;
        }

        $twitter_user['id'] = intval($profile->id);
        $twitter_user['name'] = $profile->getBestName();
        $twitter_user['screen_name'] = $profile->nickname;
        $twitter_user['location'] = ($profile->location) ? $profile->location : null;
        $twitter_user['description'] = ($profile->bio) ? $profile->bio : null;

        // TODO: avatar url template (example.com/user/avatar?size={x}x{y})
        $twitter_user['profile_image_url'] = Avatar::urlByProfile($profile, AVATAR_STREAM_SIZE);
        $twitter_user['profile_image_url_https'] = $twitter_user['profile_image_url'];

        // START introduced by qvitter API, not necessary for StatusNet API
        $twitter_user['profile_image_url_profile_size'] = Avatar::urlByProfile($profile, AVATAR_PROFILE_SIZE);
        try {
            $avatar  = Avatar::getUploaded($profile);
            $origurl = $avatar->displayUrl();
        } catch (Exception $e) {
            $origurl = $twitter_user['profile_image_url_profile_size'];
        }
        $twitter_user['profile_image_url_original'] = $origurl;

        $twitter_user['groups_count'] = $profile->getGroupCount();
        foreach (array('linkcolor', 'backgroundcolor') as $key) {
            $twitter_user[$key] = Profile_prefs::getConfigData($profile, 'theme', $key);
        }
        // END introduced by qvitter API, not necessary for StatusNet API

        $twitter_user['url'] = ($profile->homepage) ? $profile->homepage : null;
        $twitter_user['protected'] = (!empty($user) && $user->private_stream) ? true : false;
        $twitter_user['followers_count'] = $profile->subscriberCount();

        // Note: some profiles don't have an associated user

        $twitter_user['friends_count'] = $profile->subscriptionCount();

        $twitter_user['created_at'] = $this->dateTwitter($profile->created);

        $timezone = 'UTC';

        if (!empty($user) && $user->timezone) {
            $timezone = $user->timezone;
        }

        $t = new DateTime;
        $t->setTimezone(new DateTimeZone($timezone));

        $twitter_user['utc_offset'] = $t->format('Z');
        $twitter_user['time_zone'] = $timezone;
        $twitter_user['statuses_count'] = $profile->noticeCount();

        // Is the requesting user following this user?
        // These values might actually also mean "unknown". Ambiguity issues?
        $twitter_user['following'] = false;
        $twitter_user['statusnet_blocking'] = false;
        $twitter_user['notifications'] = false;

        if ($this->scoped instanceof Profile) {
            try {
                $sub = Subscription::getSubscription($this->scoped, $profile);
                // Notifications on?
                $twitter_user['following'] = true;
                $twitter_user['statusnet_blocking']  = $this->scoped->hasBlocked($profile);
                $twitter_user['notifications'] = ($sub->jabber || $sub->sms);
            } catch (NoResultException $e) {
                // well, the values are already false...
            }
        }

        if ($get_notice) {
            $notice = $profile->getCurrentNotice();
            if ($notice instanceof Notice) {
                // don't get user!
                $twitter_user['status'] = $this->twitterStatusArray($notice, false);
            }
        }

        // StatusNet-specific

        $twitter_user['statusnet_profile_url'] = $profile->profileurl;

        // The event call to handle NoticeSimpleStatusArray lets plugins add data to the output array
        Event::handle('TwitterUserArray', array($profile, &$twitter_user, $this->scoped, array()));

        return $twitter_user;
    }

    function twitterStatusArray($notice, $include_user=true)
    {
        $base = $this->twitterSimpleStatusArray($notice, $include_user);

        // FIXME: MOVE TO SHARE PLUGIN
        if (!empty($notice->repeat_of)) {
            $original = Notice::getKV('id', $notice->repeat_of);
            if ($original instanceof Notice) {
                $orig_array = $this->twitterSimpleStatusArray($original, $include_user);
                $base['retweeted_status'] = $orig_array;
            }
        }

        return $base;
    }

    function twitterSimpleStatusArray($notice, $include_user=true)
    {
        $profile = $notice->getProfile();

        $twitter_status = array();
        $twitter_status['text'] = $notice->content;
        $twitter_status['truncated'] = false; # Not possible on StatusNet
        $twitter_status['created_at'] = $this->dateTwitter($notice->created);
        try {
            // We could just do $notice->reply_to but maybe the future holds a
            // different story for parenting.
            $parent = $notice->getParent();
            $in_reply_to = $parent->id;
        } catch (Exception $e) {
            $in_reply_to = null;
        }
        $twitter_status['in_reply_to_status_id'] = $in_reply_to;

        $source = null;

        $ns = $notice->getSource();
        if ($ns instanceof Notice_source) {
            if (!empty($ns->name) && !empty($ns->url)) {
                $source = '<a href="'
		    . htmlspecialchars($ns->url)
		    . '" rel="nofollow">'
		    . htmlspecialchars($ns->name)
		    . '</a>';
            } else {
                $source = $ns->code;
            }
        }

        $twitter_status['uri'] = $notice->getUri();
        $twitter_status['source'] = $source;
        $twitter_status['id'] = intval($notice->id);

        $replier_profile = null;

        if ($notice->reply_to) {
            $reply = Notice::getKV(intval($notice->reply_to));
            if ($reply) {
                $replier_profile = $reply->getProfile();
            }
        }

        $twitter_status['in_reply_to_user_id'] =
            ($replier_profile) ? intval($replier_profile->id) : null;
        $twitter_status['in_reply_to_screen_name'] =
            ($replier_profile) ? $replier_profile->nickname : null;

        if (isset($notice->lat) && isset($notice->lon)) {
            // This is the format that GeoJSON expects stuff to be in
            $twitter_status['geo'] = array('type' => 'Point',
                                           'coordinates' => array((float) $notice->lat,
                                                                  (float) $notice->lon));
        } else {
            $twitter_status['geo'] = null;
        }

        // Enclosures
        $attachments = $notice->attachments();

        if (!empty($attachments)) {

            $twitter_status['attachments'] = array();

            foreach ($attachments as $attachment) {
                try {
                    $enclosure_o = $attachment->getEnclosure();
                    $enclosure = array();
                    $enclosure['url'] = $enclosure_o->url;
                    $enclosure['mimetype'] = $enclosure_o->mimetype;
                    $enclosure['size'] = $enclosure_o->size;
                    $twitter_status['attachments'][] = $enclosure;
                } catch (ServerException $e) {
                    // There was not enough metadata available
                }
            }
        }

        if ($include_user && $profile) {
            // Don't get notice (recursive!)
            $twitter_user = $this->twitterUserArray($profile, false);
            $twitter_status['user'] = $twitter_user;
        }

        // StatusNet-specific

        $twitter_status['statusnet_html'] = $notice->rendered;
        $twitter_status['statusnet_conversation_id'] = intval($notice->conversation);

        // The event call to handle NoticeSimpleStatusArray lets plugins add data to the output array
        Event::handle('NoticeSimpleStatusArray', array($notice, &$twitter_status, $this->scoped,
                                                       array('include_user'=>$include_user)));

        return $twitter_status;
    }

    function twitterGroupArray($group)
    {
        $twitter_group = array();

        $twitter_group['id'] = intval($group->id);
        $twitter_group['url'] = $group->permalink();
        $twitter_group['nickname'] = $group->nickname;
        $twitter_group['fullname'] = $group->fullname;

        if ($this->scoped instanceof Profile) {
            $twitter_group['member'] = $this->scoped->isMember($group);
            $twitter_group['blocked'] = Group_block::isBlocked(
                $group,
                $this->scoped
            );
        }

        $twitter_group['admin_count'] = $group->getAdminCount();
        $twitter_group['member_count'] = $group->getMemberCount();
        $twitter_group['original_logo'] = $group->original_logo;
        $twitter_group['homepage_logo'] = $group->homepage_logo;
        $twitter_group['stream_logo'] = $group->stream_logo;
        $twitter_group['mini_logo'] = $group->mini_logo;
        $twitter_group['homepage'] = $group->homepage;
        $twitter_group['description'] = $group->description;
        $twitter_group['location'] = $group->location;
        $twitter_group['created'] = $this->dateTwitter($group->created);
        $twitter_group['modified'] = $this->dateTwitter($group->modified);

        return $twitter_group;
    }

    function twitterRssGroupArray($group)
    {
        $entry = array();
        $entry['content']=$group->description;
        $entry['title']=$group->nickname;
        $entry['link']=$group->permalink();
        $entry['published']=common_date_iso8601($group->created);
        $entry['updated']==common_date_iso8601($group->modified);
        $taguribase = common_config('integration', 'groupuri');
        $entry['id'] = "group:$groupuribase:$entry[link]";

        $entry['description'] = $entry['content'];
        $entry['pubDate'] = common_date_rfc2822($group->created);
        $entry['guid'] = $entry['link'];

        return $entry;
    }

    function twitterListArray($list)
    {
        $profile = Profile::getKV('id', $list->tagger);

        $twitter_list = array();
        $twitter_list['id'] = $list->id;
        $twitter_list['name'] = $list->tag;
        $twitter_list['full_name'] = '@'.$profile->nickname.'/'.$list->tag;;
        $twitter_list['slug'] = $list->tag;
        $twitter_list['description'] = $list->description;
        $twitter_list['subscriber_count'] = $list->subscriberCount();
        $twitter_list['member_count'] = $list->taggedCount();
        $twitter_list['uri'] = $list->getUri();

        if ($this->scoped instanceof Profile) {
            $twitter_list['following'] = $list->hasSubscriber($this->scoped);
        } else {
            $twitter_list['following'] = false;
        }

        $twitter_list['mode'] = ($list->private) ? 'private' : 'public';
        $twitter_list['user'] = $this->twitterUserArray($profile, false);

        return $twitter_list;
    }

    function twitterRssEntryArray($notice)
    {
        $entry = array();

        if (Event::handle('StartRssEntryArray', array($notice, &$entry))) {
            $profile = $notice->getProfile();

            // We trim() to avoid extraneous whitespace in the output

            $entry['content'] = common_xml_safe_str(trim($notice->rendered));
            $entry['title'] = $profile->nickname . ': ' . common_xml_safe_str(trim($notice->content));
            $entry['link'] = common_local_url('shownotice', array('notice' => $notice->id));
            $entry['published'] = common_date_iso8601($notice->created);

            $taguribase = TagURI::base();
            $entry['id'] = "tag:$taguribase:$entry[link]";

            $entry['updated'] = $entry['published'];
            $entry['author'] = $profile->getBestName();

            // Enclosures
            $attachments = $notice->attachments();
            $enclosures = array();

            foreach ($attachments as $attachment) {
                try {
                    $enclosure_o = $attachment->getEnclosure();
                    $enclosure = array();
                    $enclosure['url'] = $enclosure_o->url;
                    $enclosure['mimetype'] = $enclosure_o->mimetype;
                    $enclosure['size'] = $enclosure_o->size;
                    $enclosures[] = $enclosure;
                } catch (ServerException $e) {
                    // There was not enough metadata available
                }
            }

            if (!empty($enclosures)) {
                $entry['enclosures'] = $enclosures;
            }

            // Tags/Categories
            $tag = new Notice_tag();
            $tag->notice_id = $notice->id;
            if ($tag->find()) {
                $entry['tags']=array();
                while ($tag->fetch()) {
                    $entry['tags'][]=$tag->tag;
                }
            }
            $tag->free();

            // RSS Item specific
            $entry['description'] = $entry['content'];
            $entry['pubDate'] = common_date_rfc2822($notice->created);
            $entry['guid'] = $entry['link'];

            if (isset($notice->lat) && isset($notice->lon)) {
                // This is the format that GeoJSON expects stuff to be in.
                // showGeoRSS() below uses it for XML output, so we reuse it
                $entry['geo'] = array('type' => 'Point',
                                      'coordinates' => array((float) $notice->lat,
                                                             (float) $notice->lon));
            } else {
                $entry['geo'] = null;
            }

            Event::handle('EndRssEntryArray', array($notice, &$entry));
        }

        return $entry;
    }

    function twitterRelationshipArray($source, $target)
    {
        $relationship = array();

        $relationship['source'] =
            $this->relationshipDetailsArray($source->getProfile(), $target->getProfile());
        $relationship['target'] =
            $this->relationshipDetailsArray($target->getProfile(), $source->getProfile());

        return array('relationship' => $relationship);
    }

    function relationshipDetailsArray(Profile $source, Profile $target)
    {
        $details = array();

        $details['screen_name'] = $source->getNickname();
        $details['followed_by'] = $target->isSubscribed($source);

        try {
            $sub = Subscription::getSubscription($source, $target);
            $details['following'] = true;
            $details['notifications_enabled'] = ($sub->jabber || $sub->sms);
        } catch (NoResultException $e) {
            $details['following'] = false;
            $details['notifications_enabled'] = false;
        }

        $details['blocking'] = $source->hasBlocked($target);
        $details['id'] = intval($source->id);

        return $details;
    }

    function showTwitterXmlRelationship($relationship)
    {
        $this->elementStart('relationship');

        foreach($relationship as $element => $value) {
            if ($element == 'source' || $element == 'target') {
                $this->elementStart($element);
                $this->showXmlRelationshipDetails($value);
                $this->elementEnd($element);
            }
        }

        $this->elementEnd('relationship');
    }

    function showXmlRelationshipDetails($details)
    {
        foreach($details as $element => $value) {
            $this->element($element, null, $value);
        }
    }

    function showTwitterXmlStatus($twitter_status, $tag='status', $namespaces=false)
    {
        $attrs = array();
        if ($namespaces) {
            $attrs['xmlns:statusnet'] = 'http://status.net/schema/api/1/';
        }
        $this->elementStart($tag, $attrs);
        foreach($twitter_status as $element => $value) {
            switch ($element) {
            case 'user':
                $this->showTwitterXmlUser($twitter_status['user']);
                break;
            case 'text':
                $this->element($element, null, common_xml_safe_str($value));
                break;
            case 'attachments':
                $this->showXmlAttachments($twitter_status['attachments']);
                break;
            case 'geo':
                $this->showGeoXML($value);
                break;
            case 'retweeted_status':
                // FIXME: MOVE TO SHARE PLUGIN
                $this->showTwitterXmlStatus($value, 'retweeted_status');
                break;
            default:
                if (strncmp($element, 'statusnet_', 10) == 0) {
                    $this->element('statusnet:'.substr($element, 10), null, $value);
                } else {
                    $this->element($element, null, $value);
                }
            }
        }
        $this->elementEnd($tag);
    }

    function showTwitterXmlGroup($twitter_group)
    {
        $this->elementStart('group');
        foreach($twitter_group as $element => $value) {
            $this->element($element, null, $value);
        }
        $this->elementEnd('group');
    }

    function showTwitterXmlList($twitter_list)
    {
        $this->elementStart('list');
        foreach($twitter_list as $element => $value) {
            if($element == 'user') {
                $this->showTwitterXmlUser($value, 'user');
            }
            else {
                $this->element($element, null, $value);
            }
        }
        $this->elementEnd('list');
    }

    function showTwitterXmlUser($twitter_user, $role='user', $namespaces=false)
    {
        $attrs = array();
        if ($namespaces) {
            $attrs['xmlns:statusnet'] = 'http://status.net/schema/api/1/';
        }
        $this->elementStart($role, $attrs);
        foreach($twitter_user as $element => $value) {
            if ($element == 'status') {
                $this->showTwitterXmlStatus($twitter_user['status']);
            } else if (strncmp($element, 'statusnet_', 10) == 0) {
                $this->element('statusnet:'.substr($element, 10), null, $value);
            } else {
                $this->element($element, null, $value);
            }
        }
        $this->elementEnd($role);
    }

    function showXmlAttachments($attachments) {
        if (!empty($attachments)) {
            $this->elementStart('attachments', array('type' => 'array'));
            foreach ($attachments as $attachment) {
                $attrs = array();
                $attrs['url'] = $attachment['url'];
                $attrs['mimetype'] = $attachment['mimetype'];
                $attrs['size'] = $attachment['size'];
                $this->element('enclosure', $attrs, '');
            }
            $this->elementEnd('attachments');
        }
    }

    function showGeoXML($geo)
    {
        if (empty($geo)) {
            // empty geo element
            $this->element('geo');
        } else {
            $this->elementStart('geo', array('xmlns:georss' => 'http://www.georss.org/georss'));
            $this->element('georss:point', null, $geo['coordinates'][0] . ' ' . $geo['coordinates'][1]);
            $this->elementEnd('geo');
        }
    }

    function showGeoRSS($geo)
    {
        if (!empty($geo)) {
            $this->element(
                'georss:point',
                null,
                $geo['coordinates'][0] . ' ' . $geo['coordinates'][1]
            );
        }
    }

    function showTwitterRssItem($entry)
    {
        $this->elementStart('item');
        $this->element('title', null, $entry['title']);
        $this->element('description', null, $entry['description']);
        $this->element('pubDate', null, $entry['pubDate']);
        $this->element('guid', null, $entry['guid']);
        $this->element('link', null, $entry['link']);

        // RSS only supports 1 enclosure per item
        if(array_key_exists('enclosures', $entry) and !empty($entry['enclosures'])){
            $enclosure = $entry['enclosures'][0];
            $this->element('enclosure', array('url'=>$enclosure['url'],'type'=>$enclosure['mimetype'],'length'=>$enclosure['size']), null);
        }

        if(array_key_exists('tags', $entry)){
            foreach($entry['tags'] as $tag){
                $this->element('category', null,$tag);
            }
        }

        $this->showGeoRSS($entry['geo']);
        $this->elementEnd('item');
    }

    function showJsonObjects($objects)
    {
        print(json_encode($objects));
    }

    function showSingleXmlStatus($notice)
    {
        $this->initDocument('xml');
        $twitter_status = $this->twitterStatusArray($notice);
        $this->showTwitterXmlStatus($twitter_status, 'status', true);
        $this->endDocument('xml');
    }

    function showSingleAtomStatus($notice)
    {
        header('Content-Type: application/atom+xml; charset=utf-8');
        print $notice->asAtomEntry(true, true, true, $this->scoped);
    }

    function show_single_json_status($notice)
    {
        $this->initDocument('json');
        $status = $this->twitterStatusArray($notice);
        $this->showJsonObjects($status);
        $this->endDocument('json');
    }

    function showXmlTimeline($notice)
    {
        $this->initDocument('xml');
        $this->elementStart('statuses', array('type' => 'array',
                                              'xmlns:statusnet' => 'http://status.net/schema/api/1/'));

        if (is_array($notice)) {
            $notice = new ArrayWrapper($notice);
        }

        while ($notice->fetch()) {
            try {
                $twitter_status = $this->twitterStatusArray($notice);
                $this->showTwitterXmlStatus($twitter_status);
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
        }

        $this->elementEnd('statuses');
        $this->endDocument('xml');
    }

    function showRssTimeline($notice, $title, $link, $subtitle, $suplink = null, $logo = null, $self = null)
    {
        $this->initDocument('rss');

        $this->element('title', null, $title);
        $this->element('link', null, $link);

        if (!is_null($self)) {
            $this->element(
                'atom:link',
                array(
                    'type' => 'application/rss+xml',
                    'href' => $self,
                    'rel'  => 'self'
                )
           );
        }

        if (!is_null($suplink)) {
            // For FriendFeed's SUP protocol
            $this->element('link', array('xmlns' => 'http://www.w3.org/2005/Atom',
                                         'rel' => 'http://api.friendfeed.com/2008/03#sup',
                                         'href' => $suplink,
                                         'type' => 'application/json'));
        }

        if (!is_null($logo)) {
            $this->elementStart('image');
            $this->element('link', null, $link);
            $this->element('title', null, $title);
            $this->element('url', null, $logo);
            $this->elementEnd('image');
        }

        $this->element('description', null, $subtitle);
        $this->element('language', null, 'en-us');
        $this->element('ttl', null, '40');

        if (is_array($notice)) {
            $notice = new ArrayWrapper($notice);
        }

        while ($notice->fetch()) {
            try {
                $entry = $this->twitterRssEntryArray($notice);
                $this->showTwitterRssItem($entry);
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                // continue on exceptions
            }
        }

        $this->endTwitterRss();
    }

    function showAtomTimeline($notice, $title, $id, $link, $subtitle=null, $suplink=null, $selfuri=null, $logo=null)
    {
        $this->initDocument('atom');

        $this->element('title', null, $title);
        $this->element('id', null, $id);
        $this->element('link', array('href' => $link, 'rel' => 'alternate', 'type' => 'text/html'), null);

        if (!is_null($logo)) {
            $this->element('logo',null,$logo);
        }

        if (!is_null($suplink)) {
            // For FriendFeed's SUP protocol
            $this->element('link', array('rel' => 'http://api.friendfeed.com/2008/03#sup',
                                         'href' => $suplink,
                                         'type' => 'application/json'));
        }

        if (!is_null($selfuri)) {
            $this->element('link', array('href' => $selfuri,
                'rel' => 'self', 'type' => 'application/atom+xml'), null);
        }

        $this->element('updated', null, common_date_iso8601('now'));
        $this->element('subtitle', null, $subtitle);

        if (is_array($notice)) {
            $notice = new ArrayWrapper($notice);
        }

        while ($notice->fetch()) {
            try {
                $this->raw($notice->asAtomEntry());
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
        }

        $this->endDocument('atom');
    }

    function showRssGroups($group, $title, $link, $subtitle)
    {
        $this->initDocument('rss');

        $this->element('title', null, $title);
        $this->element('link', null, $link);
        $this->element('description', null, $subtitle);
        $this->element('language', null, 'en-us');
        $this->element('ttl', null, '40');

        if (is_array($group)) {
            foreach ($group as $g) {
                $twitter_group = $this->twitterRssGroupArray($g);
                $this->showTwitterRssItem($twitter_group);
            }
        } else {
            while ($group->fetch()) {
                $twitter_group = $this->twitterRssGroupArray($group);
                $this->showTwitterRssItem($twitter_group);
            }
        }

        $this->endTwitterRss();
    }

    function showTwitterAtomEntry($entry)
    {
        $this->elementStart('entry');
        $this->element('title', null, common_xml_safe_str($entry['title']));
        $this->element(
            'content',
            array('type' => 'html'),
            common_xml_safe_str($entry['content'])
        );
        $this->element('id', null, $entry['id']);
        $this->element('published', null, $entry['published']);
        $this->element('updated', null, $entry['updated']);
        $this->element('link', array('type' => 'text/html',
                                     'href' => $entry['link'],
                                     'rel' => 'alternate'));
        $this->element('link', array('type' => $entry['avatar-type'],
                                     'href' => $entry['avatar'],
                                     'rel' => 'image'));
        $this->elementStart('author');

        $this->element('name', null, $entry['author-name']);
        $this->element('uri', null, $entry['author-uri']);

        $this->elementEnd('author');
        $this->elementEnd('entry');
    }

    function showAtomGroups($group, $title, $id, $link, $subtitle=null, $selfuri=null)
    {
        $this->initDocument('atom');

        $this->element('title', null, common_xml_safe_str($title));
        $this->element('id', null, $id);
        $this->element('link', array('href' => $link, 'rel' => 'alternate', 'type' => 'text/html'), null);

        if (!is_null($selfuri)) {
            $this->element('link', array('href' => $selfuri,
                'rel' => 'self', 'type' => 'application/atom+xml'), null);
        }

        $this->element('updated', null, common_date_iso8601('now'));
        $this->element('subtitle', null, common_xml_safe_str($subtitle));

        if (is_array($group)) {
            foreach ($group as $g) {
                $this->raw($g->asAtomEntry());
            }
        } else {
            while ($group->fetch()) {
                $this->raw($group->asAtomEntry());
            }
        }

        $this->endDocument('atom');

    }

    function showJsonTimeline($notice)
    {
        $this->initDocument('json');

        $statuses = array();

        if (is_array($notice)) {
            $notice = new ArrayWrapper($notice);
        }

        while ($notice->fetch()) {
            try {
                $twitter_status = $this->twitterStatusArray($notice);
                array_push($statuses, $twitter_status);
            } catch (Exception $e) {
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
        }

        $this->showJsonObjects($statuses);

        $this->endDocument('json');
    }

    function showJsonGroups($group)
    {
        $this->initDocument('json');

        $groups = array();

        if (is_array($group)) {
            foreach ($group as $g) {
                $twitter_group = $this->twitterGroupArray($g);
                array_push($groups, $twitter_group);
            }
        } else {
            while ($group->fetch()) {
                $twitter_group = $this->twitterGroupArray($group);
                array_push($groups, $twitter_group);
            }
        }

        $this->showJsonObjects($groups);

        $this->endDocument('json');
    }

    function showXmlGroups($group)
    {

        $this->initDocument('xml');
        $this->elementStart('groups', array('type' => 'array'));

        if (is_array($group)) {
            foreach ($group as $g) {
                $twitter_group = $this->twitterGroupArray($g);
                $this->showTwitterXmlGroup($twitter_group);
            }
        } else {
            while ($group->fetch()) {
                $twitter_group = $this->twitterGroupArray($group);
                $this->showTwitterXmlGroup($twitter_group);
            }
        }

        $this->elementEnd('groups');
        $this->endDocument('xml');
    }

    function showXmlLists($list, $next_cursor=0, $prev_cursor=0)
    {

        $this->initDocument('xml');
        $this->elementStart('lists_list');
        $this->elementStart('lists', array('type' => 'array'));

        if (is_array($list)) {
            foreach ($list as $l) {
                $twitter_list = $this->twitterListArray($l);
                $this->showTwitterXmlList($twitter_list);
            }
        } else {
            while ($list->fetch()) {
                $twitter_list = $this->twitterListArray($list);
                $this->showTwitterXmlList($twitter_list);
            }
        }

        $this->elementEnd('lists');

        $this->element('next_cursor', null, $next_cursor);
        $this->element('previous_cursor', null, $prev_cursor);

        $this->elementEnd('lists_list');
        $this->endDocument('xml');
    }

    function showJsonLists($list, $next_cursor=0, $prev_cursor=0)
    {
        $this->initDocument('json');

        $lists = array();

        if (is_array($list)) {
            foreach ($list as $l) {
                $twitter_list = $this->twitterListArray($l);
                array_push($lists, $twitter_list);
            }
        } else {
            while ($list->fetch()) {
                $twitter_list = $this->twitterListArray($list);
                array_push($lists, $twitter_list);
            }
        }

        $lists_list = array(
            'lists' => $lists,
            'next_cursor' => $next_cursor,
            'next_cursor_str' => strval($next_cursor),
            'previous_cursor' => $prev_cursor,
            'previous_cursor_str' => strval($prev_cursor)
        );

        $this->showJsonObjects($lists_list);

        $this->endDocument('json');
    }

    function showTwitterXmlUsers($user)
    {
        $this->initDocument('xml');
        $this->elementStart('users', array('type' => 'array',
                                           'xmlns:statusnet' => 'http://status.net/schema/api/1/'));

        if (is_array($user)) {
            foreach ($user as $u) {
                $twitter_user = $this->twitterUserArray($u);
                $this->showTwitterXmlUser($twitter_user);
            }
        } else {
            while ($user->fetch()) {
                $twitter_user = $this->twitterUserArray($user);
                $this->showTwitterXmlUser($twitter_user);
            }
        }

        $this->elementEnd('users');
        $this->endDocument('xml');
    }

    function showJsonUsers($user)
    {
        $this->initDocument('json');

        $users = array();

        if (is_array($user)) {
            foreach ($user as $u) {
                $twitter_user = $this->twitterUserArray($u);
                array_push($users, $twitter_user);
            }
        } else {
            while ($user->fetch()) {
                $twitter_user = $this->twitterUserArray($user);
                array_push($users, $twitter_user);
            }
        }

        $this->showJsonObjects($users);

        $this->endDocument('json');
    }

    function showSingleJsonGroup($group)
    {
        $this->initDocument('json');
        $twitter_group = $this->twitterGroupArray($group);
        $this->showJsonObjects($twitter_group);
        $this->endDocument('json');
    }

    function showSingleXmlGroup($group)
    {
        $this->initDocument('xml');
        $twitter_group = $this->twitterGroupArray($group);
        $this->showTwitterXmlGroup($twitter_group);
        $this->endDocument('xml');
    }

    function showSingleJsonList($list)
    {
        $this->initDocument('json');
        $twitter_list = $this->twitterListArray($list);
        $this->showJsonObjects($twitter_list);
        $this->endDocument('json');
    }

    function showSingleXmlList($list)
    {
        $this->initDocument('xml');
        $twitter_list = $this->twitterListArray($list);
        $this->showTwitterXmlList($twitter_list);
        $this->endDocument('xml');
    }

    function dateTwitter($dt)
    {
        $dateStr = date('d F Y H:i:s', strtotime($dt));
        $d = new DateTime($dateStr, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone(common_timezone()));
        return $d->format('D M d H:i:s O Y');
    }

    function initDocument($type='xml')
    {
        switch ($type) {
        case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            $this->startXML();
            break;
        case 'json':
            header('Content-Type: application/json; charset=utf-8');

            // Check for JSONP callback
            if (isset($this->callback)) {
                print $this->callback . '(';
            }
            break;
        case 'rss':
            header("Content-Type: application/rss+xml; charset=utf-8");
            $this->initTwitterRss();
            break;
        case 'atom':
            header('Content-Type: application/atom+xml; charset=utf-8');
            $this->initTwitterAtom();
            break;
        default:
            // TRANS: Client error on an API request with an unsupported data format.
            $this->clientError(_('Not a supported data format.'));
        }

        return;
    }

    function endDocument($type='xml')
    {
        switch ($type) {
        case 'xml':
            $this->endXML();
            break;
        case 'json':
            // Check for JSONP callback
            if (isset($this->callback)) {
                print ')';
            }
            break;
        case 'rss':
            $this->endTwitterRss();
            break;
        case 'atom':
            $this->endTwitterRss();
            break;
        default:
            // TRANS: Client error on an API request with an unsupported data format.
            $this->clientError(_('Not a supported data format.'));
        }
        return;
    }

    function initTwitterRss()
    {
        $this->startXML();
        $this->elementStart(
            'rss',
            array(
                'version'      => '2.0',
                'xmlns:atom'   => 'http://www.w3.org/2005/Atom',
                'xmlns:georss' => 'http://www.georss.org/georss'
            )
        );
        $this->elementStart('channel');
        Event::handle('StartApiRss', array($this));
    }

    function endTwitterRss()
    {
        $this->elementEnd('channel');
        $this->elementEnd('rss');
        $this->endXML();
    }

    function initTwitterAtom()
    {
        $this->startXML();
        // FIXME: don't hardcode the language here!
        $this->elementStart('feed', array('xmlns' => 'http://www.w3.org/2005/Atom',
                                          'xml:lang' => 'en-US',
                                          'xmlns:thr' => 'http://purl.org/syndication/thread/1.0'));
    }

    function endTwitterAtom()
    {
        $this->elementEnd('feed');
        $this->endXML();
    }

    function showProfile($profile, $content_type='xml', $notice=null, $includeStatuses=true)
    {
        $profile_array = $this->twitterUserArray($profile, $includeStatuses);
        switch ($content_type) {
        case 'xml':
            $this->showTwitterXmlUser($profile_array);
            break;
        case 'json':
            $this->showJsonObjects($profile_array);
            break;
        default:
            // TRANS: Client error on an API request with an unsupported data format.
            $this->clientError(_('Not a supported data format.'));
        }
        return;
    }

    private static function is_decimal($str)
    {
        return preg_match('/^[0-9]+$/', $str);
    }

    function getTargetUser($id)
    {
        if (empty($id)) {
            // Twitter supports these other ways of passing the user ID
            if (self::is_decimal($this->arg('id'))) {
                return User::getKV($this->arg('id'));
            } else if ($this->arg('id')) {
                $nickname = common_canonical_nickname($this->arg('id'));
                return User::getKV('nickname', $nickname);
            } else if ($this->arg('user_id')) {
                // This is to ensure that a non-numeric user_id still
                // overrides screen_name even if it doesn't get used
                if (self::is_decimal($this->arg('user_id'))) {
                    return User::getKV('id', $this->arg('user_id'));
                }
            } else if ($this->arg('screen_name')) {
                $nickname = common_canonical_nickname($this->arg('screen_name'));
                return User::getKV('nickname', $nickname);
            } else {
                // Fall back to trying the currently authenticated user
                return $this->scoped->getUser();
            }

        } else if (self::is_decimal($id)) {
            return User::getKV($id);
        } else {
            $nickname = common_canonical_nickname($id);
            return User::getKV('nickname', $nickname);
        }
    }

    function getTargetProfile($id)
    {
        if (empty($id)) {

            // Twitter supports these other ways of passing the user ID
            if (self::is_decimal($this->arg('id'))) {
                return Profile::getKV($this->arg('id'));
            } else if ($this->arg('id')) {
                // Screen names currently can only uniquely identify a local user.
                $nickname = common_canonical_nickname($this->arg('id'));
                $user = User::getKV('nickname', $nickname);
                return $user ? $user->getProfile() : null;
            } else if ($this->arg('user_id')) {
                // This is to ensure that a non-numeric user_id still
                // overrides screen_name even if it doesn't get used
                if (self::is_decimal($this->arg('user_id'))) {
                    return Profile::getKV('id', $this->arg('user_id'));
                }
            } else if ($this->arg('screen_name')) {
                $nickname = common_canonical_nickname($this->arg('screen_name'));
                $user = User::getKV('nickname', $nickname);
                return $user instanceof User ? $user->getProfile() : null;
            } else {
                // Fall back to trying the currently authenticated user
                return $this->scoped;
            }
        } else if (self::is_decimal($id)) {
            return Profile::getKV($id);
        } else {
            $nickname = common_canonical_nickname($id);
            $user = User::getKV('nickname', $nickname);
            return $user ? $user->getProfile() : null;
        }
    }

    function getTargetGroup($id)
    {
        if (empty($id)) {
            if (self::is_decimal($this->arg('id'))) {
                return User_group::getKV('id', $this->arg('id'));
            } else if ($this->arg('id')) {
                return User_group::getForNickname($this->arg('id'));
            } else if ($this->arg('group_id')) {
                // This is to ensure that a non-numeric group_id still
                // overrides group_name even if it doesn't get used
                if (self::is_decimal($this->arg('group_id'))) {
                    return User_group::getKV('id', $this->arg('group_id'));
                }
            } else if ($this->arg('group_name')) {
                return User_group::getForNickname($this->arg('group_name'));
            }

        } else if (self::is_decimal($id)) {
            return User_group::getKV('id', $id);
        } else if ($this->arg('uri')) { // FIXME: move this into empty($id) check?
            return User_group::getKV('uri', urldecode($this->arg('uri')));
        } else {
            return User_group::getForNickname($id);
        }
    }

    function getTargetList($user=null, $id=null)
    {
        $tagger = $this->getTargetUser($user);
        $list = null;

        if (empty($id)) {
            $id = $this->arg('id');
        }

        if($id) {
            if (is_numeric($id)) {
                $list = Profile_list::getKV('id', $id);

                // only if the list with the id belongs to the tagger
                if(empty($list) || $list->tagger != $tagger->id) {
                    $list = null;
                }
            }
            if (empty($list)) {
                $tag = common_canonical_tag($id);
                $list = Profile_list::getByTaggerAndTag($tagger->id, $tag);
            }

            if (!empty($list) && $list->private) {
                if ($this->scoped->id == $list->tagger) {
                    return $list;
                }
            } else {
                return $list;
            }
        }
        return null;
    }

    /**
     * Returns query argument or default value if not found. Certain
     * parameters used throughout the API are lightly scrubbed and
     * bounds checked.  This overrides Action::arg().
     *
     * @param string $key requested argument
     * @param string $def default value to return if $key is not provided
     *
     * @return var $var
     */
    function arg($key, $def=null)
    {
        // XXX: Do even more input validation/scrubbing?

        if (array_key_exists($key, $this->args)) {
            switch($key) {
            case 'page':
                $page = (int)$this->args['page'];
                return ($page < 1) ? 1 : $page;
            case 'count':
                $count = (int)$this->args['count'];
                if ($count < 1) {
                    return 20;
                } elseif ($count > 200) {
                    return 200;
                } else {
                    return $count;
                }
            case 'since_id':
                $since_id = (int)$this->args['since_id'];
                return ($since_id < 1) ? 0 : $since_id;
            case 'max_id':
                $max_id = (int)$this->args['max_id'];
                return ($max_id < 1) ? 0 : $max_id;
            default:
                return parent::arg($key, $def);
            }
        } else {
            return $def;
        }
    }

    /**
     * Calculate the complete URI that called up this action.  Used for
     * Atom rel="self" links.  Warning: this is funky.
     *
     * @return string URL    a URL suitable for rel="self" Atom links
     */
    function getSelfUri()
    {
        $action = mb_substr(get_class($this), 0, -6); // remove 'Action'

        $id = $this->arg('id');
        $aargs = array('format' => $this->format);
        if (!empty($id)) {
            $aargs['id'] = $id;
        }

        $user = $this->arg('user');
        if (!empty($user)) {
            $aargs['user'] = $user;
        }

        $tag = $this->arg('tag');
        if (!empty($tag)) {
            $aargs['tag'] = $tag;
        }

        parse_str($_SERVER['QUERY_STRING'], $params);
        $pstring = '';
        if (!empty($params)) {
            unset($params['p']);
            $pstring = http_build_query($params);
        }

        $uri = common_local_url($action, $aargs);

        if (!empty($pstring)) {
            $uri .= '?' . $pstring;
        }

        return $uri;
    }
}
