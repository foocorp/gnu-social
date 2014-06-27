<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011,2012, StatusNet, Inc.
 *
 * ActivitySpam Plugin
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
 * @copyright 2011,2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Check new notices with activity spam service.
 * 
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011,2012 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class ActivitySpamPlugin extends Plugin
{
    public $server = null;
    public $hideSpam = false;

    const REVIEWSPAM = 'ActivitySpamPlugin::REVIEWSPAM';
    const TRAINSPAM = 'ActivitySpamPlugin::TRAINSPAM';

    /**
     * Initializer
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function initialize()
    {
        $this->filter = new SpamFilter(common_config('activityspam', 'server'),
                                       common_config('activityspam', 'consumerkey'),
                                       common_config('activityspam', 'secret'));

        $this->hideSpam = common_config('activityspam', 'hidespam');

        // Let DB_DataObject find Spam_score

        common_config_set('db', 'class_location', 
                          common_config('db', 'class_location') .':'.dirname(__FILE__));

        return true;
    }

    /**
     * Database schema setup
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('spam_score', Spam_score::schemaDef());

        Spam_score::upgrade();

        return true;
    }

    /**
     * When a notice is saved, check its spam score
     * 
     * @param Notice $notice Notice that was just saved
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onEndNoticeSave($notice)
    {
        try {

            $result = $this->filter->test($notice);

            $score = Spam_score::saveNew($notice, $result);

            $this->log(LOG_INFO, "Notice " . $notice->id . " has spam score " . $score->score);

        } catch (Exception $e) {
            // Log but continue 
            $this->log(LOG_ERR, $e->getMessage());
        }

        return true;
    }

    function onNoticeDeleteRelated($notice) {
        $score = Spam_score::getKV('notice_id', $notice->id);
        if (!empty($score)) {
            $score->delete();
        }
        return true;
    }

    function onUserRightsCheck($profile, $right, &$result) {
        switch ($right) {
        case self::REVIEWSPAM:
        case self::TRAINSPAM:
            $result = ($profile->hasRole(Profile_role::MODERATOR) || $profile->hasRole('modhelper'));
            return false;
        default:
            return true;
        }
    }

    function onGetSpamFilter(&$filter) {
        $filter = $this->filter;
        return false;
    }

    function onEndShowNoticeOptionItems($nli)
    {
        $profile = Profile::current();

        if (!empty($profile) && $profile->hasRight(self::TRAINSPAM)) {

            $notice = $nli->getNotice();
            $out = $nli->getOut();

            if (!empty($notice)) {

                $score = Spam_score::getKV('notice_id', $notice->id);

                if (empty($score)) {
                    // If it's empty, we can train it.
                    $form = new TrainSpamForm($out, $notice);
                    $form->show();
                } else if ($score->is_spam) {
                    $form = new TrainHamForm($out, $notice);
                    $form->show();
                } else if (!$score->is_spam) {
                    $form = new TrainSpamForm($out, $notice);
                    $form->show();
                }
            }
        }

        return true;
    }
    
    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onRouterInitialized($m)
    {
        $m->connect('main/train/spam',
                    array('action' => 'train', 'category' => 'spam'));
        $m->connect('main/train/ham',
                    array('action' => 'train', 'category' => 'ham'));
        $m->connect('main/spam',
                    array('action' => 'spam'));
        return true;
    }

    function onEndShowStyles($action)
    {
        $action->element('style', null,
                         '.form-train-spam input.submit { background: url('.$this->path('icons/bullet_black.png').') no-repeat 0px 0px } ' . "\n" .
                         '.form-train-ham input.submit { background: url('.$this->path('icons/exclamation.png').') no-repeat 0px 0px } ');
        return true;
    }

    function onEndPublicGroupNav($nav)
    {
        $user = common_current_user();

        if (!empty($user) && $user->hasRight(self::REVIEWSPAM)) {
            $nav->out->menuItem(common_local_url('spam'),
                                _m('MENU','Spam'),
                                // TRANS: Menu item title in search group navigation panel.
                                _('Notices marked as spam'),
                                $nav->actionName == 'spam',
                                'nav_timeline_spam');
        }

        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'ActivitySpam',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:ActivitySpam',
                            'description' =>
                            _m('Test notices against the Activity Spam service.'));
        return true;
    }

    function onEndNoticeInScope($notice, $profile, &$bResult)
    {
        if ($this->hideSpam) {
            if ($bResult) {

                $score = Spam_score::getKV('notice_id', $notice->id);

                if (!empty($score) && $score->is_spam) {
                    if (empty($profile) ||
                        ($profile->id !== $notice->profile_id &&
                         !$profile->hasRight(self::REVIEWSPAM))) {
                        $bResult = false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Pre-cache our spam scores if needed.
     */
    function onEndNoticeListPrefill(array &$notices, array &$profiles, array $notice_ids, Profile $scoped=null) {
        if ($this->hideSpam) {
            Spam_score::multiGet('notice_id', $notice_ids);
        }
        return true;
    }
}
