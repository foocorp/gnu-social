<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Add a new bookmark
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
 * Add a new bookmark
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NewbookmarkAction extends FormAction
{
    protected $complete    = null;
    protected $title       = null;
    protected $url         = null;
    protected $tags        = null;
    protected $description = null;

    function title()
    {
        // TRANS: Title for action to create a new bookmark.
        return _m('New bookmark');
    }

    protected function doPreparation()
    {
        $this->title       = $this->trimmed('title');
        $this->url         = $this->trimmed('url');
        $this->tags        = preg_split('/[\s,]+/', $this->trimmed('tags'), null,  PREG_SPLIT_NO_EMPTY);
        $this->description = $this->trimmed('description');

        return true;
    }

    /**
     * Add a new bookmark
     *
     * @return void
     */
    function handlePost()
    {
        if (empty($this->title)) {
            // TRANS: Client exception thrown when trying to create a new bookmark without a title.
            throw new ClientException(_m('Bookmark must have a title.'));
        }

        if (empty($this->url)) {
            // TRANS: Client exception thrown when trying to create a new bookmark without a URL.
            throw new ClientException(_m('Bookmark must have an URL.'));
        }

        $options = array();

        ToSelector::fillOptions($this, $options);

        $saved = Bookmark::addNew($this->scoped,
                                   $this->title,
                                   $this->url,
                                   $this->tags,
                                   $this->description,
                                   $options);
    }

    /**
     * Output a notice
     *
     * Used to generate the notice code for Ajax results.
     *
     * @param Notice $notice Notice that was saved
     *
     * @return void
     */
    function showNotice(Notice $notice)
    {
        class_exists('NoticeList'); // @fixme hack for autoloader
        $nli = new NoticeListItem($notice, $this);
        $nli->show();
    }

    /**
     * Show the bookmark form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new BookmarkForm($this,
                                 $this->title,
                                 $this->url,
                                 $this->tags,
                                 $this->description);

        $form->show();

        return;
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
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }
}
