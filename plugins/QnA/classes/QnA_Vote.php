<?php
/**
 * Data class to save users votes for
 *
 * PHP version 5
 *
 * @category QnA
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * For storing votes on question and answers
 *
 * @category QnA
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class QnA_Vote extends Managed_DataObject
{
    const UP   = 'http://activitystrea.ms/schema/1.0/like';
    const DOWN = 'http://activityschema.org/object/dislike'; // Gar!

    public $__table = 'qna_vote'; // table name
    public $id;          // char(36) primary key not null -> UUID
    public $question_id; // char(36) -> question.id UUID
    public $answer_id;   // char(36) -> question.id UUID
    public $type;        // tinyint -> vote: up (1) or down (-1)
    public $profile_id;  // int -> question.id
    public $created;     // datetime

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'For storing votes on questions and answers',
            'fields' => array(
                'id' => array(
                    'type'        => 'char',
                    'length'      => 36,
                    'not null'    => true,
                    'description' => 'UUID of the vote'
                ),
                'question_id' => array(
                    'type'        => 'char',
                    'length'      => 36,
                    'not null'    => true,
                    'description' => 'UUID of question being voted on'
                ),
                'answer_id' => array(
                    'type'        => 'char',
                    'length'      => 36,
                    'not null'    => true,
                    'description' => 'UUID of answer being voted on'
                ),
                'vote'       => array('type' => 'int', 'size' => 'tiny'),
                'profile_id' => array('type' => 'int'),
                'created'    => array('type' => 'datetime', 'not null' => true),
            ),
            'primary key' => array('id'),
            'indexes' => array(
                'profile_id_question_Id_index' => array(
                    'profile_id',
                    'question_id'
                ),
                'profile_id_question_Id_index' => array(
                    'profile_id',
                    'answer_id'
                )
            )
        );
    }

    /**
     * Save a vote on a question or answer
     *
     * @param Profile  $profile
     * @param QnA_Question the question being voted on
     * @param QnA_Answer   the answer being voted on
     * @param vote
     * @param array
     *
     * @return Void
     */
    static function save($profile, $question, $answer, $vote)
    {
        $v = new QnA_Vote();
        $v->id          = UUID::gen();
        $v->profile_id  = $profile->id;
        $v->question_id = $question->id;
        $v->answer_id   = $answer->id;
        $v->vote        = $vote;
        $v->created     = common_sql_now();

        common_log(LOG_DEBUG, "Saving vote: $v->id $v->vote");

        $v->insert();
    }
}
