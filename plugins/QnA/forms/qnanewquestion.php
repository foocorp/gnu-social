<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Form for adding a new question
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
 * @author    Zach Copley <zach@copley.name>
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
 * Form to add a new question
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@copley.name>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class QnanewquestionForm extends Form
{
    protected $title;
    protected $description;

    /**
     * Construct a new question form
     *
     * @param HTMLOutputter $out output channel
     *
     * @return void
     */
    function __construct($out = null, $title = null, $description = null, $options = null)
    {
        parent::__construct($out);
        $this->title       = $title;
        $this->description = $description;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */
    function id()
    {
        return 'newquestion-form';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */
    function formClass()
    {
        return 'form_settings ajax-notice';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    function action()
    {
        return common_local_url('qnanewquestion');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->elementStart('fieldset', array('id' => 'newquestion-data'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->out->input(
            'qna-question-title',
            // TRANS: Field label for a new question.
            _m('LABEL','Title'),
            $this->title,
            // TRANS: Field title for a new question.
            _m('The title of your question.'),
            'title'
        );
        $this->unli();
        $this->li();
        $this->out->textarea(
            'qna-question-description',
            // TRANS: Field label for question details.
            _m('LABEL','Description'),
            $this->description,
            // TRANS: Field title for question details.
            _m('Your question in detail.'),
            'description'
        );
        $this->unli();

        $this->out->elementEnd('ul');
        $toWidget = new ToSelector(
            $this->out,
            common_current_user(),
            null
        );
        $toWidget->show();
        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // TRANS: Button text for saving a new question.
        $this->out->submit('qna-question-submit', _m('BUTTON', 'Save'), 'submit', 'submit');
    }
}
