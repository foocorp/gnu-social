<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2012, StatusNet, Inc.
 *
 * Stream of latest spam messages
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
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

require_once INSTALLDIR.'/lib/noticelist.php';

/**
 * SpamAction
 * 
 * Shows the latest spam on the service
 * 
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class SpamAction extends Action
{
    var $page = null;
    var $notices = null;

    function title() {
        return _("Latest Spam");
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */

    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        // User must be logged in.

        $user = common_current_user();

        if (empty($user)) {
            throw new ClientException(_("You must be logged in to review."), 403);
        }

        // User must have the right to review spam

        if (!$user->hasRight(ActivitySpamPlugin::REVIEWSPAM)) {
            throw new ClientException(_('You cannot review spam on this site.'), 403);
        }

        $stream = new SpamNoticeStream($user->getProfile());

        $this->notices = $stream->getNotices(($this->page-1)*NOTICES_PER_PAGE,
                                             NOTICES_PER_PAGE + 1);

        if($this->page > 1 && $this->notices->N == 0) {
            throw new ClientException(_('No such page.'), 404);
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */

    function handle($argarray=null)
    {
        parent::handle($args);

        $this->showPage();
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
        $nl = new NoticeList($this->notices, $this);

        $cnt = $nl->show();

        if ($cnt == 0) {
            $this->showEmptyList();
        }

        $this->pagination($this->page > 1, 
                          $cnt > NOTICES_PER_PAGE,
                          $this->page,
                          'spam');
    }

    function showEmptyList()
    {
        // TRANS: Text displayed for public feed when there are no public notices.
        $message = _('This is the timeline of spam messages for %%site.name%% but none have been detected yet.');

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        return true;
    }
}
