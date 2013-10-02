<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Send and receive notices using the XMPP network
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
 * @category  IM
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Plugin for XMPP
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class XmppPlugin extends ImPlugin
{
    public $server = null;
    public $port = 5222;
    public $user =  'update';
    public $resource = null;
    public $encryption = true;
    public $password = null;
    public $host = null;  // only set if != server
    public $debug = false; // print extra debug info

    public $transport = 'xmpp';

    function getDisplayName(){
        // TRANS: Plugin display name.
        return _m('XMPP/Jabber/GTalk');
    }

    /**
     * Splits a Jabber ID (JID) into node, domain, and resource portions.
     *
     * Based on validation routine submitted by:
     * @copyright 2009 Patrick Georgi <patrick@georgi-clan.de>
     * @license Licensed under ISC-L, which is compatible with everything else that keeps the copyright notice intact.
     *
     * @param string $jid string to check
     *
     * @return array with "node", "domain", and "resource" indices
     * @throws Exception if input is not valid
     */
    protected function splitJid($jid)
    {
        $chars = '';
        /* the following definitions come from stringprep, Appendix C,
           which is used in its entirety by nodeprop, Chapter 5, "Prohibited Output" */
        /* C1.1 ASCII space characters */
        $chars .= "\x{20}";
        /* C1.2 Non-ASCII space characters */
        $chars .= "\x{a0}\x{1680}\x{2000}-\x{200b}\x{202f}\x{205f}\x{3000a}";
        /* C2.1 ASCII control characters */
        $chars .= "\x{00}-\x{1f}\x{7f}";
        /* C2.2 Non-ASCII control characters */
        $chars .= "\x{80}-\x{9f}\x{6dd}\x{70f}\x{180e}\x{200c}\x{200d}\x{2028}\x{2029}\x{2060}-\x{2063}\x{206a}-\x{206f}\x{feff}\x{fff9}-\x{fffc}\x{1d173}-\x{1d17a}";
        /* C3 - Private Use */
        $chars .= "\x{e000}-\x{f8ff}\x{f0000}-\x{ffffd}\x{100000}-\x{10fffd}";
        /* C4 - Non-character code points */
        $chars .= "\x{fdd0}-\x{fdef}\x{fffe}\x{ffff}\x{1fffe}\x{1ffff}\x{2fffe}\x{2ffff}\x{3fffe}\x{3ffff}\x{4fffe}\x{4ffff}\x{5fffe}\x{5ffff}\x{6fffe}\x{6ffff}\x{7fffe}\x{7ffff}\x{8fffe}\x{8ffff}\x{9fffe}\x{9ffff}\x{afffe}\x{affff}\x{bfffe}\x{bffff}\x{cfffe}\x{cffff}\x{dfffe}\x{dffff}\x{efffe}\x{effff}\x{ffffe}\x{fffff}\x{10fffe}\x{10ffff}";
        /* C5 - Surrogate codes */
        $chars .= "\x{d800}-\x{dfff}";
        /* C6 - Inappropriate for plain text */
        $chars .= "\x{fff9}-\x{fffd}";
        /* C7 - Inappropriate for canonical representation */
        $chars .= "\x{2ff0}-\x{2ffb}";
        /* C8 - Change display properties or are deprecated */
        $chars .= "\x{340}\x{341}\x{200e}\x{200f}\x{202a}-\x{202e}\x{206a}-\x{206f}";
        /* C9 - Tagging characters */
        $chars .= "\x{e0001}\x{e0020}-\x{e007f}";

        /* Nodeprep forbids some more characters */
        $nodeprepchars = $chars;
        $nodeprepchars .= "\x{22}\x{26}\x{27}\x{2f}\x{3a}\x{3c}\x{3e}\x{40}";

        $parts = explode("/", $jid, 2);
        if (count($parts) > 1) {
            $resource = $parts[1];
            if ($resource == '') {
                // Warning: empty resource isn't legit.
                // But if we're normalizing, we may as well take it...
            }
        } else {
            $resource = null;
        }

        $node = explode("@", $parts[0]);
        if ((count($node) > 2) || (count($node) == 0)) {
            // TRANS: Exception thrown when using too many @ signs in a Jabber ID.
            throw new Exception(_m('Invalid JID: too many @s.'));
        } else if (count($node) == 1) {
            $domain = $node[0];
            $node = null;
        } else {
            $domain = $node[1];
            $node = $node[0];
            if ($node == '') {
            // TRANS: Exception thrown when using @ sign not followed by a Jabber ID.
                throw new Exception(_m('Invalid JID: @ but no node'));
            }
        }

        // Length limits per http://xmpp.org/rfcs/rfc3920.html#addressing
        if ($node !== null) {
            if (strlen($node) > 1023) {
                // TRANS: Exception thrown when using too long a Jabber ID (>1023).
                throw new Exception(_m('Invalid JID: node too long.'));
            }
            if (preg_match("/[".$nodeprepchars."]/u", $node)) {
                // TRANS: Exception thrown when using an invalid Jabber ID.
                // TRANS: %s is the invalid Jabber ID.
                throw new Exception(sprintf(_m('Invalid JID node "%s".'),$node));
            }
        }

        if (strlen($domain) > 1023) {
            // TRANS: Exception thrown when using too long a Jabber domain (>1023).
            throw new Exception(_m('Invalid JID: domain too long.'));
        }
        if (!common_valid_domain($domain)) {
            // TRANS: Exception thrown when using an invalid Jabber domain name.
            // TRANS: %s is the invalid domain name.
            throw new Exception(sprintf(_m('Invalid JID domain name "%s".'),$domain));
        }

        if ($resource !== null) {
            if (strlen($resource) > 1023) {
                // TRANS: Exception thrown when using too long a resource (>1023).
                throw new Exception("Invalid JID: resource too long.");
            }
            if (preg_match("/[".$chars."]/u", $resource)) {
                // TRANS: Exception thrown when using an invalid Jabber resource.
                // TRANS: %s is the invalid resource.
                throw new Exception(sprintf(_m('Invalid JID resource "%s".'),$resource));
            }
        }

        return array('node' => is_null($node) ? null : mb_strtolower($node),
                     'domain' => is_null($domain) ? null : mb_strtolower($domain),
                     'resource' => $resource);
    }

    /**
     * Checks whether a string is a syntactically valid Jabber ID (JID),
     * either with or without a resource.
     *
     * Note that a bare domain can be a valid JID.
     *
     * @param string $jid string to check
     * @param bool $check_domain whether we should validate that domain...
     *
     * @return     boolean whether the string is a valid JID
     */
    protected function validateFullJid($jid, $check_domain=false)
    {
        try {
            $parts = $this->splitJid($jid);
            if ($check_domain) {
                if (!$this->checkDomain($parts['domain'])) {
                    return false;
                }
            }
            return $parts['resource'] !== ''; // missing or present; empty ain't kosher
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Checks whether a string is a syntactically valid base Jabber ID (JID).
     * A base JID won't include a resource specifier on the end; since we
     * take it off when reading input we can't really use them reliably
     * to direct outgoing messages yet (sorry guys!)
     *
     * Note that a bare domain can be a valid JID.
     *
     * @param string $jid string to check
     * @param bool $check_domain whether we should validate that domain...
     *
     * @return     boolean whether the string is a valid JID
     */
    protected function validateBaseJid($jid, $check_domain=false)
    {
        try {
            $parts = $this->splitJid($jid);
            if ($check_domain) {
                if (!$this->checkDomain($parts['domain'])) {
                    return false;
                }
            }
            return ($parts['resource'] === null); // missing; empty ain't kosher
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Normalizes a Jabber ID for comparison, dropping the resource component if any.
     *
     * @param string $jid JID to check
     * @param bool $check_domain if true, reject if the domain isn't findable
     *
     * @return string an equivalent JID in normalized (lowercase) form
     */
    function normalize($jid)
    {
        try {
            $parts = $this->splitJid($jid);
            if ($parts['node'] !== null) {
                return $parts['node'] . '@' . $parts['domain'];
            } else {
                return $parts['domain'];
            }
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if this domain's got some legit DNS record
     */
    protected function checkDomain($domain)
    {
        if (checkdnsrr("_xmpp-server._tcp." . $domain, "SRV")) {
            return true;
        }
        if (checkdnsrr($domain, "ANY")) {
            return true;
        }
        return false;
    }

    function daemonScreenname()
    {
        $ret = $this->user . '@' . $this->server;
        if($this->resource)
        {
            return $ret . '/' . $this->resource;
        }else{
            return $ret;
        }
    }

    function validate($screenname)
    {
        return $this->validateBaseJid($screenname, common_config('email', 'check_domain'));
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'XMPPHP_XMPP':
            require_once $dir . '/extlib/XMPPHP/XMPP.php';
            return false;
        }

        return parent::onAutoload($cls);
    }

    function onStartImDaemonIoManagers(&$classes)
    {
        parent::onStartImDaemonIoManagers($classes);
        $classes[] = new XmppManager($this); // handles pings/reconnects
        return true;
    }

    function microiduri($screenname)
    {
        return 'xmpp:' . $screenname;
    }

    function sendMessage($screenname, $body)
    {
        $this->queuedConnection()->message($screenname, $body, 'chat');
    }

    function sendNotice($screenname, $notice)
    {
        $msg   = $this->formatNotice($notice);
        $entry = $this->format_entry($notice);

        $this->queuedConnection()->message($screenname, $msg, 'chat', null, $entry);
        return true;
    }

    /**
     * extra information for XMPP messages, as defined by Twitter
     *
     * @param Profile $profile Profile of the sending user
     * @param Notice  $notice  Notice being sent
     *
     * @return string Extra information (Atom, HTML, addresses) in string format
     */
    function format_entry($notice)
    {
        $profile = $notice->getProfile();

        $entry = $notice->asAtomEntry(true, true);

        $xs = new XMLStringer();
        $xs->elementStart('html', array('xmlns' => 'http://jabber.org/protocol/xhtml-im'));
        $xs->elementStart('body', array('xmlns' => 'http://www.w3.org/1999/xhtml'));
        $xs->element('a', array('href' => $profile->profileurl),
                     $profile->nickname);
        $xs->text(": ");
        if (!empty($notice->rendered)) {
            $xs->raw($notice->rendered);
        } else {
            $xs->raw(common_render_content($notice->content, $notice));
        }
        $xs->text(" ");
        $xs->element('a', array(
            'href'=>common_local_url('conversation',
                array('id' => $notice->conversation)).'#notice-'.$notice->id),
             // TRANS: Link description to notice in conversation.
             // TRANS: %s is a notice ID.
             sprintf(_m('[%s]'),$notice->id));
        $xs->elementEnd('body');
        $xs->elementEnd('html');

        $html = $xs->getString();

        return $html . ' ' . $entry;
    }

    function receiveRawMessage($pl)
    {
        $from = $this->normalize($pl['from']);

        if ($pl['type'] != 'chat') {
            $this->log(LOG_WARNING, "Ignoring message of type ".$pl['type']." from $from: " . $pl['xml']->toString());
            return true;
        }

        if (mb_strlen($pl['body']) == 0) {
            $this->log(LOG_WARNING, "Ignoring message with empty body from $from: "  . $pl['xml']->toString());
            return true;
        }

        $this->handleIncoming($from, $pl['body']);

        return true;
    }

    /**
     * Build a queue-proxied XMPP interface object. Any outgoing messages
     * will be run back through us for enqueing rather than sent directly.
     *
     * @return QueuedXMPP
     * @throws Exception if server settings are invalid.
     */
    function queuedConnection(){
        if(!isset($this->server)){
            // TRANS: Exception thrown when the plugin configuration is incorrect.
            throw new Exception(_m('You must specify a server in the configuration.'));
        }
        if(!isset($this->port)){
            // TRANS: Exception thrown when the plugin configuration is incorrect.
            throw new Exception(_m('You must specify a port in the configuration.'));
        }
        if(!isset($this->user)){
            // TRANS: Exception thrown when the plugin configuration is incorrect.
            throw new Exception(_m('You must specify a user in the configuration.'));
        }
        if(!isset($this->password)){
            // TRANS: Exception thrown when the plugin configuration is incorrect.
            throw new Exception(_m('You must specify a password in the configuration.'));
        }

        return new QueuedXMPP($this, $this->host ?
                                    $this->host :
                                    $this->server,
                                    $this->port,
                                    $this->user,
                                    $this->password,
                                    $this->resource,
                                    $this->server,
                                    $this->debug ?
                                    true : false,
                                    $this->debug ?
                                    XMPPHP_Log::LEVEL_VERBOSE :  null
                                    );
    }

    /**
     * Add XMPP plugin daemon to the list of daemon to start
     *
     * @param array $daemons the list of daemons to run
     *
     * @return boolean hook return
     */
    function onGetValidDaemons(&$daemons)
    {
        if( isset($this->server) &&
            isset($this->port)   &&
            isset($this->user)   &&
            isset($this->password) ){

            array_push(
                $daemons,
                INSTALLDIR
                . '/scripts/imdaemon.php'
            );
        }

        return true;
    }


    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'XMPP',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews, Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:XMPP',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('The XMPP plugin allows users to send and receive notices over the XMPP/Jabber network.'));
        return true;
    }
}

