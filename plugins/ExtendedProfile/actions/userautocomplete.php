<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action for showing Twitter-like JSON search results
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
 * @category  Search
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}


class UserautocompleteAction extends Action
{
    var $query;

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean true if nothing goes wrong
     */
    function prepare($args)
    {
        parent::prepare($args);
        $this->query = $this->trimmed('term');
        return true;
    }

    /**
     * Handle a request
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showResults();
    }

    /**
     * Search for users matching the query and spit the results out
     * as a quick-n-dirty JSON document
     *
     * @return void
     */
    function showResults()
    {
        $people = array();

        $profile = new Profile();

        $search_engine = $profile->getSearchEngine('profile');
        $search_engine->set_sort_mode('nickname_desc');
        $search_engine->limit(0, 10);
        $search_engine->query(strtolower($this->query . '*'));

        $cnt = $profile->find();

        if ($cnt > 0) {

            $sql = 'SELECT profile.* FROM profile, user WHERE profile.id = user.id '
                . ' AND LEFT(LOWER(profile.nickname), '
                . strlen($this->query)
                . ') = \'%s\' '
                . ' LIMIT 0, 10';

            $profile->query(sprintf($sql, $this->query));
        }
        
        while ($profile->fetch()) {
             $people[] = $profile->nickname;
        }

        header('Content-Type: application/json; charset=utf-8');
        print json_encode($people);
    }

    /**
     * Do we need to write to the database?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }
}
