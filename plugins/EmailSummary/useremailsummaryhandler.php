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
        return 'sitesum';
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

        $ess = Email_summary_status::staticGet('user_id', $user_id);
	
        if (!empty($ess) && !$ess->send_summary) {
            common_log(LOG_INFO, sprintf('Not sending email summary for user %s by request.', $user_id));
            return true;
        }

        $since_id = null;
	
        if (!empty($ess)) {
            $since_id = $ess->last_summary_id;
        }
	  
        $user = User::staticGet('id', $user_id);

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
	
        $notice = $user->ownFriendsTimeline(0, self::MAX_NOTICES, $since_id);

        if (empty($notice) || $notice->N == 0) {
            common_log(LOG_WARNING, sprintf('Not sending email summary for user %s; no notices.', $user_id));
            return true;
        }

        // XXX: This is risky fingerpoken in der objektvars, but I didn't feel like
        // figuring out a better way. -ESP

        $new_top = null;
	
        if ($notice instanceof ArrayWrapper) {
            $new_top = $notice->_items[0]->id;
        }
	
        $out = new XMLStringer();

        $out->elementStart('div', array('width' => '100%',
                                        'style' => 'background-color: #ffffff; border: 4px solid #4c609a; padding: 10px;'));

        $out->elementStart('div', array('style' => 'color: #ffffff; background-color: #4c609a; font-weight: bold; margin-bottom: 10px; padding: 4px;'));
    	$out->raw(sprintf(_('Recent updates from %1s for %2s:'),
                          common_config('site', 'name'),
                          $profile->getBestName()));
        $out->elementEnd('div');

        $out->elementStart('table', array('width' => '550px',
                                          'style' => 'border: none; border-collapse: collapse;', 'cellpadding' => '6'));

        while ($notice->fetch()) {
	    
            $profile = Profile::staticGet('id', $notice->profile_id);
	    
            if (empty($profile)) {
                continue;
            }
	    
            $avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);

            $out->elementStart('tr');
            $out->elementStart('td', array('width' => AVATAR_STREAM_SIZE,
                                           'height' => AVATAR_STREAM_SIZE,
                                           'align' => 'left',
                                           'valign' => 'top',
                                           'style' => 'border-bottom: 1px dotted #C5CEE3; padding: 10px 6px 10px 6px;'));
            $out->element('img', array('src' => ($avatar) ?
                                       $avatar->displayUrl() :
                                       Avatar::defaultImage(AVATAR_STREAM_SIZE),
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
            $noticeurl = $notice->bestUrl();
            // above should always return an URL
            assert(!empty($noticeurl));
            $out->elementStart('a', array('rel' => 'bookmark',
                                          'href' => $noticeurl));
            $dt = common_date_iso8601($notice->created);
            $out->element('abbr', array('style' => 'border-bottom: none;',
                                        'title' => $dt),
                          common_date_string($notice->created));
            $out->elementEnd('a');
            if ($notice->hasConversation()) {
                $conv = Conversation::staticGet('id', $notice->conversation);
                $convurl = $conv->uri;
                if (!empty($convurl)) {
                    $out->text(' ');
                    $out->element('a',
                                  array('href' => $convurl.'#notice-'.$notice->id),
                                  _('in context'));
                }
            }
            $out->elementEnd('div');
            $out->elementEnd('td');
            $out->elementEnd('tr');
        }
	
        $out->elementEnd('table');

        $out->raw(sprintf(_('<p><a href="%1s">change your email settings for %2s</a></p>'),
                          common_local_url('emailsettings'),
                          common_config('site', 'name')));

        $out->elementEnd('div');
	
        $body = $out->getString();
	
        // FIXME: do something for people who don't like HTML email
	
        mail_to_user($user, _('Updates from your network'), $body,
                     array('Content-Type' => 'text/html; charset=UTF-8'));

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
