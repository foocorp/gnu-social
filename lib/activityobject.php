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
 * A noun-ish thing in the activity universe
 *
 * The activity streams spec talks about activity objects, while also having
 * a tag activity:object, which is in fact an activity object. Aaaaaah!
 *
 * This is just a thing in the activity universe. Can be the subject, object,
 * or indirect object (target!) of an activity verb. Rotten name, and I'm
 * propagating it. *sigh*
 *
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */
class ActivityObject
{
    const ARTICLE   = 'http://activitystrea.ms/schema/1.0/article';
    const BLOGENTRY = 'http://activitystrea.ms/schema/1.0/blog-entry';
    const NOTE      = 'http://activitystrea.ms/schema/1.0/note';
    const STATUS    = 'http://activitystrea.ms/schema/1.0/status';
    const FILE      = 'http://activitystrea.ms/schema/1.0/file';
    const PHOTO     = 'http://activitystrea.ms/schema/1.0/photo';
    const ALBUM     = 'http://activitystrea.ms/schema/1.0/photo-album';
    const PLAYLIST  = 'http://activitystrea.ms/schema/1.0/playlist';
    const VIDEO     = 'http://activitystrea.ms/schema/1.0/video';
    const AUDIO     = 'http://activitystrea.ms/schema/1.0/audio';
    const BOOKMARK  = 'http://activitystrea.ms/schema/1.0/bookmark';
    const PERSON    = 'http://activitystrea.ms/schema/1.0/person';
    const GROUP     = 'http://activitystrea.ms/schema/1.0/group';
    const PLACE     = 'http://activitystrea.ms/schema/1.0/place';
    const COMMENT   = 'http://activitystrea.ms/schema/1.0/comment';
    // ^^^^^^^^^^ tea!

    // Atom elements we snarf

    const TITLE   = 'title';
    const SUMMARY = 'summary';
    const ID      = 'id';
    const SOURCE  = 'source';

    const NAME  = 'name';
    const URI   = 'uri';
    const EMAIL = 'email';

    const POSTEROUS   = 'http://posterous.com/help/rss/1.0';
    const AUTHOR      = 'author';
    const USERIMAGE   = 'userImage';
    const PROFILEURL  = 'profileUrl';
    const NICKNAME    = 'nickName';
    const DISPLAYNAME = 'displayName';

    public $element;
    public $type;
    public $id;
    public $title;
    public $summary;
    public $content;
    public $link;
    public $source;
    public $avatarLinks = array();
    public $geopoint;
    public $poco;
    public $displayName;

    // @todo move this stuff to it's own PHOTO activity object
    const MEDIA_DESCRIPTION = 'description';

    public $thumbnail;
    public $largerImage;
    public $description;

    /**
     * Constructor
     *
     * This probably needs to be refactored
     * to generate a local class (ActivityPerson, ActivityFile, ...)
     * based on the object type.
     *
     * @param DOMElement $element DOM thing to turn into an Activity thing
     */
    function __construct($element = null)
    {
        if (empty($element)) {
            return;
        }

        $this->element = $element;

        $this->geopoint = $this->_childContent(
            $element,
            ActivityContext::POINT,
            ActivityContext::GEORSS
        );

        if ($element->tagName == 'author') {
            $this->_fromAuthor($element);
        } else if ($element->tagName == 'item') {
            $this->_fromRssItem($element);
        } else {
            $this->_fromAtomEntry($element);
        }

        // Some per-type attributes...
        if ($this->type == self::PERSON || $this->type == self::GROUP) {
            $this->displayName = $this->title;

            $photos = ActivityUtils::getLinks($element, 'photo');
            if (count($photos)) {
                foreach ($photos as $link) {
                    $this->avatarLinks[] = new AvatarLink($link);
                }
            } else {
                $avatars = ActivityUtils::getLinks($element, 'avatar');
                foreach ($avatars as $link) {
                    $this->avatarLinks[] = new AvatarLink($link);
                }
            }

            $this->poco = new PoCo($element);
        }

        if ($this->type == self::PHOTO) {

            $this->thumbnail   = ActivityUtils::getLink($element, 'preview');
            $this->largerImage = ActivityUtils::getLink($element, 'enclosure');

            $this->description = ActivityUtils::childContent(
                $element,
                ActivityObject::MEDIA_DESCRIPTION,
                Activity::MEDIA
            );
        }
    }

    private function _fromAuthor($element)
    {
        $this->type  = self::PERSON; // XXX: is this fair?
        $this->title = $this->_childContent($element, self::NAME);

        $this->id = $this->_childContent($element, self::URI);

        if (empty($this->id)) {
            $email = $this->_childContent($element, self::EMAIL);
            if (!empty($email)) {
                // XXX: acct: ?
                $this->id = 'mailto:'.$email;
            }
        }
    }

