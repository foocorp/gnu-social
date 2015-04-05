<?php
/**
 * GNU social - a federating social network
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
 * @category  Plugin
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2015 Free Software Foundaction, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://gnu.io/social
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ActivityverbAction extends ManagedAction
{
    protected $needLogin = true;
    protected $canPost   = true;

    protected $verb      = null;

    public function title()
    {
        $title = null;
        Event::handle('ActivityVerbTitle', array($this, $this->verb, $this->notice, $this->scoped, &$title));
        return $title;
    }

    protected function doPreparation()
    {
        $this->verb = $this->trimmed('verb');
        if (empty($this->verb)) {
            throw new ServerException('A verb has not been specified.');
        }

        $this->notice = Notice::getById($this->trimmed('id'));

        if (!$this->notice->inScope($this->scoped)) {
            // TRANS: %1$s is a user nickname, %2$d is a notice ID (number).
            throw new ClientException(sprintf(_('%1$s has no access to notice %2$d.'),
                                        $this->scoped->getNickname(), $this->notice->getID()), 403);
        }

        Event::handle('ActivityVerbDoPreparation', array($this, $this->verb, $this->notice, $this->scoped));
    }

    protected function doPost()
    {
        if (Event::handle('ActivityVerbDoPost', array($this, $this->verb, $this->notice, $this->scoped))) {
            // TRANS: Error when a POST method for an activity verb has not been handled by a plugin.
            throw new ClientException(sprintf(_('Could not handle POST for verb "%1$s".'), $this->verb));
        }
    }

    protected function showContent()
    {
        if (Event::handle('ActivityVerbShowContent', array($this, $this->verb, $this->notice, $this->scoped))) {
            // TRANS: Error when a page for an activity verb has not been handled by a plugin.
            $this->element('div', 'error', sprintf(_('Could not show content for verb "%1$s".'), $this->verb));
        }
    }
}
