<?php
/*

phpmsnclass ver 2.0s
Luke Fitzgerald <lw.fitzgerald@googlemail.com>

Based on MSN class ver 2.0 by Tommy Wu, Ricky Su
License: GPL

Documentation on the MSN protocol can be found at: http://msnpiki.msnfanatic.com/index.php/Main_Page

This class uses MSNP15.

In addition to PHP5, the additional php modules required are:
curl pcre mcrypt bcmath

*/

class MSN {
    const PROTOCOL = 'MSNP15';
    const PASSPORT_URL = 'https://login.live.com/RST.srf';
    const BUILDVER = '8.1.0178';
    const PROD_KEY = 'PK}_A_0N_K%O?A9S';
    const PROD_ID = 'PROD0114ES4Z%Q5W';
    const LOGIN_METHOD = 'SSO';

    const OIM_SEND_URL = 'https://ows.messenger.msn.com/OimWS/oim.asmx';
    const OIM_SEND_SOAP = 'http://messenger.live.com/ws/2006/09/oim/Store2';

    const OIM_MAILDATA_URL = 'https://rsi.hotmail.com/rsi/rsi.asmx';
    const OIM_MAILDATA_SOAP = 'http://www.hotmail.msn.com/ws/2004/09/oim/rsi/GetMetadata';
    const OIM_READ_URL = 'https://rsi.hotmail.com/rsi/rsi.asmx';
    const OIM_READ_SOAP = 'http://www.hotmail.msn.com/ws/2004/09/oim/rsi/GetMessage';
    const OIM_DEL_URL = 'https://rsi.hotmail.com/rsi/rsi.asmx';
    const OIM_DEL_SOAP = 'http://www.hotmail.msn.com/ws/2004/09/oim/rsi/DeleteMessages';

    const MEMBERSHIP_URL = 'https://contacts.msn.com/abservice/SharingService.asmx';
    const MEMBERSHIP_SOAP = 'http://www.msn.com/webservices/AddressBook/FindMembership';

    const ADDMEMBER_URL = 'https://contacts.msn.com/abservice/SharingService.asmx';
    const ADDMEMBER_SOAP = 'http://www.msn.com/webservices/AddressBook/AddMember';

    const DELMEMBER_URL = 'https://contacts.msn.com/abservice/SharingService.asmx';
    const DELMEMBER_SOAP = 'http://www.msn.com/webservices/AddressBook/DeleteMember';

    // the message length (include header) is limited (maybe since WLM 8.5 released)
    // for WLM: 1664 bytes
    // for YIM: 518 bytes
    const MAX_MSN_MESSAGE_LEN = 1664;
    const MAX_YAHOO_MESSAGE_LEN = 518;

    private $debug;
    private $timeout;

    private $id;
    private $ticket;
    private $user = '';
    private $password = '';
    private $NSfp = false;
    private $passport_policy = '';
    private $alias;
    private $psm;
    private $retry_wait;
    private $update_pending;
    private $PhotoStickerFile = false;
    private $Emotions = false;
    private $XFRReqTimeout = 60;
    private $SBStreamTimeout = 2;
    private $MsnObjArray = array();
    private $MsnObjMap = array();
    private $ABAuthHeader;
    private $ABService;
    private $Contacts;

    private $server = 'messenger.hotmail.com';
    private $port = 1863;

    private $clientid = '';

    private $error = '';

    private $authed = false;

    private $oim_try = 3;

    private $font_fn = 'Arial';
    private $font_co = '333333';
    private $font_ef = '';

    // Begin added for StatusNet

    private $aContactList = array();
    private $aADL = array();

    /**
    * Holds session information indexed by screenname if
    * session has no socket or socket if socket present
    *
    * @var array
    */
    private $switchBoardSessions = array();

    /**
    * Holds sockets indexed by screenname
    *
    * @var array
    */
    private $switchBoardSessionLookup = array();

    /**
    * Holds references to sessions waiting for XFR
    *
    * @var array
    */
    private $waitingForXFR = array();

    /**
    * Event Handler Functions
    */
    private $myEventHandlers = array();

    // End added for StatusNet

    /**
    * Constructor method
    *
    * @param array $Configs Array of configuration options
    *                       'user'           - Username
    *                       'password'       - Password
    *                       'alias'          - Bot nickname
    *                       'psm'            - Bot personal status message
    *                       'retry_wait'     - Time to wait before trying to reconnect
    *                       'update_pending' - Whether to update pending contacts
    *                       'PhotoSticker'   - Photo file to use (?)
    *                       'debug'          - Enable/Disable debugging mode
    * @param integer $timeout Connection timeout
    * @param integer $client_id Client id (hexadecimal)
    * @return MSN
    */
    public function __construct ($Configs = array(), $timeout = 15, $client_id = 0x7000800C) {
        $this->user = $Configs['user'];
        $this->password = $Configs['password'];
        $this->alias = isset($Configs['alias']) ? $Configs['alias'] : '';
        $this->psm = isset($Configs['psm']) ? $Configs['psm'] : '';
        $this->retry_wait = isset($Configs['retry_wait']) ? $Configs['retry_wait'] : 30;
        $this->update_pending = isset($Configs['update_pending']) ? $Configs['update_pending'] : true;
        $this->PhotoStickerFile=isset($Configs['PhotoSticker']) ? $Configs['PhotoSticker'] : false;

        if ($this->Emotions = isset($Configs['Emotions']) ? $Configs['Emotions']:false) {
            foreach($this->Emotions as $EmotionFilePath)
                $this->MsnObj($EmotionFilePath,$Type=2);
        }
        $this->debug = isset($Configs['debug']) ? $Configs['debug'] : false;
        $this->timeout = $timeout;

        // Check support
        if (!function_exists('curl_init')) throw new Exception("curl module not found!\n");
        if (!function_exists('preg_match')) throw new Exception("pcre module not found!\n");
        if (!function_exists('mcrypt_cbc')) throw new Exception("mcrypt module not found!\n");
        if (!function_exists('bcmod')) throw new Exception("bcmath module not found!\n");

        /*
         http://msnpiki.msnfanatic.com/index.php/Client_ID
         Client ID for MSN:
         normal MSN 8.1 clientid is:
         01110110 01001100 11000000 00101100
         = 0x764CC02C

         we just use following:
         * 0x04: Your client can send/receive Ink (GIF format)
         * 0x08: Your client can send/recieve Ink (ISF format)
         * 0x8000: This means you support Winks receiving (If not set the official Client will warn with 'contact has an older client and is not capable of receiving Winks')
         * 0x70000000: This is the value for MSNC7 (WL Msgr 8.1)
         = 0x7000800C;
         */
        $this->clientid = $client_id;
        $this->ABService = new SoapClient(realpath(dirname(__FILE__)).'/soap/msnab_sharingservice.wsdl', array('trace' => 1));
    }

    /**
     * Signon methods
     */

    /**
     * Connect to the NS server
     *
     * @param String $user Username
     * @param String $password Password
     * @param String $redirect_server Redirect server
     * @param Integer $redirect_port Redirect port
     * @return Boolean Returns true if successful
     */
    private function connect($user, $password, $redirect_server = '', $redirect_port = 1863) {
        $this->id = 1;
        if ($redirect_server === '') {
            $this->NSfp = @fsockopen($this->server, $this->port, $errno, $errstr, $this->timeout);
            if (!$this->NSfp) {
                $this->error = "!!! Could not connect to $this->server:$this->port, error => $errno, $errstr";
                return false;
            }
        }
        else {
            $this->NSfp = @fsockopen($redirect_server, $redirect_port, $errno, $errstr, $this->timeout);
            if (!$this->NSfp) {
                $this->error = "!!! Could not connect to $redirect_server:$redirect_port, error => $errno, $errstr";
                return false;
            }
        }
        $this->authed = false;
        // MSNP9
        // NS: >> VER {id} MSNP9 CVR0
        // MSNP15
        // NS: >>> VER {id} MSNP15 CVR0
        $this->ns_writeln("VER $this->id ".self::PROTOCOL.' CVR0');

        $start_tm = time();
        while (!self::socketcheck($this->NSfp)) {
            $data = $this->ns_readln();
            // no data?
            if ($data === false) {
                // logout now
                // NS: >>> OUT
                $this->ns_writeln("OUT");
                @fclose($this->NSfp);
                $this->error = 'Timeout, maybe protocol changed!';
                return false;
            }

            $code = substr($data, 0, 3);
            $start_tm = time();

            switch ($code) {
                case 'VER':
                    // MSNP9
                    // NS: <<< VER {id} MSNP9 CVR0
                    // NS: >>> CVR {id} 0x0409 winnt 5.1 i386 MSMSGS 6.0.0602 msmsgs {user}
                    // MSNP15
                    // NS: <<< VER {id} MSNP15 CVR0
                    // NS: >>> CVR {id} 0x0409 winnt 5.1 i386 MSMSGS 8.1.0178 msmsgs {user}
                    $this->ns_writeln("CVR $this->id 0x0409 winnt 5.1 i386 MSMSGS ".self::BUILDVER." msmsgs $user");
                    break;

                case 'CVR':
                    // MSNP9
                    // NS: <<< CVR {id} {ver_list} {download_serve} ....
                    // NS: >>> USR {id} TWN I {user}
                    // MSNP15
                    // NS: <<< CVR {id} {ver_list} {download_serve} ....
                    // NS: >>> USR {id} SSO I {user}
                    $this->ns_writeln("USR $this->id ".self::LOGIN_METHOD." I $user");
                    break;

                case 'USR':
                    // already login for passport site, finish the login process now.
                    // NS: <<< USR {id} OK {user} {verify} 0
                    if ($this->authed) return true;
                    // max. 16 digits for password
                    if (strlen($password) > 16)
                    $password = substr($password, 0, 16);

                    $this->user = $user;
                    $this->password = $password;
                    // NS: <<< USR {id} SSO S {policy} {nonce}
                    @list(/* USR */, /* id */, /* SSO */, /* S */, $policy, $nonce) = @explode(' ', $data);

                    $this->passport_policy = $policy;
                    $aTickets = $this->get_passport_ticket();
                    if (!$aTickets || !is_array($aTickets)) {
                        // logout now
                        // NS: >>> OUT
                        $this->ns_writeln("OUT");
                        @fclose($this->NSfp);
                        $this->error = 'Passport authentication failed!';
                        return false;
                    }

                    $ticket = $aTickets['ticket'];
                    $secret = $aTickets['secret'];
                    $this->ticket = $aTickets;
                    $login_code = $this->generateLoginBLOB($secret, $nonce);

                    // NS: >>> USR {id} SSO S {ticket} {login_code}
                    $this->ns_writeln("USR $this->id ".self::LOGIN_METHOD." S $ticket $login_code");
                    $this->authed = true;
                    break;

                case 'XFR':
                    // main login server will redirect to anther NS after USR command
                    // MSNP9
                    // NS: <<< XFR {id} NS {server} 0 {server}
                    // MSNP15
                    // NS: <<< XFR {id} NS {server} U D
                    @list(/* XFR */, /* id */, $Type, $server) = @explode(' ', $data);
                    if ($Type!='NS') break;
                    @list($ip, $port) = @explode(':', $server);
                    // this connection will close after XFR
                    @fclose($this->NSfp);

                    $this->NSfp = @fsockopen($ip, $port, $errno, $errstr, $this->timeout);
                    if (!$this->NSfp) {
                        $this->error = "Can't connect to $ip:$port, error => $errno, $errstr";
                        return false;
                    }

                    // MSNP9
                    // NS: >> VER {id} MSNP9 CVR0
                    // MSNP15
                    // NS: >>> VER {id} MSNP15 CVR0
                    $this->ns_writeln("VER $this->id ".self::PROTOCOL.' CVR0');
                    break;

                case 'GCF':
                    // return some policy data after 'USR {id} SSO I {user}' command
                    // NS: <<< GCF 0 {size}
                    @list(/* GCF */, /* 0 */, $size) = @explode(' ', $data);
                    // we don't need the data, just read it and drop
                    if (is_numeric($size) && $size > 0)
                        $this->ns_readdata($size);
                    break;

                default:
                    // we'll quit if got any error
                    if (is_numeric($code)) {
                        // logout now
                        // NS: >>> OUT
                        $this->ns_writeln("OUT");
                        @fclose($this->NSfp);
                        $this->error = "Error code: $code, please check the detail information from: http://msnpiki.msnfanatic.com/index.php/Reference:Error_List";
                        return false;
                    }
                    // unknown response from server, just ignore it
                    break;
            }
        }
        // never goto here
    }

