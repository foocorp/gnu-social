<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List of popular notices
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
 * @author    Zach Copley <zach@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * List of popular notices
 *
 * We provide a list of the most popular notices. Popularity
 * is measured by
 *
 * @category Personal
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class FavoritedAction extends Action
{
    var $page = null;

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        if ($this->page == 1) {
            // TRANS: Page title for first page of favorited notices.
            return _('Popular notices');
        } else {
            // TRANS: Page title for all but first page of favorited notices.
            // TRANS: %d is the page number being displayed.
            return sprintf(_('Popular notices, page %d'), $this->page);
        }
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Description on page displaying favorited notices.
        return _('The most popular notices on the site right now.');
    }

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     * @todo move queries from showContent() to here
     */
    function prepare($args)
    {
        parent::prepare($args);
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Handle request
     *
     * Shows a page with list of favorite notices
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        $this->showPage();
    }

    /**
     * Show the page notice
     *
     * Shows instructions for the page
     *
     * @return void
     */
    function showPageNotice()
    {
        $instr  = $this->getInstructions();
        $output = common_markup_to_html($instr);

        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    function showEmptyList()
    {
        // TRANS: Text displayed instead of a list when a site does not yet have any favourited notices.
        $message = _('Favorite notices appear on this page but no one has favorited one yet.') . ' ';

        if (common_logged_in()) {
            // TRANS: Additional text displayed instead of a list when a site does not yet have any favourited notices for logged in users.
            $message .= _('Be the first to add a notice to your favorites by clicking the fave button next to any notice you like.');
        }
        else {
            // TRANS: Additional text displayed instead of a list when a site does not yet have any favourited notices for not logged in users.
            // TRANS: %%action.register%% is a registration link. "[link text](link)" is Mark Down. Do not change the formatting.
            $message .= _('Why not [register an account](%%action.register%%) and be the first to add a notice to your favorites!');
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    /**
     * Content area
     *
     * Shows the list of popular notices
     *
     * @return void
     */
    function showContent()
    {
        $stream = new PopularNoticeStream($this->scoped);
        $notice = $stream->getNotices(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE+1);

        $nl = new NoticeList($notice, $this);

        $cnt = $nl->show();

        if ($cnt == 0) {
            $this->showEmptyList();
        }

        $this->pagination($this->page > 1, $cnt > NOTICES_PER_PAGE,
                          $this->page, 'favorited');
    }
}
