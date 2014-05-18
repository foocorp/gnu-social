<?php
/**
 * Repeat action.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Repeat action
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class RepeatAction extends FormAction
{
    protected $needPost = true; // At least for now, until repeat interface is available

    protected $notice = null;   // Notice that is being repeated.
    protected $repeat = null;   // The resulting repeat object/notice.

    protected function prepare(array $args=array())
    {
        parent::prepare($args);

        $id = $this->trimmed('notice');

        if (empty($id)) {
            // TRANS: Client error displayed when trying to repeat a notice while not providing a notice ID.
            $this->clientError(_('No notice specified.'));
        }

        $this->notice = Notice::getKV('id', $id);

        if (!$this->notice instanceof Notice) {
            // TRANS: Client error displayed when trying to repeat a non-existing notice.
            $this->clientError(_('Notice not found.'));
        }

        $this->repeat = $this->notice->repeat($this->scoped->id, 'web');
        if (!$this->repeat instanceof Notice) {
            // TRANS: Error when unable to repeat a notice for unknown reason.
            $this->clientError(_('Could not repeat notice for unknown reason. Please contact the webmaster!'));
        }

        return true;
    }

    /**
     * Class handler.
     *
     * @param array $args query arguments
     *
     * @return void
     */
    protected function showContent()
    {
        $this->element('p', array('id' => 'repeat_response',
                                  'class' => 'repeated'),
                            // TRANS: Confirmation text after repeating a notice.
                            _('Repeated!'));
    }
}
