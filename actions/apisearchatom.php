<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action for showing Twitter-like Atom search results
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

/**
 * Action for outputting search results in Twitter compatible Atom
 * format.
 *
 * TODO: abstract Atom stuff into a ruseable base class like
 * RSS10Action.
 *
 * @category Search
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      ApiPrivateAuthAction
 */
class ApiSearchAtomAction extends ApiPrivateAuthAction
{
    var $cnt;
    var $query;
    var $lang;
    var $rpp;
    var $page;
    var $since_id;
    var $geocode;

    /**
     * Constructor
     *
     * Just wraps the Action constructor.
     *
     * @param string  $output URI to output to, default = stdout
     * @param boolean $indent Whether to indent output, default true
     *
     * @see Action::__construct
     */
    function __construct($output='php://output', $indent=null)
    {
        parent::__construct($output, $indent);
    }

    /**
     * Do we need to write to the database?
     *
     * @return boolean true
     */
    function isReadonly()
    {
        return true;
    }

    /**
     * Read arguments and initialize members
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return boolean success
     */
    function prepare($args)
    {
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

        // TODO: Also, language and geocode

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
        common_debug("In apisearchatom handle()");
        $this->showAtom();
    }

    /**
     * Get the notices to output as results. This also sets some class
     * attrs so we can use them to calculate pagination, and output
     * since_id and max_id.
     *
     * @return array an array of Notice objects sorted in reverse chron
     */
    function getNotices()
    {
        // TODO: Support search operators like from: and to:, boolean, etc.

        $notices = array();
        $notice = new Notice();

        // lcase it for comparison
        $q = strtolower($this->query);

        $search_engine = $notice->getSearchEngine('notice');
        $search_engine->set_sort_mode('chron');
        $search_engine->limit(($this->page - 1) * $this->rpp,
            $this->rpp + 1, true);
        if (false === $search_engine->query($q)) {
            $this->cnt = 0;
        } else {
            $this->cnt = $notice->find();
        }

        $cnt = 0;
        $this->max_id = 0;

        if ($this->cnt > 0) {
            while ($notice->fetch()) {
                ++$cnt;

                if (!$this->max_id) {
                    $this->max_id = $notice->id;
                }

                if ($this->since_id && $notice->id <= $this->since_id) {
                    break;
                }

                if ($cnt > $this->rpp) {
                    break;
                }

                $notices[] = clone($notice);
            }
        }

        return $notices;
    }

    /**
     * Output search results as an Atom feed
     *
     * @return void
     */
    function showAtom()
    {
        $notices = $this->getNotices();

        $this->initAtom();
        $this->showFeed();

        foreach ($notices as $n) {
            $profile = $n->getProfile();

            // Don't show notices from deleted users

            if (!empty($profile)) {
                $this->showEntry($n);
            }
        }

        $this->endAtom();
    }

