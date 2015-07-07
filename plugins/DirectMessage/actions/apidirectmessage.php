<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a the direct messages from or to a user
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
 * @author    Adrian Lang <mail@adrianlang.de>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Robin Millette <robin@millette.info>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Show a list of direct messages from or to the authenticating user
 *
 * @category API
 * @package  StatusNet
 * @author   Adrian Lang <mail@adrianlang.de>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <robin@millette.info>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiDirectMessageAction extends ApiAuthAction
{
    var $messages     = null;
    var $title        = null;
    var $subtitle     = null;
    var $link         = null;
    var $selfuri_base = null;
    var $id           = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        if (!$this->scoped instanceof Profile) {
            // TRANS: Client error given when a user was not found (404).
            $this->clientError(_('No such user.'), 404);
        }

        $server   = common_root_url();
        $taguribase = TagURI::base();

        if ($this->arg('sent')) {

            // Action was called by /api/direct_messages/sent.format

            $this->title = sprintf(
                // TRANS: Title. %s is a user nickname.
                _("Direct messages from %s"),
                $this->scoped->getNickname()
            );
            $this->subtitle = sprintf(
                // TRANS: Subtitle. %s is a user nickname.
                _("All the direct messages sent from %s"),
                $this->scoped->getNickname()
            );
            $this->link = $server . $this->scoped->getNickname() . '/outbox';
            $this->selfuri_base = common_root_url() . 'api/direct_messages/sent';
            $this->id = "tag:$taguribase:SentDirectMessages:" . $this->scoped->getID();
        } else {
            $this->title = sprintf(
                // TRANS: Title. %s is a user nickname.
                _("Direct messages to %s"),
                $this->scoped->getNickname()
            );
            $this->subtitle = sprintf(
                // TRANS: Subtitle. %s is a user nickname.
                _("All the direct messages sent to %s"),
                $this->scoped->getNickname()
            );
            $this->link = $server . $this->scoped->getNickname() . '/inbox';
            $this->selfuri_base = common_root_url() . 'api/direct_messages';
            $this->id = "tag:$taguribase:DirectMessages:" . $this->scoped->getID();
        }

        $this->messages = $this->getMessages();

        return true;
    }

    protected function handle()
    {
        parent::handle();
        $this->showMessages();
    }

    /**
     * Show the messages
     *
     * @return void
     */
    function showMessages()
    {
        switch($this->format) {
        case 'xml':
            $this->showXmlDirectMessages();
            break;
        case 'rss':
            $this->showRssDirectMessages();
            break;
        case 'atom':
            $this->showAtomDirectMessages();
            break;
        case 'json':
            $this->showJsonDirectMessages();
            break;
        default:
            // TRANS: Client error displayed when coming across a non-supported API method.
            $this->clientError(_('API method not found.'), $code = 404);
            break;
        }
    }

    /**
     * Get notices
     *
     * @return array notices
     */
    function getMessages()
    {
        $message  = new Message();

        if ($this->arg('sent')) {
            $message->from_profile = $this->scoped->getID();
        } else {
            $message->to_profile = $this->scoped->getID();
        }

        if (!empty($this->max_id)) {
            $message->whereAdd('id <= ' . $this->max_id);
        }

        if (!empty($this->since_id)) {
            $message->whereAdd('id > ' . $this->since_id);
        }

        $message->orderBy('created DESC, id DESC');
        $message->limit((($this->page - 1) * $this->count), $this->count);
        $message->find();

        $messages = array();

        while ($message->fetch()) {
            $messages[] = clone($message);
        }

        return $messages;
    }

    /**
     * Is this action read only?
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * When was this notice last modified?
     *
     * @return string datestamp of the latest notice in the stream
     */
    function lastModified()
    {
        if (!empty($this->messages)) {
            return strtotime($this->messages[0]->created);
        }

        return null;
    }

    // BEGIN import from lib/apiaction.php

    function showSingleXmlDirectMessage($message)
    {
        $this->initDocument('xml');
        $dmsg = $this->directMessageArray($message);
        $this->showXmlDirectMessage($dmsg, true);
        $this->endDocument('xml');
    }

    function showSingleJsonDirectMessage($message)
    {
        $this->initDocument('json');
        $dmsg = $this->directMessageArray($message);
        $this->showJsonObjects($dmsg);
        $this->endDocument('json');
    }

    function showXmlDirectMessage($dm, $namespaces=false)
    {
        $attrs = array();
        if ($namespaces) {
            $attrs['xmlns:statusnet'] = 'http://status.net/schema/api/1/';
        }
        $this->elementStart('direct_message', $attrs);
        foreach($dm as $element => $value) {
            switch ($element) {
            case 'sender':
            case 'recipient':
                $this->showTwitterXmlUser($value, $element);
                break;
            case 'text':
                $this->element($element, null, common_xml_safe_str($value));
                break;
            default:
                $this->element($element, null, $value);
                break;
            }
        }
        $this->elementEnd('direct_message');
    }

    function directMessageArray($message)
    {
        $dmsg = array();

        $from_profile = $message->getFrom();
        $to_profile = $message->getTo();

        $dmsg['id'] = intval($message->id);
        $dmsg['sender_id'] = intval($from_profile->id);
        $dmsg['text'] = trim($message->content);
        $dmsg['recipient_id'] = intval($to_profile->id);
        $dmsg['created_at'] = $this->dateTwitter($message->created);
        $dmsg['sender_screen_name'] = $from_profile->nickname;
        $dmsg['recipient_screen_name'] = $to_profile->nickname;
        $dmsg['sender'] = $this->twitterUserArray($from_profile, false);
        $dmsg['recipient'] = $this->twitterUserArray($to_profile, false);

        return $dmsg;
    }

    function rssDirectMessageArray($message)
    {
        $entry = array();

        $from = $message->getFrom();

        $entry['title'] = sprintf('Message from %1$s to %2$s',
            $from->nickname, $message->getTo()->nickname);

        $entry['content'] = common_xml_safe_str($message->rendered);
        $entry['link'] = common_local_url('showmessage', array('message' => $message->id));
        $entry['published'] = common_date_iso8601($message->created);

        $taguribase = TagURI::base();

        $entry['id'] = "tag:$taguribase:$entry[link]";
        $entry['updated'] = $entry['published'];

        $entry['author-name'] = $from->getBestName();
        $entry['author-uri'] = $from->homepage;

        $entry['avatar'] = $from->avatarUrl(AVATAR_STREAM_SIZE);
        try {
            $avatar = $from->getAvatar(AVATAR_STREAM_SIZE);
            $entry['avatar-type'] = $avatar->mediatype;
        } catch (Exception $e) {
            $entry['avatar-type'] = 'image/png';
        }

        // RSS item specific

        $entry['description'] = $entry['content'];
        $entry['pubDate'] = common_date_rfc2822($message->created);
        $entry['guid'] = $entry['link'];

        return $entry;
    }

    // END import from lib/apiaction.php

    /**
     * Shows a list of direct messages as Twitter-style XML array
     *
     * @return void
     */
    function showXmlDirectMessages()
    {
        $this->initDocument('xml');
        $this->elementStart('direct-messages', array('type' => 'array',
                                                     'xmlns:statusnet' => 'http://status.net/schema/api/1/'));

        foreach ($this->messages as $m) {
            $dm_array = $this->directMessageArray($m);
            $this->showXmlDirectMessage($dm_array);
        }

        $this->elementEnd('direct-messages');
        $this->endDocument('xml');
    }

    /**
     * Shows a list of direct messages as a JSON encoded array
     *
     * @return void
     */
    function showJsonDirectMessages()
    {
        $this->initDocument('json');

        $dmsgs = array();

        foreach ($this->messages as $m) {
            $dm_array = $this->directMessageArray($m);
            array_push($dmsgs, $dm_array);
        }

        $this->showJsonObjects($dmsgs);
        $this->endDocument('json');
    }

    /**
     * Shows a list of direct messages as RSS items
     *
     * @return void
     */
    function showRssDirectMessages()
    {
        $this->initDocument('rss');

        $this->element('title', null, $this->title);

        $this->element('link', null, $this->link);
        $this->element('description', null, $this->subtitle);
        $this->element('language', null, 'en-us');

        $this->element(
            'atom:link',
            array(
                'type' => 'application/rss+xml',
                'href' => $this->selfuri_base . '.rss',
                'rel' => self
                ),
            null
        );
        $this->element('ttl', null, '40');

        foreach ($this->messages as $m) {
            $entry = $this->rssDirectMessageArray($m);
            $this->showTwitterRssItem($entry);
        }

        $this->endTwitterRss();
    }

    /**
     * Shows a list of direct messages as Atom entries
     *
     * @return void
     */
    function showAtomDirectMessages()
    {
        $this->initDocument('atom');

        $this->element('title', null, $this->title);
        $this->element('id', null, $this->id);

        $selfuri = common_root_url() . 'api/direct_messages.atom';

        $this->element(
            'link', array(
            'href' => $this->link,
            'rel' => 'alternate',
            'type' => 'text/html'),
            null
        );
        $this->element(
            'link', array(
            'href' => $this->selfuri_base . '.atom', 'rel' => 'self',
            'type' => 'application/atom+xml'),
            null
        );
        $this->element('updated', null, common_date_iso8601('now'));
        $this->element('subtitle', null, $this->subtitle);

        foreach ($this->messages as $m) {
            $entry = $this->rssDirectMessageArray($m);
            $this->showTwitterAtomEntry($entry);
        }

        $this->endDocument('atom');
    }

    /**
     * An entity tag for this notice
     *
     * Returns an Etag based on the action name, language, and
     * timestamps of the notice
     *
     * @return string etag
     */
    function etag()
    {
        if (!empty($this->messages)) {

            $last = count($this->messages) - 1;

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_user_cache_hash($this->auth_user),
                      common_language(),
                      strtotime($this->messages[0]->created),
                      strtotime($this->messages[$last]->created)
                )
            )
            . '"';
        }

        return null;
    }
}
