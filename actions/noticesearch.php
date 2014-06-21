<?php
/**
 * Notice search action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/searchaction.php';

/**
 * Notice search action class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 * @todo     common parent for people and content search?
 */
class NoticesearchAction extends SearchAction
{
    protected $q = null;

    function prepare($args)
    {
        parent::prepare($args);

        $this->q = $this->trimmed('q');

        // FIXME: very dependent on tag format
        if (preg_match('/^#([\pL\pN_\-\.]{1,64})/ue', $this->q)) {
            common_redirect(common_local_url('tag',
                                             array('tag' => common_canonical_tag(substr($this->q, 1)))),
                            303);
        }

        if (!empty($this->q)) {

            $profile = Profile::current();
            $stream  = new SearchNoticeStream($this->q, $profile);
            $page    = $this->trimmed('page');

            if (empty($page)) {
                $page = 1;
            } else {
                $page = (int)$page;
            }

            $this->notice = $stream->getNotices((($page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);
        }

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Get instructions
     *
     * @return string instruction text
     */
    function getInstructions()
    {
        // TRANS: Instructions for Notice search page.
        // TRANS: %%site.name%% is the name of the StatusNet site.
        return _('Search for notices on %%site.name%% by their contents. Separate search terms by spaces; they must be 3 characters or more.');
    }

    /**
     * Get title
     *
     * @return string title
     */
    function title()
    {
        // TRANS: Title of the page where users can search for notices.
        return _('Text search');
    }

    function getFeeds()
    {
        $q = $this->trimmed('q');

        if (!$q) {
            return null;
        }

        return array(new Feed(Feed::RSS1, common_local_url('noticesearchrss',
                                                           array('q' => $q)),
                              // TRANS: Test in RSS notice search.
                              // TRANS: %1$s is the query, %2$s is the StatusNet site name.
                              sprintf(_('Search results for "%1$s" on %2$s'),
                                      $q, common_config('site', 'name'))));
    }

    /**
     * Show results
     *
     * @param string  $q    search query
     * @param integer $page page number
     *
     * @return void
     */
    function showResults($q, $page)
    {
        if (Event::handle('StartNoticeSearchShowResults', array($this, $q, $this->notice))) {
            if ($this->notice->N === 0) {
                $this->showEmptyResults($q, $page);
            } else {
                $terms = preg_split('/[\s,]+/', $q);
                $nl = new SearchNoticeList($this->notice, $this, $terms);
                $cnt = $nl->show();
                $this->pagination($page > 1,
                                  $cnt > NOTICES_PER_PAGE,
                                  $page,
                                  'noticesearch',
                                  array('q' => $q));
            }
            Event::handle('EndNoticeSearchShowResults', array($this, $q, $this->notice));
        }
    }

    function showEmptyResults($q, $page)
    {
            // TRANS: Text for notice search results is the query had no results.
            $this->element('p', 'error', _('No results.'));

            $this->searchSuggestions($q);
            if (common_logged_in()) {
                // TRANS: Text for logged in users making a query for notices without results.
                // TRANS: This message contains a Markdown link.
                $message = sprintf(_('Be the first to [post on this topic](%%%%action.newnotice%%%%?status_textarea=%s)!'), urlencode($q));
            }
            else {
                // TRANS: Text for not logged in users making a query for notices without results.
                // TRANS: This message contains Markdown links.
                $message = sprintf(_('Why not [register an account](%%%%action.register%%%%) and be the first to [post on this topic](%%%%action.newnotice%%%%?status_textarea=%s)!'), urlencode($q));
            }

            $this->elementStart('div', 'guide');
            $this->raw(common_markup_to_html($message));
            $this->elementEnd('div');
            return;
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('q');
    }
}

class SearchNoticeList extends NoticeList {
    function __construct($notice, $out=null, $terms)
    {
        parent::__construct($notice, $out);
        $this->terms = $terms;
    }

    function newListItem($notice)
    {
        return new SearchNoticeListItem($notice, $this->out, $this->terms);
    }
}

class SearchNoticeListItem extends NoticeListItem {
    function __construct($notice, $out=null, $terms)
    {
        parent::__construct($notice, $out);
        $this->terms = $terms;
    }

    function showContent()
    {
        // FIXME: URL, image, video, audio
        $this->out->elementStart('p', array('class' => 'e-content'));
        if ($this->notice->rendered) {
            $this->out->raw($this->highlight($this->notice->rendered, $this->terms));
        } else {
            // XXX: may be some uncooked notices in the DB,
            // we cook them right now. This should probably disappear in future
            // versions (>> 0.4.x)
            $this->out->raw($this->highlight(common_render_content($this->notice->content, $this->notice), $this->terms));
        }
        $this->out->elementEnd('p');

    }

    /**
     * Highlist query terms
     *
     * @param string $text  notice text
     * @param array  $terms terms to highlight
     *
     * @return void
     */
    function highlight($text, $terms)
    {
        /* Highligh search terms */
        $options = implode('|', array_map('preg_quote', array_map('htmlspecialchars', $terms),
                                                            array_fill(0, sizeof($terms), '/')));
        $pattern = "/($options)/i";
        $result = '';

        /* Divide up into text (highlight me) and tags (don't touch) */
        $chunks = preg_split('/(<[^>]+>)/', $text, 0, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($chunks as $i => $chunk) {
            if ($i % 2 == 1) {
                // odd: delimiter (tag)
                $result .= $chunk;
            } else {
                // even: freetext between tags
                $result .= preg_replace($pattern, '<strong>\\1</strong>', $chunk);
            }
        }

        return $result;
    }
}
