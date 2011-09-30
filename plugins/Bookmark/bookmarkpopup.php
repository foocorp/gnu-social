<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Post a new bookmark in a popup window
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
 * @category  Bookmark
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Action for posting a new bookmark
 *
 * @category Bookmark
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link     http://status.net/
 */
class BookmarkpopupAction extends NewbookmarkAction
{
    /**
     * Show the title section of the window
     *
     * @return void
     */
    function showTitle()
    {
        $this->element('title',
                       // TRANS: Title for mini-posting window loaded from bookmarklet.
                       // TRANS: %s is the StatusNet site name.
                       null, sprintf(_m('Bookmark on %s'),
                                     common_config('site', 'name')));
    }

    /**
     * Show the header section of the page
     *
     * Shows a stub page and the bookmark form.
     *
     * @return void
     */
    function showHeader()
    {
        $this->elementStart('div', array('id' => 'header'));
        $this->elementStart('address');
        $this->element('a', array('class' => 'url',
                                  'href' => common_local_url('top')),
                         '');
        $this->elementEnd('address');
        if (common_logged_in()) {
            $form = new BookmarkForm($this,
                                     $this->title,
                                     $this->url);
            $form->show();
        }
        $this->elementEnd('div');
    }

    /**
     * Hide the core section of the page
     *
     * @return void
     */
    function showCore()
    {
    }

    /**
     * Hide the footer section of the page
     *
     * @return void
     */
    function showFooter()
    {
    }

    function showScripts()
    {
        parent::showScripts();
        $this->script(Plugin::staticPath('Bookmark', 'bookmarkpopup.js'));
    }
}
