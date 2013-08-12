<?php
  /**
   * StatusNet - the distributed open-source microblogging tool
   * Copyright (C) 2011, StatusNet, Inc.
   *
   * Score of a notice by activity spam service
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
   * @category  Spam
   * @package   StatusNet
   * @author    Evan Prodromou <evan@status.net>
   * @copyright 2011 StatusNet, Inc.
   * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
   * @link      http://status.net/
   */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Score of a notice per the activity spam service
 *
 * @category Spam
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */

class Spam_score extends Managed_DataObject
{
    const MAX_SCALE = 10000;
    public $__table = 'spam_score'; // table name

    public $notice_id;   // int
    public $score;       // float
    public $created;     // datetime

    function saveNew($notice, $result) {

        $score = new Spam_score();

        $score->notice_id      = $notice->id;
        $score->score          = $result->probability;
        $score->is_spam        = $result->isSpam;
        $score->scaled         = Spam_score::scale($score->score);
        $score->created        = common_sql_now();
        $score->notice_created = $notice->created;

        $score->insert();

        self::blow('spam_score:notice_ids');

        return $score;
    }

    function save($notice, $result) {

        $orig  = null;
        $score = Spam_score::staticGet('notice_id', $notice->id);

        if (empty($score)) {
            $score = new Spam_score();
        } else {
            $orig = clone($score);
        }

        $score->notice_id      = $notice->id;
        $score->score          = $result->probability;
        $score->is_spam        = $result->isSpam;
        $score->scaled         = Spam_score::scale($score->score);
        $score->created        = common_sql_now();
        $score->notice_created = $notice->created;

        if (empty($orig)) {
            $score->insert();
        } else {
            $score->update($orig);
        }
        
        self::blow('spam_score:notice_ids');

        return $score;
    }

    function delete()
    {
        self::blow('spam_score:notice_ids');
        self::blow('spam_score:notice_ids;last');
        parent::delete();
    }

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'score of the notice per activityspam',
            'fields' => array(
                'notice_id' => array('type' => 'int',
                                     'not null' => true,
                                     'description' => 'notice getting scored'),
                'score' => array('type' => 'double',
                                 'not null' => true,
                                 'description' => 'score for the notice (0.0, 1.0)'),
                'scaled' => array('type' => 'int',
                                  'description' => 'scaled score for the notice (0, 10000)'),
                'is_spam' => array('type' => 'tinyint',
                                   'description' => 'flag for spamosity'),
                'created' => array('type' => 'datetime',
                                   'not null' => true,
                                   'description' => 'date this record was created'),
                'notice_created' => array('type' => 'datetime',
                                          'description' => 'date the notice was created'),
            ),
            'primary key' => array('notice_id'),
            'foreign keys' => array(
                'spam_score_notice_id_fkey' => array('notice', array('notice_id' => 'id')),
            ),
            'indexes' => array(
                'spam_score_created_idx' => array('created'),
                'spam_score_scaled_idx' => array('scaled'),
            ),
        );
    }

    public static function upgrade()
    {
        Spam_score::upgradeScaled();
        Spam_score::upgradeIsSpam();
        Spam_score::upgradeNoticeCreated();
    }

    protected static function upgradeScaled()
    {
        $score = new Spam_score();
        $score->whereAdd('scaled IS NULL');
        
        if ($score->find()) {
            while ($score->fetch()) {
                $orig = clone($score);
                $score->scaled = Spam_score::scale($score->score);
                $score->update($orig);
            }
        }
    }

    protected static function upgradeIsSpam()
    {
        $score = new Spam_score();
        $score->whereAdd('is_spam IS NULL');
        
        if ($score->find()) {
            while ($score->fetch()) {
                $orig = clone($score);
                $score->is_spam = ($score->score >= 0.90) ? 1 : 0;
                $score->update($orig);
            }
        }
    }

    protected static function upgradeNoticeCreated()
    {
        $score = new Spam_score();
        $score->whereAdd('notice_created IS NULL');
        
        if ($score->find()) {
            while ($score->fetch()) {
                $notice = Notice::staticGet('id', $score->notice_id);
                if (!empty($notice)) {
                    $orig = clone($score);
                    $score->notice_created = $notice->created;
                    $score->update($orig);
                }
            }
        }
    }

    public static function scale($score)
    {
        $raw = round($score * Spam_score::MAX_SCALE);
        return max(0, min(Spam_score::MAX_SCALE, $raw));
    }
}
