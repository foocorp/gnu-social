<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Output a user directory
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
 * @category  Public
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET'))
{
    exit(1);
}

require_once INSTALLDIR . '/lib/publicgroupnav.php';

/**
 * User directory
 *
 * @category Personal
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class UserdirectoryAction extends Action
{
    /**
     * The page we're on
     *
     * @var integer
     */
    public $page;

    /**
     * What to filter the search results by
     *
     * @var string
     */
    public $filter;

    /**
     * Column to sort by
     *
     * @var string
     */
    public $sort;

    /**
     * How to order search results, ascending or descending
     *
     * @var string
     */
    public $reverse;

    /**
     * Query
     *
     * @var string
     */
    public $q;

    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // @todo fixme: This looks kinda gross

        if ($this->filter == 'all') {
            if ($this->page != 1) {
                // TRANS: Page title for user directory. %d is a page number.
                return(sprintf(_m('User Directory, page %d'), $this->page));
            }
            // TRANS: Page title for user directory.
            return _m('User directory');
        } else if ($this->page == 1) {
            return sprintf(
                // TRANS: Page title for user directory. %s is the applied filter.
                _m('User directory - %s'),
                strtoupper($this->filter)
            );
        } else {
            return sprintf(
                // TRANS: Page title for user directory.
                // TRANS: %1$s is the applied filter, %2$d is a page number.
                _m('User directory - %1$s, page %2$d'),
                strtoupper($this->filter),
                $this->page
            );
        }
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: %%site.name%% is the name of the StatusNet site.
        return _m('Search for people on %%site.name%% by their name, '
            . 'location, or interests. Separate the terms by spaces; '
            . ' they must be 3 characters or more.'
        );
    }

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

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

        $this->page    = ($this->arg('page')) ? ($this->arg('page') + 0) : 1;
        $this->filter  = $this->arg('filter', 'all');
        $this->reverse = $this->boolean('reverse');
        $this->q       = $this->trimmed('q');
        $this->sort    = $this->arg('sort', 'nickname');

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Handle request
     *
     * Shows the page
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    /**
     * Show the page notice
     *
     * Shows instructions for the page
     *
     * @return void
     */
    function showPageNotice()
    {
        $instr  = $this->getInstructions();
        $output = common_markup_to_html($instr);

        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }


    /**
     * Content area
     *
     * Shows the list of popular notices
     *
     * @return void
     */
    function showContent()
    {
        $this->showForm();

        $this->elementStart('div', array('id' => 'profile_directory'));

        $alphaNav = new AlphaNav($this, false, false, array('0-9', 'All'));
        $alphaNav->show();

        $profile = null;
        $profile = $this->getUsers();
        $cnt     = 0;

        if (!empty($profile)) {
            $profileList = new SortableSubscriptionList(
                $profile,
                common_current_user(),
                $this
            );

            $cnt = $profileList->show();
            $profile->free();

            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }
        }

        $args = array();
        if (isset($this->q)) {
            $args['q'] = $this->q;
        } elseif (isset($this->filter) && $this->filter != 'all') {
            $args['filter'] = $this->filter;
        }

        $this->pagination(
            $this->page > 1,
            $cnt > PROFILES_PER_PAGE,
            $this->page,
            'userdirectory',
            $args
        );

        $this->elementEnd('div');

    }

    function showForm($error=null)
    {
        $this->elementStart(
            'form',
            array(
                'method' => 'get',
                'id'     => 'form_search',
                'class'  => 'form_settings',
                'action' => common_local_url('userdirectory')
            )
        );

        $this->elementStart('fieldset');

        // TRANS: Fieldset legend.
        $this->element('legend', null, _m('Search site'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');

        // TRANS: Field label for user directory filter.
        $this->input('q', _m('Keyword(s)'), $this->q);

        // TRANS: Button text.
        $this->submit('search', _m('BUTTON','Search'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /*
     * Get users filtered by the current filter, sort key,
     * sort order, and page
     */
    function getUsers()
    {
        $profile = new Profile();

        $offset = ($this->page - 1) * PROFILES_PER_PAGE;
        $limit  = PROFILES_PER_PAGE + 1;

        if (isset($this->q)) {
             // User is searching via query
             $search_engine = $profile->getSearchEngine('profile');

             $mode = 'reverse_chron';

             if ($this->sort == 'nickname') {
                 if ($this->reverse) {
                     $mode = 'nickname_desc';
                 } else {
                     $mode = 'nickname_asc';
                 }
             } else {
                 if ($this->reverse) {
                     $mode = 'chron';
                 }
             }

             $search_engine->set_sort_mode($mode);
             $search_engine->limit($offset, $limit);
             $search_engine->query($this->q);

             $profile->find();
        } else {
            // User is browsing via AlphaNav
            $sort   = $this->getSortKey();
            $sql    = 'SELECT profile.* FROM profile, user WHERE profile.id = user.id';

            switch($this->filter)
            {
            case 'all':
                // NOOP
                break;
            case '0-9':
                $sql .=
                    '  AND LEFT(profile.nickname, 1) BETWEEN \'0\' AND \'9\'';
                break;
            default:
                $sql .= sprintf(
                    ' AND LEFT(LOWER(profile.nickname), 1) = \'%s\'',
                    $this->filter
                );
            }

            $sql .= sprintf(
                ' ORDER BY profile.%s %s, profile.nickname ASC LIMIT %d, %d',
                $sort,
                $this->reverse ? 'DESC' : 'ASC',
                $offset,
                $limit
            );

            $profile->query($sql);
        }

        return $profile;
    }

    /**
     * Filter the sort parameter
     *
     * @return string   a column name for sorting
     */
    function getSortKey()
    {
        switch ($this->sort) {
        case 'nickname':
            return $this->sort;
            break;
        case 'created':
            return $this->sort;
            break;
        default:
            return 'nickname';
        }
    }

    /**
     * Show a nice message when there's no search results
     */
    function showEmptyListMessage()
    {
        if (!empty($this->filter) && ($this->filter != 'all')) {
            $this->element(
                'p',
                'error',
                sprintf(
                    // TRANS: Empty list message for user directory.
                    _m('No users starting with %s'),
                    $this->filter
                )
            );
        } else {
            // TRANS: Empty list message for user directory.
            $this->element('p', 'error', _m('No results.'));
            // TRANS: Standard search suggestions shown when a search does not give any results.
            $message = _m("* Make sure all words are spelled correctly.
* Try different keywords.
* Try more general keywords.
* Try fewer keywords.");
            $message .= "\n";

            $this->elementStart('div', 'help instructions');
            $this->raw(common_markup_to_html($message));
            $this->elementEnd('div');
        }
    }
}
