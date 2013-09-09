<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for API actions that only require auth when a site
 * is configured to be private
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
 * @package   StatusNet
 * @author    Adrian Lang <mail@adrianlang.de>
 * @author    Brenda Wallace <shiny@cpan.org>
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Dan Moore <dan@moore.cx>
 * @author    Evan Prodromou <evan@status.net>
 * @author    mEDI <medi@milaro.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Actions extending this class will require auth only if a site is private
 *
 * @category API
 * @package  StatusNet
 * @author   Adrian Lang <mail@adrianlang.de>
 * @author   Brenda Wallace <shiny@cpan.org>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Dan Moore <dan@moore.cx>
 * @author   Evan Prodromou <evan@status.net>
 * @author   mEDI <medi@milaro.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiPrivateAuthAction extends ApiAuthAction
{
   /**
     * Does this API resource require authentication?
     *
     * @return boolean true or false
     */
    function requiresAuth()
    {
        // If the site is "private", all API methods except statusnet/config
        // need authentication
        if (common_config('site', 'private')) {
            return true;
        }

        return false;
    }
}
