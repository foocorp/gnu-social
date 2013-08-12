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
class NewbookmarkAction extends Action
{
    protected $user        = null;
    protected $error       = null;
    protected $complete    = null;
    protected $title       = null;
    protected $url         = null;
    protected $tags        = null;
    protected $description = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Title for action to create a new bookmark.
        return _m('New bookmark');
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

        if ($this->boolean('ajax')) {
            StatusNet::setApi(true);
        }

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown when trying to create a new bookmark while not logged in.
            throw new ClientException(_m('Must be logged in to post a bookmark.'),
                                      403);
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        $this->title       = $this->trimmed('title');
        $this->url         = $this->trimmed('url');
        $this->tags        = $this->trimmed('tags');
        $this->description = $this->trimmed('description');

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
        parent::handle($argarray);

        if ($this->isPost()) {
            $this->newBookmark();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Add a new bookmark
     *
     * @return void
     */
    function newBookmark()
    {
        try {
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

            $saved = Bookmark::saveNew($this->user->getProfile(),
                                       $this->title,
                                       $this->url,
                                       $this->tags,
                                       $this->description,
                                       $options);

        } catch (ClientException $ce) {
            if ($this->boolean('ajax')) {
                header('Content-Type: text/xml;charset=utf-8');
                $this->xw->startDocument('1.0', 'UTF-8');
                $this->elementStart('html');
                $this->elementStart('head');
                // TRANS: Page title after an AJAX error occurs
                $this->element('title', null, _('Ajax Error'));
                $this->elementEnd('head');
                $this->elementStart('body');
                $this->element('p', array('id' => 'error'), $ce->getMessage());
                $this->elementEnd('body');
                $this->elementEnd('html');
                return;
            } else {
                $this->error = $ce->getMessage();
                $this->showPage();
                return;
            }
        }

        if ($this->boolean('ajax')) {
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after posting a bookmark.
            $this->element('title', null, _m('Bookmark posted'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showNotice($saved);
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect($saved->bestUrl(), 303);
        }
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
    function showNotice($notice)
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
