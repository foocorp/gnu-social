<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget for showing a list of feeds
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
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Widget for showing a list of feeds
 *
 * Typically used for Action::showExportList()
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Action::showExportList()
 */
class FeedList extends Widget
{
    var $action = null;

    protected $feeds = null;

    public function __construct(Action $action=null, array $feeds=array())
    {
        parent::__construct($action);
        $this->action = $action;
        $this->feeds  = $feeds;
    }

    public function show()
    {
        if (Event::handle('StartShowFeedLinkList', array($this->action, &$this->feeds))) {
            if (!empty($this->feeds)) {
                $this->out->elementStart('div', array('id' => 'export_data',
                                                      'class' => 'section'));
                // TRANS: Header for feed links (h2).
                $this->out->element('h2', null, _('Feeds'));
                $this->out->elementStart('ul', array('class' => 'xoxo'));

                foreach ($this->feeds as $feed) {
                    $this->feedItem($feed);
                }

                $this->out->elementEnd('ul');
                $this->out->elementEnd('div');
            }
            Event::handle('EndShowFeedLinkList', array($this->action, &$this->feeds));
        }
    }

    function feedItem($feed)
    {
        if (Event::handle('StartShowFeedLink', array($this->action, &$feed))) {
            $classname = null;

            switch ($feed->type) {
            case Feed::RSS1:
            case Feed::RSS2:
                $classname = 'rss';
                break;
            case Feed::ATOM:
                $classname = 'atom';
                break;
            case Feed::FOAF:
                $classname = 'foaf';
                break;
            case Feed::JSON:
                $classname = 'json';
                break;
            }

            $this->out->elementStart('li');
            $this->out->element('a', array('href' => $feed->url,
                                           'class' => $classname,
                                           'type' => $feed->mimeType(),
                                           'title' => $feed->title),
                                $feed->typeName());
            $this->out->elementEnd('li');
            Event::handle('EndShowFeedLink', array($this->action, $feed));
        }
    }
}
