<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 */

/**
 * @package ShareNoticePlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET')) { exit(1); }

class ShareNoticePlugin extends Plugin
{
    public $targets = array(
        array('Twitter'),
        array('Facebook'),
        array('StatusNet', array('baseurl' => 'http://identi.ca'))
    );

    public function onEndShowStylesheets(Action $action) {
        $action->cssLink($this->path('css/sharenotice.css'));
        return true;
    }

    function onStartShowNoticeItem($item)
    {
        $notice = $item->notice;
        $out = $item->out;

        $out->elementStart('ul', array('class' => 'notice-share'));
        foreach ($this->targets as $data) {
            $type = $data[0];
            $args = (count($data) > 1) ? $data[1] : array();
            $target = $this->getShareTarget($type, $notice, $args);
            $this->showShareTarget($out, $target);
        }
        $out->elementEnd('ul');
    }

    private function getShareTarget($type, $notice, $args)
    {
        $class = ucfirst($type) . 'ShareTarget';

        return new $class($notice, $args);
    }

    private function showShareTarget(HTMLOutputter $out, NoticeShareTarget $target)
    {
        $class = $target->getClass();
        $text = $target->getText();
        $url = $target->targetUrl();

        $out->elementStart('li', array('class' => 'notice-share-' . $class));
        $out->elementStart('a', array(
            'href' => $url,
            'title' => $text,
            'target' => '_blank'
        ));
        $out->element('span', array(), $text);
        $out->elementEnd('a');
        $out->elementEnd('li');
    }
}

abstract class NoticeShareTarget
{
    protected $notice;

    public function __construct($notice)
    {
        $this->notice = $notice;
    }

    public abstract function getClass();

    public abstract function getText();

    public abstract function targetUrl();
}

abstract class GenericNoticeShareTarget extends NoticeShareTarget
{
    protected function maxLength()
    {
        return 140; // typical
    }

    protected function statusText()
    {
        // TRANS: %s is notice content that is shared on Twitter, Facebook or another platform.
        $pattern = _m('"%s"');
        $url = $this->notice->bestUrl();
        $suffix = ' ' . $url;
        $room = $this->maxLength() - mb_strlen($suffix) - (mb_strlen($pattern) - mb_strlen('%s'));

        $content = $this->notice->content;
        // TRANS: Truncation symbol.
        $truncation_symbol = _m('…');
        $truncation_symbol_length = mb_strlen($truncation_symbol);
        if (mb_strlen($content) > $room) {
            $content = mb_substr($content, 0, $room - $truncation_symbol_length) . $truncation_symbol;
        }

        return sprintf($pattern, $content) . $suffix;
    }
}

class TwitterShareTarget extends GenericNoticeShareTarget
{
    public function getClass()
    {
        return 'twitter';
    }

    public function getText()
    {
        // TRANS: Tooltip for image to share a notice on Twitter.
        return _m('Share on Twitter');
    }

    public function targetUrl()
    {
        $args = array(
            'status' => $this->statusText()
        );
        return 'http://twitter.com/home?' .
                http_build_query($args, null, '&');
    }
}

class StatusNetShareTarget extends GenericNoticeShareTarget
{
    protected $baseurl;

    public function __construct($notice, $args)
    {
        parent::__construct($notice);
        $this->baseurl = $args['baseurl'];
    }

    public function getClass()
    {
        return 'statusnet';
    }

    public function getText()
    {
        $host = parse_url($this->baseurl, PHP_URL_HOST);
        // TRANS: Tooltip for image to share a notice on another platform (other than Twitter or Facebook).
        // TRANS: %s is a host name.
        return sprintf(_m('Share on %s'), $host);
    }

    public function targetUrl()
    {
        $args = array(
            'status_textarea' => $this->statusText()
        );
        return $this->baseurl . '/notice/new?' .
                http_build_query($args, null, '&');
    }
}

class FacebookShareTarget extends NoticeShareTarget
{
    public function getClass()
    {
        return 'facebook';
    }

    public function getText()
    {
        // TRANS: Tooltip for image to share a notice on Facebook.
        return _m('Share on Facebook');
    }

    public function targetUrl()
    {
        $args = array(
            'u' => $this->notice->bestUrl(),
            // TRANS: %s is notice content that is shared on Twitter, Facebook or another platform.
            't' => sprintf(_m('"%s"'), $this->notice->content),
        );
        return 'http://www.facebook.com/sharer.php?' .
            http_build_query($args, null, '&');
    }

    /**
     * Provide plugin version information.
     *
     * This data is used when showing the version page.
     *
     * @param array &$versions array of version data arrays; see EVENTS.txt
     *
     * @return boolean hook value
     */
    function onPluginVersion(&$versions)
    {
        $url = 'http://status.net/wiki/Plugin:ShareNotice';

        $versions[] = array('name' => 'ShareNotice',
            'version' => STATUSNET_VERSION,
            'author' => 'Brion Vibber',
            'homepage' => $url,
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('This plugin allows sharing of notices to Twitter, Facebook and other platforms.'));

        return true;
    }
}
