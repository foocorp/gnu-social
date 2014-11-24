<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 *
 * Handler for queue items of type 'usersum', sends an email summaries
 * to a particular user.
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
 * Handler for queue items of type 'usersum', sends an email summaries
 * to a particular user.
 *
 * @category  Email
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class UserEmailSummaryHandler extends QueueHandler
{
    // Maximum number of notices to include by default. This is probably too much.
    const MAX_NOTICES = 200;

    /**
     * Return transport keyword which identifies items this queue handler
     * services; must be defined for all subclasses.
     *
     * Must be 8 characters or less to fit in the queue_item database.
     * ex "email", "jabber", "sms", "irc", ...
     *
     * @return string
     */
    function transport()
    {
        return 'usersum';
    }

    /**
     * Send a summary email to the user
     *
     * @param mixed $object
     * @return boolean true on success, false on failure
     */
    function handle($user_id)
    {
        // Skip if they've asked not to get summaries

        $ess = Email_summary_status::getKV('user_id', $user_id);

        if (!empty($ess) && !$ess->send_summary) {
            common_log(LOG_INFO, sprintf('Not sending email summary for user %s by request.', $user_id));
            return true;
        }

        $since_id = null;

        if (!empty($ess)) {
            $since_id = $ess->last_summary_id;
        }

        $user = User::getKV('id', $user_id);

        if (empty($user)) {
            common_log(LOG_INFO, sprintf('Not sending email summary for user %s; no such user.', $user_id));
            return true;
        }

        if (empty($user->email)) {
            common_log(LOG_INFO, sprintf('Not sending email summary for user %s; no email address.', $user_id));
            return true;
        }

        $profile = $user->getProfile();

        if (empty($profile)) {
            common_log(LOG_WARNING, sprintf('Not sending email summary for user %s; no profile.', $user_id));
            return true;
        }

        // An InboxNoticeStream for a certain user, scoped to its own view
        $stream = new InboxNoticeStream($profile);

        $notice = $stream->getNotices(0, self::MAX_NOTICES, $since_id);

        if (empty($notice) || $notice->N == 0) {
            common_log(LOG_WARNING, sprintf('Not sending email summary for user %s; no notices.', $user_id));
            return true;
        }

        // XXX: This is risky fingerpoken in der objektvars, but I didn't feel like
        // figuring out a better way. -ESP

        $new_top = null;

        if ($notice->fetch()) {
            $new_top = $notice->id;
        }

        // TRANS: Subject for e-mail.
        $subject = sprintf(_m('Your latest updates from %s'), common_config('site', 'name'));

        $out = new XMLStringer(true);

        $out->elementStart('html');
        $out->elementStart('head');
        $out->element('title', null, $subject);
        $out->elementEnd('head');
        $out->elementStart('body');
        $out->elementStart('div', array('width' => '100%',
                                        'style' => 'background-color: #ffffff; border: 4px solid #4c609a; padding: 10px;'));

        $out->elementStart('div', array('style' => 'color: #ffffff; background-color: #4c609a; font-weight: bold; margin-bottom: 10px; padding: 4px;'));
        // TRANS: Text in e-mail summary.
        // TRANS: %1$s is the StatusNet sitename, %2$s is the recipient's profile name.
        $out->raw(sprintf(_m('Recent updates from %1$s for %2$s:'),
                          common_config('site', 'name'),
                          $profile->getBestName()));
        $out->elementEnd('div');

        $out->elementStart('table', array('width' => '550px',
                                          'style' => 'border: none; border-collapse: collapse;', 'cellpadding' => '6'));

        do {
            $profile = Profile::getKV('id', $notice->profile_id);

            if (empty($profile)) {
                continue;
            }

            $avatarUrl = $profile->avatarUrl(AVATAR_STREAM_SIZE);

            $out->elementStart('tr');
            $out->elementStart('td', array('width' => AVATAR_STREAM_SIZE,
                                           'height' => AVATAR_STREAM_SIZE,
                                           'align' => 'left',
                                           'valign' => 'top',
                                           'style' => 'border-bottom: 1px dotted #C5CEE3; padding: 10px 6px 10px 6px;'));
            $out->element('img', array('src' => $avatarUrl,
                                       'width' => AVATAR_STREAM_SIZE,
                                       'height' => AVATAR_STREAM_SIZE,
                                       'alt' => $profile->getBestName()));
            $out->elementEnd('td');
            $out->elementStart('td', array('align' => 'left',
                                           'valign' => 'top',
                                           'style' => 'border-bottom: 1px dotted #C5CEE3; padding: 10px 6px 10px 6px;'));
            $out->element('a', array('href' => $profile->profileurl),
                          $profile->nickname);
            $out->text(' ');
            $out->raw($notice->rendered);
            $out->elementStart('div', array('style' => 'font-size: 0.8em; padding-top: 4px;'));
            $noticeurl = $notice->getUrl();
            // above should always return an URL
            assert(!empty($noticeurl));
            $out->elementStart('a', array('rel' => 'bookmark',
                                          'href' => $noticeurl));
            $dt = common_date_iso8601($notice->created);
            $out->element('abbr', array('style' => 'border-bottom: none;',
                                        'title' => $dt),
                          common_date_string($notice->created));
            $out->elementEnd('a');
            $out->element('a', array('href' => $notice->getConversationUrl()),
                          // TRANS: Link text for link to conversation view.
                          _m('in context'));
            $out->elementEnd('div');
            $out->elementEnd('td');
            $out->elementEnd('tr');
        } while ($notice->fetch());

        $out->elementEnd('table');

        // TRANS: Link text for link to e-mail settings.
        // TRANS: %1$s is a link to the e-mail settings, %2$s is the StatusNet sitename.
        $out->raw("<p>" . sprintf(_m('<a href="%1$s">change your email settings for %2$s</a>'),
                          common_local_url('emailsettings'),
                          common_config('site', 'name'))."</p>");

        $out->elementEnd('div');
        $out->elementEnd('body');
        $out->elementEnd('html');

        $body = $out->getString();

        // FIXME: do something for people who don't like HTML email

        mail_to_user($user,
                     $subject,
                     $body,
                     array('Content-Type' => 'text/html; charset=utf-8',
                           'Mime-Version' => '1.0'));

        if (empty($ess)) {
            $ess = new Email_summary_status();

            $ess->user_id         = $user_id;
            $ess->created         = common_sql_now();
            $ess->last_summary_id = $new_top;
            $ess->modified        = common_sql_now();

            $ess->insert();
        } else {
            $orig = clone($ess);

            $ess->last_summary_id = $new_top;
            $ess->modified        = common_sql_now();

            $ess->update($orig);
        }

        return true;
    }
}
