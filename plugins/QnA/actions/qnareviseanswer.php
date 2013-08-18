<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Revise an answer
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
 * Revise an answer
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class QnareviseanswerAction extends Action
{
    protected $user     = null;
    protected $error    = null;
    protected $question = null;
    protected $answer   = null;
    protected $content  = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Page title for revising a question
        return _m('Revise answer');
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
            throw new ClientException(
                // TRANS: Client exception thrown trying to answer a question while not logged in.
                _m("You must be logged in to answer to a question."),
                403
            );
        }

        $id = substr($this->trimmed('id'), 7);

        $this->answer   = QnA_Answer::getKV('id', $id);
        $this->question = $this->answer->getQuestion();

        if (empty($this->answer) || empty($this->question)) {
            throw new ClientException(
                // TRANS: Client exception thrown trying to respond to a non-existing question.
                _m('Invalid or missing answer.'),
                404
            );
        }

        $this->answerText = $this->trimmed('answer');

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
            $this->checkSessionToken();
            if ($this->arg('revise')) {
                $this->showContent();
                return;
            } else if ($this->arg('best')) {
                if ($this->user->id == $this->question->profile_id) {
                    $this->markBest();
                    return;
                }
            } else {
                $this->reviseAnswer();
                return;
            }
        }

        $this->showPage();
    }

    /**
     * Revise the answer
     *
     * @return void
     */
    function reviseAnswer()
    {
        $answer = $this->answer;

        try {
            $orig = clone($answer);
            $answer->content = $this->answerText;
            $answer->revisions++;
            $result = $answer->update($orig);
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }
        if ($this->boolean('ajax')) {
            common_debug("ajaxy part");
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after sending an answer.
            $this->element('title', null, _m('Answer'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $form = new QnashowanswerForm($this, $answer);
            $form->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect($this->answer->bestUrl(), 303);
        }
    }

    /**
     * Mark the answer as the "best" answer
     *
     * @return void
     */
    function markBest()
    {
        $question = $this->question;
        $answer   = $this->answer;

        try {
            // close the question to further answers
            $orig = clone($question);
            $question->closed = 1;
            $result = $question->update($orig);

            // mark this answer an the best answer
            $orig = clone($answer);
            $answer->best = 1;
            $result = $answer->update($orig);
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }
        if ($this->boolean('ajax')) {
            common_debug("ajaxy part");
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after sending an answer.
            $this->element('title', null, _m('Answer'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $form = new QnashowanswerForm($this, $answer);
            $form->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect($this->answer->bestUrl(), 303);
        }
    }

    /**
     * Show the revise answer form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        if ($this->boolean('ajax')) {
            $this->showAjaxReviseForm();
        } else {
            $form = new QnareviseanswerForm($this->answer, $this);
            $form->show();
        }

        return;
    }

    function showAjaxReviseForm()
    {
        header('Content-Type: text/xml;charset=utf-8');
        $this->xw->startDocument('1.0', 'UTF-8');
        $this->elementStart('html');
        $this->elementStart('head');
        // TRANS: Form title for sending an answer.
        $this->element('title', null, _m('TITLE','Answer'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $form = new QnareviseanswerForm($this->answer, $this);
        $form->show();
        $this->elementEnd('body');
        $this->elementEnd('html');
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
