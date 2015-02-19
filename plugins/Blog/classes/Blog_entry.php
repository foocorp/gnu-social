<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Data structure for blog entries
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
 * @category  Blog
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Data structure for blog entries
 *
 * @category  Blog
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class Blog_entry extends Managed_DataObject
{
    public $__table = 'blog_entry';

    public $id; // UUID
    public $profile_id; // int
    public $title; // varchar(255)
    public $summary; // text
    public $content; // text
    public $uri; // text
    public $url; // text
    public $created; // datetime
    public $modified; // datetime

    const TYPE = ActivityObject::ARTICLE;

    static function schemaDef()
    {
        return array(
            'description' => 'lite blog entry',
            'fields' => array(
                'id' => array('type' => 'char',
                              'length' => 36,
                              'not null' => true,
                              'description' => 'Unique ID (UUID)'),
                'profile_id' => array('type' => 'int',
                                      'not null' => true,
                                      'description' => 'Author profile ID'),
                'title' => array('type' => 'varchar',
                                 'length' => 255,
                                 'description' => 'title of the entry'),
                'summary' => array('type' => 'text',
                                   'description' => 'initial summary'),
                'content' => array('type' => 'text',
                                   'description' => 'HTML content of the entry'),
                'uri' => array('type' => 'varchar',
                               'length' => 255,
                               'description' => 'URI (probably http://) for this entry'),
                'url' => array('type' => 'varchar',
                               'length' => 255,
                               'description' => 'URL (probably http://) for this entry'),
                'created' => array('type' => 'datetime',
                                   'not null' => true,
                                   'description' => 'date this record was created'),
                'modified' => array('type' => 'datetime',
                                    'not null' => true,
                                    'description' => 'date this record was created'),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'blog_entry_uri_key' => array('uri'),
            ),
            'indexes' => array(
                'blog_entry_profile_id_idx' => array('profile_id'),
                'blog_entry_created_idx' => array('created')
            ),
            'foreign keys' => array(
                'blog_entry_profile_id_fkey' => array('profile', array('profile_id' => 'id')),
            ),
        );
    }

    static function saveNew($profile, $title, $content, $options=null)
    {
        if (is_null($options)) {
            $options = array();
        }

        $be             = new Blog_entry();
        $be->id         = (string) new UUID();
        $be->profile_id = $profile->id;
        $be->title      = $title; // Note: not HTML-protected
        $be->content    = common_purify($content);

        if (array_key_exists('summary', $options)) {
            $be->summary = common_purify($options['summary']);
        } else {
            // Already purified
            $be->summary = self::summarize($be->content);
        }

        // Don't save an identical summary

        if ($be->summary == $be->content) {
            $be->summary = null;
        }

        $url = common_local_url('showblogentry', array('id' => $be->id));

        if (!array_key_exists('uri', $options)) {
            $options['uri'] = $url;
        } 

        $be->uri = $options['uri'];

        if (!array_key_exists('url', $options)) {
            $options['url'] = $url;
        }

        $be->url = $options['url'];

        if (!array_key_exists('created', $options)) {
            $be->created = common_sql_now();
        }
        
        $be->created = $options['created'];

        $be->modified = common_sql_now();

        $be->insert();

        // Use user's preferences for short URLs, if possible

        try {
            $user = $profile->isLocal()
                        ? $profile->getUser()
                        : null;
            $shortUrl = File_redirection::makeShort($url, $user);
        } catch (Exception $e) {
            // Don't let this stop us.
            $shortUrl = $url;
        }

        // XXX: this might be too long.

        if (!empty($be->summary)) {
            $options['rendered'] = $be->summary . ' ' . 
                XMLStringer::estring('a', array('href' => $url,
                                                'class' => 'blog-entry'),
                                     _('More...'));
            $text = common_strip_html($be->summary);
        } else {
            $options['rendered'] = $be->content;
            $text = common_strip_html($be->content);
        }


        if (Notice::contentTooLong($text)) {
            $text = substr($text, 0, Notice::maxContent() - mb_strlen($shortUrl) - 2) .
                '… ' . $shortUrl;
        }

        // Override this no matter what.
        
        $options['object_type'] = self::TYPE;

        $source = array_key_exists('source', $options) ?
                                    $options['source'] : 'web';
        
        $saved = Notice::saveNew($profile->id, $text, $source, $options);

        return $saved;
    }

    /**
     * Summarize the contents of a blog post
     *
     * We take the first div or paragraph of the blog post if there's a hit;
     * Otherwise we take the whole thing.
     * 
     * @param string $html HTML of full content
     */
    static function summarize($html)
    {
        if (preg_match('#<p>.*?</p>#s', $html, $matches)) {
            return $matches[0];
        } else if (preg_match('#<div>.*?</div>#s', $html, $matches)) {
            return $matches[0];
        } else {
            return $html;
        }
    }

    static function fromNotice($notice)
    {
        return Blog_entry::getKV('uri', $notice->uri);
    }

    function getNotice()
    {
        return Notice::getKV('uri', $this->uri);
    }

    function asActivityObject()
    {
        $obj = new ActivityObject();

        $obj->id      = $this->uri;
        $obj->type    = self::TYPE;
        $obj->title   = $this->title;
        $obj->summary = $this->summary;
        $obj->content = $this->content;
        $obj->link    = $this->url;

        return $obj;
    }
}
