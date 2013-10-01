<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A single list item for showing in a message list
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
 * @category  Widget
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
 * A single item in a message list
 *
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
abstract class MessageListItem extends Widget
{
    var $message;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     Output context
     * @param Message       $message Message to show
     */
    function __construct($out, $message)
    {
        parent::__construct($out);
        $this->message = $message;
    }

    /**
     * Show the widget
     *
     * @return void
     */
    function show()
    {
        $this->out->elementStart('li', array('class' => 'hentry notice',
                                             'id' => 'message-' . $this->message->id));

        $profile = $this->getMessageProfile();

        $this->out->elementStart('div', 'entry-title');
        $this->out->elementStart('span', 'vcard author');
        $this->out->elementStart('a', array('href' => $profile->profileurl,
                                            'class' => 'url'));
        $avatarUrl = $profile->avatarUrl(AVATAR_STREAM_SIZE);
        $this->out->element('img', array('src' => $avatarUrl,
                                         'class' => 'photo avatar',
                                         'width' => AVATAR_STREAM_SIZE,
                                         'height' => AVATAR_STREAM_SIZE,
                                         'alt' =>
                                         ($profile->fullname) ? $profile->fullname :
                                         $profile->nickname));
        $this->out->element('span', array('class' => 'nickname fn'),
                            $profile->nickname);
        $this->out->elementEnd('a');
        $this->out->elementEnd('span');

        // FIXME: URL, image, video, audio
        $this->out->elementStart('p', array('class' => 'entry-content'));
        $this->out->raw($this->message->rendered);
        $this->out->elementEnd('p');
        $this->out->elementEnd('div');

        $messageurl = common_local_url('showmessage',
                                       array('message' => $this->message->id));

        // XXX: we need to figure this out better. Is this right?
        if (strcmp($this->message->uri, $messageurl) != 0 &&
            preg_match('/^http/', $this->message->uri)) {
            $messageurl = $this->message->uri;
        }

        $this->out->elementStart('div', 'entry-content');
        $this->out->elementStart('a', array('rel' => 'bookmark',
                                            'class' => 'timestamp',
                                            'href' => $messageurl));
        $dt = common_date_iso8601($this->message->created);
        $this->out->element('abbr', array('class' => 'published',
                                          'title' => $dt),
                            common_date_string($this->message->created));
        $this->out->elementEnd('a');

        if ($this->message->source) {
            $this->out->elementStart('span', 'source');
            // FIXME: bad i18n. Device should be a parameter (from %s).
            // TRANS: Followed by notice source (usually the client used to send the notice).
            $this->out->text(_('from'));
            $this->showSource($this->message->source);
            $this->out->elementEnd('span');
        }
        $this->out->elementEnd('div');

        $this->out->elementEnd('li');
    }

    /**
     * Dummy method. Serves no other purpose than to make strings available used
     * in self::showSource() through xgettext.
     *
     * @return void
     */
    function messageListItemDummyMessages()
    {
        // A dummy array with messages. These will get extracted by xgettext and
        // are used in self::showSource().
        $dummy_messages = array(
            // TRANS: A possible notice source (web interface).
            _m('SOURCE','web'),
            // TRANS: A possible notice source (XMPP).
            _m('SOURCE','xmpp'),
            // TRANS: A possible notice source (e-mail).
            _m('SOURCE','mail'),
            // TRANS: A possible notice source (OpenMicroBlogging).
            _m('SOURCE','omb'),
            // TRANS: A possible notice source (Application Programming Interface).
            _m('SOURCE','api'),
        );
    }

    /**
     * Show the source of the message
     *
     * Returns either the name (and link) of the API client that posted the notice,
     * or one of other other channels.
     *
     * @param string $source the source of the message
     *
     * @return void
     */
    function showSource($source)
    {
        $source_name = _m('SOURCE',$source);
        switch ($source) {
        case 'web':
        case 'xmpp':
        case 'mail':
        case 'omb':
        case 'api':
            $this->out->element('span', 'device', $source_name);
            break;
        default:
            $ns = Notice_source::getKV($source);
            if ($ns) {
                $this->out->elementStart('span', 'device');
                $this->out->element('a', array('href' => $ns->url,
                                               'rel' => 'external'),
                                    $ns->name);
                $this->out->elementEnd('span');
            } else {
                $this->out->element('span', 'device', $source_name);
            }
            break;
        }
        return;
    }

    /**
     * Return the profile to show in the message item
     *
     * Overridden in sub-classes to show sender, receiver, or whatever
     *
     * @return Profile profile to show avatar and name of
     */
    abstract function getMessageProfile();
}