    private function _fromAtomEntry($element)
    {
        $this->type = $this->_childContent($element, Activity::OBJECTTYPE,
                                           Activity::SPEC);

        if (empty($this->type)) {
            $this->type = ActivityObject::NOTE;
        }

        $this->summary = ActivityUtils::childHtmlContent($element, self::SUMMARY);
        $this->content = ActivityUtils::getContent($element);

        // We don't like HTML in our titles, although it's technically allowed

        $title = ActivityUtils::childHtmlContent($element, self::TITLE);

        $this->title = html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8');

        $this->source  = $this->_getSource($element);

        $this->link = ActivityUtils::getPermalink($element);

        $this->id = $this->_childContent($element, self::ID);

        if (empty($this->id) && !empty($this->link)) { // fallback if there's no ID
            $this->id = $this->link;
        }
    }

    // @todo FIXME: rationalize with Activity::_fromRssItem()
    private function _fromRssItem($item)
    {
        $this->title = ActivityUtils::childContent($item, ActivityObject::TITLE, Activity::RSS);

        $contentEl = ActivityUtils::child($item, ActivityUtils::CONTENT, Activity::CONTENTNS);

        if (!empty($contentEl)) {
            $this->content = htmlspecialchars_decode($contentEl->textContent, ENT_QUOTES);
        } else {
            $descriptionEl = ActivityUtils::child($item, Activity::DESCRIPTION, Activity::RSS);
            if (!empty($descriptionEl)) {
                $this->content = htmlspecialchars_decode($descriptionEl->textContent, ENT_QUOTES);
            }
        }

        $this->link = ActivityUtils::childContent($item, ActivityUtils::LINK, Activity::RSS);

        $guidEl = ActivityUtils::child($item, Activity::GUID, Activity::RSS);

        if (!empty($guidEl)) {
            $this->id = $guidEl->textContent;

            if ($guidEl->hasAttribute('isPermaLink')) {
                // overwrites <link>
                $this->link = $this->id;
            }
        }
    }

    public static function fromRssAuthor($el)
    {
        $text = $el->textContent;

        if (preg_match('/^(.*?) \((.*)\)$/', $text, $match)) {
            $email = $match[1];
            $name = $match[2];
        } else if (preg_match('/^(.*?) <(.*)>$/', $text, $match)) {
            $name = $match[1];
            $email = $match[2];
        } else if (preg_match('/.*@.*/', $text)) {
            $email = $text;
            $name = null;
        } else {
            $name = $text;
            $email = null;
        }

        // Not really enough info

        $obj = new ActivityObject();

        $obj->element = $el;

        $obj->type  = ActivityObject::PERSON;
        $obj->title = $name;

        if (!empty($email)) {
            $obj->id = 'mailto:'.$email;
        }

        return $obj;
    }

    public static function fromDcCreator($el)
    {
        // Not really enough info

        $text = $el->textContent;

        $obj = new ActivityObject();

        $obj->element = $el;

        $obj->title = $text;
        $obj->type  = ActivityObject::PERSON;

        return $obj;
    }

    public static function fromRssChannel($el)
    {
        $obj = new ActivityObject();

        $obj->element = $el;

        $obj->type = ActivityObject::PERSON; // @fixme guess better

        $obj->title = ActivityUtils::childContent($el, ActivityObject::TITLE, Activity::RSS);
        $obj->link  = ActivityUtils::childContent($el, ActivityUtils::LINK, Activity::RSS);
        $obj->id    = ActivityUtils::getLink($el, Activity::SELF);

        if (empty($obj->id)) {
            $obj->id = $obj->link;
        }

        $desc = ActivityUtils::childContent($el, Activity::DESCRIPTION, Activity::RSS);

        if (!empty($desc)) {
            $obj->content = htmlspecialchars_decode($desc, ENT_QUOTES);
        }

        $imageEl = ActivityUtils::child($el, Activity::IMAGE, Activity::RSS);

        if (!empty($imageEl)) {
            $url = ActivityUtils::childContent($imageEl, Activity::URL, Activity::RSS);
            $al = new AvatarLink();
            $al->url = $url;
            $obj->avatarLinks[] = $al;
        }

        return $obj;
    }

    public static function fromPosterousAuthor($el)
    {
        $obj = new ActivityObject();

        $obj->type = ActivityObject::PERSON; // @fixme any others...?

        $userImage = ActivityUtils::childContent($el, self::USERIMAGE, self::POSTEROUS);

        if (!empty($userImage)) {
            $al = new AvatarLink();
            $al->url = $userImage;
            $obj->avatarLinks[] = $al;
        }

        $obj->link = ActivityUtils::childContent($el, self::PROFILEURL, self::POSTEROUS);
        $obj->id   = $obj->link;

        $obj->poco = new PoCo();

        $obj->poco->preferredUsername = ActivityUtils::childContent($el, self::NICKNAME, self::POSTEROUS);
        $obj->poco->displayName       = ActivityUtils::childContent($el, self::DISPLAYNAME, self::POSTEROUS);

        $obj->title = $obj->poco->displayName;

        return $obj;
    }

