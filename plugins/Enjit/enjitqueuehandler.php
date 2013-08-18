<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Queue handler for watching new notices and posting to enjit.
 * @todo FIXME: Is this actually being used/functional atm?
 */
class EnjitQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'enjit';
    }

    function handle($notice)
    {

        $profile = Profile::getKV($notice->profile_id);

        $this->log(LOG_INFO, "Posting Notice ".$notice->id." from ".$profile->nickname);

        if ( ! $notice->is_local ) {
            $this->log(LOG_INFO, "Skipping remote notice");
            return "skipped";
        }

        #
        // Build an Atom message from the notice
        #
        $noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
        $msg = $profile->nickname . ': ' . $notice->content;

        $atom  = "<entry xmlns='http://www.w3.org/2005/Atom'>\n";
        $atom .= "<apisource>".common_config('enjit','source')."</apisource>\n";
        $atom .= "<source>\n";
        $atom .= "<title>" . $profile->nickname . " - " . common_config('site', 'name') . "</title>\n";
        $atom .= "<link href='" . $profile->profileurl . "'/>\n";
        $atom .= "<link rel='self' type='application/rss+xml' href='" . common_local_url('userrss', array('nickname' => $profile->nickname)) . "'/>\n";
        $atom .= "<author><name>" . $profile->nickname . "</name></author>\n";
        $atom .= "<icon>" . $profile->avatarUrl(AVATAR_PROFILE_SIZE) . "</icon>\n";
        $atom .= "</source>\n";
        $atom .= "<title>" . htmlspecialchars($msg) . "</title>\n";
        $atom .= "<summary>" . htmlspecialchars($msg) . "</summary>\n";
        $atom .= "<link rel='alternate' href='" . $noticeurl . "' />\n";
        $atom .= "<id>". $notice->uri . "</id>\n";
        $atom .= "<published>".common_date_w3dtf($notice->created)."</published>\n";
        $atom .= "<updated>".common_date_w3dtf($notice->modified)."</updated>\n";
        $atom .= "</entry>\n";

        $url  = common_config('enjit', 'apiurl') . "/submit/". common_config('enjit','apikey');
        $data = array(
            'msg' => $atom,
        );

        #
        // POST the message to $config['enjit']['apiurl']
        #
        $request = HTTPClient::start();
        $response = $request->post($url, null, $data);

        return $response->isOk();
    }
}
