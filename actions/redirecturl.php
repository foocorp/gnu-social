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

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

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

class RedirecturlAction extends Action
{
    protected $id   = null;
    protected $file = null;

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

        $this->id = $this->trimmed('id');

        if (empty($this->id)) {
            // TRANS: Client exception thrown when no ID parameter was provided.
            throw new ClientException(_('No id parameter.'));
        }

        $this->file = File::getKV('id', $this->id);

        if (empty($this->file)) {
            // TRANS: Client exception thrown when an invalid ID parameter was provided for a file.
            // TRANS: %d is the provided ID for which the file is not present (number).
            throw new ClientException(sprintf(_('No such file "%d".'),
                                              $this->id),
                                      404);
        }

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
        common_redirect($this->file->url, 307);
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
        return 'W/"' . implode(':', array($this->arg('action'),
                                          common_user_cache_hash(),
                                          common_language(),
                                          $this->file->id)) . '"';
    }
}