    /**
     * Sign onto the NS server and retrieve the address book
     *
     * @return void
     */
    public function signon() {
        /* FIXME Don't implement the signon as a loop or we could hang
        *        the queue handler! */
        $this->debug_message('*** Trying to connect to MSN network');

        // Remove any remaining switchboard sessions
        $this->switchBoardSessions = array();
        $this->switchBoardSessionLookup = array();

        while (true) {
            // Connect
            if (!$this->connect($this->user, $this->password)) {
                $this->signonFailure("!!! Could not connect to server: $this->error");
                continue;
            }

            // Update contacts
            if ($this->UpdateContacts() === false) {
                $this->signonFailure('');
                continue;
            }

            // Get membership lists
            if (($this->aContactList = $this->getMembershipList()) === false) {
                $this->signonFailure('!!! Get membership list failed');
                continue;
            }

            if ($this->update_pending) {
                if (is_array($this->aContactList)) {
                    $pending = 'Pending';
                    foreach ($this->aContactList as $u_domain => $aUserList) {
                        foreach ($aUserList as $u_name => $aNetworks) {
                            foreach ($aNetworks as $network => $aData) {
                                if (isset($aData[$pending])) {
                                    // pending list
                                    $cnt = 0;
                                    foreach (array('Allow', 'Reverse') as $list) {
                                        if (isset($aData[$list]))
                                            $cnt++;
                                        else {
                                            if ($this->addMemberToList($u_name.'@'.$u_domain, $network, $list)) {
                                                $this->aContactList[$u_domain][$u_name][$network][$list] = false;
                                                $cnt++;
                                            }
                                        }
                                    }
                                    if ($cnt >= 2) {
                                        $id = $aData[$pending];
                                        // we can delete it from pending now
                                        if ($this->delMemberFromList($id, $u_name.'@'.$u_domain, $network, $pending))
                                            unset($this->aContactList[$u_domain][$u_name][$network][$pending]);
                                    }
                                }
                                else {
                                    // sync list
                                    foreach (array('Allow', 'Reverse') as $list) {
                                        if (!isset($aData[$list])) {
                                            if ($this->addMemberToList($u_name.'@'.$u_domain, $network, $list))
                                                $this->aContactList[$u_domain][$u_name][$network][$list] = false;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $n = 0;
            $sList = '';
            $len = 0;
            if (is_array($this->aContactList)) {
                foreach ($this->aContactList as $u_domain => $aUserList) {
                    $str = '<d n="'.$u_domain.'">';
                    $len += strlen($str);
                    if ($len > 7400) {
                        $this->aADL[$n] = '<ml l="1">'.$sList.'</ml>';
                        $n++;
                        $sList = '';
                        $len = strlen($str);
                    }
                    $sList .= $str;
                    foreach ($aUserList as $u_name => $aNetworks) {
                        foreach ($aNetworks as $network => $status) {
                            $str = '<c n="'.$u_name.'" l="3" t="'.$network.'" />';
                            $len += strlen($str);
                            // max: 7500, but <ml l="1"></d></ml> is 19,
                            // so we use 7475
                            if ($len > 7475) {
                                $sList .= '</d>';
                                $this->aADL[$n] = '<ml l="1">'.$sList.'</ml>';
                                $n++;
                                $sList = '<d n="'.$u_domain.'">'.$str;
                                $len = strlen($sList);
                            }
                            else
                                $sList .= $str;
                        }
                    }
                    $sList .= '</d>';
                }
            }
            $this->aADL[$n] = '<ml l="1">'.$sList.'</ml>';
            // NS: >>> BLP {id} BL
            $this->ns_writeln("BLP $this->id BL");
            foreach ($this->aADL as $str) {
                $len = strlen($str);
                // NS: >>> ADL {id} {size}
                $this->ns_writeln("ADL $this->id $len");
                $this->ns_writedata($str);
            }
            // NS: >>> PRP {id} MFN name
            if ($this->alias == '') $this->alias = $user;
            $aliasname = rawurlencode($this->alias);
            $this->ns_writeln("PRP $this->id MFN $aliasname");
            //設定個人大頭貼
            //$MsnObj=$this->PhotoStckObj();
            // NS: >>> CHG {id} {status} {clientid} {msnobj}
            $this->ns_writeln("CHG $this->id NLN $this->clientid");
            if ($this->PhotoStickerFile !== false)
                $this->ns_writeln("CHG $this->id NLN $this->clientid ".rawurlencode($this->MsnObj($this->PhotoStickerFile)));
            // NS: >>> UUX {id} length
            $str = '<Data><PSM>'.htmlspecialchars($this->psm).'</PSM><CurrentMedia></CurrentMedia><MachineGuid></MachineGuid></Data>';
            $len = strlen($str);
            $this->ns_writeln("UUX $this->id $len");
            $this->ns_writedata($str);
            if (!self::socketcheck($this->NSfp)) {
                $this->debug_message('*** Connected, waiting for commands');
                break;
            } else {
                $this->NSRetryWait($this->retry_wait);
            }
        }
    }

    /**
    * Called if there is an error during signon
    *
    * @param string $message Error message to log
    * @return void
    */
    private function signonFailure($message) {
        if(!empty($message)) {
            $this->debug_message($message);
        }
        $this->callHandler('ConnectFailed', $message);
        $this->NSRetryWait($this->retry_wait);
    }

    /**
    * Log out and close the NS connection
    *
    * @return void
    */
    private function nsLogout() {
        if (is_resource($this->NSfp) && !feof($this->NSfp)) {
            // logout now
            // NS: >>> OUT
            $this->ns_writeln("OUT");
            fclose($this->NSfp);
            $this->NSfp = false;
            $this->debug_message("*** Logged out");
        }
    }

    /**
     * NS and SB command handling methods
     */

    /**
     * Read and handle incoming command from NS
     *
     * @return void
     */
    private function nsReceive() {
        // Sign in again if not signed in or socket failed
        if (!is_resource($this->NSfp) || self::socketcheck($this->NSfp)) {
            $this->callHandler('Reconnect');
            $this->NSRetryWait($this->retry_wait);
            $this->signon();
            return;
        }

        $data = $this->ns_readln();
        if ($data === false) {
            // There was no data / an error when reading from the socket so reconnect
            $this->callHandler('Reconnect');
            $this->NSRetryWait($this->retry_wait);
            $this->signon();
            return;
        }

        switch (substr($data, 0, 3)) {
            case 'SBS':
                // after 'USR {id} OK {user} {verify} 0' response, the server will send SBS and profile to us
                // NS: <<< SBS 0 null
                break;

            case 'RFS':
                // FIXME:
                // NS: <<< RFS ???
                // refresh ADL, so we re-send it again
                if (is_array($this->aADL)) {
                    foreach ($this->aADL as $str) {
                        $len = strlen($str);
                        // NS: >>> ADL {id} {size}
                        $this->ns_writeln("ADL $this->id $len");
                        $this->ns_writedata($str);
                    }
                }
                break;

            case 'LST':
                // NS: <<< LST {email} {alias} 11 0
                @list(/* LST */, $email) = @explode(' ', $data);
                @list($u_name, $u_domain) = @explode('@', $email);
                if (!isset($this->aContactList[$u_domain][$u_name][1])) {
                    $this->aContactList[$u_domain][$u_name][1]['Allow'] = 'Allow';
                    $this->debug_message("*** Added to contact list: $u_name@$u_domain");
                }
                break;

            case 'ADL':
                // randomly, we get ADL command, someone add us to their contact list for MSNP15
                // NS: <<< ADL 0 {size}
                @list(/* ADL */, /* 0 */, $size) = @explode(' ', $data);
                if (is_numeric($size) && $size > 0) {
                    $data = $this->ns_readdata($size);
                    preg_match('#<ml><d n="([^"]+)"><c n="([^"]+)"(.*) t="(\d*)"(.*) /></d></ml>#', $data, $matches);
                    if (is_array($matches) && count($matches) > 0) {
                        $u_domain = $matches[1];
                        $u_name = $matches[2];
                        $network = $matches[4];
                        if (isset($this->aContactList[$u_domain][$u_name][$network]))
                            $this->debug_message("*** Someone (network: $network) added us to their list (but already in our list): $u_name@$u_domain");
                        else {
                            $re_login = false;
                            $cnt = 0;
                            foreach (array('Allow', 'Reverse') as $list) {
                                if (!$this->addMemberToList($u_name.'@'.$u_domain, $network, $list)) {
                                    if ($re_login) {
                                        $this->debug_message("*** Could not add $u_name@$u_domain (network: $network) to $list list");
                                        continue;
                                    }
                                    $aTickets = $this->get_passport_ticket();
                                    if (!$aTickets || !is_array($aTickets)) {
                                        // failed to login? ignore it
                                        $this->debug_message("*** Could not re-login, something wrong here");
                                        $this->debug_message("*** Could not add $u_name@$u_domain (network: $network) to $list list");
                                        continue;
                                    }
                                    $re_login = true;
                                    $this->ticket = $aTickets;
                                    $this->debug_message("**** Got new ticket, trying again");
                                    if (!$this->addMemberToList($u_name.'@'.$u_domain, $network, $list)) {
                                        $this->debug_message("*** Could not add $u_name@$u_domain (network: $network) to $list list");
                                        continue;
                                    }
                                }
                                $this->aContactList[$u_domain][$u_name][$network][$list] = false;
                                $cnt++;
                            }
                            $this->debug_message("*** Someone (network: $network) added us to their list: $u_name@$u_domain");
                        }
                        $str = '<ml l="1"><d n="'.$u_domain.'"><c n="'.$u_name.'" l="3" t="'.$network.'" /></d></ml>';
                        $len = strlen($str);

                        $this->callHandler('AddedToList', array('screenname' => $u_name.'@'.$u_domain, 'network' => $network));
                    }
                    else
                        $this->debug_message("*** Someone added us to their list: $data");
                }
                break;

            case 'RML':
                // randomly, we get RML command, someome remove us to their contact list for MSNP15
                // NS: <<< RML 0 {size}
                @list(/* RML */, /* 0 */, $size) = @explode(' ', $data);
                if (is_numeric($size) && $size > 0) {
                    $data = $this->ns_readdata($size);
                    preg_match('#<ml><d n="([^"]+)"><c n="([^"]+)"(.*) t="(\d*)"(.*) /></d></ml>#', $data, $matches);
                    if (is_array($matches) && count($matches) > 0) {
                        $u_domain = $matches[1];
                        $u_name = $matches[2];
                        $network = $matches[4];
                        if (isset($this->aContactList[$u_domain][$u_name][$network])) {
                            $aData = $this->aContactList[$u_domain][$u_name][$network];

                            foreach ($aData as $list => $id)
                                $this->delMemberFromList($id, $u_name.'@'.$u_domain, $network, $list);

                            unset($this->aContactList[$u_domain][$u_name][$network]);
                            $this->debug_message("*** Someone (network: $network) removed us from their list: $u_name@$u_domain");
                        }
                        else
                            $this->debug_message("*** Someone (network: $network) removed us from their list (but not in our list): $u_name@$u_domain");

                        $this->callHandler('RemovedFromList', array('screenname' => $u_name.'@'.$u_domain, 'network' => $network));
                    }
                    else
                        $this->debug_message("*** Someone removed us from their list: $data");
                }
                break;

            case 'MSG':
                // randomly, we get MSG notification from server
                // NS: <<< MSG Hotmail Hotmail {size}
                @list(/* MSG */, /* Hotmail */, /* Hotmail */, $size) = @explode(' ', $data);
                if (is_numeric($size) && $size > 0) {
                    $data = $this->ns_readdata($size);
                    $aLines = @explode("\n", $data);
                    $header = true;
                    $ignore = false;
                    $maildata = '';
                    foreach ($aLines as $line) {
                        $line = rtrim($line);
                        if ($header) {
                            if ($line === '') {
                                $header = false;
                                continue;
                            }
                            if (strncasecmp($line, 'Content-Type:', 13) == 0) {
                                if (strpos($line, 'text/x-msmsgsinitialmdatanotification') === false && strpos($line, 'text/x-msmsgsoimnotification') === false) {
                                    // we just need text/x-msmsgsinitialmdatanotification
                                    // or text/x-msmsgsoimnotification
                                    $ignore = true;
                                    break;
                                }
                            }
                            continue;
                        }
                        if (strncasecmp($line, 'Mail-Data:', 10) == 0) {
                            $maildata = trim(substr($line, 10));
                            break;
                        }
                    }
                    if ($ignore) {
                        $this->debug_message("*** Ignoring MSG for: $line");
                        break;
                    }
                    if ($maildata == '') {
                        $this->debug_message("*** Ignoring MSG not for OIM");
                        break;
                    }
                    $re_login = false;
                    if (strcasecmp($maildata, 'too-large') == 0) {
                        $this->debug_message("*** Large mail-data, need to get the data via SOAP");
                        $maildata = $this->getOIM_maildata();
                        if ($maildata === false) {
                            $this->debug_message("*** Could not get mail-data via SOAP");

                            // maybe we need to re-login again
                            $aTickets = $this->get_passport_ticket();
                            if (!$aTickets || !is_array($aTickets)) {
                                // failed to login? ignore it
                                $this->debug_message("*** Could not re-login, something wrong here, ignoring this OIM");
                                break;
                            }
                            $re_login = true;
                            $this->ticket = $aTickets;
                            $this->debug_message("*** Got new ticket, trying again");
                            $maildata = $this->getOIM_maildata();
                            if ($maildata === false) {
                                $this->debug_message("*** Could not get mail-data via SOAP, and re-login already attempted, ignoring this OIM");
                                break;
                            }
                        }
                    }
                    // could be a lots of <M>...</M>, so we can't use preg_match here
                    $p = $maildata;
                    $aOIMs = array();
                    while (1) {
                        $start = strpos($p, '<M>');
                        $end = strpos($p, '</M>');
                        if ($start === false || $end === false || $start > $end) break;
                        $end += 4;
                        $sOIM = substr($p, $start, $end - $start);
                        $aOIMs[] = $sOIM;
                        $p = substr($p, $end);
                    }
                    if (count($aOIMs) == 0) {
                        $this->debug_message("*** Ignoring empty OIM");
                        break;
                    }
                    foreach ($aOIMs as $maildata) {
                        // T: 11 for MSN, 13 for Yahoo
                        // S: 6 for MSN, 7 for Yahoo
                        // RT: the datetime received by server
                        // RS: already read or not
                        // SZ: size of message
                        // E: sender
                        // I: msgid
                        // F: always 00000000-0000-0000-0000-000000000009
                        // N: sender alias
                        preg_match('#<T>(.*)</T>#', $maildata, $matches);
                        if (count($matches) == 0) {
                            $this->debug_message("*** Ignoring OIM maildata without <T>type</T>");
                            continue;
                        }
                        $oim_type = $matches[1];
                        if ($oim_type = 13)
                            $network = 32;
                        else
                            $network = 1;
                        preg_match('#<E>(.*)</E>#', $maildata, $matches);
                        if (count($matches) == 0) {
                            $this->debug_message("*** Ignoring OIM maildata without <E>sender</E>");
                            continue;
                        }
                        $oim_sender = $matches[1];
                        preg_match('#<I>(.*)</I>#', $maildata, $matches);
                        if (count($matches) == 0) {
                            $this->debug_message("*** Ignoring OIM maildata without <I>msgid</I>");
                            continue;
                        }
                        $oim_msgid = $matches[1];
                        preg_match('#<SZ>(.*)</SZ>#', $maildata, $matches);
                        $oim_size = (count($matches) == 0) ? 0 : $matches[1];
                        preg_match('#<RT>(.*)</RT>#', $maildata, $matches);
                        $oim_time = (count($matches) == 0) ? 0 : $matches[1];
                        $this->debug_message("*** OIM received from $oim_sender, Time: $oim_time, MSGID: $oim_msgid, size: $oim_size");
                        $sMsg = $this->getOIM_message($oim_msgid);
                        if ($sMsg === false) {
                            $this->debug_message("*** Could not get OIM, msgid = $oim_msgid");
                            if ($re_login) {
                                $this->debug_message("*** Could not get OIM via SOAP, and re-login already attempted, ignoring this OIM");
                                continue;
                            }
                            $aTickets = $this->get_passport_ticket();
                            if (!$aTickets || !is_array($aTickets)) {
                                // failed to login? ignore it
                                $this->debug_message("*** Could not re-login, something wrong here, ignoring this OIM");
                                continue;
                            }
                            $re_login = true;
                            $this->ticket = $aTickets;
                            $this->debug_message("*** get new ticket, try it again");
                            $sMsg = $this->getOIM_message($oim_msgid);
                            if ($sMsg === false) {
                                $this->debug_message("*** Could not get OIM via SOAP, and re-login already attempted, ignoring this OIM");
                                continue;
                            }
                        }
                        $this->debug_message("*** MSG (Offline) from $oim_sender (network: $network): $sMsg");
                        $this->callHandler('IMin', array('sender' => $oim_sender, 'message' => $sMsg, 'network' => $network, 'offline' => true));
                    }
                }
                break;

            case 'UBM':
                // randomly, we get UBM, this is the message from other network, like Yahoo!
                // NS: <<< UBM {email} $network $type {size}
                @list(/* UBM */, $from_email, $network, $type, $size) = @explode(' ', $data);
                if (is_numeric($size) && $size > 0) {
                    $data = $this->ns_readdata($size);
                    $aLines = @explode("\n", $data);
                    $header = true;
                    $ignore = false;
                    $sMsg = '';
                    foreach ($aLines as $line) {
                        $line = rtrim($line);
                        if ($header) {
                            if ($line === '') {
                                $header = false;
                                continue;
                            }
                            if (strncasecmp($line, 'TypingUser:', 11) == 0) {
                                $ignore = true;
                                break;
                            }
                            continue;
                        }
                        $aSubLines = @explode("\r", $line);
                        foreach ($aSubLines as $str) {
                            if ($sMsg !== '')
                            $sMsg .= "\n";
                            $sMsg .= $str;
                        }
                    }
                    if ($ignore) {
                        $this->debug_message("*** Ignoring message from $from_email: $line");
                        break;
                    }
                    $this->debug_message("*** MSG from $from_email (network: $network): $sMsg");
                    $this->callHandler('IMin', array('sender' => $from_email, 'message' => $sMsg, 'network' => $network, 'offline' => false));
                }
                break;

            case 'UBX':
                // randomly, we get UBX notification from server
                // NS: <<< UBX email {network} {size}
                @list(/* UBX */, /* email */, /* network */, $size) = @explode(' ', $data);
                // we don't need the notification data, so just ignore it
                if (is_numeric($size) && $size > 0)
                    $this->ns_readdata($size);
                break;

            case 'CHL':
                // randomly, we'll get challenge from server
                // NS: <<< CHL 0 {code}
                @list(/* CHL */, /* 0 */, $chl_code) = @explode(' ', $data);
                $fingerprint = $this->getChallenge($chl_code);
                // NS: >>> QRY {id} {product_id} 32
                // NS: >>> fingerprint
                $this->ns_writeln("QRY $this->id ".self::PROD_ID.' 32');
                $this->ns_writedata($fingerprint);
                $this->ns_writeln("CHG $this->id NLN $this->clientid");
                if ($this->PhotoStickerFile !== false)
                    $this->ns_writeln("CHG $this->id NLN $this->clientid ".rawurlencode($this->MsnObj($this->PhotoStickerFile)));
                break;
            case 'CHG':
                // NS: <<< CHG {id} {status} {code}
                // ignore it
                // change our status to online first
                break;

            case 'XFR':
                // sometimes, NS will redirect to another NS
                // MSNP9
                // NS: <<< XFR {id} NS {server} 0 {server}
                // MSNP15
                // NS: <<< XFR {id} NS {server} U D
                // for normal switchboard XFR
                // NS: <<< XFR {id} SB {server} CKI {cki} U messenger.msn.com 0
                @list(/* XFR */, /* {id} */, $server_type, $server, /* CKI */, $cki_code) = @explode(' ', $data);
                @list($ip, $port) = @explode(':', $server);
                if ($server_type != 'SB') {
                    // maybe exit?
                    // this connection will close after XFR
                    $this->nsLogout();
                    continue;
                }

                $this->debug_message("NS: <<< XFR SB");
                $session = array_shift($this->waitingForXFR);
                $this->connectToSBSession('Active', $ip, $port, $session['to'], array('cki' => $cki_code));
                break;
            case 'QNG':
                // NS: <<< QNG {time}
                @list(/* QNG */, $ping_wait) = @explode(' ', $data);
                $this->callHandler('Pong', $ping_wait);
                break;

            case 'RNG':
                if ($this->PhotoStickerFile !== false)
                    $this->ns_writeln("CHG $this->id NLN $this->clientid ".rawurlencode($this->MsnObj($this->PhotoStickerFile)));
                else
                    $this->ns_writeln("CHG $this->id NLN $this->clientid");
                // someone is trying to talk to us
                // NS: <<< RNG {session_id} {server} {auth_type} {ticket} {email} {alias} U {client} 0
                $this->debug_message("NS: <<< RNG $data");
                @list(/* RNG */, $sid, $server, /* auth_type */, $ticket, $email, $name) = @explode(' ', $data);
                @list($sb_ip, $sb_port) = @explode(':', $server);
                $this->debug_message("*** RING from $email, $sb_ip:$sb_port");
                $this->addContact($email, 1, $email, true);
                $this->connectToSBSession('Passive', $sb_ip, $sb_port, $email, array('sid' => $sid, 'ticket' => $ticket));
                break;

            case 'NLN':
                // NS: <<< NLN {status} {email} {networkid} {nickname} {clientid} {dpobj}
                @list(/* NLN */, $status, $email, $network, $nickname) = @explode(' ', $data);
                $this->callHandler('StatusChange', array('screenname' => $email, 'status' => $status, 'network' => $network, 'nickname' => $nickname));
                break;

            case 'OUT':
                // force logout from NS
                // NS: <<< OUT xxx
                $this->debug_message("*** LOGOUT from NS");
                return $this->nsLogout();

            default:
                $code = substr($data,0,3);
                if (is_numeric($code)) {
                    $this->error = "Error code: $code, please check the detail information from: http://msnpiki.msnfanatic.com/index.php/Reference:Error_List";
                    $this->debug_message("*** NS: $this->error");

                    return $this->nsLogout();
                }
                break;
        }
    }

    /**
     * Read and handle incoming command/message from
     * a switchboard session socket
     */
    private function sbReceive($socket) {
        $intsocket = (int) $socket;
        $session = &$this->switchBoardSessions[$intsocket];

        if (feof($socket)) {
            // Unset session lookup value
            unset($this->switchBoardSessionLookup[$session['to']]);

            // Unset session itself
            unset($this->switchBoardSessions[$intsocket]);
            return;
        }

        $id = &$session['id'];

        $data = $this->sb_readln($socket);
        $code = substr($data, 0, 3);
        switch($code) {
            case 'IRO':
                // SB: <<< IRO {id} {rooster} {roostercount} {email} {alias} {clientid}
                @list(/* IRO */, /* id */, $cur_num, $total, $email, $alias, $clientid) = @explode(' ', $data);
                $this->debug_message("*** $email joined session");
                if ($email == $session['to']) {
                    $session['joined'] = true;
                    $this->callHandler('SessionReady', array('to' => $email));
                }
                break;
            case 'BYE':
                $this->debug_message("*** Quit for BYE");
                $this->endSBSession($socket);
                break;
            case 'USR':
                // SB: <<< USR {id} OK {user} {alias}
                // we don't need the data, just ignore it
                // request user to join this switchboard
                // SB: >>> CAL {id} {user}
                $this->sb_writeln($socket, $id, "CAL $id ".$session['to']);
                break;
            case 'CAL':
                // SB: <<< CAL {id} RINGING {?}
                // we don't need this, just ignore, and wait for other response
                $session['id']++;
                break;
            case 'JOI':
                // SB: <<< JOI {user} {alias} {clientid?}
                // someone join us
                @list(/* JOI */, $email) = @explode(' ', $data);
                if ($email == $session['to']) {
                    $session['joined'] = true;
                    $this->callHandler('SessionReady', array('to' => $email));
                }
                break;
            case 'MSG':
                // SB: <<< MSG {email} {alias} {len}
                @list(/* MSG */, $from_email, /* alias */, $len) = @explode(' ', $data);
                $len = trim($len);
                $data = $this->sb_readdata($socket, $len);
                $aLines = @explode("\n", $data);
                $header = true;
                $ignore = false;
                $is_p2p = false;
                $sMsg = '';
                foreach ($aLines as $line) {
                    $line = rtrim($line);
                    if ($header) {
                        if ($line === '') {
                            $header = false;
                            continue;
                        }
                        if (strncasecmp($line, 'TypingUser:', 11) == 0) {
                            // typing notification, just ignore
                            $ignore = true;
                            break;
                        }
                        if (strncasecmp($line, 'Chunk:', 6) == 0) {
                            // we don't handle any split message, just ignore
                            $ignore = true;
                            break;
                        }
                        if (strncasecmp($line, 'Content-Type: application/x-msnmsgrp2p', 38) == 0) {
                            // p2p message, ignore it, but we need to send acknowledgement for it...
                            $is_p2p = true;
                            $p = strstr($data, "\n\n");
                            $sMsg = '';
                            if ($p === false) {
                                $p = strstr($data, "\r\n\r\n");
                                if ($p !== false)
                                $sMsg = substr($p, 4);
                            }
                            else
                            $sMsg = substr($p, 2);
                            break;
                        }
                        if (strncasecmp($line, 'Content-Type: application/x-', 28) == 0) {
                            // ignore all application/x-... message
                            // for example:
                            //      application/x-ms-ink        => ink message
                            $ignore = true;
                            break;
                        }
                        if (strncasecmp($line, 'Content-Type: text/x-', 21) == 0) {
                            // ignore all text/x-... message
                            // for example:
                            //      text/x-msnmsgr-datacast         => nudge, voice clip....
                            //      text/x-mms-animemoticon         => customized animemotion word
                            $ignore = true;
                            break;
                        }
                        continue;
                    }
                    if ($sMsg !== '')
                        $sMsg .= "\n";
                    $sMsg .= $line;
                }
                if ($ignore) {
                    $this->debug_message("*** Ignoring SB data from $from_email: $line");
                    break;
                }
                if ($is_p2p) {
                    // we will ignore any p2p message after sending acknowledgement
                    $ignore = true;
                    $len = strlen($sMsg);
                    $this->debug_message("*** p2p message from $from_email, size $len");
                    // header = 48 bytes
                    // content >= 0 bytes
                    // footer = 4 bytes
                    // so it need to >= 52 bytes
                    /*if ($len < 52) {
                        $this->debug_message("*** p2p: size error, less than 52!");
                        break;
                    }*/
                    $aDwords = @unpack("V12dword", $sMsg);
                    if (!is_array($aDwords)) {
                        $this->debug_message("*** p2p: header unpack error!");
                        break;
                    }
                    $this->debug_message("*** p2p: dump received message:\n".$this->dump_binary($sMsg));
                    $hdr_SessionID = $aDwords['dword1'];
                    $hdr_Identifier = $aDwords['dword2'];
                    $hdr_DataOffsetLow = $aDwords['dword3'];
                    $hdr_DataOffsetHigh = $aDwords['dword4'];
                    $hdr_TotalDataSizeLow = $aDwords['dword5'];
                    $hdr_TotalDataSizeHigh = $aDwords['dword6'];
                    $hdr_MessageLength = $aDwords['dword7'];
                    $hdr_Flag = $aDwords['dword8'];
                    $hdr_AckID = $aDwords['dword9'];
                    $hdr_AckUID = $aDwords['dword10'];
                    $hdr_AckSizeLow = $aDwords['dword11'];
                    $hdr_AckSizeHigh = $aDwords['dword12'];
                    $this->debug_message("*** p2p: header SessionID = $hdr_SessionID");
                    $this->debug_message("*** p2p: header Inentifier = $hdr_Identifier");
                    $this->debug_message("*** p2p: header Data Offset Low = $hdr_DataOffsetLow");
                    $this->debug_message("*** p2p: header Data Offset High = $hdr_DataOffsetHigh");
                    $this->debug_message("*** p2p: header Total Data Size Low = $hdr_TotalDataSizeLow");
                    $this->debug_message("*** p2p: header Total Data Size High = $hdr_TotalDataSizeHigh");
                    $this->debug_message("*** p2p: header MessageLength = $hdr_MessageLength");
                    $this->debug_message("*** p2p: header Flag = $hdr_Flag");
                    $this->debug_message("*** p2p: header AckID = $hdr_AckID");
                    $this->debug_message("*** p2p: header AckUID = $hdr_AckUID");
                    $this->debug_message("*** p2p: header AckSize Low = $hdr_AckSizeLow");
                    $this->debug_message("*** p2p: header AckSize High = $hdr_AckSizeHigh");
                    if ($hdr_Flag == 2) {
                        //This is an ACK from SB ignore....
                        $this->debug_message("*** p2p: //This is an ACK from SB ignore....:\n");
                        break;
                    }
                    $MsgBody = $this->linetoArray(substr($sMsg, 48, -4));
                    $this->debug_message("*** p2p: body".print_r($MsgBody, true));
                    if (($MsgBody['EUF-GUID']=='{A4268EEC-FEC5-49E5-95C3-F126696BDBF6}')&&($PictureFilePath=$this->GetPictureFilePath($MsgBody['Context']))) {
                        while (true) {
                            if ($this->sb_readln($socket) === false) break;
                        }
                        $this->debug_message("*** p2p: Inv hdr:\n".$this->dump_binary(substr($sMsg, 0, 48)));
                        preg_match('/{([0-9A-F\-]*)}/i', $MsgBody['Via'], $Matches);
                        $BranchGUID = $Matches[1];
                        //it's an invite to send a display picture.
                        $new_id = ~$hdr_Identifier;
                        $hdr = pack(
                            "LLLLLLLLLLLL", $hdr_SessionID,
                            $new_id,
                            0, 0,
                            $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh,
                            0,
                            2,
                            $hdr_Identifier,
                            $hdr_AckID,
                            $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh
                        );
                        $footer = pack("L", 0);
                        $message = "MIME-Version: 1.0\r\nContent-Type: application/x-msnmsgrp2p\r\nP2P-Dest: $from_email\r\n\r\n$hdr$footer";
                        $len = strlen($message);
                        $this->sb_writeln($socket, $id, "MSG $id D $len");
                        $this->sb_writedata($socket, $message);
                        $this->debug_message("*** p2p: send display picture acknowledgement for $hdr_SessionID");
                        $this->debug_message("*** p2p: Invite ACK message:\n".$this->dump_binary($message));
                        $this->sb_readln($socket); // Read ACK;
                        $this->debug_message("*** p2p: Invite ACK Hdr:\n".$this->dump_binary($hdr));
                        $new_id -= 3;
                        //Send 200 OK message
                        $MessageContent="SessionID: ".$MsgBody['SessionID']."\r\n\r\n".pack("C", 0);
                        $MessagePayload=
                            "MSNSLP/1.0 200 OK\r\n".
                            "To: <msnmsgr:".$from_email.">\r\n".
                            "From: <msnmsgr:".$this->user.">\r\n".
                            "Via: ".$MsgBody['Via']."\r\n".
                            "CSeq: ".($MsgBody['CSeq']+1)."\r\n".
                            "Call-ID: ".$MsgBody['Call-ID']."\r\n".
                            "Max-Forwards: 0\r\n".
                            "Content-Type: application/x-msnmsgr-sessionreqbody\r\n".
                            "Content-Length: ".strlen($MessageContent)."\r\n\r\n".
                        $MessageContent;
                        $hdr_TotalDataSizeLow=strlen($MessagePayload);
                        $hdr_TotalDataSizeHigh=0;
                        $hdr = pack(
                            "LLLLLLLLLLLL", $hdr_SessionID,
                            $new_id,
                            0, 0,
                            $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh,
                            strlen($MessagePayload),
                            0,
                            rand(),
                            0,
                            0, 0
                        );

                        $message =
                            "MIME-Version: 1.0\r\n".
                            "Content-Type: application/x-msnmsgrp2p\r\n".
                            "P2P-Dest: $from_email\r\n\r\n$hdr$MessagePayload$footer";
                        $this->sb_writeln($socket, $id, "MSG $id D ".strlen($message));
                        $this->sb_writedata($socket, $message);
                        $this->debug_message("*** p2p: dump 200 ok message:\n".$this->dump_binary($message));
                        $this->sb_readln($socket); // Read ACK;

                        $this->debug_message("*** p2p: 200 ok:\n".$this->dump_binary($hdr));
                        // send data preparation message
                        // send 4 null bytes as data
                        $hdr_TotalDataSizeLow = 4;
                        $hdr_TotalDataSizeHigh = 0 ;
                        $new_id++;
                        $hdr = pack(
                            "LLLLLLLLLLLL",
                            $MsgBody['SessionID'],
                            $new_id,
                            0, 0,
                            $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh,
                            $hdr_TotalDataSizeLow,
                            0,
                            rand(),
                            0,
                            0, 0
                        );
                        $message =
                            "MIME-Version: 1.0\r\n".
                            "Content-Type: application/x-msnmsgrp2p\r\n".
                            "P2P-Dest: $from_email\r\n\r\n$hdr".pack('L', 0)."$footer";
                        $this->sb_writeln($socket, $id, "MSG $id D ".strlen($message));
                        $this->sb_writedata($socket, $message);
                        $this->debug_message("*** p2p: dump send Data preparation message:\n".$this->dump_binary($message));
                        $this->debug_message("*** p2p: Data Prepare Hdr:\n".$this->dump_binary($hdr));
                        $this->sb_readln($socket); // Read ACK;

                        // send Data Content..
                        $footer=pack('N',1);
                        $new_id++;
                        $FileSize=filesize($PictureFilePath);
                        if ($hTitle=fopen($PictureFilePath,'rb')) {
                            $Offset = 0;
                            //$new_id++;
                            while (!feof($hTitle)) {
                                $FileContent = fread($hTitle, 1024);
                                $FileContentSize = strlen($FileContent);
                                $hdr = pack(
                                    "LLLLLLLLLLLL",
                                    $MsgBody['SessionID'],
                                    $new_id,
                                    $Offset, 0,
                                    $FileSize, 0,
                                    $FileContentSize,
                                    0x20,
                                    rand(),
                                    0,
                                    0, 0
                                );
                                $message =
                                    "MIME-Version: 1.0\r\n".
                                    "Content-Type: application/x-msnmsgrp2p\r\n".
                                    "P2P-Dest: $from_email\r\n\r\n$hdr$FileContent$footer";
                                $this->sb_writeln($socket, $id, "MSG $id D ".strlen($message));
                                $this->sb_writedata($socket, $message);
                                $this->debug_message("*** p2p: dump send Data Content message  $Offset / $FileSize :\n".$this->dump_binary($message));
                                $this->debug_message("*** p2p: Data Content Hdr:\n".$this->dump_binary($hdr));
                                //$this->SB_readln($socket);//Read ACK;
                                $Offset += $FileContentSize;
                            }
                        }
                        //Send Bye
                        /*
                        $MessageContent="\r\n".pack("C", 0);
                        $MessagePayload=
                            "BYE MSNMSGR:MSNSLP/1.0\r\n".
                            "To: <msnmsgr:$from_email>\r\n".
                            "From: <msnmsgr:".$this->user.">\r\n".
                            "Via: MSNSLP/1.0/TLP ;branch={".$BranchGUID."}\r\n".
                            "CSeq: 0\r\n".
                            "Call-ID: ".$MsgBody['Call-ID']."\r\n".
                            "Max-Forwards: 0\r\n".
                            "Content-Type: application/x-msnmsgr-sessionclosebody\r\n".
                            "Content-Length: ".strlen($MessageContent)."\r\n\r\n".$MessageContent;
                        $footer=pack('N',0);
                        $hdr_TotalDataSizeLow=strlen($MessagePayload);
                        $hdr_TotalDataSizeHigh=0;
                        $new_id++;
                        $hdr = pack("LLLLLLLLLLLL",
                        0,
                        $new_id,
                        0, 0,
                        $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh,
                        0,
                        0,
                        rand(),
                        0,
                        0,0);
                        $message =
                                    "MIME-Version: 1.0\r\n".
                                    "Content-Type: application/x-msnmsgrp2p\r\n".
                                    "P2P-Dest: $from_email\r\n\r\n$hdr$MessagePayload$footer";
                        $this->sb_writeln($socket, $id, "MSG $id D ".strlen($message));
                        $id++;
                        $this->sb_writedata($socket, $message);
                        $this->debug_message("*** p2p: dump send BYE message :\n".$this->dump_binary($message));
                        */
                        break;
                    }
                    //TODO:
                    //if ($hdr_Flag == 2) {
                    // just send ACK...
                    //    $this->sb_writeln($socket, $id, "ACK $id");
                    //    break;
                    //}
                    if ($hdr_SessionID == 4) {
                        // ignore?
                        $this->debug_message("*** p2p: ignore flag 4");
                        break;
                    }
                    $finished = false;
                    if ($hdr_TotalDataSizeHigh == 0) {
                        // only 32 bites size
                        if (($hdr_MessageLength + $hdr_DataOffsetLow) == $hdr_TotalDataSizeLow)
                        $finished = true;
                    }
                    else {
                        // we won't accept any file transfer
                        // so I think we won't get any message size need to use 64 bits
                        // 64 bits size here, can't count directly...
                        $totalsize = base_convert(sprintf("%X%08X", $hdr_TotalDataSizeHigh, $hdr_TotalDataSizeLow), 16, 10);
                        $dataoffset = base_convert(sprintf("%X%08X", $hdr_DataOffsetHigh, $hdr_DataOffsetLow), 16, 10);
                        $messagelength = base_convert(sprintf("%X", $hdr_MessageLength), 16, 10);
                        $now_size = bcadd($dataoffset, $messagelength);
                        if (bccomp($now_size, $totalsize) >= 0)
                        $finished = true;
                    }
                    if (!$finished) {
                        // ignore not finished split packet
                        $this->debug_message("*** p2p: ignore split packet, not finished");
                        break;
                    }
                    //$new_id = ~$hdr_Identifier;
                    /*
                     $new_id++;
                     $hdr = pack("LLLLLLLLLLLL", $hdr_SessionID,
                     $new_id,
                     0, 0,
                     $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh,
                     0,
                     2,
                     $hdr_Identifier,
                     $hdr_AckID,
                     $hdr_TotalDataSizeLow, $hdr_TotalDataSizeHigh);
                     $footer = pack("L", 0);
                     $message = "MIME-Version: 1.0\r\nContent-Type: application/x-msnmsgrp2p\r\nP2P-Dest: $from_email\r\n\r\n$hdr$footer";
                     $len = strlen($message);
                     $this->sb_writeln($socket, $id, "MSG $id D $len");
                     $id++;
                     $this->sb_writedata($socket, $message);
                     $this->debug_message("*** p2p: send acknowledgement for $hdr_SessionID");
                     $this->debug_message("*** p2p: dump sent message:\n".$this->dump_binary($hdr.$footer));
                     */
                    break;
                }
                $this->debug_message("*** MSG from $from_email: $sMsg");
                $this->callHandler('IMin', array('sender' => $from_email, 'message' => $sMsg, 'network' => 1, 'offline' => false));
                break;
            case '217':
                $this->debug_message('*** User '.$session['to'].' is offline. Trying OIM.');
                $session['offline'] = true;
                break;
            default:
                if (is_numeric($code)) {
                    $this->error = "Error code: $code, please check the detail information from: http://msnpiki.msnfanatic.com/index.php/Reference:Error_List";
                    $this->debug_message("*** SB: $this->error");
                }
                break;
        }
    }

    /**
     * Checks for new data and calls appropriate methods
     *
     * This method is usually called in an infinite loop to keep checking for new data
     *
     * @return void
     */
    public function receive() {
        // First, get an array of sockets that have data that is ready to be read
        $ready = array();
        $ready = $this->getSockets();
        $numrdy = stream_select($ready, $w = NULL, $x = NULL, NULL);

        // Now that we've waited for something, go through the $ready
        // array and read appropriately

        foreach ($ready as $socket) {
            if ($socket == $this->NSfp) {
                $this->nsReceive();
            } else {
                $this->sbReceive($socket);
            }
        }
    }

    /**
     * Switchboard related methods
     */

    /**
     * Send a request for a switchboard session
     *
     * @param string $to Target email for switchboard session
     */
    private function reqSBSession($to) {
        $this->debug_message("*** Request SB for $to");
        $this->ns_writeln("XFR $this->id SB");

        // Add to the queue of those waiting for a switchboard session reponse
        $this->switchBoardSessions[$to] = array(
            'to' => $to,
            'socket' => NULL,
            'id' => 1,
            'joined' => false,
            'offline' => false,
            'XFRReqTime' => time()
        );
        $this->waitingForXFR[$to] = &$this->switchBoardSessions[$to];
    }

    /**
     * Following an XFR or RNG, connect to the switchboard session
     *
     * @param string $mode Mode, either 'Active' (in the case of XFR) or 'Passive' (in the case of RNG)
     * @param string $ip IP of Switchboard
     * @param integer $port Port of Switchboard
     * @param string $to User on other end of Switchboard
     * @param array $param Array of parameters - 'cki', 'ticket', 'sid'
     * @return boolean true if successful
     */
    private function connectToSBSession($mode, $ip, $port, $to, $param) {
        $this->debug_message("*** SB: Trying to connect to switchboard server $ip:$port");

        $socket = @fsockopen($ip, $port, $errno, $errstr, $this->timeout);
        if (!$socket) {
            $this->debug_message("*** SB: Can't connect to $ip:$port, error => $errno, $errstr");
            return false;
        }

        // Store the socket in the lookup array
        $this->switchBoardSessionLookup[$to] = $socket;

        // Store the socket in the sessions array
        $this->switchBoardSessions[$to] = array(
            'to' => $to,
            'socket' => $socket,
            'id' => 1,
            'joined' => false,
            'offline' => false,
            'XFRReqTime' => time()
        );

        // Change the index of the session to the socket
        $intsocket = (int) $socket;
        $this->switchBoardSessions[$intsocket] = $this->switchBoardSessions[$to];
        unset($this->switchBoardSessions[$to]);

        $id = &$this->switchBoardSessions[$intsocket]['id'];

        if ($mode == 'Active') {
            $cki_code = $param['cki'];

            // SB: >>> USR {id} {user} {cki}
            $this->sb_writeln($socket, $id, "USR $id $this->user $cki_code");
        } else {
            // Passive
            $ticket = $param['ticket'];
            $sid = $param['sid'];

            // SB: >>> ANS {id} {user} {ticket} {session_id}
            $this->sb_writeln($socket, $id, "ANS $id $this->user $ticket $sid");
        }
    }

    /**
    * Called when we want to end a switchboard session
    * or a switchboard session ends
    *
    * @param resource $socket Socket
    * @param boolean $killsession Whether to delete the session
    * @return void
    */
    private function endSBSession($socket) {
        if (!self::socketcheck($socket)) {
            $this->sb_writeln($socket, $fake = 0, 'OUT');
        }
        @fclose($socket);

        // Unset session lookup value
        $intsocket = (int) $socket;
        unset($this->switchBoardSessionLookup[$this->switchBoardSessions[$intsocket]['to']]);

        // Unset session itself
        unset($this->switchBoardSessions[$intsocket]);
    }

    /**
     * Send a message via an existing SB session
     *
     * @param string $to Recipient for message
     * @param string $message Message
     * @return boolean true on success
     */
    private function sendMessageViaSB($to, $message) {
        $socket = $this->switchBoardSessionLookup[$to];
        if (self::socketcheck($socket)) {
            return false;
        }

        $id = &$this->switchBoardSessions[(int) $socket]['id'];

        $aMessage = $this->getMessage($message);
        // CheckEmotion...
        $MsnObjDefine = $this->GetMsnObjDefine($aMessage);
        if ($MsnObjDefine !== '') {
            $SendString = "MIME-Version: 1.0\r\nContent-Type: text/x-mms-emoticon\r\n\r\n$MsnObjDefine";
            $len = strlen($SendString);

            if ($this->sb_writeln($socket, $id, "MSG $id N $len") === false ||
                $this->sb_writedata($socket, $SendString) === false) {
                    $this->endSBSession($socket);
                    return false;
                }
        }
        $len = strlen($aMessage);

        if ($this->sb_writeln($socket, $id, "MSG $id N $len") === false ||
            $this->sb_writedata($socket, $aMessage) === false) {
                $this->endSBSession($socket);
                return false;
            }

        // Don't close the SB session, we might as well leave it open
        return true;
    }

    /**
     * Send a message to a user on another network
     *
     * @param string $to Intended recipient
     * @param string $message Message
     * @param integer $network Network
     * @return void
     */
    private function sendOtherNetworkMessage($to, $message, $network) {
        $message = $this->getMessage($message, $network);
        $len = strlen($message);
        if ($this->ns_writeln("UUM $this->id $to $network 1 $len") === false ||
            $this->ns_writedata($Message) === false) {
            return false;
        }
        $this->debug_message("*** Sent to $to (network: $network):\n$Message");
        return true;
    }

    /**
     * Send a message
     *
     * @param string $to To address in form user@host.com(@network)
     *                   where network is 1 for MSN, 32 for Yahoo
     *                   and 'Offline' for offline messages
     * @param string $message Message
     * @param boolean &$waitForSession Boolean passed by reference,
     *                                 if set to true on return, message
     *                                 did not fail to send but is
     *                                 waiting for a valid session
     *
     * @return boolean true on success
     */
    public function sendMessage($to, $message, &$waitForSession) {
        if ($message != '') {
            $toParts = explode('@', $to);
            if(count($toParts) < 3) {
                list($name, $host) = $toParts;
                $network = 1;
            } else {
                list($name, $host, $network) = $toParts;
            }

            $recipient = $name.'@'.$host;

            if ($network === 1) {
                if (!isset($this->switchBoardSessionLookup[$recipient])) {
                    if (!isset($this->switchBoardSessions[$recipient]) || time() - $this->switchBoardSessions[$recipient]['XFRReqTime'] > $this->XFRReqTimeout) {
                        $this->debug_message("*** No existing SB session or request has timed out");
                        $this->reqSBSession($recipient);
                    }

                    $waitForSession = true;
                    return false;
                } else {
                    $socket = $this->switchBoardSessionLookup[$recipient];
                    $intsocket = (int) $socket;
                    if ($this->switchBoardSessions[$intsocket]['offline']) {
                        $this->debug_message("*** Contact ($recipient) offline, sending OIM");
                        $this->endSBSession($socket);
                        $waitForSession = false;
                        return $this->sendMessage($recipient.'@Offline', $message);
                    } else {
                        if ($this->switchBoardSessions[$intsocket]['joined'] !== true) {
                            $this->debug_message("*** Recipient has not joined session, returning false");
                            $waitForSession = true;
                            return false;
                        }

                        $this->debug_message("*** Attempting to send message to $recipient using existing SB session");

                        if ($this->sendMessageViaSB($recipient, $message)) {
                            $this->debug_message('*** Message sent successfully');
                            return true;
                        }

                        $waitForSession = false;
                        return false;
                    }
                }
            } elseif ($network == 'Offline') {
                //Send OIM
                //FIXME: 修正Send OIM
                $lockkey = '';
                $re_login = false;
                for ($i = 0; $i < $this->oim_try; $i++) {
                    if (($oim_result = $this->sendOIM($recipient, $message, $lockkey)) === true) break;
                    if (is_array($oim_result) && $oim_result['challenge'] !== false) {
                        // need challenge lockkey
                        $this->debug_message("*** Need challenge code for ".$oim_result['challenge']);
                        $lockkey = $this->getChallenge($oim_result['challenge']);
                        continue;
                    }
                    if ($oim_result === false || $oim_result['auth_policy'] !== false) {
                        if ($re_login) {
                            $this->debug_message("*** Can't send OIM, but we already re-logged-in again, so returning false");
                            return false;
                        }
                        $this->debug_message("*** Can't send OIM, maybe ticket expired, trying to login again");

                        // Maybe we need to re-login again
                        if (!$this->get_passport_ticket()) {
                            $this->debug_message("*** Can't re-login, something went wrong here, returning false");
                            return false;
                        }
                        $this->debug_message("*** Getting new ticket and trying again");
                        continue;
                    }
                }
                return true;
            } else {
                // Other network
                return $this->sendOtherNetworkMessage($recipient, $message, $network);
            }
        }
        return true;
    }

    /**
     * OIM methods
     */

    /**
    * Get OIM mail data
    *
    * @return string mail data or false on failure
    */
    function getOIM_maildata() {
        preg_match('#t=(.*)&p=(.*)#', $this->ticket['web_ticket'], $matches);
        if (count($matches) == 0) {
            $this->debug_message('*** No web ticket?');
            return false;
        }
        $t = htmlspecialchars($matches[1]);
        $p = htmlspecialchars($matches[2]);
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Header>
  <PassportCookie xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <t>'.$t.'</t>
    <p>'.$p.'</p>
  </PassportCookie>
</soap:Header>
<soap:Body>
  <GetMetadata xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi" />
</soap:Body>
</soap:Envelope>';

        $header_array = array(
            'SOAPAction: '.self::OIM_MAILDATA_SOAP,
            'Content-Type: text/xml; charset=utf-8',
            'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; Messenger '.self::BUILDVER.')'
        );

        $this->debug_message('*** URL: '.self::OIM_MAILDATA_URL);
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::OIM_MAILDATA_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->debug) curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            $this->debug_message("*** Could not get OIM maildata! http code: $http_code");
            return false;
        }

        // <GetMetadataResponse xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">See #XML_Data</GetMetadataResponse>
        preg_match('#<GetMetadataResponse([^>]*)>(.*)</GetMetadataResponse>#', $data, $matches);
        if (count($matches) == 0) {
            $this->debug_message('*** Could not get OIM maildata');
            return false;
        }
        return $matches[2];
    }

    /**
    * Fetch OIM message with given id
    *
    * @param string $msgid
    * @return string Message or false on failure
    */
    function getOIM_message($msgid) {
        preg_match('#t=(.*)&p=(.*)#', $this->ticket['web_ticket'], $matches);
        if (count($matches) == 0) {
            $this->debug_message('*** No web ticket?');
            return false;
        }
        $t = htmlspecialchars($matches[1]);
        $p = htmlspecialchars($matches[2]);

        // read OIM
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Header>
  <PassportCookie xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <t>'.$t.'</t>
    <p>'.$p.'</p>
  </PassportCookie>
</soap:Header>
<soap:Body>
  <GetMessage xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <messageId>'.$msgid.'</messageId>
    <alsoMarkAsRead>false</alsoMarkAsRead>
  </GetMessage>
</soap:Body>
</soap:Envelope>';

        $header_array = array(
            'SOAPAction: '.self::OIM_READ_SOAP,
            'Content-Type: text/xml; charset=utf-8',
            'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; Messenger '.self::BUILDVER.')'
        );

        $this->debug_message('*** URL: '.self::OIM_READ_URL);
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::OIM_READ_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->debug) curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            $this->debug_message("*** Can't get OIM: $msgid, http code = $http_code");
            return false;
        }

        // why can't use preg_match('#<GetMessageResult>(.*)</GetMessageResult>#', $data, $matches)?
        // multi-lines?
        $start = strpos($data, '<GetMessageResult>');
        $end = strpos($data, '</GetMessageResult>');
        if ($start === false || $end === false || $start > $end) {
            $this->debug_message("*** Can't get OIM: $msgid");
            return false;
        }
        $lines = substr($data, $start + 18, $end - $start);
        $aLines = @explode("\n", $lines);
        $header = true;
        $ignore = false;
        $sOIM = '';
        foreach ($aLines as $line) {
            $line = rtrim($line);
            if ($header) {
                if ($line === '') {
                    $header = false;
                    continue;
                }
                continue;
            }
            // stop at empty lines
            if ($line === '') break;
            $sOIM .= $line;
        }
        $sMsg = base64_decode($sOIM);
        //$this->debug_message("*** we get OIM ($msgid): $sMsg");

        // delete OIM
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Header>
  <PassportCookie xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <t>'.$t.'</t>
    <p>'.$p.'</p>
  </PassportCookie>
</soap:Header>
<soap:Body>
  <DeleteMessages xmlns="http://www.hotmail.msn.com/ws/2004/09/oim/rsi">
    <messageIds>
      <messageId>'.$msgid.'</messageId>
    </messageIds>
  </DeleteMessages>
</soap:Body>
</soap:Envelope>';

        $header_array = array(
            'SOAPAction: '.self::OIM_DEL_SOAP,
            'Content-Type: text/xml; charset=utf-8',
            'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; Messenger '.self::BUILDVER.')'
        );

        $this->debug_message('*** URL: '.self::OIM_DEL_URL);
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::OIM_DEL_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->debug) curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200)
            $this->debug_message("*** Could not delete OIM: $msgid, http code = $http_code");
        else
            $this->debug_message("*** OIM ($msgid) deleted");
        return $sMsg;
    }

    /**
     * Send offline message
     *
     * @param string $to Intended recipient
     * @param string $sMessage Message
     * @param string $lockkey Lock key
     * @return mixed true on success or error data
     */
    private function sendOIM($to, $sMessage, $lockkey) {
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Header>
  <From memberName="'.$this->user.'"
        friendlyName="=?utf-8?B?'.base64_encode($this->user).'?="
        xml:lang="zh-TW"
        proxy="MSNMSGR"
        xmlns="http://messenger.msn.com/ws/2004/09/oim/"
        msnpVer="'.self::PROTOCOL.'"
        buildVer="'.self::BUILDVER.'"/>
  <To memberName="'.$to.'" xmlns="http://messenger.msn.com/ws/2004/09/oim/"/>
  <Ticket passport="'.htmlspecialchars($this->ticket['oim_ticket']).'"
          appid="'.self::PROD_ID.'"
          lockkey="'.$lockkey.'"
          xmlns="http://messenger.msn.com/ws/2004/09/oim/"/>
  <Sequence xmlns="http://schemas.xmlsoap.org/ws/2003/03/rm">
    <Identifier xmlns="http://schemas.xmlsoap.org/ws/2002/07/utility">http://messenger.msn.com</Identifier>
    <MessageNumber>1</MessageNumber>
  </Sequence>
</soap:Header>
<soap:Body>
  <MessageType xmlns="http://messenger.msn.com/ws/2004/09/oim/">text</MessageType>
  <Content xmlns="http://messenger.msn.com/ws/2004/09/oim/">MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: base64
X-OIM-Message-Type: OfflineMessage
X-OIM-Run-Id: {DAB68CFA-38C9-449B-945E-38AFA51E50A7}
X-OIM-Sequence-Num: 1

'.chunk_split(base64_encode($sMessage)).'
  </Content>
</soap:Body>
</soap:Envelope>';

        $header_array = array(
            'SOAPAction: '.self::OIM_SEND_SOAP,
            'Content-Type: text/xml',
            'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; Messenger '.self::BUILDVER.')'
        );

        $this->debug_message('*** URL: '.self::OIM_SEND_URL);
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::OIM_SEND_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->debug) curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code == 200) {
            $this->debug_message("*** OIM sent for $to");
            return true;
        }

        $challenge = false;
        $auth_policy = false;
        // the lockkey is invalid, authenticated fail, we need challenge it again
        // <LockKeyChallenge xmlns="http://messenger.msn.com/ws/2004/09/oim/">364763969</LockKeyChallenge>
        preg_match("#<LockKeyChallenge (.*)>(.*)</LockKeyChallenge>#", $data, $matches);
        if (count($matches) != 0) {
            // yes, we get new LockKeyChallenge
            $challenge = $matches[2];
            $this->debug_message("*** OIM need new challenge ($challenge) for $to");
        }
        // auth policy error
        // <RequiredAuthPolicy xmlns="http://messenger.msn.com/ws/2004/09/oim/">MBI_SSL</RequiredAuthPolicy>
        preg_match("#<RequiredAuthPolicy (.*)>(.*)</RequiredAuthPolicy>#", $data, $matches);
        if (count($matches) != 0) {
            $auth_policy = $matches[2];
            $this->debug_message("*** OIM need new auth policy ($auth_policy) for $to");
        }
        if ($auth_policy === false && $challenge === false) {
            //<faultcode xmlns:q0="http://messenger.msn.com/ws/2004/09/oim/">q0:AuthenticationFailed</faultcode>
            preg_match("#<faultcode (.*)>(.*)</faultcode>#", $data, $matches);
            if (count($matches) == 0) {
                // no error, we assume the OIM is sent
                $this->debug_message("*** OIM sent for $to");
                return true;
            }
            $err_code = $matches[2];
            //<faultstring>Exception of type 'System.Web.Services.Protocols.SoapException' was thrown.</faultstring>
            preg_match("#<faultstring>(.*)</faultstring>#", $data, $matches);
            if (count($matches) > 0)
                $err_msg = $matches[1];
            else
                $err_msg = '';
            $this->debug_message("*** OIM failed for $to");
            $this->debug_message("*** OIM Error code: $err_code");
            $this->debug_message("*** OIM Error Message: $err_msg");
            return false;
        }
        return array('challenge' => $challenge, 'auth_policy' => $auth_policy);
    }

