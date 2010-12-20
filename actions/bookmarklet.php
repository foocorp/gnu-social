<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Handler for posting new notices
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
 * @category  Bookmarklet
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/actions/newnotice.php';

/**
 * Action for posting a notice
 *
 * @category Bookmarklet
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class BookmarkletAction extends NewnoticeAction
{
    function showTitle()
    {
        // TRANS: Title for mini-posting window loaded from bookmarklet.
        // TRANS: %s is the StatusNet site name.
        $this->element('title', null, sprintf(_('Post to %s'), common_config('site', 'name')));
    }

    function showHeader()
    {
        $this->elementStart('div', array('id' => 'header'));
        $this->elementStart('address');
        $this->element('a', array('class' => 'url',
                                  'href' => common_local_url('public')),
                         '');
        $this->elementEnd('address');
        if (common_logged_in()) {
            $this->showNoticeForm();
        }
        $this->elementEnd('div');
    }

    function showCore()
    {
    }

    function showFooter()
    {
    }
}
