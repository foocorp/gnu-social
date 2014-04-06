<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Form for answering a question
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
 * Form to add a new answer to a question
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class QnanewanswerForm extends Form
{
    protected $question;
    protected $showQuestion;

    /**
     * Construct a new answer form
     *
     * @param QnA_Question $question
     * @param HTMLOutputter $out output channel
     *
     * @return void
     */
    function __construct(HTMLOutputter $out, QnA_Question $question, $showQuestion = false)
    {
        parent::__construct($out);
        $this->question = $question;
        $this->showQuestion = $showQuestion;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'answer-form';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_settings qna_answer_form ajax-notice';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('qnanewanswer');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $question = $this->question;
        $out      = $this->out;
        $id       = "question-" . $question->id;

        if ($this->showQuestion) {
            $out->raw($this->question->asHTML());
        }

        $out->hidden('qna-question-id', $id, 'id');
        // TRANS: Field label.
        $out->textarea('qna-answer', _m('Enter your answer'), null, null, 'answer', true);
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button text for submitting a poll response.
        $this->out->submit('qna-answer-submit', _m('BUTTON', 'Answer'), 'submit', 'submit');
    }
}