    /**
     * Contact / Membership list methods
     */

    /**
    * Fetch contact list
    *
    * @return boolean true on success
    */
    private function UpdateContacts() {
        $ABApplicationHeaderArray = array(
            'ABApplicationHeader' => array(
                ':' => array('xmlns' => 'http://www.msn.com/webservices/AddressBook'),
                'ApplicationId' => 'CFE80F9D-180F-4399-82AB-413F33A1FA11',
                'IsMigration' => false,
                'PartnerScenario' => 'ContactSave'
             )
        );

        $ABApplicationHeader = new SoapHeader('http://www.msn.com/webservices/AddressBook', 'ABApplicationHeader', $this->Array2SoapVar($ABApplicationHeaderArray));
        $ABFindAllArray = array(
            'ABFindAll' => array(
                ':' => array('xmlns'=>'http://www.msn.com/webservices/AddressBook'),
                'abId' => '00000000-0000-0000-0000-000000000000',
                'abView' => 'Full',
                'lastChange' => '0001-01-01T00:00:00.0000000-08:00',
            )
        );
        $ABFindAll = new SoapParam($this->Array2SoapVar($ABFindAllArray), 'ABFindAll');
        $this->ABService->__setSoapHeaders(array($ABApplicationHeader, $this->ABAuthHeader));
        $this->Contacts = array();
        try {
            $this->debug_message('*** Updating Contacts...');
            $Result = $this->ABService->ABFindAll($ABFindAll);
            $this->debug_message("*** Result:\n".print_r($Result, true)."\n".$this->ABService->__getLastResponse());
            foreach($Result->ABFindAllResult->contacts->Contact as $Contact)
                $this->Contacts[$Contact->contactInfo->passportName] = $Contact;
        } catch(Exception $e) {
            $this->debug_message("*** Update Contacts Error \nRequest:".$this->ABService->__getLastRequest()."\nError:".$e->getMessage());
            return false;
        }
        return true;
    }

