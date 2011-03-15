<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Form for adding a new bookmark
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
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Form to add a new bookmark
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class BookmarkForm extends Form
{
    private $_title       = null;
    private $_url         = null;
    private $_tags        = null;
    private $_description = null;

    /**
     * Construct a bookmark form
     *
     * @param HTMLOutputter $out         output channel
     * @param string        $title       Title of the bookmark
     * @param string        $url         URL of the bookmark
     * @param string        $tags        Tags to show
     * @param string        $description Description of the bookmark
     *
     * @return void
     */

    function __construct($out=null, $title=null, $url=null, $tags=null,
                         $description=null)
    {
        parent::__construct($out);

        $this->_title       = $title;
        $this->_url         = $url;
        $this->_tags        = $tags;
        $this->_description = $description;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_new_bookmark';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_settings ajax-notice';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('newbookmark');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'new_bookmark_data'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->out->input('title',
                          _('Title'),
                          $this->_title,
                          _('Title of the bookmark'));
        $this->unli();

        $this->li();
        $this->out->input('url',
                          _('URL'),
                          $this->_url,   
                          _('URL to bookmark'));
        $this->unli();

        $this->li();
        $this->out->input('tags',
                          _('Tags'),
                          $this->_tags,   
                          _('Comma- or space-separated list of tags'));
        $this->unli();

        $this->li();
        $this->out->input('description',
                          _('Description'),
                          $this->_description,   
                          _('Description of the URL'));
        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _m('BUTTON', 'Save'));
    }
}
