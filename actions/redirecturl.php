<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Redirect to the given URL
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
 * @category  URL
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Redirect to a given URL
 *
 * This is our internal low-budget URL-shortener
 *
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class RedirecturlAction extends ManagedAction
{
    protected $file = null;

    protected function doPreparation()
    {
        $this->file = File::getByID($this->int('id'));

        return true;
    }

    public function showPage()
    {
        common_redirect($this->file->getUrl(false), 301);
    }

    function isReadOnly($args)
    {
        return true;
    }

    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null

        return strtotime($this->file->modified);
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
        return 'W/"' . implode(':', array($this->getActionName(),
                                          common_user_cache_hash(),
                                          common_language(),
                                          $this->file->getID())) . '"';
    }
}