    /**
    * Add contact
    *
    * @param string $email
    * @param integer $network
    * @param string $display
    * @param boolean $sendADL
    * @return boolean true on success
    */
    private function addContact($email, $network, $display = '', $sendADL = false) {
        if ($network != 1) return true;
        if (isset($this->Contacts[$email])) return true;

        $ABContactAddArray = array(
            'ABContactAdd' => array(
                ':' => array('xmlns' => 'http://www.msn.com/webservices/AddressBook'),
                'abId' => '00000000-0000-0000-0000-000000000000',
                'contacts' => array(
                    'Contact' => array(
                        ':' => array('xmlns' => 'http://www.msn.com/webservices/AddressBook'),
                        'contactInfo' => array(
                            'contactType' => 'LivePending',
                            'passportName' => $email,
                            'isMessengerUser' => true,
                            'MessengerMemberInfo' => array(
                                'DisplayName' => $email
                            )
                        )
                    )
                ),
                'options' => array(
                    'EnableAllowListManagement' => true
                )
            )
        );
        $ABContactAdd = new SoapParam($this->Array2SoapVar($ABContactAddArray), 'ABContactAdd');
        try {
            $this->debug_message("*** Adding Contact $email...");
            $this->ABService->ABContactAdd($ABContactAdd);
        } catch(Exception $e) {
            $this->debug_message("*** Add Contact Error \nRequest:".$this->ABService->__getLastRequest()."\nError:".$e->getMessage());
            return false;
        }
        if ($sendADL && !feof($this->NSfp)) {
            @list($u_name, $u_domain) = @explode('@', $email);
            foreach (array('1', '2') as $l) {
                $str = '<ml l="1"><d n="'.$u_domain.'"><c n="'.$u_name.'" l="'.$l.'" t="'.$network.'" /></d></ml>';
                $len = strlen($str);
                // NS: >>> ADL {id} {size}
                $this->ns_writeln("ADL $this->id $len");
                $this->ns_writedata($str);
            }
        }
        $this->UpdateContacts();
        return true;
    }

