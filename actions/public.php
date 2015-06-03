<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action for displaying the public stream
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
 * @category  Public
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Action for displaying the public stream
 *
 * @category Public
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      PublicrssAction
 * @see      PublicxrdsAction
 */
class PublicAction extends SitestreamAction
{
    protected function streamPrepare()
    {
        if ($this->scoped instanceof Profile && $this->scoped->isLocal() && $this->scoped->getUser()->streamModeOnly()) {
            $this->stream = new PublicNoticeStream($this->scoped);
        } else {
            $this->stream = new ThreadingPublicNoticeStream($this->scoped);
        }
    }

    /**
     * Title of the page
     *
     * @return page title, including page number if over 1
     */
    function title()
    {
        if ($this->page > 1) {
            // TRANS: Title for all public timeline pages but the first.
            // TRANS: %d is the page number.
            return sprintf(_('Public timeline, page %d'), $this->page);
        } else {
            // TRANS: Title for the first public timeline page.
            return _('Public timeline');
        }
    }

    function extraHead()
    {
        parent::extraHead();
        $this->element('meta', array('http-equiv' => 'X-XRDS-Location',
                                           'content' => common_local_url('publicxrds')));

        $rsd = common_local_url('rsd');

        // RSD, http://tales.phrasewise.com/rfc/rsd

        $this->element('link', array('rel' => 'EditURI',
                                     'type' => 'application/rsd+xml',
                                     'href' => $rsd));

        if ($this->page != 1) {
            $this->element('link', array('rel' => 'canonical',
                                         'href' => common_local_url('public')));
        }
    }

    function showSections()
    {
        // Show invite button, as long as site isn't closed, and
        // we have a logged in user.
        if (common_config('invite', 'enabled') && !common_config('site', 'closed') && common_logged_in()) {
            if (!common_config('site', 'private')) {
                $ibs = new InviteButtonSection(
                    $this,
                    // TRANS: Button text for inviting more users to the StatusNet instance.
                    // TRANS: Less business/enterprise-oriented language for public sites.
                    _m('BUTTON', 'Send invite')
                );
            } else {
                $ibs = new InviteButtonSection($this);
            }
            $ibs->show();
        }

        $p = Profile::current();

        if (!common_config('performance', 'high')) {
            $cloud = new PublicTagCloudSection($this);
            $cloud->show();
        }
        $feat = new FeaturedUsersSection($this);
        $feat->show();
    }

    /**
     * Output <head> elements for RSS and Atom feeds
     *
     * @return void
     */
    function getFeeds()
    {
        return array(new Feed(Feed::JSON,
                              common_local_url('ApiTimelinePublic',
                                               array('format' => 'as')),
                              // TRANS: Link description for public timeline feed.
                              _('Public Timeline Feed (Activity Streams JSON)')),
                    new Feed(Feed::RSS1, common_local_url('publicrss'),
                              // TRANS: Link description for public timeline feed.
                              _('Public Timeline Feed (RSS 1.0)')),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelinePublic',
                                               array('format' => 'rss')),
                              // TRANS: Link description for public timeline feed.
                              _('Public Timeline Feed (RSS 2.0)')),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelinePublic',
                                               array('format' => 'atom')),
                              // TRANS: Link description for public timeline feed.
                              _('Public Timeline Feed (Atom)')));
    }
}
