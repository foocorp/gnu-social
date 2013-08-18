<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/actions/shownotice.php');

// @todo FIXME: Add documentation.
class FileAction extends Action
{
    var $id = null;
    var $filerec = null;

    function prepare($args)
    {
        parent::prepare($args);
        $this->id = $this->trimmed('notice');
        if (empty($this->id)) {
            // TRANS: Client error displayed when no notice ID was given trying do display a file.
            $this->clientError(_('No notice ID.'));
        }
        $notice = Notice::getKV('id', $this->id);
        if (empty($notice)) {
            // TRANS: Client error displayed when an invalid notice ID was given trying do display a file.
            $this->clientError(_('No notice.'));
        }
        $atts = $notice->attachments();
        if (empty($atts)) {
            // TRANS: Client error displayed when trying do display a file for a notice without a file attachement.
            $this->clientError(_('No attachments.'));
        }
        foreach ($atts as $att) {
            if (!empty($att->filename)) {
                $this->filerec = $att;
                break;
            }
        }
        if (empty($this->filerec)) {
            // XXX: Is this translation hint correct? If yes, please remove comment, if no, please correct and remove comment.
            // TRANS: Client error displayed when trying do display a file for a notice with file attachements
            // TRANS: that could not be found.
            $this->clientError(_('No uploaded attachments.'));
        }
        return true;
    }

    function handle() {
        common_redirect($this->filerec->url);
    }

    /**
     * Is this action read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }
}
