<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Close a question to further answers
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
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
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
 * Close a question to new answers
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class QnaclosequestionAction extends Action
{
    protected $user        = null;
    protected $error       = null;
    protected $complete    = null;

    protected $question    = null;
    protected $answer      = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Page title for close a question
        return _m('Close question');
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
            GNUsocial::setApi(true);
        }

        $this->user = common_current_user();

        if (empty($this->user)) {
            throw new ClientException(
                // TRANS: Client exception thrown trying to close a question when not logged in
                _m("You must be logged in to close a question."),
                403
            );
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        $id = substr($this->trimmed('id'), 9);
        $this->question = QnA_Question::getKV('id', $id);
        if (empty($this->question)) {
            // TRANS: Client exception thrown trying to respond to a non-existing question.
            throw new ClientException(_m('Invalid or missing question.'), 404);
        }

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
            $this->closeQuestion();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Close a question
     *
     * @return void
     */
    function closeQuestion()
    {
        $user = common_current_user();

        try {
            if ($user->id != $this->question->profile_id) {
                // TRANS: Exception thrown trying to close another user's question.
                throw new Exception(_m('You did not ask this question.'));
            }

            $orig = clone($this->question);
            $this->question->closed = 1;
            $this->question->update($orig);

        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            // TRANS: Page title after sending an answer.
            $this->element('title', null, _m('Answers'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $form = new QnashowquestionForm($this, $this->question);
            $form->show();
            $this->elementEnd('body');
            $this->endHTML();
        } else {
            common_redirect($this->question->getUrl(), 303);
        }
    }

    /**
     * Show the close question form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        // blar
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
