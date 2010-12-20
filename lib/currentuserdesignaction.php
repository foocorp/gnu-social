<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for actions that use the current user's design
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for actions that use the current user's design
 *
 * Some pages (settings in particular) use the current user's chosen
 * design. This superclass returns that design.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley    <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class CurrentUserDesignAction extends Action
{

    protected $cur = null; // The current user

    /**
     * For initializing members of the class. Set a the
     * current user here.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->cur = common_current_user();

	return true;
    }

    /**
     * A design for this action
     *
     * Returns the design preferences for the current user.
     *
     * @return Design a design object to use
     */
    function getDesign()
    {
        if (!empty($this->cur)) {

            $design = $this->cur->getDesign();

            if (!empty($design)) {
                return $design;
            }
        }

        return parent::getDesign();
    }

    function getCurrentUser()
    {
	return $this->cur;
    }
}

