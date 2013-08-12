<?php
/**
 * Show latest and greatest profile flags
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Show the latest and greatest profile flags
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class AdminprofileflagAction extends Action
{
    var $page     = null;
    var $profiles = null;

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

        $user = common_current_user();

        // User must be logged in.

        if (!common_logged_in()) {
            // TRANS: Error message displayed when trying to perform an action that requires a logged in user.
            $this->clientError(_m('Not logged in.'));
            return;
        }

        $user = common_current_user();

        // ...because they're logged in

        assert(!empty($user));

        // It must be a "real" login, not saved cookie login

        if (!common_is_real_login()) {
            // Cookie theft is too easy; we require automatic
            // logins to re-authenticate before admining the site
            common_set_returnto($this->selfUrl());
            if (Event::handle('RedirectToLogin', array($this, $user))) {
                common_redirect(common_local_url('login'), 303);
            }
        }

        // User must have the right to review flags

        if (!$user->hasRight(UserFlagPlugin::REVIEWFLAGS)) {
            // TRANS: Error message displayed when trying to review profile flags while not authorised.
            $this->clientError(_m('You cannot review profile flags.'));
            return false;
        }

        $this->page = $this->trimmed('page');

        if (empty($this->page)) {
            $this->page = 1;
        }

        $this->profiles = $this->getProfiles();

        return true;
    }

    /**
     * Handle request
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
     * Title of this page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Title for page with a list of profiles that were flagged for review.
        return _m('Flagged profiles');
    }

    /**
     * save the profile flag
     *
     * @return void
     */
    function showContent()
    {
        $pl = new FlaggedProfileList($this->profiles, $this);

        $cnt = $pl->show();

        $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                          $this->page, 'adminprofileflag');
    }

    /**
     * Retrieve this action's profiles
     *
     * @return Profile $profile Profile query results
     */
    function getProfiles()
    {
        $ufp = new User_flag_profile();

        $ufp->selectAdd();
        $ufp->selectAdd('profile_id');
        $ufp->selectAdd('count(*) as flag_count');

        $ufp->whereAdd('cleared is NULL');

        $ufp->groupBy('profile_id');
        $ufp->orderBy('flag_count DESC, profile_id DESC');

        $offset = ($this->page-1) * PROFILES_PER_PAGE;
        $limit  = PROFILES_PER_PAGE + 1;

        $ufp->limit($offset, $limit);

        $profiles = array();

        if ($ufp->find()) {
            while ($ufp->fetch()) {
                $profile = Profile::staticGet('id', $ufp->profile_id);
                if (!empty($profile)) {
                    $profiles[] = $profile;
                }
            }
        }

        $ufp->free();

        return new ArrayWrapper($profiles);
    }
}

/**
 * Specialization of ProfileList to show flagging information
 *
 * Most of the hard part is done in FlaggedProfileListItem.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class FlaggedProfileList extends ProfileList
{
    /**
     * Factory method for creating new list items
     *
     * @param Profile $profile Profile to create an item for
     *
     * @return ProfileListItem newly-created item
     */
    function newListItem($profile)
    {
        return new FlaggedProfileListItem($this->profile, $this->action);
    }
}

