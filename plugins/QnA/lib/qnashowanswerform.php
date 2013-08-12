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
 * Form for showing / revising an answer
 *
 * @category Form
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class QnashowanswerForm extends Form
{
    /**
     * The answer to show
     */
    protected $answer   = null;

    /**
     * The question this is an answer to
     */
    protected $question = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out    output channel
     * @param QnA_Answer    $answer answer to revise
     */
    function __construct($out = null, $answer = null)
    {
        parent::__construct($out);

        $this->answer   = $answer;
        $this->question = $answer->getQuestion();
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'show-' . $this->answer->id;
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('qnareviseanswer');
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
        // TRANS: Form legend for showing the answer.
        $this->out->element('legend', null, _m('Answer'));
    }

    /**
     * Data elements
     *
     * @return void
     */
    function formData()
    {
        $this->out->hidden(
            'qna-answer-id',
            'answer-' . $this->answer->id,
            'id'
        );

        $this->out->raw($this->answer->asHTML());
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
                if (empty($this->answer->best)) {
                    $this->out->submit(
                        'qna-best-answer',
                        // TRANS: Button text for marking an answer as "best".
                        _m('BUTTON', 'Best'),
                        'submit',
                        'best',
                        // TRANS: Title for button text marking an answer as "best".
                        _m('Mark this answer as the best answer.')
                    );

                }
            }

            /*
             * @todo FIXME: Revise is disabled until we figure out the
             *         Ostatus bits This comment is just a reminder
             *         that the UI for this works.
             */
            /*
            if ($user->id == $this->answer->profile_id) {
                $this->out->submit(
                    'revise',
                    // TRANS: Button text for revising an answer.
                    _m('BUTTON', 'Revise'),
                    'submit',
                    null,
                    // TRANS: Title for button text for revising an answer.
                    _m('Revise your answer.')
                );
            }
             */
        }
    }

    /**
     * Class of the form.
     *
     * @return string the form's class
     */
    function formClass()
    {
        return 'form_answer_show ajax';
    }
}
