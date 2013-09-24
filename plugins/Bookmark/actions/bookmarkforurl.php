<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Returns a pre-filled bookmark form for a given URL
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
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Returns a prefilled bookmark form for a given URL
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class BookmarkforurlAction extends Action
{
    protected $url        = null;
    protected $oembed     = null;
    protected $thumbnail  = null;
    protected $title      = null;

    /**
     * For initializing members of the class.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     */
    function prepare($args)
    {
        parent::prepare($args);

        if (!$this->isPost()) {
            throw new ClientException(_('POST only'), 405);
        }

        $this->checkSessionToken();
        $this->url = $this->trimmed('url');

        if (empty($this->url)) {
            throw new ClientException(_('URL is required.'), 400);
        }

        if (!Validate::uri($this->url, array('allowed_schemes' => array('http', 'https')))) {
            throw new ClientException(_('Invalid URL.'), 400);
        }

        $f = File::getKV('url', $this->url);

        if (empty($url)) { 
           $f = File::processNew($this->url);
        }

        // How about now?

        if (!empty($f)) {
            $this->oembed    = File_oembed::getKV('file_id', $f->id);
            if (!empty($this->oembed)) {
                $this->title = $this->oembed->title;
            }
            $this->thumbnail = File_thumbnail::getKV('file_id', $f->id);
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $args is ignored since it's now passed in in prepare()
     *
     * @return void
     */

    function handle($args=null)
    {
        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        $this->element('title', null, _('Bookmark form'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $bf = new BookmarkForm($this, $this->title, $this->url, null, null, $this->thumbnail);
        $bf->show();
        $this->elementEnd('body');
        $this->endHTML();
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
        return false;
    }
}
