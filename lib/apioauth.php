<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base action for OAuth API endpoints
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}
require_once INSTALLDIR . '/lib/apiaction.php';
require_once INSTALLDIR . '/lib/statusnetoauthstore.php';

/**
 * Base action for API OAuth enpoints. Clean up the
 * request. Some other common functions.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ApiOauthAction extends ApiAction
{
    /**
     * Is this a read-only action?
     *
     * @return boolean false
     */
    function isReadOnly($args)
    {
        return false;
    }

    function prepare($args)
    {
        parent::prepare($args);
        return true;
    }

    /**
     * Handle input, produce output
     *
     * Switches on request method; either shows the form or handles its input.
     *
     * @param array $args $_REQUEST data
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        self::cleanRequest();
    }

    /*
     * Clean up the request so the OAuth library doesn't find
     * any extra parameters or anything else it's not expecting.
     * I'm looking at you, p parameter.
     */

    static function cleanRequest()
    {
        // kill evil effects of magical slashing
        if (get_magic_quotes_gpc() == 1) {
            $_POST = array_map('stripslashes', $_POST);
            $_GET = array_map('stripslashes', $_GET);
        }

        // strip out the p param added in index.php
        unset($_GET['p']);
        unset($_POST['p']);
        unset($_REQUEST['p']);

        $queryArray = explode('&', $_SERVER['QUERY_STRING']);

        for ($i = 0; $i < sizeof($queryArray); $i++) {
            if (substr($queryArray[$i], 0, 2) == 'p=') {
                unset($queryArray[$i]);
            }
        }

        $_SERVER['QUERY_STRING'] = implode('&', $queryArray);
    }

}
