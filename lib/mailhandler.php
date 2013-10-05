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

require_once(INSTALLDIR . '/lib/mail.php');
require_once('Mail/mimeDecode.php');

// @todo FIXME: we use both Mail_mimeDecode and mailparse
// Need to move everything to mailparse

class MailHandler
{
    function __construct()
    {
    }

    function handle_message($rawmessage)
    {
        list($from, $to, $msg, $attachments) = $this->parse_message($rawmessage);
        if (!$from || !$to || !$msg) {
            // TRANS: Error message in incoming mail handler used when an incoming e-mail cannot be processed.
            $this->error(null, _('Could not parse message.'));
        }
        common_log(LOG_INFO, "Mail from $from to $to with ".count($attachments) .' attachment(s): ' .substr($msg, 0, 20));
        $user = $this->user_from_header($from);
        if (!$user) {
            // TRANS: Error message in incoming mail handler used when an incoming e-mail is not from a registered user.
            $this->error($from, _('Not a registered user.'));
            return false;
        }
        if (!$this->user_match_to($user, $to)) {
            // TRANS: Error message in incoming mail handler used when an incoming e-mail is not from a user's incoming e-mail address.
            $this->error($from, _('Sorry, that is not your incoming email address.'));
            return false;
        }
        if (!$user->emailpost) {
            // TRANS: Error message in incoming mail handler used when no incoming e-mail is allowed.
            $this->error($from, _('Sorry, no incoming email allowed.'));
            return false;
        }
        $response = $this->handle_command($user, $from, $msg);
        if ($response) {
            return true;
        }
        $msg = $this->cleanup_msg($msg);
        $msg = $user->shortenLinks($msg);
        if (Notice::contentTooLong($msg)) {
            // TRANS: Error message in incoming mail handler used when an incoming e-mail contains too many characters.
            $this->error($from, sprintf(_m('That\'s too long. Maximum notice size is %d character.',
                                          'That\'s too long. Maximum notice size is %d characters.',
                                          Notice::maxContent()),
                                        Notice::maxContent()));
        }

        $mediafiles = array();

        foreach($attachments as $attachment){
            $mf = null;

            try {
                $mf = MediaFile::fromFilehandle($attachment, $user->getProfile());
            } catch(ClientException $ce) {
                $this->error($from, $ce->getMessage());
            }

            $msg .= ' ' . $mf->shortUrl();

            array_push($mediafiles, $mf);
            fclose($attachment);
        }

        $err = $this->add_notice($user, $msg, $mediafiles);

        if (is_string($err)) {
            $this->error($from, $err);
            return false;
        } else {
            return true;
        }
    }

    function error($from, $msg)
    {
        file_put_contents("php://stderr", $msg . "\n");
        exit(1);
    }

    function user_from_header($from_hdr)
    {
        $froms = mailparse_rfc822_parse_addresses($from_hdr);
        if (!$froms) {
            return null;
        }
        $from = $froms[0];
        $addr = common_canonical_email($from['address']);
        $user = User::getKV('email', $addr);
        if (!$user) {
            $user = User::getKV('smsemail', $addr);
        }
        return $user;
    }

    function user_match_to($user, $to_hdr)
    {
        $incoming = $user->incomingemail;
        $tos = mailparse_rfc822_parse_addresses($to_hdr);
        foreach ($tos as $to) {
            if (strcasecmp($incoming, $to['address']) == 0) {
                return true;
            }
        }
        return false;
    }

    function handle_command($user, $from, $msg)
    {
        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($user, $msg);
        if ($cmd) {
            $cmd->execute(new MailChannel($from));
            return true;
        }
        return false;
    }

    function respond($from, $to, $response)
    {
        $headers['From'] = $to;
        $headers['To'] = $from;
        // TRANS: E-mail subject for reply to an e-mail command.
        $headers['Subject'] = _('Command complete');

        return mail_send(array($from), $headers, $response);
    }