    private function _childContent($element, $tag, $namespace=ActivityUtils::ATOM)
    {
        return ActivityUtils::childContent($element, $tag, $namespace);
    }

    // Try to get a unique id for the source feed

    private function _getSource($element)
    {
        $sourceEl = ActivityUtils::child($element, 'source');

        if (empty($sourceEl)) {
            return null;
        } else {
            $href = ActivityUtils::getLink($sourceEl, 'self');
            if (!empty($href)) {
                return $href;
            } else {
                return ActivityUtils::childContent($sourceEl, 'id');
            }
        }
    }

    static function fromNotice(Notice $notice)
    {
        $object = new ActivityObject();

        $object->type    = ActivityObject::NOTE;

        $object->id      = $notice->uri;
        $object->title   = $notice->content;
        $object->content = $notice->rendered;
        $object->link    = $notice->bestUrl();

        return $object;
    }

    static function fromProfile(Profile $profile)
    {
        $object = new ActivityObject();

        $object->type   = ActivityObject::PERSON;
        $object->id     = $profile->getUri();
        $object->title  = $profile->getBestName();
        $object->link   = $profile->profileurl;

        $orig = $profile->getOriginalAvatar();

        if (!empty($orig)) {
            $object->avatarLinks[] = AvatarLink::fromAvatar($orig);
        }

        $sizes = array(
            AVATAR_PROFILE_SIZE,
            AVATAR_STREAM_SIZE,
            AVATAR_MINI_SIZE
        );

        foreach ($sizes as $size) {
            $alink  = null;
            $avatar = $profile->getAvatar($size);

            if (!empty($avatar)) {
                $alink = AvatarLink::fromAvatar($avatar);
            } else {
                $alink = new AvatarLink();
                $alink->type   = 'image/png';
                $alink->height = $size;
                $alink->width  = $size;
                $alink->url    = Avatar::defaultImage($size);

                if ($size == AVATAR_PROFILE_SIZE) {
                    // Hack for Twitter import: we don't have a 96x96 image,
                    // but we do have a 73x73 image. For now, fake it with that.
                    $avatar = $profile->getAvatar(73);
                    if ($avatar) {
                        $alink = AvatarLink::fromAvatar($avatar);
                        $alink->height= $size;
                        $alink->width = $size;
                    }
                }
            }

            $object->avatarLinks[] = $alink;
        }

        if (isset($profile->lat) && isset($profile->lon)) {
            $object->geopoint = (float)$profile->lat
                . ' ' . (float)$profile->lon;
        }

        $object->poco = PoCo::fromProfile($profile);

        return $object;
    }

    static function fromGroup($group)
    {
        $object = new ActivityObject();

        $object->type   = ActivityObject::GROUP;
        $object->id     = $group->getUri();
        $object->title  = $group->getBestName();
        $object->link   = $group->getUri();

        $object->avatarLinks[] = AvatarLink::fromFilename(
            $group->homepage_logo,
            AVATAR_PROFILE_SIZE
        );

        $object->avatarLinks[] = AvatarLink::fromFilename(
            $group->stream_logo,
            AVATAR_STREAM_SIZE
        );

        $object->avatarLinks[] = AvatarLink::fromFilename(
            $group->mini_logo,
            AVATAR_MINI_SIZE
        );

        $object->poco = PoCo::fromGroup($group);

        return $object;
    }

    function asString($tag='activity:object')
    {
        $xs = new XMLStringer(true);

        $xs->elementStart($tag);

        $xs->element('activity:object-type', null, $this->type);

        $xs->element(self::ID, null, $this->id);

        if (!empty($this->title)) {
            $xs->element(
                self::TITLE,
                null,
                common_xml_safe_str($this->title)
            );
        }

        if (!empty($this->summary)) {
            $xs->element(
                self::SUMMARY,
                null,
                common_xml_safe_str($this->summary)
            );
        }

        if (!empty($this->content)) {
            // XXX: assuming HTML content here
            $xs->element(
                ActivityUtils::CONTENT,
                array('type' => 'html'),
                common_xml_safe_str($this->content)
            );
        }

        if (!empty($this->link)) {
            $xs->element(
                'link',
                array(
                    'rel' => 'alternate',
                    'type' => 'text/html',
                    'href' => $this->link
                ),
                null
            );
        }

        if ($this->type == ActivityObject::PERSON
            || $this->type == ActivityObject::GROUP) {

            foreach ($this->avatarLinks as $avatar) {
                $xs->element(
                    'link', array(
                        'rel'  => 'avatar',
                        'type'         => $avatar->type,
                        'media:width'  => $avatar->width,
                        'media:height' => $avatar->height,
                        'href' => $avatar->url
                    ),
                    null
                );
            }
        }

        if (!empty($this->geopoint)) {
            $xs->element(
                'georss:point',
                null,
                $this->geopoint
            );
        }

        if (!empty($this->poco)) {
            $xs->raw($this->poco->asString());
        }

        $xs->elementEnd($tag);

        return $xs->getString();
    }
}
