<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Respond to a Poll
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
 * @category  Poll
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Respond to a Poll
 *
 * @category  Poll
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class RespondPollAction extends Action
{
    protected $user        = null;
    protected $error       = null;
    protected $complete    = null;

    protected $poll        = null;
    protected $selection   = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Page title for poll response.
        return _m('Poll response');
    }

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
        if ($this->boolean('ajax')) {
            StatusNet::setApi(true);
        }

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown trying to respond to a poll while not logged in.
            throw new ClientException(_m('You must be logged in to respond to a poll.'),
                                      403);
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        $id = $this->trimmed('id');
        $this->poll = Poll::getKV('id', $id);
        if (empty($this->poll)) {
            // TRANS: Client exception thrown trying to respond to a non-existing poll.
            throw new ClientException(_m('Invalid or missing poll.'), 404);
        }

        $selection = intval($this->trimmed('pollselection'));
        if ($selection < 1 || $selection > count($this->poll->getOptions())) {
            // TRANS: Client exception thrown responding to a poll with an invalid answer.
            throw new ClientException(_m('Invalid poll selection.'));
        }
        $this->selection = $selection;

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        parent::handle($argarray);

        if ($this->isPost()) {
            $this->respondPoll();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Add a new Poll
     *
     * @return void
     */
    function respondPoll()
    {
        try {
            $notice = Poll_response::saveNew($this->user->getProfile(),
                                             $this->poll,
                                             $this->selection);
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title after sending a poll response.
            $this->element('title', null, _m('Poll results'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $form = new PollResultForm($this->poll, $this);
            $form->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect($this->poll->bestUrl(), 303);
        }
    }

    /**
     * Show the Poll form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new PollResponseForm($this->poll, $this);

        $form->show();

        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }
}
