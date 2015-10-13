<?php
/**
 * Data class to mark notices as bookmarks
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * For storing the fact that a notice is a bookmark
 *
 * @category Bookmark
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class Bookmark extends Managed_DataObject
{
    public $__table = 'bookmark'; // table name
    public $id;          // char(36) primary_key not_null
    public $profile_id;  // int(4) not_null
    public $url;         // varchar(191) not_null   not 255 because utf8mb4 takes more space
    public $title;       // varchar(191)   not 255 because utf8mb4 takes more space
    public $uri;         // varchar(191)   not 255 because utf8mb4 takes more space
    public $description; // text
    public $created;     // datetime

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'id' => array('type' => 'char',
                            'length' => 36,
                            'not null' => true),
                'profile_id' => array('type' => 'int', 'not null' => true),
                'uri' => array('type' => 'varchar',
                            'length' => 191,
                            'not null' => true),
                'url' => array('type' => 'varchar',
                            'length' => 191,
                            'not null' => true),
                'title' => array('type' => 'varchar', 'length' => 191),
                'description' => array('type' => 'text'),
                'created' => array('type' => 'datetime', 'not null' => true),
            ),
            'primary key' => array('uri'),
            'unique keys' => array(
                'bookmark_id_key' => array('id'),
            ),
            'foreign keys' => array(
                'bookmark_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
                'bookmark_uri_fkey' => array('notice', array('uri' => 'uri')),
            ),
            'indexes' => array('bookmark_created_idx' => array('created'),
                            'bookmark_url_idx' => array('url'),
                            'bookmark_profile_id_idx' => array('profile_id'),
            ),
        );
    }

    /**
     * Get a bookmark based on a notice
     *
     * @param   Notice              $stored Notice activity which represents the Bookmark
     *
     * @return  Bookmark            The found bookmark object.
     * @throws  NoResultException   When you don't find it after all.
     */
    static public function fromStored(Notice $stored)
    {
        return self::getByPK(array('uri' => $stored->getUri()));
    }

    public function getStored()
    {
        return Notice::getByKeys(array('uri' => $this->getUri()));
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getUrl()
    {
        if (empty($this->url)) {
            throw new InvalidUrlException($this->url);
        }
        return $this->url;
    }

    /**
     * Get the bookmark that a user made for an URL
     *
     * @param Profile $profile Profile to check for
     * @param string  $url     URL to check for
     *
     * @return Bookmark bookmark found or null
     */
    static function getByURL(Profile $profile, $url)
    {
        $nb = new Bookmark();

        $nb->profile_id = $profile->getID();
        $nb->url        = $url;

        if (!$nb->find(true)) {
            throw new NoResultException($nb);
        }

        return $nb;
    }

    /**
     * Store a Bookmark object
     *
     * @param Profile $profile     To save the bookmark for
     * @param string  $title       Title of the bookmark
     * @param string  $url         URL of the bookmark
     * @param string  $description Description of the bookmark
     *
     * @return Bookmark the Bookmark object
     */
    static function saveActivityObject(ActivityObject $actobj, Notice $stored)
    {
        $url = null;
        // each extra element is array('tagname', array('attr'=>'val', ...), 'content')
        foreach ($actobj->extra as $extra) {
            if ($extra[1]['rel'] !== 'related') {
                continue;
            }
            if ($url===null && strlen($extra[1]['href'])>0) {
                $url = $extra[1]['href'];
            } elseif ($url !== null) {
                // TRANS: Client exception thrown when a bookmark is formatted incorrectly.
                throw new ClientException(sprintf(_m('Expected exactly 1 link rel=related in a Bookmark, got %1$d.'), count($relLinkEls)));
            }
        }
        if (is_null($url)) {
            // TRANS: Client exception thrown when a bookmark is formatted incorrectly.
            throw new ClientException(sprintf(_m('Expected exactly 1 link rel=related in a Bookmark, got %1$d.'), count($relLinkEls)));
        }

        if (!strlen($actobj->title)) {
            throw new ClientException(_m('You must provide a non-empty title.'));
        }
        if (!common_valid_http_url($url)) {
            throw new ClientException(_m('Only web bookmarks can be posted (HTTP or HTTPS).'));
        }

        try {
            $object = self::getByURL($stored->getProfile(), $url);
            throw new ClientException(_m('You have already bookmarked this URL.'));
        } catch (NoResultException $e) {
            // Alright, so then we have to create it.
        }

        $nb = new Bookmark();

        $nb->id          = UUID::gen();
        $nb->uri         = $stored->getUri();
        $nb->profile_id  = $stored->getProfile()->getID();
        $nb->title       = $actobj->title;
        $nb->url         = $url;
        $nb->description = $actobj->summary;
        $nb->created     = $stored->created;

        $result = $nb->insert();
        if ($result === false) {
            throw new ServerException('Could not insert Bookmark into database!');
        }

        return $nb;
    }

    public function asActivityObject()
    {
        $stored = $this->getStored();

        $object = new ActivityObject();
        $object->id      = $this->getUri();
        $object->type    = ActivityObject::BOOKMARK;
        $object->title   = $this->getTitle();
        $object->summary = $this->getDescription();
        $object->link    = $stored->getUrl();
        $object->content = $stored->getRendered();

        // Attributes of the URL

        $attachments = $stored->attachments();

        if (count($attachments) != 1) {
            // TRANS: Server exception thrown when a bookmark has multiple attachments.
            throw new ServerException(_m('Bookmark notice with the '.
                                        'wrong number of attachments.'));
        }

        $bookmarkedurl = $attachments[0];

        $attrs = array('rel' => 'related',
                       'href' => $bookmarkedurl->getUrl());

        if (!strlen($bookmarkedurl->title)) {
            $attrs['title'] = $bookmarkedurl->title;
        }

        $object->extra[] = array('link', $attrs, null);

        // Attributes of the thumbnail, if any

        try {
            $thumbnail = $bookmarkedurl->getThumbnail();
            $tattrs = array('rel' => 'preview',
                            'href' => $thumbnail->getUrl());

            if (!empty($thumbnail->width)) {
                $tattrs['media:width'] = $thumbnail->width;
            }

            if (!empty($thumbnail->height)) {
                $tattrs['media:height'] = $thumbnail->height;
            }

            $object->extra[] = array('link', $tattrs, null);
        } catch (UnsupportedMediaException $e) {
            // No image thumbnail metadata available
        }

        return $object;
    }

    /**
     * Save a new notice bookmark
     *
     * @param Profile $profile     To save the bookmark for
     * @param string  $title       Title of the bookmark
     * @param string  $url         URL of the bookmark
     * @param array   $rawtags     array of tags
     * @param string  $description Description of the bookmark
     * @param array   $options     Options for the Notice::saveNew()
     *
     * @return Notice saved notice
     */
    static function addNew(Profile $actor, $title, $url, array $rawtags, $description, array $options=array())
    {
        $act        = new Activity();
        $act->verb  = ActivityVerb::POST;
        $act->time  = time();
        $act->actor = $actor->asActivityObject();

        $actobj = new ActivityObject();
        $actobj->type = ActivityObject::BOOKMARK;
        $actobj->title = $title;
        $actobj->summary = $description;
        $actobj->extra[] = array('link', array('rel'=>'related', 'href'=>$url), null);
        $act->objects[] = $actobj;

        $act->enclosures[] = $url;

        $tags    = array();
        $replies = array();

        // filter "for:nickname" tags
        foreach ($rawtags as $tag) {
            if (strtolower(mb_substr($tag, 0, 4)) == 'for:') {
                // skip if done by caller
                if (!array_key_exists('replies', $options)) {
                    $nickname = mb_substr($tag, 4);
                    $other    = common_relative_profile($actor, $nickname);
                    if (!empty($other)) {
                        $replies[] = $other->getUri();
                    }
                }
            } else {
                $tags[] = common_canonical_tag($tag);
            }
        }

        $hashtags = array();
        $taglinks = array();

        foreach ($tags as $tag) {
            $hashtags[] = '#'.$tag;
            $attrs      = array('href' => Notice_tag::url($tag),
                                'rel'  => $tag,
                                'class' => 'tag');
            $taglinks[] = XMLStringer::estring('a', $attrs, $tag);
        }

        // Use user's preferences for short URLs, if possible
        // FIXME: Should be possible to with the Profile object...
        try {
            $user = $actor->getUser();
            $shortUrl = File_redirection::makeShort($url, empty($user) ? null : $user);
        } catch (Exception $e) {
            // Don't let this stop us.
            $shortUrl = $url;
        }

        // TRANS: Rendered bookmark content.
        // TRANS: %1$s is a URL, %2$s the bookmark title, %3$s is the bookmark description,
        // TRANS: %4$s is space separated list of hash tags.
        $actobj->content = sprintf(_m('<span class="xfolkentry">'.
                                      '<a class="taggedlink" href="%1$s">%2$s</a> '.
                                      '<span class="description">%3$s</span> '.
                                      '<span class="meta">%4$s</span>'.
                                      '</span>'),
                                    htmlspecialchars($url),
                                    htmlspecialchars($title),
                                    htmlspecialchars($description),
                                    implode(' ', $taglinks));

        foreach ($tags as $term) {
            $catEl = new AtomCategory();
            $catEl->term = $term;
            $activity->categories[] = $catEl;
        }

        $options = array_merge(array('urls' => array($url),
                                     'rendered' => $rendered,
                                     'tags' => $tags,
                                     'replies' => $replies,
                                     'object_type' => ActivityObject::BOOKMARK),
                               $options);

        return Notice::saveActivity($act, $actor, $options);
    }
}
