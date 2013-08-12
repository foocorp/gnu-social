<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for showing / revising an answer
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
 * @category  Form
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/form.php';

/**
 * Form for showing a question
 *
 * @category Form
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class QnashowquestionForm extends Form
{
    /**
     * The question to show
     */
    var $question = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out      output channel
     * @param QnA_Question  $question the question to show
     */
    function __construct($out = null, $question = null)
    {
        parent::__construct($out);
        $this->question = $question;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'question-' . $this->question->id;
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('qnaclosequestion');
    }

    /**
     * Include a session token for CSRF protection
     *
     * @return void
     */
    function sessionToken()
    {
        $this->out->hidden(
            'token',
            common_session_token()
        );
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        // TRANS: Form legend for revising the answer.
        $this->out->element('legend', null, _m('LEGEND','Question'));
    }

    /**
     * Data elements
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden(
            'qna-question-id',
            'question-' . $this->question->id,
            'id'
        );

        $this->out->hidden(
            'answer-action',
            common_local_url(
                'qnanewanswer',
                null,
                array('id' => 'question-' . $this->question->id)
            )
        );

        $this->out->raw($this->question->asHTML());
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        $user = common_current_user();
        if (empty($user)) {
            return;
        }

        if (empty($this->question->closed)) {
            if ($user->id == $this->question->profile_id) {
             $this->out->submit(
                'qna-question-close',
                // TRANS: Button text for closing a question.
                _m('BUTTON', 'Close'),
                'submit',
                'submit',
                // TRANS: Title for button text for closing a question.
                _m('Close the question to no one can answer it anymore.')
             );
            }
        }
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_question_show ajax';
    }
}
