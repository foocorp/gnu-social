<?php
        	
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Check nickname
 *
 * Returns 1 if nickname is ok, 0 if not
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
 * @category  API
 * @package   GNUsocial
 * @author    Hannes Mannerheim <h@nnesmannerhe.im>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ApiCheckNicknameAction extends ApiAction
{

    protected function prepare($args)
    {
        parent::prepare($args);

        if ($this->format !== 'json') {
            $this->clientError('This method currently only serves JSON.', 415);
        }

        return true;
    }

    protected function handle()
    {
        parent::handle();

        $nickname = $this->trimmed('nickname');

        try {
            Nickname::normalize($nickname, true);
            $nickname_ok = 1;
        } catch (NicknameException $e) {
            $nickname_ok = 0;
        }

        $this->initDocument('json');
        $this->showJsonObjects($nickname_ok);
        $this->endDocument('json');
    }
}