/**
 * Specialization of ProfileListItem to show flagging information
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class FlaggedProfileListItem extends ProfileListItem
{
    const MAX_FLAGGERS = 5;

    var $user   = null;
    var $r2args = null;

    /**
     * Overload parent's action list with our own moderation-oriented buttons
     *
     * @return void
     */
    function showActions()
    {
        $this->user = common_current_user();

        list($action, $this->r2args) = $this->out->returnToArgs();

        $this->r2args['action'] = $action;

        $this->startActions();
        if (Event::handle('StartProfileListItemActionElements', array($this))) {
            $this->out->elementStart('li', 'entity_moderation');
            // TRANS: Header for moderation menu with action buttons for flagged profiles (like 'sandbox', 'silence', ...).
            $this->out->element('p', null, _m('Moderate'));
            $this->out->elementStart('ul');
            $this->showSandboxButton();
            $this->showSilenceButton();
            $this->showDeleteButton();
            $this->showClearButton();
            $this->out->elementEnd('ul');
            $this->out->elementEnd('li');
            Event::handle('EndProfileListItemActionElements', array($this));
        }
        $this->endActions();
    }

    /**
     * Show a button to sandbox the profile
     *
     * @return void
     */
    function showSandboxButton()
    {
        if ($this->user->hasRight(Right::SANDBOXUSER)) {
            $this->out->elementStart('li', 'entity_sandbox');
            if ($this->profile->isSandboxed()) {
                $usf = new UnSandboxForm($this->out, $this->profile, $this->r2args);
                $usf->show();
            } else {
                $sf = new SandboxForm($this->out, $this->profile, $this->r2args);
                $sf->show();
            }
            $this->out->elementEnd('li');
        }
    }

    /**
     * Show a button to silence the profile
     *
     * @return void
     */
    function showSilenceButton()
    {
        if ($this->user->hasRight(Right::SILENCEUSER)) {
            $this->out->elementStart('li', 'entity_silence');
            if ($this->profile->isSilenced()) {
                $usf = new UnSilenceForm($this->out, $this->profile, $this->r2args);
                $usf->show();
            } else {
                $sf = new SilenceForm($this->out, $this->profile, $this->r2args);
                $sf->show();
            }
            $this->out->elementEnd('li');
        }
    }

    /**
     * Show a button to delete user and profile
     *
     * @return void
     */
    function showDeleteButton()
    {

        if ($this->user->hasRight(Right::DELETEUSER)) {
            $this->out->elementStart('li', 'entity_delete');
            $df = new DeleteUserForm($this->out, $this->profile, $this->r2args);
            $df->show();
            $this->out->elementEnd('li');
        }
    }

    /**
     * Show a button to clear flags
     *
     * @return void
     */
    function showClearButton()
    {
        if ($this->user->hasRight(UserFlagPlugin::CLEARFLAGS)) {
            $this->out->elementStart('li', 'entity_clear');
            $cf = new ClearFlagForm($this->out, $this->profile, $this->r2args);
            $cf->show();
            $this->out->elementEnd('li');
        }
    }

    /**
     * Overload parent function to add flaggers list
     *
     * @return void
     */
    function endProfile()
    {
        $this->showFlaggersList();
        parent::endProfile();
    }

    /**
     * Show a list of people who've flagged this profile
     *
     * @return void
     */
    function showFlaggersList()
    {
        $flaggers = array();

        $ufp = new User_flag_profile();

        $ufp->selectAdd();
        $ufp->selectAdd('user_id');
        $ufp->profile_id = $this->profile->id;
        $ufp->orderBy('created');

        if ($ufp->find()) { // XXX: this should always happen
            while ($ufp->fetch()) {
                $user = User::staticGet('id', $ufp->user_id);
                if (!empty($user)) { // XXX: this would also be unusual
                    $flaggers[] = clone($user);
                }
            }
        }

        $cnt    = count($flaggers);
        $others = 0;

        if ($cnt > self::MAX_FLAGGERS) {
            $flaggers = array_slice($flaggers, 0, self::MAX_FLAGGERS);
            $others   = $cnt - self::MAX_FLAGGERS;
        }

        $lnks = array();

        foreach ($flaggers as $flagger) {

            $url = common_local_url('showstream',
                                    array('nickname' => $flagger->nickname));

            $lnks[] = XMLStringer::estring('a', array('href' => $url,
                                                      'class' => 'flagger'),
                                           $flagger->nickname);
        }

        if ($cnt > 0) {
            if ($others > 0) {
                $flagging_users = implode(', ', $lnks);
                // TRANS: Message displayed on a profile if it has been flagged.
                // TRANS: %1$s is a comma separated list of at most 5 user nicknames that flagged.
                // TRANS: %2$d is a positive integer of additional flagging users. Also used for plural.
                $text .= sprintf(_m('Flagged by %1$s and %2$d other', 'Flagged by %1$s and %2$d others', $others), $flagging_users, $others);
            } else {
                // TRANS: Message displayed on a profile if it has been flagged.
                // TRANS: %s is a comma separated list of at most 5 user nicknames that flagged.
                $text .= sprintf(_m('Flagged by %s'), $flagging_users);
            }

            $this->out->elementStart('p', array('class' => 'flaggers'));
            $this->out->raw($text);
            $this->out->elementEnd('p');
        }
    }
}
