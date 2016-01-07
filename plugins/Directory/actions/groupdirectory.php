<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Output a group directory
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Group directory
 *
 * @category Directory
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class GroupdirectoryAction extends ManagedAction
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
        // @fixme: This looks kinda gross

        if ($this->filter == 'all') {
            if ($this->page != 1) {
                // TRANS: Title for group directory page. %d is a page number.
                return(sprintf(_m('Group Directory, page %d'), $this->page));
            }
            // TRANS: Title for group directory page.
            return _m('Group directory');
        } else if ($this->page == 1) {
            return sprintf(
                // TRANS: Title for group directory page when it is filtered.
                // TRANS: %s is the filter string.
                _m('Group directory - %s'),
                strtoupper($this->filter)
            );
        } else {
            return sprintf(
                // TRANS: Title for group directory page when it is filtered.
                // TRANS: %1$s is the filter string, %2$d is a page number.
                _m('Group directory - %1$s, page %2$d'),
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
        // TRANS: Page instructions.
        return _m("After you join a group you can send messages to all other members\n".
                 "using the syntax \"!groupname\".\n\n".
                 "Browse groups, or search for groups by their name, location or topic.\n".
                 "Separate the terms by spaces; they must be three characters or more.") . "\n";
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

    protected function doPreparation()
    {
        $this->page    = ($this->arg('page')) ? ($this->arg('page') + 0) : 1;
        $this->filter  = $this->arg('filter', 'all');
        $this->reverse = $this->boolean('reverse');
        $this->q       = $this->trimmed('q');
        $this->sort    = $this->arg('sort', 'nickname');

        common_set_returnto($this->selfUrl());
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
     * Shows the groups
     *
     * @return void
     */
    function showContent()
    {
        if (common_logged_in()) {
            $this->elementStart(
                'p',
                array(
                    'id' => 'new_group'
                )
            );
            $this->element(
                'a',
                array(
                    'href'  => common_local_url('newgroup'),
                    'class' => 'more'),
                    // TRANS: Link to create a new group on the group list page.
                    _m('Create a new group')
            );
            $this->elementEnd('p');
        }

        $this->showForm();

        $this->elementStart('div', array('id' => 'profile_directory'));

        // @todo FIXME: Does "All" need i18n here?
        $alphaNav = new AlphaNav($this, false, false, array('0-9', 'All'));
        $alphaNav->show();

        $group   = null;
        $group   = $this->getGroups();
        $cnt     = 0;

        if (!empty($group)) {
            $groupList = new SortableGroupList(
                $group,
                common_current_user(),
                $this
            );

            $cnt = $groupList->show();
            $group->free();

            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }
        }

        $args = array();
        if (isset($this->q)) {
            $args['q'] = $this->q;
        } else {
            $args['filter'] = $this->filter;
        }

        $this->pagination(
            $this->page > 1,
            $cnt > PROFILES_PER_PAGE,
            $this->page,
            'groupdirectory',
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
                'action' => common_local_url('groupdirectory')
            )
        );

        $this->elementStart('fieldset');

        // TRANS: Fieldset legend.
        $this->element('legend', null, _m('Search groups'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');

        // TRANS: Field label for input of one or more keywords.
        $this->input('q', _m('Keyword(s)'), $this->q);

        // TRANS: Button text for searching group directory.
        $this->submit('search', _m('BUTTON','Search'));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /*
     * Get groups filtered by the current filter, sort key,
     * sort order, and page
     */
    function getGroups()
    {
        $group = new User_group();

        // Disable this to get global group searches
        $group->joinAdd(array('id', 'local_group:group_id'));

        $order = false;

        if (!empty($this->q)) {
            $wheres = array('nickname', 'fullname', 'homepage', 'description', 'location');
            foreach ($wheres as $where) {
                // Double % because of sprintf
                $group->whereAdd(sprintf('LOWER(%1$s.%2$s) LIKE LOWER("%%%3$s%%")',
                                        $group->escapedTableName(), $where,
                                        $group->escape($this->q)),
                                    'OR');
            }

            $order = sprintf('%1$s.%2$s %3$s',
                            $group->escapedTableName(),
                            $this->getSortKey('created'),
                            $this->reverse ? 'DESC' : 'ASC');
        } else {
            // User is browsing via AlphaNav

            switch($this->filter) {
            case 'all':
                // NOOP
                break;
            case '0-9':
                $group->whereAdd(sprintf('LEFT(%1$s.%2$s, 1) BETWEEN %3$s AND %4$s',
                                        $group->escapedTableName(),
                                        'nickname',
                                        $group->_quote("0"),
                                        $group->_quote("9")));
                break;
            default:
                $group->whereAdd(sprintf('LEFT(LOWER(%1$s.%2$s), 1) = %3$s',
                                            $group->escapedTableName(),
                                            'nickname',
                                            $group->_quote($this->filter)));
            }

            $order = sprintf('%1$s.%2$s %3$s, %1$s.%4$s ASC',
                            $group->escapedTableName(),
                            $this->getSortKey('nickname'),
                            $this->reverse ? 'DESC' : 'ASC',
                            'nickname');
        }

        $offset = ($this->page-1) * PROFILES_PER_PAGE;
        $limit  = PROFILES_PER_PAGE + 1;

        $group->selectAdd();
        $group->selectAdd('profile_id');
        $group->orderBy($order);
        $group->limit($offset, $limit);

        $group->find();

        return Profile::multiGet('id', $group->fetchAll('profile_id'));
    }

    /**
     * Filter the sort parameter
     *
     * @return string   a column name for sorting
     */
    function getSortKey($def='created')
    {
        switch ($this->sort) {
        case 'nickname':
        case 'created':
            return $this->sort;
        default:
            return $def;
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
                    // TRANS: Empty list message for searching group directory.
                    // TRANS: %s is the search string.
                    _m('No groups starting with %s.'),
                    $this->filter
                )
            );
        } else {
            // TRANS: Empty list message for searching group directory.
            $this->element('p', 'error', _m('No results.'));
            // TRANS: Help text for searching group directory.
            $message = _m("* Make sure all words are spelled correctly.\n".
                          "* Try different keywords.\n".
                          "* Try more general keywords.\n".
                          "* Try fewer keywords.");
            $this->elementStart('div', 'help instructions');
            $this->raw(common_markup_to_html($message));
            $this->elementEnd('div');
        }
    }

    function showSections()
    {
        $gbp = new GroupsByPostsSection($this);
        $gbp->show();
        $gbm = new GroupsByMembersSection($this);
        $gbm->show();
    }
}
