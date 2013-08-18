<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Notice stream of notices with a given attachment
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
 * List notices that contain/link to/use a given URL
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NoticebyurlAction extends Action
{
    protected $url     = null;
    protected $file    = null;
    protected $notices = null;
    protected $page    = null;

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

        $this->file = File::getKV('id', $this->trimmed('id'));

        if (empty($this->file)) {
            // TRANS: Client exception thrown when an unknown URL is provided.
            throw new ClientException(_m('Unknown URL.'));
        }

        $pageArg = $this->trimmed('page');

        $this->page = (empty($pageArg)) ? 1 : intval($pageArg);

        $this->notices = $this->file->stream(($this->page - 1) * NOTICES_PER_PAGE,
                                             NOTICES_PER_PAGE + 1);

        return true;
    }

    /**
     * Title of the page
     *
     * @return string page title
     */
    function title()
    {
        if ($this->page == 1) {
            // TRANS: Title of notice stream of notices with a given attachment (first page).
            // TRANS: %s is the URL.
            return sprintf(_m('Notices linking to %s'), $this->file->url);
        } else {
            // TRANS: Title of notice stream of notices with a given attachment (all but first page).
            // TRANS: %1$s is the URL, %2$s is the page number.
            return sprintf(_m('Notices linking to %1$s, page %2$d'),
                           $this->file->url,
                           $this->page);
        }
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
        $this->showPage();
    }

    /**
     * Show main page content.
     *
     * Shows a list of the notices that link to the given URL
     *
     * @return void
     */
    function showContent()
    {
        $nl = new NoticeList($this->notices, $this);

        $nl->show();

        $cnt = $nl->show();

        $this->pagination($this->page > 1,
                          $cnt > NOTICES_PER_PAGE,
                          $this->page,
                          'noticebyurl',
                          array('id' => $this->file->id));
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

    /**
     * Return last modified, if applicable.
     *
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null
        return null;
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */
    function etag()
    {
        return null;
    }
}
