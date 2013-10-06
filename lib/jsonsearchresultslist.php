<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notices
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
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * widget-like class for showing JSON search results
 *
 * @category Search
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */

class JSONSearchResultsList
{
    protected $notice;  // protected attrs invisible to json_encode()
    protected $rpp;

    // The below attributes are carefully named so the JSON output from
    // this obj matches the output from search.twitter.com

    var $results;
    var $since_id;
    var $max_id;
    var $refresh_url;
    var $results_per_page;
    var $completed_in;
    var $page;
    var $query;

    /**
     * constructor
     *
     * @param Notice $notice   stream of notices from DB_DataObject
     * @param string $query    the original search query
     * @param int    $rpp      the number of results to display per page
     * @param int    $page     a page offset
     * @param int    $since_id only display notices newer than this
     */

    function __construct($notice, $query, $rpp, $page, $since_id = 0)
    {
        $this->notice           = $notice;
        $this->query            = urlencode($query);
        $this->results_per_page = $rpp;
        $this->rpp              = $rpp;
        $this->page             = $page;
        $this->since_id         = $since_id;
        $this->results          = array();
    }

    /**
     * show the list of search results
     *
     * @return int $count of the search results listed.
     */

    function show()
    {
        $cnt = 0;
        $this->max_id = 0;

        $time_start = microtime(true);

        while ($this->notice->fetch() && $cnt <= $this->rpp) {
            $cnt++;

            // XXX: Hmmm. this depends on desc sort order
            if (!$this->max_id) {
                $this->max_id = (int)$this->notice->id;
            }

            if ($this->since_id && $this->notice->id <= $this->since_id) {
                break;
            }

            if ($cnt > $this->rpp) {
                break;
            }

            $profile = $this->notice->getProfile();

            // Don't show notices from deleted users

            if (!empty($profile)) {
                $item = new ResultItem($this->notice);
                array_push($this->results, $item);
            }
        }

        $time_end           = microtime(true);
        $this->completed_in = $time_end - $time_start;

        // Set other attrs

        $this->refresh_url = '?since_id=' . $this->max_id .
            '&q=' . $this->query;

        // pagination stuff

        if ($cnt > $this->rpp) {
            $this->next_page = '?page=' . ($this->page + 1) .
                '&max_id=' . $this->max_id;
            if ($this->rpp != 15) {
                $this->next_page .= '&rpp=' . $this->rpp;
            }
            $this->next_page .= '&q=' . $this->query;
        }

        if ($this->page > 1) {
            $this->previous_page = '?page=' . ($this->page - 1) .
                '&max_id=' . $this->max_id;
            if ($this->rpp != 15) {
                $this->previous_page .= '&rpp=' . $this->rpp;
            }
            $this->previous_page .= '&q=' . $this->query;
        }

        print json_encode($this);

        return $cnt;
    }
}

/**
 * widget for displaying a single JSON search result
 *
 * @category UI
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      JSONSearchResultsList
 */

class ResultItem
{
    /** The notice this item is based on. */

    protected $notice;  // protected attrs invisible to json_encode()

    /** The profile associated with the notice. */

    protected $profile;

    // The below attributes are carefully named so the JSON output from
    // this obj matches the output from search.twitter.com

    var $text;
    var $to_user_id;
    var $to_user;
    var $from_user;
    var $id;
    var $from_user_id;
    var $iso_language_code;
    var $source;
    var $profile_image_url;
    var $created_at;

    /**
     * constructor
     *
     * Also initializes the profile attribute.
     *
     * @param Notice $notice The notice we'll display
     */

    function __construct($notice)
    {
        $this->notice  = $notice;
        $this->profile = $notice->getProfile();
        $this->buildResult();
    }

    /**
     * Build a search result object
     *
     * This populates the the result in preparation for JSON encoding.
     *
     * @return void
     */

    function buildResult()
    {
        $this->text      = $this->notice->content;
        $replier_profile = null;

        if ($this->notice->reply_to) {
            $reply = Notice::getKV(intval($this->notice->reply_to));
            if ($reply) {
                $replier_profile = $reply->getProfile();
            }
        }

        $this->to_user_id = ($replier_profile) ?
            intval($replier_profile->id) : null;
        $this->to_user    = ($replier_profile) ?
            $replier_profile->nickname : null;

        $this->from_user    = $this->profile->nickname;
        $this->id           = $this->notice->id;
        $this->from_user_id = $this->profile->id;

        $this->iso_language_code = Profile_prefs::getConfigData($this->profile, 'site', 'language');
        
        $this->source = $this->getSourceLink($this->notice->source);

        $this->profile_image_url = $this->profile->avatarUrl(AVATAR_STREAM_SIZE);

        $this->created_at = common_date_rfc2822($this->notice->created);
    }

    /**
     * Show the source of the notice
     *
     * Either the name (and link) of the API client that posted the notice,
     * or one of other other channels.
     *
     * @param string $source the source of the Notice
     *
     * @return string a fully rendered source of the Notice
     */
    function getSourceLink($source)
    {
        // Gettext translations for the below source types are available.
        $source_name = _($source);
        switch ($source) {
        case 'web':
        case 'xmpp':
        case 'mail':
        case 'omb':
        case 'api':
            break;
        default:
            $ns = Notice_source::getKV($source);
            if ($ns) {
                $source_name = '<a href="' . $ns->url . '">' . $ns->name . '</a>';
            }
            break;
        }

        return $source_name;
    }
}
