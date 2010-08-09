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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/publicgroupnav.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

// Farther than any human will go

define('MAX_PUBLIC_PAGE', 100);

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

class PublicAction extends Action
{
    /**
     * page of the stream we're on; default = 1
     */

    var $page = null;
    var $notice;

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Read and validate arguments
     *
     * @param array $args URL parameters
     *
     * @return boolean success value
     */

    function prepare($args)
    {
        parent::prepare($args);
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        if ($this->page > MAX_PUBLIC_PAGE) {
            $this->clientError(sprintf(_("Beyond the page limit (%s)."), MAX_PUBLIC_PAGE));
        }

        common_set_returnto($this->selfUrl());

        $this->notice = Notice::publicStream(($this->page-1)*NOTICES_PER_PAGE,
                                       NOTICES_PER_PAGE + 1);

        if (!$this->notice) {
            $this->serverError(_('Could not retrieve public stream.'));
            return;
        }

        if($this->page > 1 && $this->notice->N == 0){
            // TRANS: Server error when page not found (404)
            $this->serverError(_('No such page.'),$code=404);
        }

        return true;
    }

    /**
     * handle request
     *
     * Show the public stream, using recipe method showPage()
     *
     * @param array $args arguments, mostly unused
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        $this->showPage();
    }

    /**
     * Title of the page
     *
     * @return page title, including page number if over 1
     */

    function title()
    {
        if ($this->page > 1) {
            return sprintf(_('Public timeline, page %d'), $this->page);
        } else {
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
    }

    /**
     * Output <head> elements for RSS and Atom feeds
     *
     * @return void
     */

    function getFeeds()
    {
        return array(new Feed(Feed::RSS1, common_local_url('publicrss'),
                              _('Public Stream Feed (RSS 1.0)')),
                     new Feed(Feed::RSS2,
                              common_local_url('ApiTimelinePublic',
                                               array('format' => 'rss')),
                              _('Public Stream Feed (RSS 2.0)')),
                     new Feed(Feed::ATOM,
                              common_local_url('ApiTimelinePublic',
                                               array('format' => 'atom')),
                              _('Public Stream Feed (Atom)')));
    }

    /**
     * Show tabset for this page
     *
     * Uses the PublicGroupNav widget
     *
     * @return void
     * @see PublicGroupNav
     */

    function showLocalNav()
    {
        $nav = new PublicGroupNav($this);
        $nav->show();
    }

    function showEmptyList()
    {
        $message = _('This is the public timeline for %%site.name%% but no one has posted anything yet.') . ' ';

        if (common_logged_in()) {
            $message .= _('Be the first to post!');
        }
        else {
            if (! (common_config('site','closed') || common_config('site','inviteonly'))) {
                $message .= _('Why not [register an account](%%action.register%%) and be the first to post!');
            }
	}

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    /**
     * Fill the content area
     *
     * Shows a list of the notices in the public stream, with some pagination
     * controls.
     *
     * @return void
     */

    function showContent()
    {
        $nl = new NoticeList($this->notice, $this);

        $cnt = $nl->show();

        if ($cnt == 0) {
            $this->showEmptyList();
        }

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'public');
    }

    function showSections()
    {
        // $top = new TopPostersSection($this);
        // $top->show();
        $pop = new PopularNoticeSection($this);
        $pop->show();
        $gbp = new GroupsByMembersSection($this);
        $gbp->show();
        $feat = new FeaturedUsersSection($this);
        $feat->show();
    }

    function showAnonymousMessage()
    {
        if (! (common_config('site','closed') || common_config('site','inviteonly'))) {
            $m = _('This is %%site.name%%, a social networking site. ' .
                   '[Join now](%%action.register%%) to share notices about yourself with friends, family, and colleagues! ' .
                   '([Read more](%%doc.help%%))');
        } else {
            $m = _('This is %%site.name%%, a social networking site.');
        }
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($m));
        $this->elementEnd('div');
    }
}
