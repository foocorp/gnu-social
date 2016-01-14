<?php

/**
 * Information action
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for displaying dialog box like messages to the user
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see ErrorAction
 */

class InfoAction extends ManagedAction
{
    var $message = null;

    function __construct($title, $message, $output='php://output', $indent=null)
    {
        parent::__construct($output, $indent);

        $this->message = $message;
        $this->title   = $title;

        // XXX: hack alert: usually we aren't going to
        // call this page directly, but because it's
        // an action it needs an args array anyway
        $this->prepare($_REQUEST);
    }
    
    /**
     * Page title.
     *
     * @return page title
     */

    function title()
    {
        return empty($this->title) ? '' : $this->title;
    }

    function isReadOnly($args)
    {
        return true;
    }

    // Overload a bunch of stuff so the page isn't too bloated

    function showBody()
    {
        $this->elementStart('body', array('id' => 'error'));
        $this->elementStart('div', array('id' => 'wrap'));
        $this->showHeader();
        $this->showCore();
        $this->showFooter();
        $this->elementEnd('div');
        $this->elementEnd('body');
    }

    function showCore()
    {
        $this->elementStart('div', array('id' => 'core'));
        $this->elementStart('div', array('id' => 'aside_primary_wrapper'));
        $this->elementStart('div', array('id' => 'content_wrapper'));
        $this->elementStart('div', array('id' => 'site_nav_local_views_wrapper'));
        $this->showContentBlock();
        $this->elementEnd('div');
        $this->elementEnd('div');
        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    function showHeader()
    {
        $this->elementStart('div', array('id' => 'header'));
        $this->showLogo();
        $this->showPrimaryNav();
        $this->elementEnd('div');
    }

    /**
     * Display content.
     *
     * @return nothing
     */
    function showContent()
    {
        $this->element('div', array('class' => 'info'), $this->message);
    }

}
