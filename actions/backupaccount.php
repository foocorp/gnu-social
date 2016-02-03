<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Download a backup of your own account to the browser
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
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Download a backup of your own account to the browser
 *
 * We go through some hoops to make this only respond to POST, since
 * it's kind of expensive and there's probably some downside to having
 * your account in all kinds of search engines.
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class BackupaccountAction extends FormAction
{
    protected $form = 'BackupAccount';

    function title()
    {
        // TRANS: Title for backup account page.
        return _('Backup account');
    }

    protected function doPreparation()
    {
        if (!$this->scoped->hasRight(Right::BACKUPACCOUNT)) {
            // TRANS: Client exception thrown when trying to backup an account without having backup rights.
            throw new ClientException(_('You may not backup your account.'), 403);
        }

        return true;
    }

    protected function doPost()
    {
        $stream = new UserActivityStream($this->scoped->getUser(), true, UserActivityStream::OUTPUT_RAW);

        header('Content-Disposition: attachment; filename='.urlencode($this->scoped->getNickname()).'.atom');
        header('Content-Type: application/atom+xml; charset=utf-8');

        // @fixme atom feed logic is in getString...
        // but we just want it to output to the outputter.
        $this->raw($stream->getString());
    }

    public function isReadOnly($args) {
        return true;
    }

    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null
        return null;
    }

    function etag()
    {
        return null;
    }
}