    function log($level, $msg)
    {
        common_log($level, 'MailDaemon: '.$msg);
    }

    function add_notice($user, $msg, $mediafiles)
    {
        try {
            $notice = Notice::saveNew($user->id, $msg, 'mail');
        } catch (Exception $e) {
            $this->log(LOG_ERR, $e->getMessage());
            return $e->getMessage();
        }
        foreach($mediafiles as $mf){
            $mf->attachToNotice($notice);
        }

        $this->log(LOG_INFO,
                   'Added notice ' . $notice->id . ' from user ' . $user->nickname);
        return true;
    }

    function parse_message($contents)
    {
        $parsed = Mail_mimeDecode::decode(array('input' => $contents,
                                                'include_bodies' => true,
                                                'decode_headers' => true,
                                                'decode_bodies' => true));
        if (!$parsed) {
            return null;
        }

        $from = $parsed->headers['from'];

        $to = $parsed->headers['to'];

        $type = $parsed->ctype_primary . '/' . $parsed->ctype_secondary;

        $attachments = array();

        $this->extract_part($parsed,$msg,$attachments);

        return array($from, $to, $msg, $attachments);
    }

    function extract_part($parsed,&$msg,&$attachments){
        if ($parsed->ctype_primary == 'multipart') {
            if($parsed->ctype_secondary == 'alternative'){
                $altmsg = $this->extract_msg_from_multipart_alternative_part($parsed);
                if(!empty($altmsg)) $msg = $altmsg;
            }else{
                foreach($parsed->parts as $part){
                    $this->extract_part($part,$msg,$attachments);
                }
            }
        } else if ($parsed->ctype_primary == 'text'
            && $parsed->ctype_secondary=='plain') {
            $msg = $parsed->body;
            if(strtolower($parsed->ctype_parameters['charset']) != "utf-8"){
                $msg = utf8_encode($msg);
            }
        }else if(!empty($parsed->body)){
            if(common_config('attachments', 'uploads')){
                //only save attachments if uploads are enabled
                $attachment = tmpfile();
                fwrite($attachment, $parsed->body);
                $attachments[] = $attachment;
            }
        }
    }

    function extract_msg_from_multipart_alternative_part($parsed){
        foreach ($parsed->parts as $part) {
            $this->extract_part($part,$msg,$attachments);
        }
        //we don't want any attachments that are a result of this parsing
        return $msg;
    }

    function unsupported_type($type)
    {
        // TRANS: Error message in incoming mail handler used when an incoming e-mail is of an unsupported type.
        // TRANS: %s is the unsupported type.
        $this->error(null, sprintf(_('Unsupported message type: %s.'), $type));
    }

    function cleanup_msg($msg)
    {
        $lines = explode("\n", $msg);

        $output = '';

        foreach ($lines as $line) {
            // skip quotes
            if (preg_match('/^\s*>.*$/', $line)) {
                continue;
            }
            // skip start of quote
            if (preg_match('/^\s*On.*wrote:\s*$/', $line)) {
                continue;
            }
            // probably interesting to someone, not us
            if (preg_match('/^\s*Sent via/', $line)) {
                continue;
            }
            if (preg_match('/^\s*Sent from my/', $line)) {
                continue;
            }

            // skip everything after a sig
            if (preg_match('/^\s*--+\s*$/', $line) ||
                preg_match('/^\s*__+\s*$/', $line))
            {
                break;
            }
            // skip everything after Outlook quote
            if (preg_match('/^\s*-+\s*Original Message\s*-+\s*$/', $line)) {
                break;
            }
            // skip everything after weird forward
            if (preg_match('/^\s*Begin\s+forward/', $line)) {
                break;
            }
            // skip everything after a blank line if we already have content
            if ($output !== '' && $line === '') {
                break;
            }

            $output .= ' ' . $line;
        }

        preg_replace('/\s+/', ' ', $output);
        return trim($output);
    }
}
