<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action for showing profiles self-tagged with a given tag
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * This class outputs a paginated list of profiles self-tagged with a given tag
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Action
 */
class PeopletagAction extends Action
{

    var $tag  = null;
    var $page = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->tag = $this->trimmed('tag');

        if (!common_valid_profile_tag($this->tag)) {
            // TRANS: Client error displayed when trying to tag a profile with an invalid tag.
            // TRANS: %s is the invalid tag.
            $this->clientError(sprintf(_('Not a valid people tag: %s.'),
                $this->tag));
            return;
        }

        $this->page = ($this->arg('page')) ? $this->arg('page') : 1;

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return boolean is read only action?
     */
    function handle($argarray)
    {
        parent::handle($argarray);
        $this->showPage();
    }

    /**
     * Whips up a query to get a list of profiles based on the provided
     * people tag and page, initalizes a ProfileList widget, and displays
     * it to the user.
     *
     * @return nothing
     */
    function showContent()
    {

        $profile = new Profile();

        $offset = ($this->page - 1) * PROFILES_PER_PAGE;
        $limit  = PROFILES_PER_PAGE + 1;

        if (common_config('db', 'type') == 'pgsql') {
            $lim = ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $lim = ' LIMIT ' . $offset . ', ' . $limit;
        }

        // XXX: memcached this

        $qry =  'SELECT profile.* ' .
                'FROM profile JOIN profile_tag ' .
                'ON profile.id = profile_tag.tagger ' .
                'WHERE profile_tag.tagger = profile_tag.tagged ' .
                "AND tag = '%s' " .
                'ORDER BY profile_tag.modified DESC%s';

        $profile->query(sprintf($qry, $this->tag, $lim));

        $ptl = new PeopleTagList($profile, $this); // pass the ammunition
        $cnt = $ptl->show();

        $this->pagination($this->page > 1,
                          $cnt > PROFILES_PER_PAGE,
                          $this->page,
                          'peopletag',
                          array('tag' => $this->tag));
    }

    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        // TRANS: Page title for users with a certain self-tag.
        // TRANS: %1$s is the tag, %2$s is the page number.
        return sprintf(_('Users self-tagged with %1$s - page %2$d'),
            $this->tag, $this->page);
    }
}

class PeopleTagList extends ProfileList
{
    function newListItem($profile)
    {
        return new PeopleTagListItem($profile, $this->action);
    }
}

class PeopleTagListItem extends ProfileListItem
{
    function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'peopletag')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }

    function homepageAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'peopletag')) {
            $aAttrs['rel'] = 'nofollow';
        }

        return $aAttrs;
    }
}
