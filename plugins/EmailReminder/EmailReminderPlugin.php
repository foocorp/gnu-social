<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Plugin for sending email reminders about various things
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
 * @category  OnDemand
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
 * Email reminder plugin
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EmailReminderPlugin extends Plugin
{
    /**
     * Set up email_reminder table
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('email_reminder', Email_reminder::schemaDef());
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
        $qm->connect('siterem', 'SiteConfirmReminderHandler');
        $qm->connect('uregrem', 'UserConfirmRegReminderHandler');
        $qm->connect('uinvrem', 'UserInviteReminderHandler');

        return true;
    }

    function onEndDocFileForTitle($title, $paths, &$filename)
    {
        if (empty($filename)) {
            $filename = dirname(__FILE__) . '/mail-src/' . $title;
            return false;
        }

        return true;
    }

    /**
     * Send a reminder and record doing so
     *
     * @param string $type      type of reminder
     * @param mixed  $object    Confirm_address or Invitation object
     * @param string $subject   subjct of the email reminder
     * @param int    $day       number of days
     */
    static function sendReminder($type, $object, $subject, $day)
    {
        // XXX: -1 is a for the special one-time reminder (maybe 30) would be
        // better?  Like >= 30 days?
        if ($day == -1) {
            $title = "{$type}-onetime";
        } else {
            $title = "{$type}-{$day}";
        }

        // Record the fact that we sent a reminder
        if (self::sendReminderEmail($type, $object, $subject, $title)) {
            try {
                Email_reminder::recordReminder($type, $object, $day);
                common_log(
                    LOG_INFO,
                    "Sent {$type} reminder to {$object->address}.",
                    __FILE__
                );
            } catch (Exception $e) {
                // oh noez
                common_log(LOG_ERR, $e->getMessage(), __FILE__);
            }
        }

        return true;
    }

    /**
     * Send a real live email reminder
     *
     * @todo This would probably be better as two or more sep functions
     *
     * @param string $type      type of reminder
     * @param mixed  $object    Confirm_address or Invitation object
     * @param string $subject   subjct of the email reminder
     * @param string $title     title of the email reminder
     * @return boolean true if the email subsystem doesn't explode
     */
    static function sendReminderEmail($type, $object, $subject, $title = null) {

        $sitename   = common_config('site', 'name');
        $recipients = array($object->address);
        $inviter    = null;
        $inviterurl = null;

        if ($type == UserInviteReminderHandler::INVITE_REMINDER) {
            $user = User::getKV($object->user_id);
            if (!empty($user)) {
                $profile    = $user->getProfile();
                $inviter    = $profile->getBestName();
                $inviterUrl = $profile->profileurl;
            }
        }

        $headers['From'] = mail_notify_from();
        $headers['To']   = trim($object->address);
        // TRANS: Subject for confirmation e-mail.
        // TRANS: %s is the StatusNet sitename.
        $headers['Subject']      = $subject;
        $headers['Content-Type'] = 'text/html; charset=UTF-8';

        $confirmUrl = common_local_url('register', array('code' => $object->code));

        $template = DocFile::forTitle($title, DocFile::mailPaths());

        $blankfillers = array('confirmurl' => $confirmUrl);

        if ($type == UserInviteReminderHandler::INVITE_REMINDER) {
            $blankfillers['inviter'] = $inviter;
            $blankfillers['inviterurl'] = $inviterUrl;
            // @todo private invitation message?
        }

        $body = $template->toHTML($blankfillers);

        return mail_send($recipients, $headers, $body);
    }

    /**
     *
     * @param type $versions
     * @return type
     */
    function onPluginVersion(array &$versions)
    {
        $versions[] = array(
            'name'           => 'EmailReminder',
            'version'        => GNUSOCIAL_VERSION,
            'author'         => 'Zach Copley',
            'homepage'       => 'http://status.net/wiki/Plugin:EmailReminder',
            // TRANS: Plugin description.
            'rawdescription' => _m('Send email reminders for various things.')
        );
        return true;
    }

}