    /**
    * Remove contact from list
    *
    * @param integer $memberID
    * @param string $email
    * @param integer $network
    * @param string $list
    */
    function delMemberFromList($memberID, $email, $network, $list) {
        if ($network != 1 && $network != 32) return true;
        if ($memberID === false) return true;
        $user = $email;
        $ticket = htmlspecialchars($this->ticket['contact_ticket']);
        if ($network == 1)
            $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>996CDE1E-AA53-4477-B943-2BE802EA6166</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactMsgrAPI</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <DeleteMember xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceHandle>
            <Id>0</Id>
            <Type>Messenger</Type>
            <ForeignId></ForeignId>
        </serviceHandle>
        <memberships>
            <Membership>
                <MemberRole>'.$list.'</MemberRole>
                <Members>
                    <Member xsi:type="PassportMember" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <Type>Passport</Type>
                        <MembershipId>'.$memberID.'</MembershipId>
                        <State>Accepted</State>
                    </Member>
                </Members>
            </Membership>
        </memberships>
    </DeleteMember>
</soap:Body>
</soap:Envelope>';
        else
            $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>996CDE1E-AA53-4477-B943-2BE802EA6166</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactMsgrAPI</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <DeleteMember xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceHandle>
            <Id>0</Id>
            <Type>Messenger</Type>
            <ForeignId></ForeignId>
        </serviceHandle>
        <memberships>
            <Membership>
                <MemberRole>'.$list.'</MemberRole>
                <Members>
                    <Member xsi:type="EmailMember" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <Type>Email</Type>
                        <MembershipId>'.$memberID.'</MembershipId>
                        <State>Accepted</State>
                    </Member>
                </Members>
            </Membership>
        </memberships>
    </DeleteMember>
</soap:Body>
</soap:Envelope>';

        $header_array = array(
            'SOAPAction: '.self::DELMEMBER_SOAP,
            'Content-Type: text/xml; charset=utf-8',
            'User-Agent: MSN Explorer/9.0 (MSN 8.0; TmstmpExt)'
        );

        $this->debug_message('*** URL: '.self::DELMEMBER_URL);
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::DELMEMBER_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->debug) curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            preg_match('#<faultcode>(.*)</faultcode><faultstring>(.*)</faultstring>#', $data, $matches);
            if (count($matches) == 0) {
                $this->debug_message("*** Could not delete member (network: $network) $email ($memberID) from $list list");
                return false;
            }
            $faultcode = trim($matches[1]);
            $faultstring = trim($matches[2]);
            if (strcasecmp($faultcode, 'soap:Client') || stripos($faultstring, 'Member does not exist') === false) {
                $this->debug_message("*** Could not delete member (network: $network) $email ($memberID) from $list list, error code: $faultcode, $faultstring");
                return false;
            }
            $this->debug_message("*** Could not delete member (network: $network) $email ($memberID) from $list list, not present in list");
            return true;
        }
        $this->debug_message("*** Member successfully deleted (network: $network) $email ($memberID) from $list list");
        return true;
    }

    /**
    * Add contact to list
    *
    * @param string $email
    * @param integer $network
    * @param string $list
    */
    function addMemberToList($email, $network, $list) {
        if ($network != 1 && $network != 32) return true;
        $ticket = htmlspecialchars($this->ticket['contact_ticket']);
        $user = $email;

        if ($network == 1)
            $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>996CDE1E-AA53-4477-B943-2BE802EA6166</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactMsgrAPI</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <AddMember xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceHandle>
            <Id>0</Id>
            <Type>Messenger</Type>
            <ForeignId></ForeignId>
        </serviceHandle>
        <memberships>
            <Membership>
                <MemberRole>'.$list.'</MemberRole>
                <Members>
                    <Member xsi:type="PassportMember" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <Type>Passport</Type>
                        <State>Accepted</State>
                        <PassportName>'.$user.'</PassportName>
                    </Member>
                </Members>
            </Membership>
        </memberships>
    </AddMember>
</soap:Body>
</soap:Envelope>';
        else
            $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>996CDE1E-AA53-4477-B943-2BE802EA6166</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>ContactMsgrAPI</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <AddMember xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceHandle>
            <Id>0</Id>
            <Type>Messenger</Type>
            <ForeignId></ForeignId>
        </serviceHandle>
        <memberships>
            <Membership>
                <MemberRole>'.$list.'</MemberRole>
                <Members>
                    <Member xsi:type="EmailMember" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
                        <Type>Email</Type>
                        <State>Accepted</State>
                        <Email>'.$user.'</Email>
                        <Annotations>
                            <Annotation>
                                <Name>MSN.IM.BuddyType</Name>
                                <Value>32:YAHOO</Value>
                            </Annotation>
                        </Annotations>
                    </Member>
                </Members>
            </Membership>
        </memberships>
    </AddMember>
</soap:Body>
</soap:Envelope>';
        $header_array = array(
            'SOAPAction: '.self::ADDMEMBER_SOAP,
            'Content-Type: text/xml; charset=utf-8',
            'User-Agent: MSN Explorer/9.0 (MSN 8.0; TmstmpExt)'
        );

        $this->debug_message('*** URL: '.self::ADDMEMBER_URL);
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::ADDMEMBER_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->debug) curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            preg_match('#<faultcode>(.*)</faultcode><faultstring>(.*)</faultstring>#', $data, $matches);
            if (count($matches) == 0) {
                $this->debug_message("*** Could not add member (network: $network) $email to $list list");
                return false;
            }
            $faultcode = trim($matches[1]);
            $faultstring = trim($matches[2]);
            if (strcasecmp($faultcode, 'soap:Client') || stripos($faultstring, 'Member already exists') === false) {
                $this->debug_message("*** Could not add member (network: $network) $email to $list list, error code: $faultcode, $faultstring");
                return false;
            }
            $this->debug_message("*** Could not add member (network: $network) $email to $list list, already present");
            return true;
        }
        $this->debug_message("*** Member successfully added (network: $network) $email to $list list");
        return true;
    }

    /**
    * Get membership lists
    *
    * @param mixed $returnData Membership list or false on failure
    */
    function getMembershipList($returnData = false) {
        $ticket = htmlspecialchars($this->ticket['contact_ticket']);
        $XML = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soapenc="http://schemas.xmlsoap.org/soap/encoding/">
<soap:Header>
    <ABApplicationHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ApplicationId>996CDE1E-AA53-4477-B943-2BE802EA6166</ApplicationId>
        <IsMigration>false</IsMigration>
        <PartnerScenario>Initial</PartnerScenario>
    </ABApplicationHeader>
    <ABAuthHeader xmlns="http://www.msn.com/webservices/AddressBook">
        <ManagedGroupRequest>false</ManagedGroupRequest>
        <TicketToken>'.$ticket.'</TicketToken>
    </ABAuthHeader>
</soap:Header>
<soap:Body>
    <FindMembership xmlns="http://www.msn.com/webservices/AddressBook">
        <serviceFilter>
            <Types>
                <ServiceType>Messenger</ServiceType>
                <ServiceType>Invitation</ServiceType>
                <ServiceType>SocialNetwork</ServiceType>
                <ServiceType>Space</ServiceType>
                <ServiceType>Profile</ServiceType>
            </Types>
        </serviceFilter>
    </FindMembership>
</soap:Body>
</soap:Envelope>';
        $header_array = array(
            'SOAPAction: '.self::MEMBERSHIP_SOAP,
            'Content-Type: text/xml; charset=utf-8',
            'User-Agent: MSN Explorer/9.0 (MSN 8.0; TmstmpExt)'
        );
        $this->debug_message('*** URL: '.self::MEMBERSHIP_URL);
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::MEMBERSHIP_URL);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->debug) curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) return false;
        $p = $data;
        $aMemberships = array();
        while (1) {
            //$this->debug_message("search p = $p");
            $start = strpos($p, '<Membership>');
            $end = strpos($p, '</Membership>');
            if ($start === false || $end === false || $start > $end) break;
            //$this->debug_message("start = $start, end = $end");
            $end += 13;
            $sMembership = substr($p, $start, $end - $start);
            $aMemberships[] = $sMembership;
            //$this->debug_message("add sMembership = $sMembership");
            $p = substr($p, $end);
        }
        //$this->debug_message("aMemberships = ".var_export($aMemberships, true));

        $aContactList = array();
        foreach ($aMemberships as $sMembership) {
            //$this->debug_message("sMembership = $sMembership");
            if (isset($matches)) unset($matches);
            preg_match('#<MemberRole>(.*)</MemberRole>#', $sMembership, $matches);
            if (count($matches) == 0) continue;
            $sMemberRole = $matches[1];
            //$this->debug_message("MemberRole = $sMemberRole");
            if ($sMemberRole != 'Allow' && $sMemberRole != 'Reverse' && $sMemberRole != 'Pending') continue;
            $p = $sMembership;
            if (isset($aMembers)) unset($aMembers);
            $aMembers = array();
            while (1) {
                //$this->debug_message("search p = $p");
                $start = strpos($p, '<Member xsi:type="');
                $end = strpos($p, '</Member>');
                if ($start === false || $end === false || $start > $end) break;
                //$this->debug_message("start = $start, end = $end");
                $end += 9;
                $sMember = substr($p, $start, $end - $start);
                $aMembers[] = $sMember;
                //$this->debug_message("add sMember = $sMember");
                $p = substr($p, $end);
            }
            //$this->debug_message("aMembers = ".var_export($aMembers, true));
            foreach ($aMembers as $sMember) {
                //$this->debug_message("sMember = $sMember");
                if (isset($matches)) unset($matches);
                preg_match('#<Member xsi\:type="([^"]*)">#', $sMember, $matches);
                if (count($matches) == 0) continue;
                $sMemberType = $matches[1];
                //$this->debug_message("MemberType = $sMemberType");
                $network = -1;
                preg_match('#<MembershipId>(.*)</MembershipId>#', $sMember, $matches);
                if (count($matches) == 0) continue;
                $id = $matches[1];
                if ($sMemberType == 'PassportMember') {
                    if (strpos($sMember, '<Type>Passport</Type>') === false) continue;
                    $network = 1;
                    preg_match('#<PassportName>(.*)</PassportName>#', $sMember, $matches);
                }
                else if ($sMemberType == 'EmailMember') {
                    if (strpos($sMember, '<Type>Email</Type>') === false) continue;
                    // Value is 32: or 32:YAHOO
                    preg_match('#<Annotation><Name>MSN.IM.BuddyType</Name><Value>(.*):(.*)</Value></Annotation>#', $sMember, $matches);
                    if (count($matches) == 0) continue;
                    if ($matches[1] != 32) continue;
                    $network = 32;
                    preg_match('#<Email>(.*)</Email>#', $sMember, $matches);
                }
                if ($network == -1) continue;
                if (count($matches) > 0) {
                    $email = $matches[1];
                    @list($u_name, $u_domain) = @explode('@', $email);
                    if ($u_domain == NULL) continue;
                    $aContactList[$u_domain][$u_name][$network][$sMemberRole] = $id;
                    $this->debug_message("*** Adding new contact (network: $network, status: $sMemberRole): $u_name@$u_domain ($id)");
                }
            }
        }
        return $aContactList;
    }

    /**
     * MsnObj related methods
     */

    /**
     *
     * @param $FilePath 圖檔路徑
     * @param $Type     檔案類型 3=>大頭貼,2表情圖案
     * @return array
     */
    private function MsnObj($FilePath, $Type = 3) {
        if (!($FileSize=filesize($FilePath))) return '';
        $Location = md5($FilePath);
        $Friendly = md5($FilePath.$Type);
        if (isset($this->MsnObjMap[$Location])) return $this->MsnObjMap[$Location];
        $sha1d = base64_encode(sha1(file_get_contents($FilePath), true));
        $sha1c = base64_encode(sha1("Creator".$this->user."Size$FileSize"."Type$Type"."Location$Location"."Friendly".$Friendly."SHA1D$sha1d", true));
        $this->MsnObjArray[$Location] = $FilePath;
        $MsnObj = '<msnobj Creator="'.$this->user.'" Size="'.$FileSize.'" Type="'.$Type.'" Location="'.$Location.'" Friendly="'.$Friendly.'" SHA1D="'.$sha1d.'" SHA1C="'.$sha1c.'"/>';
        $this->MsnObjMap[$Location] = $MsnObj;
        $this->debug_message("*** p2p: addMsnObj $FilePath::$MsnObj\n");
        return $MsnObj;
    }

    private function GetPictureFilePath($Context) {
        $MsnObj = base64_decode($Context);
        if (preg_match('/location="(.*?)"/i', $MsnObj, $Match))
            $location = $Match[1];
        $this->debug_message("*** p2p: PictureFile[$location] ::All".print_r($this->MsnObjArray,true)."\n");
        if ($location && isset($this->MsnObjArray[$location]))
            return $this->MsnObjArray[$location];
        return false;
    }

    private function GetMsnObjDefine($Message) {
        $DefineString = '';
        if (is_array($this->Emotions))
            foreach ($this->Emotions as $Pattern => $FilePath) {
                if (strpos($Message, $Pattern) !== false)
                $DefineString .= "$Pattern\t".$this->MsnObj($FilePath, 2)."\t";
            }
        return $DefineString;
    }

    /**
     * Socket methods
     */

    /**
     * Read data of specified size from NS socket
     *
     * @param integer $size Size to read
     * @return string Data read
     */
    private function ns_readdata($size) {
        $data = '';
        $count = 0;
        while (!feof($this->NSfp)) {
            $buf = @fread($this->NSfp, $size - $count);
            $data .= $buf;
            $count += strlen($buf);
            if ($count >= $size) break;
        }
        $this->debug_message("NS: data ($size/$count) <<<\n$data");
        return $data;
    }

    /**
     * Read line from the NS socket
     *
     * @return string Data read
     */
    private function ns_readln() {
        $data = @fgets($this->NSfp, 4096);
        if ($data !== false) {
            $data = trim($data);
            $this->debug_message("NS: <<< $data");
        }
        return $data;
    }

    /**
     * Write line to NS socket
     *
     * Also increments id
     *
     * @param string $data Line to write to socket
     * @return mixed Bytes written or false on failure
     */
    private function ns_writeln($data) {
        $result = @fwrite($this->NSfp, $data."\r\n");
        if ($result !== false) {
            $this->debug_message("NS: >>> $data");
            $this->id++;
        }
        return $result;
    }

    /**
     * Write data to NS socket
     *
     * @param string $data Data to write to socket
     * @return mixed Bytes written or false on failure
     */
    private function ns_writedata($data) {
        $result = @fwrite($this->NSfp, $data);
        if ($result !== false) {
            $this->debug_message("NS: >>> $data");
        }
        return $result;
    }

    /**
     * Read data of specified size from given SB socket
     *
     * @param resource $socket SB socket
     * @param integer $size Size to read
     * @return string Data read
     */
    private function sb_readdata($socket, $size) {
        $data = '';
        $count = 0;
        while (!feof($socket)) {
            $buf = @fread($socket, $size - $count);
            $data .= $buf;
            $count += strlen($buf);
            if ($count >= $size) break;
        }
        $this->debug_message("SB: data ($size/$count) <<<\n$data");
        return $data;
    }

    /**
     * Read line from given SB socket
     *
     * @param resource $socket SB Socket
     * @return string Line read
     */
    private function sb_readln($socket) {
        $data = @fgets($socket, 4096);
        if ($data !== false) {
            $data = trim($data);
            $this->debug_message("SB: <<< $data");
        }
        return $data;
    }

    /**
     * Write line to given SB socket
     *
     * Also increments id
     *
     * @param resource $socket SB socket
     * @param integer $id Reference to SB id
     * @param string $data Line to write
     * @return mixed Bytes written or false on error
     */
    private function sb_writeln($socket, &$id, $data) {
        $result = @fwrite($socket, $data."\r\n");
        if ($result !== false) {
            $this->debug_message("SB: >>> $data");
            $id++;
        }
        return $result;
    }

    /**
     * Write data to given SB socket
     *
     * @param resource $socket SB socket
     * @param $data Data to write to socket
     * @return mixed Bytes written or false on error
     */
    private function sb_writedata($socket, $data) {
        $result = @fwrite($socket, $data);
        if ($result !== false) {
            $this->debug_message("SB: >>> $data");
        }
        return $result;
    }

    /**
     * Get all the sockets currently in use
     *
     * @return array Array of socket resources
     */
    public function getSockets() {
        return array_merge(array($this->NSfp), $this->switchBoardSessionLookup);
    }

    /**
     * Checks socket for end of file
     *
     * @param resource $socket Socket to check
     * @return boolean true if end of file (socket)
     */
    private static function socketcheck($socket){
        $info = stream_get_meta_data($socket);
        return $info['eof'];
    }

    /**
     * Key generation methods
     */

    private function derive_key($key, $magic) {
        $hash1 = $this->mhash_sha1($magic, $key);
        $hash2 = $this->mhash_sha1($hash1.$magic, $key);
        $hash3 = $this->mhash_sha1($hash1, $key);
        $hash4 = $this->mhash_sha1($hash3.$magic, $key);
        return $hash2.substr($hash4, 0, 4);
    }

    private function generateLoginBLOB($key, $challenge) {
        $key1 = base64_decode($key);
        $key2 = $this->derive_key($key1, 'WS-SecureConversationSESSION KEY HASH');
        $key3 = $this->derive_key($key1, 'WS-SecureConversationSESSION KEY ENCRYPTION');

        // get hash of challenge using key2
        $hash = $this->mhash_sha1($challenge, $key2);

        // get 8 bytes random data
        $iv = substr(base64_encode(rand(1000,9999).rand(1000,9999)), 2, 8);

        $cipher = mcrypt_cbc(MCRYPT_3DES, $key3, $challenge."\x08\x08\x08\x08\x08\x08\x08\x08", MCRYPT_ENCRYPT, $iv);

        $blob = pack('LLLLLLL', 28, 1, 0x6603, 0x8004, 8, 20, 72);
        $blob .= $iv;
        $blob .= $hash;
        $blob .= $cipher;

        return base64_encode($blob);
    }

    /**
    * Generate challenge response
    *
    * @param string $code
    * @return string challenge response code
    */
    private function getChallenge($code) {
        // MSNP15
        // http://msnpiki.msnfanatic.com/index.php/MSNP11:Challenges
        // Step 1: The MD5 Hash
        $md5Hash = md5($code.self::PROD_KEY);
        $aMD5 = @explode("\0", chunk_split($md5Hash, 8, "\0"));
        for ($i = 0; $i < 4; $i++) {
            $aMD5[$i] = implode('', array_reverse(@explode("\0", chunk_split($aMD5[$i], 2, "\0"))));
            $aMD5[$i] = (0 + base_convert($aMD5[$i], 16, 10)) & 0x7FFFFFFF;
        }

        // Step 2: A new string
        $chl_id = $code.self::PROD_ID;
        $chl_id .= str_repeat('0', 8 - (strlen($chl_id) % 8));

        $aID = @explode("\0", substr(chunk_split($chl_id, 4, "\0"), 0, -1));
        for ($i = 0; $i < count($aID); $i++) {
            $aID[$i] = implode('', array_reverse(@explode("\0", chunk_split($aID[$i], 1, "\0"))));
            $aID[$i] = 0 + base_convert(bin2hex($aID[$i]), 16, 10);
        }

        // Step 3: The 64 bit key
        $magic_num = 0x0E79A9C1;
        $str7f = 0x7FFFFFFF;
        $high = 0;
        $low = 0;
        for ($i = 0; $i < count($aID); $i += 2) {
            $temp = $aID[$i];
            $temp = bcmod(bcmul($magic_num, $temp), $str7f);
            $temp = bcadd($temp, $high);
            $temp = bcadd(bcmul($aMD5[0], $temp), $aMD5[1]);
            $temp = bcmod($temp, $str7f);

            $high = $aID[$i+1];
            $high = bcmod(bcadd($high, $temp), $str7f);
            $high = bcadd(bcmul($aMD5[2], $high), $aMD5[3]);
            $high = bcmod($high, $str7f);

            $low = bcadd(bcadd($low, $high), $temp);
        }

        $high = bcmod(bcadd($high, $aMD5[1]), $str7f);
        $low = bcmod(bcadd($low, $aMD5[3]), $str7f);

        $new_high = bcmul($high & 0xFF, 0x1000000);
        $new_high = bcadd($new_high, bcmul($high & 0xFF00, 0x100));
        $new_high = bcadd($new_high, bcdiv($high & 0xFF0000, 0x100));
        $new_high = bcadd($new_high, bcdiv($high & 0xFF000000, 0x1000000));
        // we need integer here
        $high = 0+$new_high;

        $new_low = bcmul($low & 0xFF, 0x1000000);
        $new_low = bcadd($new_low, bcmul($low & 0xFF00, 0x100));
        $new_low = bcadd($new_low, bcdiv($low & 0xFF0000, 0x100));
        $new_low = bcadd($new_low, bcdiv($low & 0xFF000000, 0x1000000));
        // we need integer here
        $low = 0+$new_low;

        // we just use 32 bits integer, don't need the key, just high/low
        // $key = bcadd(bcmul($high, 0x100000000), $low);

        // Step 4: Using the key
        $md5Hash = md5($code.self::PROD_KEY);
        $aHash = @explode("\0", chunk_split($md5Hash, 8, "\0"));

        $hash = '';
        $hash .= sprintf("%08x", (0 + base_convert($aHash[0], 16, 10)) ^ $high);
        $hash .= sprintf("%08x", (0 + base_convert($aHash[1], 16, 10)) ^ $low);
        $hash .= sprintf("%08x", (0 + base_convert($aHash[2], 16, 10)) ^ $high);
        $hash .= sprintf("%08x", (0 + base_convert($aHash[3], 16, 10)) ^ $low);

        return $hash;
    }

    /**
     * Utility methods
     */

    private function Array2SoapVar($Array, $ReturnSoapVarObj = true, $TypeName = null, $TypeNameSpace = null) {
        $ArrayString = '';
        foreach($Array as $Key => $Val) {
            if ($Key{0} == ':') continue;
            $Attrib = '';
            if (is_array($Val[':'])) {
                foreach ($Val[':'] as $AttribName => $AttribVal)
                    $Attrib .= " $AttribName = '$AttribVal'";
            }
            if ($Key{0} == '!') {
                //List Type Define
                $Key = substr($Key,1);
                foreach ($Val as $ListKey => $ListVal) {
                    if ($ListKey{0} == ':') continue;
                    if (is_array($ListVal)) $ListVal = $this->Array2SoapVar($ListVal, false);
                    elseif (is_bool($ListVal)) $ListVal = $ListVal ? 'true' : 'false';
                    $ArrayString .= "<$Key$Attrib>$ListVal</$Key>";
                }
                continue;
            }
            if (is_array($Val)) $Val = $this->Array2SoapVar($Val, false);
            elseif (is_bool($Val)) $Val = $Val ? 'true' : 'false';
            $ArrayString .= "<$Key$Attrib>$Val</$Key>";
        }
        if ($ReturnSoapVarObj) return new SoapVar($ArrayString, XSD_ANYXML, $TypeName, $TypeNameSpace);
        return $ArrayString;
    }

    private function linetoArray($lines) {
        $lines = str_replace("\r", '', $lines);
        $lines = explode("\n", $lines);
        foreach ($lines as $line) {
            if (!isset($line{3})) continue;
            list($Key, $Val) = explode(':', $line);
            $Data[trim($Key)] = trim($Val);
        }
        return $Data;
    }

    /**
    * Get Passport ticket
    *
    * @param string $url URL string (Optional)
    * @return mixed Array of tickets or false on failure
    */
    private function get_passport_ticket($url = '') {
        $user = $this->user;
        $password = htmlspecialchars($this->password);

        if ($url === '')
            $passport_url = self::PASSPORT_URL;
        else
            $passport_url = $url;

        $XML = '<?xml version="1.0" encoding="UTF-8"?>
<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"
          xmlns:wsse="http://schemas.xmlsoap.org/ws/2003/06/secext"
          xmlns:saml="urn:oasis:names:tc:SAML:1.0:assertion"
          xmlns:wsp="http://schemas.xmlsoap.org/ws/2002/12/policy"
          xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"
          xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/03/addressing"
          xmlns:wssc="http://schemas.xmlsoap.org/ws/2004/04/sc"
          xmlns:wst="http://schemas.xmlsoap.org/ws/2004/04/trust">
<Header>
  <ps:AuthInfo xmlns:ps="http://schemas.microsoft.com/Passport/SoapServices/PPCRL" Id="PPAuthInfo">
    <ps:HostingApp>{7108E71A-9926-4FCB-BCC9-9A9D3F32E423}</ps:HostingApp>
    <ps:BinaryVersion>4</ps:BinaryVersion>
    <ps:UIVersion>1</ps:UIVersion>
    <ps:Cookies></ps:Cookies>
    <ps:RequestParams>AQAAAAIAAABsYwQAAAAxMDMz</ps:RequestParams>
  </ps:AuthInfo>
  <wsse:Security>
    <wsse:UsernameToken Id="user">
      <wsse:Username>'.$user.'</wsse:Username>
      <wsse:Password>'.$password.'</wsse:Password>
    </wsse:UsernameToken>
  </wsse:Security>
</Header>
<Body>
  <ps:RequestMultipleSecurityTokens xmlns:ps="http://schemas.microsoft.com/Passport/SoapServices/PPCRL" Id="RSTS">
    <wst:RequestSecurityToken Id="RST0">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>http://Passport.NET/tb</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST1">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>messengerclear.live.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="'.$this->passport_policy.'"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST2">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>messenger.msn.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="?id=507"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST3">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>contacts.msn.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="MBI"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST4">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>messengersecure.live.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="MBI_SSL"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST5">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>spaces.live.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="MBI"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
    <wst:RequestSecurityToken Id="RST6">
      <wst:RequestType>http://schemas.xmlsoap.org/ws/2004/04/security/trust/Issue</wst:RequestType>
      <wsp:AppliesTo>
        <wsa:EndpointReference>
          <wsa:Address>storage.msn.com</wsa:Address>
        </wsa:EndpointReference>
      </wsp:AppliesTo>
      <wsse:PolicyReference URI="MBI"></wsse:PolicyReference>
    </wst:RequestSecurityToken>
  </ps:RequestMultipleSecurityTokens>
</Body>
</Envelope>';

        $this->debug_message("*** URL: $passport_url");
        $this->debug_message("*** Sending SOAP:\n$XML");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $passport_url);
        if ($this->debug) curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $XML);
        $data = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->debug_message("*** Get Result:\n$data");

        if ($http_code != 200) {
            // sometimes, redirect to another URL
            // MSNP15
            //<faultcode>psf:Redirect</faultcode>
            //<psf:redirectUrl>https://msnia.login.live.com/pp450/RST.srf</psf:redirectUrl>
            //<faultstring>Authentication Failure</faultstring>
            if (strpos($data, '<faultcode>psf:Redirect</faultcode>') === false) {
                $this->debug_message("*** Could not get passport ticket! http code = $http_code");
                return false;
            }
            preg_match("#<psf\:redirectUrl>(.*)</psf\:redirectUrl>#", $data, $matches);
            if (count($matches) == 0) {
                $this->debug_message('*** Redirected, but could not get redirect URL!');
                return false;
            }
            $redirect_url = $matches[1];
            if ($redirect_url == $passport_url) {
                $this->debug_message('*** Redirected, but to same URL!');
                return false;
            }
            $this->debug_message("*** Redirected to $redirect_url");
            return $this->get_passport_ticket($redirect_url);
        }

        // sometimes, redirect to another URL, also return 200
        // MSNP15
        //<faultcode>psf:Redirect</faultcode>
        //<psf:redirectUrl>https://msnia.login.live.com/pp450/RST.srf</psf:redirectUrl>
        //<faultstring>Authentication Failure</faultstring>
        if (strpos($data, '<faultcode>psf:Redirect</faultcode>') !== false) {
            preg_match("#<psf\:redirectUrl>(.*)</psf\:redirectUrl>#", $data, $matches);
            if (count($matches) != 0) {
                $redirect_url = $matches[1];
                if ($redirect_url == $passport_url) {
                    $this->debug_message('*** Redirected, but to same URL!');
                    return false;
                }
                $this->debug_message("*** Redirected to $redirect_url");
                return $this->get_passport_ticket($redirect_url);
            }
        }

        // no Redurect faultcode or URL
        // we should get the ticket here

        // we need ticket and secret code
        // RST1: messengerclear.live.com
        // <wsse:BinarySecurityToken Id="Compact1">t=tick&p=</wsse:BinarySecurityToken>
        // <wst:BinarySecret>binary secret</wst:BinarySecret>
        // RST2: messenger.msn.com
        // <wsse:BinarySecurityToken Id="Compact2">t=tick</wsse:BinarySecurityToken>
        // RST3: contacts.msn.com
        // <wsse:BinarySecurityToken Id="Compact3">t=tick&p=</wsse:BinarySecurityToken>
        // RST4: messengersecure.live.com
        // <wsse:BinarySecurityToken Id="Compact4">t=tick&p=</wsse:BinarySecurityToken>
        // RST5: spaces.live.com
        // <wsse:BinarySecurityToken Id="Compact5">t=tick&p=</wsse:BinarySecurityToken>
        // RST6: storage.msn.com
        // <wsse:BinarySecurityToken Id="Compact6">t=tick&p=</wsse:BinarySecurityToken>
        preg_match("#".
            "<wsse\:BinarySecurityToken Id=\"Compact1\">(.*)</wsse\:BinarySecurityToken>(.*)".
            "<wst\:BinarySecret>(.*)</wst\:BinarySecret>(.*)".
            "<wsse\:BinarySecurityToken Id=\"Compact2\">(.*)</wsse\:BinarySecurityToken>(.*)".
            "<wsse\:BinarySecurityToken Id=\"Compact3\">(.*)</wsse\:BinarySecurityToken>(.*)".
            "<wsse\:BinarySecurityToken Id=\"Compact4\">(.*)</wsse\:BinarySecurityToken>(.*)".
            "<wsse\:BinarySecurityToken Id=\"Compact5\">(.*)</wsse\:BinarySecurityToken>(.*)".
            "<wsse\:BinarySecurityToken Id=\"Compact6\">(.*)</wsse\:BinarySecurityToken>(.*)".
            "#",
        $data, $matches);

        // no ticket found!
        if (count($matches) == 0) {
            // Since 2011/2/15, the return value will be Compact2, not PPToken2

            // we need ticket and secret code
            // RST1: messengerclear.live.com
            // <wsse:BinarySecurityToken Id="Compact1">t=tick&p=</wsse:BinarySecurityToken>
            // <wst:BinarySecret>binary secret</wst:BinarySecret>
            // RST2: messenger.msn.com
            // <wsse:BinarySecurityToken Id="PPToken2">t=tick</wsse:BinarySecurityToken>
            // RST3: contacts.msn.com
            // <wsse:BinarySecurityToken Id="Compact3">t=tick&p=</wsse:BinarySecurityToken>
            // RST4: messengersecure.live.com
            // <wsse:BinarySecurityToken Id="Compact4">t=tick&p=</wsse:BinarySecurityToken>
            // RST5: spaces.live.com
            // <wsse:BinarySecurityToken Id="Compact5">t=tick&p=</wsse:BinarySecurityToken>
            // RST6: storage.msn.com
            // <wsse:BinarySecurityToken Id="Compact6">t=tick&p=</wsse:BinarySecurityToken>
            preg_match("#".
                       "<wsse\:BinarySecurityToken Id=\"Compact1\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wst\:BinarySecret>(.*)</wst\:BinarySecret>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact2\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact3\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact4\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact5\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "<wsse\:BinarySecurityToken Id=\"Compact6\">(.*)</wsse\:BinarySecurityToken>(.*)".
                       "#",
                       $data, $matches);
            // no ticket found!
            if (count($matches) == 0) {
                $this->debug_message("*** Can't get passport ticket!");
                return false;
            }
        }

        //$this->debug_message(var_export($matches, true));
        // matches[0]: all data
        // matches[1]: RST1 (messengerclear.live.com) ticket
        // matches[2]: ...
        // matches[3]: RST1 (messengerclear.live.com) binary secret
        // matches[4]: ...
        // matches[5]: RST2 (messenger.msn.com) ticket
        // matches[6]: ...
        // matches[7]: RST3 (contacts.msn.com) ticket
        // matches[8]: ...
        // matches[9]: RST4 (messengersecure.live.com) ticket
        // matches[10]: ...
        // matches[11]: RST5 (spaces.live.com) ticket
        // matches[12]: ...
        // matches[13]: RST6 (storage.live.com) ticket
        // matches[14]: ...

        // so
        // ticket => $matches[1]
        // secret => $matches[3]
        // web_ticket => $matches[5]
        // contact_ticket => $matches[7]
        // oim_ticket => $matches[9]
        // space_ticket => $matches[11]
        // storage_ticket => $matches[13]

        // yes, we get ticket
        $aTickets = array(
            'ticket' => html_entity_decode($matches[1]),
            'secret' => html_entity_decode($matches[3]),
            'web_ticket' => html_entity_decode($matches[5]),
            'contact_ticket' => html_entity_decode($matches[7]),
            'oim_ticket' => html_entity_decode($matches[9]),
            'space_ticket' => html_entity_decode($matches[11]),
            'storage_ticket' => html_entity_decode($matches[13])
        );
        $this->ticket = $aTickets;
        //$this->debug_message(var_export($aTickets, true));
        $ABAuthHeaderArray = array(
            'ABAuthHeader' => array(
                ':' => array('xmlns' => 'http://www.msn.com/webservices/AddressBook'),
                'ManagedGroupRequest' => false,
                'TicketToken' => htmlspecialchars($this->ticket['contact_ticket']),
            )
        );
        $this->ABAuthHeader = new SoapHeader('http://www.msn.com/webservices/AddressBook', 'ABAuthHeader', $this->Array2SoapVar($ABAuthHeaderArray));
        return $aTickets;
    }

    /**
    * Generate the data to send a message
    *
    * @param string $sMessage Message
    * @param integer $network Network
    * @return string Message data
    */
    private function getMessage($sMessage, $network = 1) {
        $msg_header = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nX-MMS-IM-Format: FN=$this->font_fn; EF=$this->font_ef; CO=$this->font_co; CS=0; PF=22\r\n\r\n";
        $msg_header_len = strlen($msg_header);
        if ($network == 1)
            $maxlen = self::MAX_MSN_MESSAGE_LEN - $msg_header_len;
        else
            $maxlen = self::MAX_YAHOO_MESSAGE_LEN - $msg_header_len;
        $sMessage = str_replace("\r", '', $sMessage);
        $msg = substr($sMessage, 0, $maxlen);
        return $msg_header.$msg;
    }

    /**
    * Sleep for the given number of seconds
    *
    * @param integer $wait Number of seconds to sleep for
    */
    private function NSRetryWait($wait) {
        $this->debug_message("*** Sleeping for $wait seconds before retrying");
        sleep($wait);
    }

    /**
     * Sends a ping command
     *
     * Should be called about every 50 seconds
     *
     * @return void
     */
    public function sendPing() {
        // NS: >>> PNG
        $this->ns_writeln("PNG");
    }

    /**
    * Methods to add / call callbacks
    */

    /**
     * Calls User Handler
     *
     * Calls registered handler for a specific event.
     *
     * @param string $event Command (event) name (Rvous etc)
     * @param array $data Data
     * @see registerHandler
     * @return void
     */
    private function callHandler($event, $data = NULL) {
        if (isset($this->myEventHandlers[$event])) {
            if ($data !== NULL) {
                call_user_func($this->myEventHandlers[$event], $data);
            } else {
                call_user_func($this->myEventHandlers[$event]);
            }
        }
    }

    /**
     * Registers a user handler
     *
     * Handler List
     * IMIn, SessionReady, Pong, ConnectFailed, Reconnect,
     * AddedToList, RemovedFromList, StatusChange
     *
     * @param string $event Event name
     * @param string $handler User function to call
     * @see callHandler
     * @return boolean true if successful
     */
    public function registerHandler($event, $handler) {
        if (is_callable($handler)) {
            $this->myEventHandlers[$event] = $handler;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Debugging methods
     */

    /**
     * Print message if debugging is enabled
     *
     * @param string $str Message to print
     */
    private function debug_message($str) {
        if (!$this->debug) return;
        echo $str."\n";
    }

    /**
     * Dump binary data
     *
     * @param string $str Data string
     * @return Binary data
     */
    private function dump_binary($str) {
        $buf = '';
        $a_str = '';
        $h_str = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            if (($i % 16) == 0) {
                if ($buf !== '') {
                    $buf .= "$h_str $a_str\n";
                }
                $buf .= sprintf("%04X:", $i);
                $a_str = '';
                $h_str = '';
            }
            $ch = ord($str[$i]);
            if ($ch < 32)
            $a_str .= '.';
            else
            $a_str .= chr($ch);
            $h_str .= sprintf(" %02X", $ch);
        }
        if ($h_str !== '')
        $buf .= "$h_str $a_str\n";
        return $buf;
    }

    function mhash_sha1($data, $key)
    {
        if (extension_loaded("mhash"))
            return mhash(MHASH_SHA1, $data, $key);

        if (function_exists("hash_hmac"))
            return hash_hmac('sha1', $data, $key, true);

        // RFC 2104 HMAC implementation for php. Hacked by Lance Rushing
        $b = 64;
        if (strlen($key) > $b)
            $key = pack("H*", sha1($key));
        $key = str_pad($key, $b, chr(0x00));
        $ipad = str_pad("", $b, chr(0x36));
        $opad = str_pad("", $b, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;

        $sha1_value = sha1($k_opad . pack("H*", sha1($k_ipad . $data)));

        $hash_data = '';
        $str = join('',explode('\x', $sha1_value));
        $len = strlen($str);
        for ($i = 0; $i < $len; $i += 2)
            $hash_data .= chr(hexdec(substr($str, $i, 2)));
        return $hash_data;
    }
}
