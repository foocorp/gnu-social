<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for RSS 1.0 feed actions
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
 * @category  Mail
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Earle Martin <earle@downlode.org>
 * @copyright 2008-9 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

define('DEFAULT_RSS_LIMIT', 48);

class Rss10Action extends Action
{
    // This will contain the details of each feed item's author and be used to generate SIOC data.

    var $creators = array();
    var $limit = DEFAULT_RSS_LIMIT;
    var $notices = null;
    var $tags_already_output = array();

    /**
     * Constructor
     *
     * Just wraps the Action constructor.
     *
     * @param string  $output URI to output to, default = stdout
     * @param boolean $indent Whether to indent output, default true
     *
     * @see Action::__construct
     */

    function __construct($output='php://output', $indent=null)
    {
        parent::__construct($output, $indent);
    }

    /**
     * Do we need to write to the database?
     *
     * @return boolean true
     */

    function isReadonly()
    {
        return true;
    }

    /**
     * Read arguments and initialize members
     *
     * @param array $args Arguments from $_REQUEST
     * @return boolean success
     */

    function prepare($args)
    {
        parent::prepare($args);

        $this->limit = (int) $this->trimmed('limit');

        if ($this->limit == 0) {
            $this->limit = DEFAULT_RSS_LIMIT;
        }

        if (common_config('site', 'private')) {
            if (!isset($_SERVER['PHP_AUTH_USER'])) {

                // This header makes basic auth go
                header('WWW-Authenticate: Basic realm="GNU social RSS"');

                // If the user hits cancel -- bam!
                $this->show_basic_auth_error();
                return;
            } else {
                $nickname = $_SERVER['PHP_AUTH_USER'];
                $password = $_SERVER['PHP_AUTH_PW'];

                if (!common_check_user($nickname, $password)) {
                    // basic authentication failed
                    list($proxy, $ip) = common_client_ip();

                    common_log(LOG_WARNING, "Failed RSS auth attempt, nickname = $nickname, proxy = $proxy, ip = $ip.");
                    $this->show_basic_auth_error();
                    return;
                }
            }
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

    function handle($args)
    {
        // Parent handling, including cache check
        parent::handle($args);
        $this->showRss();
    }

    function show_basic_auth_error()
    {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/xml; charset=utf-8');
        $this->startXML();
        $this->elementStart('hash');
        $this->element('error', null, 'Could not authenticate you.');
        $this->element('request', null, $_SERVER['REQUEST_URI']);
        $this->elementEnd('hash');
        $this->endXML();
    }

    /**
     * Get the notices to output in this stream.
     *
     * @return array an array of Notice objects sorted in reverse chron
     */

    function getNotices()
    {
        return array();
    }

    /**
     * Get a description of the channel
     *
     * Returns an array with the following
     * @return array
     */

    function getChannel()
    {
        return array('url' => '',
                     'title' => '',
                     'link' => '',
                     'description' => '');
    }

    function getImage()
    {
        return null;
    }

    function showRss()
    {
        $this->initRss();
        $this->showChannel();
        $this->showImage();

        if (count($this->notices)) {
            foreach ($this->notices as $n) {
                try {
                    $this->showItem($n);
                } catch (Exception $e) {
                    // log exceptions and continue
                    common_log(LOG_ERR, $e->getMessage());
                    continue;
                }
            }
        }

        $this->showCreators();
        $this->endRss();
    }

    function showChannel()
    {

        $channel = $this->getChannel();
        $image = $this->getImage();

        $this->elementStart('channel', array('rdf:about' => $channel['url']));
        $this->element('title', null, $channel['title']);
        $this->element('link', null, $channel['link']);
        $this->element('description', null, $channel['description']);
        $this->element('cc:licence', array('rdf:resource' => common_config('license','url')));

        if ($image) {
            $this->element('image', array('rdf:resource' => $image));
        }

        $this->elementStart('items');
        $this->elementStart('rdf:Seq');

        if (count($this->notices)) {
            foreach ($this->notices as $notice) {
                $this->element('rdf:li', array('rdf:resource' => $notice->uri));
            }
        }

        $this->elementEnd('rdf:Seq');
        $this->elementEnd('items');

        $this->elementEnd('channel');
    }

    function showImage()
    {
        $image = $this->getImage();
        if ($image) {
            $channel = $this->getChannel();
            $this->elementStart('image', array('rdf:about' => $image));
            $this->element('title', null, $channel['title']);
            $this->element('link', null, $channel['link']);
            $this->element('url', null, $image);
            $this->elementEnd('image');
        }
    }

    function showItem($notice)
    {
        $profile = $notice->getProfile();
        $nurl = common_local_url('shownotice', array('notice' => $notice->id));
        $creator_uri = common_profile_uri($profile);
        $this->elementStart('item', array('rdf:about' => $notice->uri,
                            'rdf:type' => 'http://rdfs.org/sioc/types#MicroblogPost'));
        $title = $profile->nickname . ': ' . common_xml_safe_str(trim($notice->content));
        $this->element('title', null, $title);
        $this->element('link', null, $nurl);
        $this->element('description', null, $profile->nickname."'s status on ".common_exact_date($notice->created));
        if ($notice->rendered) {
            $this->element('content:encoded', null, common_xml_safe_str($notice->rendered));
        }
        $this->element('dc:date', null, common_date_w3dtf($notice->created));
        $this->element('dc:creator', null, ($profile->fullname) ? $profile->fullname : $profile->nickname);
        $this->element('foaf:maker', array('rdf:resource' => $creator_uri));
        $this->element('sioc:has_creator', array('rdf:resource' => $creator_uri.'#acct'));
        $location = $notice->getLocation();
        if ($location && isset($location->lat) && isset($location->lon)) {
            $location_uri = $location->getRdfURL();
            $attrs = array('geo:lat' => $location->lat,
                'geo:long' => $location->lon);
            if (strlen($location_uri)) {
                $attrs['rdf:resource'] = $location_uri;
            }
            $this->element('statusnet:origin', $attrs);
        }
        $this->element('statusnet:postIcon', array('rdf:resource' => $profile->avatarUrl()));
        $this->element('cc:licence', array('rdf:resource' => common_config('license', 'url')));
        if ($notice->reply_to) {
            $replyurl = common_local_url('shownotice', array('notice' => $notice->reply_to));
            $this->element('sioc:reply_of', array('rdf:resource' => $replyurl));
        }
        if (!empty($notice->conversation)) {
            $conversationurl = common_local_url('conversation',
                                         array('id' => $notice->conversation));
            $this->element('sioc:has_discussion', array('rdf:resource' => $conversationurl));
        }
        $attachments = $notice->attachments();
        if($attachments){
            foreach($attachments as $attachment){
                try {
                    $enclosure = $attachment->getEnclosure();
                    $attribs = array('rdf:resource' => $enclosure->url);
                    if ($enclosure->title) {
                        $attribs['dc:title'] = $enclosure->title;
                    }
                    if ($enclosure->modified) {
                        $attribs['dc:date'] = common_date_w3dtf($enclosure->modified);
                    }
                    if ($enclosure->size) {
                        $attribs['enc:length'] = $enclosure->size;
                    }
                    if ($enclosure->mimetype) {
                        $attribs['enc:type'] = $enclosure->mimetype;
                    }
                    $this->element('enc:enclosure', $attribs);
                } catch (ServerException $e) {
                    // There was not enough metadata available
                }
                $this->element('sioc:links_to', array('rdf:resource'=>$attachment->url));
            }
        }

        $tag = new Notice_tag();
        $tag->notice_id = $notice->id;
        if ($tag->find()) {
            $entry['tags']=array();
            while ($tag->fetch()) {
                $tagpage = common_local_url('tag', array('tag' => $tag->tag));

                if ( in_array($tag, $this->tags_already_output) ) {
                    $this->element('ctag:tagged', array('rdf:resource'=>$tagpage.'#concept'));
                    continue;
                }

                $tagrss  = common_local_url('tagrss', array('tag' => $tag->tag));
                $this->elementStart('ctag:tagged');
                $this->elementStart('ctag:Tag', array('rdf:about'=>$tagpage.'#concept', 'ctag:label'=>$tag->tag));
                $this->element('foaf:page', array('rdf:resource'=>$tagpage));
                $this->element('rdfs:seeAlso', array('rdf:resource'=>$tagrss));
                $this->elementEnd('ctag:Tag');
                $this->elementEnd('ctag:tagged');

                $this->tags_already_output[] = $tag->tag;
            }
        }
        $this->elementEnd('item');
        $this->creators[$creator_uri] = $profile;
    }

    function showCreators()
    {
        foreach ($this->creators as $uri => $profile) {
            $id = $profile->id;
            $nickname = $profile->nickname;
            $this->elementStart('foaf:Agent', array('rdf:about' => $uri));
            $this->element('foaf:nick', null, $nickname);
            if ($profile->fullname) {
                $this->element('foaf:name', null, $profile->fullname);
            }
            $this->element('foaf:holdsAccount', array('rdf:resource' => $uri.'#acct'));
            $avatar = $profile->avatarUrl();
            $this->element('foaf:depiction', array('rdf:resource' => $avatar));
            $this->elementEnd('foaf:Agent');
        }
    }

    function initRss()
    {
        $channel = $this->getChannel();
        header('Content-Type: application/rdf+xml');

        $this->startXml();
        $this->elementStart('rdf:RDF', array('xmlns:rdf' =>
                                              'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                                              'xmlns:dc' =>
                                              'http://purl.org/dc/elements/1.1/',
                                              'xmlns:cc' =>
                                              'http://creativecommons.org/ns#',
                                              'xmlns:content' =>
                                              'http://purl.org/rss/1.0/modules/content/',
                                              'xmlns:ctag' =>
                                              'http://commontag.org/ns#',
                                              'xmlns:foaf' =>
                                              'http://xmlns.com/foaf/0.1/',
                                              'xmlns:enc' =>
                                              'http://purl.oclc.org/net/rss_2.0/enc#',
                                              'xmlns:sioc' =>
                                              'http://rdfs.org/sioc/ns#',
                                              'xmlns:sioct' =>
                                              'http://rdfs.org/sioc/types#',
                                              'xmlns:rdfs' =>
                                              'http://www.w3.org/2000/01/rdf-schema#',
                                              'xmlns:geo' =>
                                              'http://www.w3.org/2003/01/geo/wgs84_pos#',
                                              'xmlns:statusnet' =>
                                              'http://status.net/ont/',
                                              'xmlns' => 'http://purl.org/rss/1.0/'));
        $this->elementStart('sioc:Site', array('rdf:about' => common_root_url()));
        $this->element('sioc:name', null, common_config('site', 'name'));
        $this->elementStart('sioc:space_of');
        $this->element('sioc:Container', array('rdf:about' =>
                                               $channel['url']));
        $this->elementEnd('sioc:space_of');
        $this->elementEnd('sioc:Site');
    }

    function endRss()
    {
        $this->elementEnd('rdf:RDF');
    }

    /**
     * When was this page last modified?
     *
     */

    function lastModified()
    {
        if (empty($this->notices)) {
            return null;
        }

        if (count($this->notices) == 0) {
            return null;
        }

        // FIXME: doesn't handle modified profiles, avatars, deleted notices

        return strtotime($this->notices[0]->created);
    }
}

