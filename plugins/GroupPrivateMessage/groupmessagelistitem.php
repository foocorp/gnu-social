<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Widget for showing an individual group message
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
 * @category  GroupPrivateMessage
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
 * Widget for showing a single group message
 *
 * @category  GroupPrivateMessage
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class GroupMessageListItem extends Widget
{
    var $gm;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out output context
     * @param Group_message $gm  Group message
     */
    function __construct($out, $gm)
    {
        parent::__construct($out);
        $this->gm = $gm;
    }

    /**
     * Show the item
     *
     * @return void
     */
    function show()
    {
        $group  = $this->gm->getGroup();
        $sender = $this->gm->getSender();

        $this->out->elementStart('li', array('class' => 'hentry notice message group-message',
                                         'id' => 'message-' . $this->gm->id));

        $this->out->elementStart('div', 'entry-title');
        $this->out->elementStart('span', 'vcard author');
        $this->out->elementStart('a',
                                 array('href' => $sender->profileurl,
                                       'class' => 'url'));
        $avatar = $sender->getAvatar(AVATAR_STREAM_SIZE);
        $this->out->element('img', array('src' => ($avatar) ?
                                    $avatar->displayUrl() :
                                    Avatar::defaultImage(AVATAR_STREAM_SIZE),
                                    'width' => AVATAR_STREAM_SIZE,
                                    'height' => AVATAR_STREAM_SIZE,
                                    'class' => 'photo avatar',
                                    'alt' => $sender->getBestName()));
        $this->out->element('span',
                            array('class' => 'nickname fn'),
                            $sender->nickname);
        $this->out->elementEnd('a');
        $this->out->elementEnd('span');

        $this->out->elementStart('p', array('class' => 'entry-content message-content'));
        $this->out->raw($this->gm->rendered);
        $this->out->elementEnd('p');
        $this->out->elementEnd('div');

        $this->out->elementStart('div', 'entry-content');
        $this->out->elementStart('a', array('rel' => 'bookmark',
                                            'class' => 'timestamp',
                                            'href' => $this->gm->url));
        $dt = common_date_iso8601($this->gm->created);
        $this->out->element('abbr', array('class' => 'published',
                                          'title' => $dt),
                            common_date_string($this->gm->created));
        $this->out->elementEnd('a');
        $this->out->elementEnd('div');

        $this->out->elementEnd('li');
    }
}
