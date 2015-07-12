<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for deleting a notice
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Personal
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

// @todo FIXME: documentation needed.
class DeletenoticeAction extends FormAction
{
    protected $notice = null;

    protected function doPreparation()
    {
        $this->notice = Notice::getByID($this->trimmed('notice'));

        if (!$this->scoped->sameAs($this->notice->getProfile()) &&
                   !$this->scoped->hasRight(Right::DELETEOTHERSNOTICE)) {
            // TRANS: Error message displayed trying to delete a notice that was not made by the current user.
            $this->clientError(_('Cannot delete this notice.'));
        }

        $this->formOpts['notice'] = $this->notice;
    }

    function getInstructions()
    {
        // TRANS: Instructions for deleting a notice.
        return _('You are about to permanently delete a notice. ' .
                 'Once this is done, it cannot be undone.');
    }

    function title()
    {
        // TRANS: Page title when deleting a notice.
        return _('Delete notice');
    }

    protected function doPost()
    {
        if ($this->arg('yes')) {
            if (Event::handle('StartDeleteOwnNotice', array($this->scoped->getUser(), $this->notice))) {
                $this->notice->delete();
                Event::handle('EndDeleteOwnNotice', array($this->scoped->getUser(), $this->notice));
            }
        } else {
            common_redirect(common_get_returnto(), 303);
        }

        common_redirect(common_local_url('top'), 303);
    }
}
