<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A plugin to enable local tab subscription
 *
 * PHP version 5
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
 *
 * @category  SearchSubPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * SearchSub plugin main class
 *
 * @category  SearchSubPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class SearchSubPlugin extends Plugin
{
    const VERSION         = '0.1';

    /**
     * Database schema setup
     *
     * @see Schema
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('searchsub', SearchSub::schemaDef());
        return true;
    }

    /**
     * Map URLs to actions
     *
     * @param URLMapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('search/:search/subscribe',
                    array('action' => 'searchsub'),
                    array('search' => Router::REGEX_TAG));
        $m->connect('search/:search/unsubscribe',
                    array('action' => 'searchunsub'),
                    array('search' => Router::REGEX_TAG));
        $m->connect(':nickname/search-subscriptions',
                    array('action' => 'searchsubs'),
                    array('nickname' => Nickname::DISPLAY_FMT));
        return true;
    }

    /**
     * Plugin version data
     *
     * @param array &$versions array of version data
     *
     * @return value
     */
    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'SearchSub',
                            'version' => self::VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/SearchSub',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Plugin to allow following all messages with a given search.'));
        return true;
    }

    /**
     * Hook inbox delivery setup so search subscribers receive all
     * notices with that search in their inbox.
     *
     * Currently makes no distinction between local messages and
     * remote ones which happen to come in to the system. Remote
     * notices that don't come in at all won't ever reach this.
     *
     * @param Notice $notice
     * @param array $ni in/out map of profile IDs to inbox constants
     * @return boolean hook result
     */
    function onStartNoticeWhoGets(Notice $notice, array &$ni)
    {
        // Warning: this is potentially very slow
        // with a lot of searches!
        $sub = new SearchSub();
        $sub->groupBy('search');
        $sub->find();
        while ($sub->fetch()) {
            $search = $sub->search;

            if ($this->matchSearch($notice, $search)) {
                // Match? Find all those who subscribed to this
                // search term and get our delivery on...
                $searchsub = new SearchSub();
                $searchsub->search = $search;
                $searchsub->find();

                while ($searchsub->fetch()) {
                    // These constants are currently not actually used, iirc
                    $ni[$searchsub->profile_id] = NOTICE_INBOX_SOURCE_SUB;
                }
            }
        }
        return true;
    }

    /**
     * Does the given notice match the given fulltext search query?
     *
     * Warning: not guaranteed to match other search engine behavior, etc.
     * Currently using a basic case-insensitive substring match, which
     * probably fits with the 'LIKE' search but not the default MySQL
     * or Sphinx search backends.
     *
     * @param Notice $notice
     * @param string $search
     * @return boolean
     */
    function matchSearch(Notice $notice, $search)
    {
        return (mb_stripos($notice->content, $search) !== false);
    }

    /**
     *
     * @param NoticeSearchAction $action
     * @param string $q
     * @param Notice $notice
     * @return boolean hook result
     */
    function onStartNoticeSearchShowResults($action, $q, $notice)
    {
        $user = common_current_user();
        if ($user) {
            $search = $q;
            $searchsub = SearchSub::pkeyGet(array('search' => $search,
                                                  'profile_id' => $user->id));
            if ($searchsub) {
                $form = new SearchUnsubForm($action, $search);
            } else {
                $form = new SearchSubForm($action, $search);
            }
            $action->elementStart('div', 'entity_actions');
            $action->elementStart('ul');
            $action->elementStart('li', 'entity_subscribe');
            $form->show();
            $action->elementEnd('li');
            $action->elementEnd('ul');
            $action->elementEnd('div');
        }
        return true;
    }

    /**
     * Menu item for personal subscriptions/groups area
     *
     * @param Widget $widget Widget being executed
     *
     * @return boolean hook return
     */
    function onEndSubGroupNav($widget)
    {
        $action = $widget->out;
        $action_name = $action->trimmed('action');

        $action->menuItem(common_local_url('searchsubs', array('nickname' => $action->user->nickname)),
                          // TRANS: SearchSub plugin menu item on user settings page.
                          _m('MENU', 'Searches'),
                          // TRANS: SearchSub plugin tooltip for user settings menu item.
                          _m('Configure search subscriptions'),
                          $action_name == 'searchsubs' && $action->arg('nickname') == $action->user->nickname);

        return true;
    }

    /**
     * Replace the built-in stub track commands with ones that control
     * search subscriptions.
     *
     * @param CommandInterpreter $cmd
     * @param string $arg
     * @param User $user
     * @param Command $result
     * @return boolean hook result
     */
    function onEndInterpretCommand($cmd, $arg, $user, &$result)
    {
        if ($result instanceof TrackCommand) {
            $result = new SearchSubTrackCommand($user, $arg);
            return false;
        } else if ($result instanceof TrackOffCommand) {
            $result = new SearchSubTrackOffCommand($user);
            return false;
        } else if ($result instanceof TrackingCommand) {
            $result = new SearchSubTrackingCommand($user);
            return false;
        } else if ($result instanceof UntrackCommand) {
            $result = new SearchSubUntrackCommand($user, $arg);
            return false;
        } else {
            return true;
        }
    }

    function onHelpCommandMessages($cmd, &$commands)
    {
        // TRANS: Help message for IM/SMS command "track <word>"
        $commands["track <word>"] = _m('COMMANDHELP', "Start following notices matching the given search query.");
        // TRANS: Help message for IM/SMS command "untrack <word>"
        $commands["untrack <word>"] = _m('COMMANDHELP', "Stop following notices matching the given search query.");
        // TRANS: Help message for IM/SMS command "track off"
        $commands["track off"] = _m('COMMANDHELP', "Disable all tracked search subscriptions.");
        // TRANS: Help message for IM/SMS command "untrack all"
        $commands["untrack all"] = _m('COMMANDHELP', "Disable all tracked search subscriptions.");
        // TRANS: Help message for IM/SMS command "tracks"
        $commands["tracks"] = _m('COMMANDHELP', "List all your search subscriptions.");
        // TRANS: Help message for IM/SMS command "tracking"
        $commands["tracking"] = _m('COMMANDHELP', "List all your search subscriptions.");
    }

    function onEndDefaultLocalNav($menu, $user)
    {
        $user = common_current_user();

        if (!empty($user)) {
            $searches = SearchSub::forProfile($user->getProfile());

            if (!empty($searches) && count($searches) > 0) {
                $searchSubMenu = new SearchSubMenu($menu->out, $user, $searches);
                // TRANS: Sub menu for searches.
                $menu->submenu(_m('MENU','Searches'), $searchSubMenu);
            }
        }

        return true;
    }
}