    /**
     * Show feed specific Atom elements
     *
     * @return void
     */
    function showFeed()
    {
        // TODO: A9 OpenSearch stuff like search.twitter.com?

        $server   = common_config('site', 'server');
        $sitename = common_config('site', 'name');

        // XXX: Use xmlns:statusnet instead?

        $this->elementStart('feed',
            array('xmlns' => 'http://www.w3.org/2005/Atom',

                             // XXX: xmlns:twitter causes Atom validation to fail
                             // It's used for the source attr on notices

                             'xmlns:twitter' => 'http://api.twitter.com/',
                             'xml:lang' => 'en-US')); // XXX Other locales ?

        $taguribase = TagURI::base();
        $this->element('id', null, "tag:$taguribase:search/$server");

        $site_uri = common_path(false);

        $search_uri = $site_uri . 'api/search.atom?q=' . urlencode($this->query);

        if ($this->rpp != 15) {
            $search_uri .= '&rpp=' . $this->rpp;
        }

        // FIXME: this alternate link is not quite right because our
        // web-based notice search doesn't support a rpp (responses per
        // page) param yet

        $this->element('link', array('type' => 'text/html',
                                     'rel'  => 'alternate',
                                     'href' => $site_uri . 'search/notice?q=' .
                                        urlencode($this->query)));

        // self link

        $self_uri = $search_uri;
        $self_uri .= ($this->page > 1) ? '&page=' . $this->page : '';

        $this->element('link', array('type' => 'application/atom+xml',
                                     'rel'  => 'self',
                                     'href' => $self_uri));

        // @todo Needs i18n?
        $this->element('title', null, "$this->query - $sitename Search");
        $this->element('updated', null, common_date_iso8601('now'));

        // XXX: The below "rel" links are not valid Atom, but it's what
        // Twitter does...

        // refresh link

        $refresh_uri = $search_uri . "&since_id=" . $this->max_id;

        $this->element('link', array('type' => 'application/atom+xml',
                                     'rel'  => 'refresh',
                                     'href' => $refresh_uri));

        // pagination links

        if ($this->cnt > $this->rpp) {

            $next_uri = $search_uri . "&max_id=" . $this->max_id .
                '&page=' . ($this->page + 1);

            $this->element('link', array('type' => 'application/atom+xml',
                                         'rel'  => 'next',
                                         'href' => $next_uri));
        }

        if ($this->page > 1) {

            $previous_uri = $search_uri . "&max_id=" . $this->max_id .
                '&page=' . ($this->page - 1);

            $this->element('link', array('type' => 'application/atom+xml',
                                         'rel'  => 'previous',
                                         'href' => $previous_uri));
        }
    }

    /**
     * Build an Atom entry similar to search.twitter.com's based on
     * a given notice
     *
     * @param Notice $notice the notice to use
     *
     * @return void
     */
    function showEntry($notice)
    {
        $server  = common_config('site', 'server');
        $profile = $notice->getProfile();
        $nurl    = common_local_url('shownotice', array('notice' => $notice->id));

        $this->elementStart('entry');

        $taguribase = TagURI::base();

        $this->element('id', null, "tag:$taguribase:$notice->id");
        $this->element('published', null, common_date_w3dtf($notice->created));
        $this->element('link', array('type' => 'text/html',
                                     'rel'  => 'alternate',
                                     'href' => $nurl));
        $this->element('title', null, common_xml_safe_str(trim($notice->content)));
        $this->element('content', array('type' => 'html'), $notice->getRendered());
        $this->element('updated', null, common_date_w3dtf($notice->created));
        $this->element('link', array('type' => 'image/png',
                                     // XXX: Twitter uses rel="image" (not valid)
                                     'rel' => 'related',
                                     'href' => $profile->avatarUrl()));

        // @todo: Here is where we'd put in a link to an atom feed for threads

        $source = null;
        $source_link = null;

        $ns = $notice->getSource();
        if ($ns instanceof Notice_source) {
            $source = $ns->code;
            if (!empty($ns->url)) {
                $source_link = $ns->url;
                if (!empty($ns->name)) {
                    $source = $ns->name;
                }
            }
        }

        $this->element("twitter:source", null, $source);
        $this->element("twitter:source_link", null, $source_link);

        $this->elementStart('author');

        $name = $profile->nickname;

        if ($profile->fullname) {
            // @todo Needs proper i18n?
            $name .= ' (' . $profile->fullname . ')';
        }

        $this->element('name', null, $name);
        $this->element('uri', null, common_profile_uri($profile));
        $this->elementEnd('author');

        $this->elementEnd('entry');
    }

    /**
     * Initialize the Atom output, send headers
     *
     * @return void
     */
    function initAtom()
    {
        header('Content-Type: application/atom+xml; charset=utf-8');
        $this->startXml();
    }

    /**
     * End the Atom feed
     *
     * @return void
     */
    function endAtom()
    {
        $this->elementEnd('feed');
    }
}
