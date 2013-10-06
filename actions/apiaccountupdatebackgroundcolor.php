<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Update a user's background color
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
 * @package   GNUSocial
 * @author    Hannes Mannerheim <h@nnesmannerhe.im>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class ApiAccountUpdateBackgroundColorAction extends ApiAuthAction
{
    var $backgroundcolor = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->user   = $this->auth_user;

        $this->backgroundcolor = $this->trimmed('backgroundcolor');
        return true;
    }

    /**
     * Handle the request
     *
     * Try to save the user's colors in her design. Create a new design
     * if the user doesn't already have one.
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                _('This method requires a POST.'),
                400, $this->format
            );
            return;
        }
		
		$validhex = preg_match('/^[a-f0-9]{6}$/i',$this->backgroundcolor);
		if($validhex === false || $validhex == 0) {
            $this->clientError(_('Not a valid hex color.'),404,'json');			
            return;
			}
		
		// save the new color
		$original = clone($this->user);
		$this->user->backgroundcolor = $this->backgroundcolor; 
		if (!$this->user->update($original)) {
            $this->clientError(_('Error updating user.'),404,'json');
            return;
		}

        $profile = $this->user->getProfile();

        if (empty($profile)) {
            $this->clientError(_('User has no profile.'),'json');
            return;
        }

        $twitter_user = $this->twitterUserArray($profile, true);

        $this->initDocument('json');
        $this->showJsonObjects($twitter_user);
        $this->endDocument('json');
    }


}
