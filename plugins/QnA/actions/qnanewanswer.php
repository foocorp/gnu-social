<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Answer a question
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
 * Answer a question
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class QnanewanswerAction extends Action
{
    protected $user     = null;
    protected $error    = null;
    protected $complete = null;

    public    $question = null;
    protected $content  = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Page title for and answer to a question.
        return _m('Answer');
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
        common_debug("in qnanewanswer");
        $this->user = common_current_user();

        if (empty($this->user)) {
            throw new ClientException(
                // TRANS: Client exception thrown trying to answer a question while not logged in.
                _m("You must be logged in to answer to a question."),
                403
            );
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        $id = substr($this->trimmed('id'), 9);

        $this->question = QnA_Question::staticGet('id', $id);

        if (empty($this->question)) {
            throw new ClientException(
                // TRANS: Client exception thrown trying to respond to a non-existing question.
                _m('Invalid or missing question.'),
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
            $this->newAnswer();
        } else {
            $this->showForm();
        }

        return;
    }

    /**
     * Add a new answer
     *
     * @return void
     */
    function newAnswer()
    {
        $profile = $this->user->getProfile();

        try {
            $notice = QnA_Answer::saveNew(
                $profile,
                $this->question,
                $this->answerText
            );
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showForm($this->error);
            return;
        }
        if ($this->boolean('ajax')) {
            common_debug("ajaxy part");
            $answer = $this->question->getAnswer($profile);
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');

            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after sending an answer.
            $this->element('title', null, _m('Answers'));
            $this->elementEnd('head');

            $this->elementStart('body');

            $nli = new NoticeAnswerListItem($notice, $this, $this->question, $answer);
            $nli->show();

            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_debug("not ajax");
            common_redirect($this->question->bestUrl(), 303);
        }
    }

    /**
     * Show the Answer form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new QnanewanswerForm($this->question, $this);
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

    /**
     * Show an Ajax-y error message
     *
     * Goes back to the browser, where it's shown in a popup.
     *
     * @param string $msg Message to show
     *
     * @return void
     */
    function ajaxErrorMsg($msg)
    {
        $this->startHTML('text/xml;charset=utf-8', true);
        $this->elementStart('head');
        // TRANS: Page title after an AJAX error occurs on the post answer page.
        $this->element('title', null, _m('Ajax Error'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $this->element('p', array('id' => 'error'), $msg);
        $this->elementEnd('body');
        $this->elementEnd('html');
    }

    /**
     * Show an Ajax-y answer form
     *
     * Goes back to the browser, where it's shown in a popup.
     *
     * @param string $msg Message to show
     *
     * @return void
     */
    function ajaxShowForm()
    {
        common_debug('ajaxShowForm()');
        $this->startHTML('text/xml;charset=utf-8', true);
        $this->elementStart('head');
        // TRANS: Title for form to send answer to a question.
        $this->element('title', null, _m('TITLE','Your answer'));
        $this->elementEnd('head');
        $this->elementStart('body');

        $form = new QnanewanswerForm($this, $this->question);
        $form->show();

        $this->elementEnd('body');
        $this->elementEnd('html');
    }

    /**
     * @param string $msg An error message, if any
     *
     * @return void
     */
    function showForm($msg = null)
    {
        common_debug("show form - msg = $msg");
        if ($this->boolean('ajax')) {
            if ($msg) {
                $this->ajaxErrorMsg($msg);
            } else {
                $this->ajaxShowForm();
            }
            return;
        }

        $this->msg = $msg;
        $this->showPage();
    }
}

class NoticeAnswerListItem extends NoticeListItem
{
    protected $question;
    protected $answer;

    /**
     * constructor
     *
     * Also initializes the profile attribute.
     *
     * @param Notice $notice The notice we'll display
     */
    function __construct($notice, $out=null, $question, $answer)
    {
        parent::__construct($notice, $out);
        $this->question = $question;
        $this->answer   = $answer;

    }

    function show()
    {
        if (empty($this->notice)) {
            common_log(LOG_WARNING, "Trying to show missing notice; skipping.");
            return;
        } else if (empty($this->profile)) {
            common_log(LOG_WARNING, "Trying to show missing profile (" . $this->notice->profile_id . "); skipping.");
            return;
        }

        $this->showStart();
        $this->showNotice();
        $this->showNoticeInfo();
        $notice = $this->question->getNotice();
        $this->out->hidden('inreplyto', $notice->id);
        $this->showEnd();
    }

    /**
     * show the content of the notice
     *
     * Shows the content of the notice. This is pre-rendered for efficiency
     * at save time. Some very old notices might not be pre-rendered, so
     * they're rendered on the spot.
     *
     * @return void
     */
    function showContent()
    {
        $this->out->elementStart('p', array('class' => 'entry-content answer-content'));
        if ($this->notice->rendered) {
            $this->out->raw($this->notice->rendered);
        } else {
            // XXX: may be some uncooked notices in the DB,
            // we cook them right now. This should probably disappear in future
            // versions (>> 0.4.x)
            $this->out->raw(common_render_content($this->notice->content, $this->notice));
        }

        if (!empty($this->answer)) {
            $form = new QnashowanswerForm($this->out, $this->answer);
            $form->show();
        } else {
            // TRANS: Error message displayed when an answer has no content.
            $out->text(_m('Answer data is missing.'));
        }

        $this->out->elementEnd('p');
    }
}
