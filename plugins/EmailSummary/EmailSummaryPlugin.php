<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Sends an email summary of the inbox to users in the network
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
 * @category  Sample
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin for sending email summaries to users
 *
 * @category  Email
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EmailSummaryPlugin extends Plugin
{
    /**
     * Database schema setup
     *
     * @return boolean hook value
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles
        $schema->ensureTable('email_summary_status', Email_summary_status::schemaDef());
        return true;
    }

    /**
     * Version info for this plugin
     *
     * @param array &$versions array of version data
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'EmailSummary',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'https://git.gnu.io/gnu/gnu-social/tree/master/plugins/EmailSummary',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Send an email summary of the inbox to users.'));
        return true;
    }

    /**
     * Register our queue handlers
     *
     * @param QueueManager $qm Current queue manager
     *
     * @return boolean hook value
     */
    function onEndInitializeQueueManager($qm)
    {
        $qm->connect('sitesum', 'SiteEmailSummaryHandler');
        $qm->connect('usersum', 'UserEmailSummaryHandler');
        return true;
    }

    /**
     * Add a checkbox to turn off email summaries
     *
     * @param Action $action Action being executed (emailsettings)
     * @param Profile $scoped Profile for whom settings are configured (current user)
     *
     * @return boolean hook value
     */
    public function onEndEmailFormData(Action $action, Profile $scoped)
    {
        $action->elementStart('li');
        $action->checkbox('emailsummary',
                          // TRANS: Checkbox label in e-mail preferences form.
                          _m('Send me a periodic summary of updates from my network'),
                          Email_summary_status::getSendSummary($scoped->id));
        $action->elementEnd('li');
        return true;
    }

    /**
     * Add a checkbox to turn off email summaries
     *
     * @param Action $action Action being executed (emailsettings)
     * @param Profile $scoped Profile for whom settings are configured (current user)
     *
     * @return boolean hook value
     */
    public function onEndEmailSaveForm(Action $action, Profile $scoped)
    {
        $sendSummary = $action->boolean('emailsummary');

        $ess = Email_summary_status::getKV('user_id', $scoped->id);

        if (empty($ess)) {

            $ess = new Email_summary_status();

            $ess->user_id      = $scoped->id;
            $ess->send_summary = $sendSummary;
            $ess->created      = common_sql_now();
            $ess->modified     = common_sql_now();

            $ess->insert();

        } else {

            $orig = clone($ess);

            $ess->send_summary = $sendSummary;
            $ess->modified     = common_sql_now();

            $ess->update($orig);
        }

        return true;
    }
}
