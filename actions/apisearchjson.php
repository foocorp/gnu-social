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
 * @copyright 2008-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/apiprivateauth.php';
require_once INSTALLDIR.'/lib/jsonsearchresultslist.php';

/**
 * Action handler for Twitter-compatible API search
 *
 * @category Search
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      ApiAction
 */
class ApiSearchJSONAction extends ApiPrivateAuthAction
{
    var $query;
    var $lang;
    var $rpp;
    var $page;
    var $since_id;
    var $limit;
    var $geocode;

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean true if nothing goes wrong
     */
    function prepare($args)
    {
        common_debug("apisearchjson prepare()");

        parent::prepare($args);

        $this->query = $this->trimmed('q');
        $this->lang  = $this->trimmed('lang');
        $this->rpp   = $this->trimmed('rpp');

        if (!$this->rpp) {
            $this->rpp = 15;
        }

        if ($this->rpp > 100) {
            $this->rpp = 100;
        }

        $this->page = $this->trimmed('page');

        if (!$this->page) {
            $this->page = 1;
        }

        // TODO: Suppport max_id -- we need to tweak the backend
        // Search classes to support it.

        $this->since_id = $this->trimmed('since_id');
        $this->geocode  = $this->trimmed('geocode');

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
     * Show search results
     *
     * @return void
     */
    function showResults()
    {
        // TODO: Support search operators like from: and to:, boolean, etc.

        $notice = new Notice();

        // lcase it for comparison
        $q = strtolower($this->query);

        $search_engine = $notice->getSearchEngine('notice');
        $search_engine->set_sort_mode('chron');
        $search_engine->limit(($this->page - 1) * $this->rpp, $this->rpp + 1, true);
        if (false === $search_engine->query($q)) {
            $cnt = 0;
        } else {
            $cnt = $notice->find();
        }

        // TODO: max_id, lang, geocode

        $results = new JSONSearchResultsList($notice, $q, $this->rpp, $this->page, $this->since_id);

        $this->initDocument('json');
        $results->show();
        $this->endDocument('json');
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
