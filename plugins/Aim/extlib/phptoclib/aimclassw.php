<?php
/*
*	PHPTOCLIB: A library for AIM connectivity through PHP using the TOC protocal.
*
*	This library is free software; you can redistribute it and/or
*	modify it under the terms of the GNU Lesser General Public
*	License as published by the Free Software Foundation; either
*	version 2.1 of the License, or (at your option) any later version.
*
*	This library is distributed in the hope that it will be useful,
*	but WITHOUT ANY WARRANTY; without even the implied warranty of
*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
*	Lesser General Public License for more details.
*
*	You should have received a copy of the GNU Lesser General Public
*	License along with this library; if not, write to the Free Software
*	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*
*/
/**
* The version of PHPTOCLIB we are running right now
*
* @access private
* @var int
*/
define("PHPTOCLIB_VERSION","1.0.0 RC1");

// Prevents Script from Timing Out
//set_time_limit(0);

// Constant Declarations

/**
* Maximum size for a direct connection IM in bytes
*
* @access private
* @var int
*/

define("MAX_DIM_SIZE",3072); //Default to 3kb

/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_WARN",74);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_MSG",75);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_UPDATEBUDDY",76);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_SIGNON",77);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_NICK",78);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_ERROR",79);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_CHATJ",80);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_CHATI",81);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_CHATUPDBUD",82);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_CHATINV",83);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_CHATLE",84);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_URL",85);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_NICKSTAT",86);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_PASSSTAT",87);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_RVOUSP",88);
/**
* Internally used for message type
*
* @access private
* @var int
*/
define("AIM_TYPE_NOT_IMPLEMENTED",666);



/**
* Internally used for connection type
*
* Internal type for a normal connection
*
* @access private
* @var int
*/
define("CONN_TYPE_NORMAL",1);
/**
* Internally used for connection type
*
* Internal type of a Dirct Connection
*
* @access private
* @var int
*/
define("CONN_TYPE_DC",2);
/**
* Internally used for connection type
*
*Internal type for a file transfer connection
*
* @access private
* @var int
*/
define("CONN_TYPE_FT",3);
/**
* Internally used for connection type
*
*Internal type for a file get connection
*
* @access private
* @var int
*/
define("CONN_TYPE_FTG",4);

/**
* Maximum size for a TOC packet
*
* @access private
* @var int
*/
define("MAX_PACKLENGTH",2048);

/**
* TOC packet type
*
* @access private
* @var int
*/
define("SFLAP_TYPE_SIGNON",1);
/**
* TOC packet type
*
* @access private
* @var int
*/
define("SFLAP_TYPE_DATA",2);
/**
* TOC packet type
*
* @access private
* @var int
*/
define("SFLAP_TYPE_ERROR",3);
/**
* TOC packet type
*
* @access private
* @var int
*/
define("SFLAP_TYPE_SIGNOFF",4);
/**
* TOC packet type
*
* @access private
* @var int
*/
define("SFLAP_TYPE_KEEPALIVE",5);
/**
* TOC packet type
*
* @access private
* @var int
*/
define("SFLAP_MAX_LENGTH",1024);



/**
* Service UID for a voice connection
*
* @access private
* @var int
*/
define('VOICE_UID', '09461341-4C7F-11D1-8222-444553540000');
/**
* Service UID for file sending 
*
* @access private
* @var int
*/
define('FILE_SEND_UID', '09461343-4C7F-11D1-8222-444553540000');
/**
* Service UID for file getting
*
* @access private
* @var int
*/
define('FILE_GET_UID', '09461348-4C7F-11D1-8222-444553540000');
/**
* Service UID for Direct connections 
*
* @access private
* @var int
*/
define('IMAGE_UID', '09461345-4C7F-11D1-8222-444553540000');
/**
* Service UID for Buddy Icons
*
* @access private
* @var int
*/
define('BUDDY_ICON_UID', '09461346-4C7F-11D1-8222-444553540000');
/**
* Service UID for stocks
*
* @access private
* @var int
*/
define('STOCKS_UID', '09461347-4C7F-11D1-8222-444553540000');
/**
* Service UID for games
*
* @access private
* @var int
*/
define('GAMES_UID', '0946134a-4C7F-11D1-8222-444553540000');

/**
* FLAP return code
*
* @access private
* @var int
*/
define("SFLAP_SUCCESS",0);
/**
* FLAP return code
*
* @access private
* @var int
*/
define("SFLAP_ERR_UNKNOWN",1);
/**
* FLAP return code
*
* @access private
* @var int
*/
define("SFLAP_ERR_ARGS",2);
/**
* FLAP return code
*
* @access private
* @var int
*/
define("SFLAP_ERR_LENGTH",3);
/**
* FLAP return code
*
* @access private
* @var int
*/
define("SFLAP_ERR_READ",4);
/**
* FLAP return code
*
* @access private
* @var int
*/
define("SFLAP_ERR_SEND",5);

/**
* FLAP version number
*
* @access private
* @var int
*/
define("SFLAP_FLAP_VERSION",1);
/**
* FLAP TLV code
*
* @access private
* @var int
*/
define("SFLAP_TLV_TAG",1);
/**
* Bytes in a FLAP header
*
* @access private
* @var int
*/
define("SFLAP_HEADER_LEN",6);

/** 
 * PHPTocLib AIM Class
 *
 * @author Jeremy Bryant <pickleman78@users.sourceforge.net>
 * @author Rajiv Makhijani <rajiv@blue-tech.org>
 * @package phptoclib
 * @version 1.0RC1
 * @copyright 2005
 * @access public
 *
 */
class Aim
{
	/** 
	 * AIM ScreenName
	 *
	 * @var String
	 * @access private
	 */
	var $myScreenName;
	
	/** 
	 * AIM Password (Plain Text)
	 *
	 * @var String
	 * @access private
	 */
	var $myPassword;
	

	/** 
	 * AIM TOC Server
	 *
	 * @var String
	 * @access public
	 */
	var $myServer="toc.oscar.aol.com";
	
	/** 
	 * AIM Formatted ScreenName
	 *
	 * @var String
	 * @access private
	 */
	var $myFormatSN;
	
	/** 
	 * AIM TOC Server Port
	 *
	 * @var String
	 * @access public
	 */
	var $myPort="5190";
	
	/** 
	 * Profile Data
	 * Use setProfile() to update
	 *
	 * @var String
	 * @access private
	 */
	var $myProfile="Powered by phpTOCLib. Please visit http://sourceforge.net/projects/phptoclib for more information";     //The profile of the bot

	/** 
	 * Socket Connection Resource ID
	 *
	 * @var Resource
	 * @access private
	 */
	var $myConnection;  //Connection resource ID
	
	/** 
	 * Roasted AIM Password
	 *
	 * @var String
	 * @access private
	 */
	var $myRoastedPass;
	
	/** 
	 * Last Message Recieved From Server
	 *
	 * @var String
	 * @access private
	 */
	var $myLastReceived;
	
	/** 
	 * Current Seq Number Used to Communicate with Server
	 *
	 * @var Integer
	 * @access private
	 */
	var $mySeqNum;
	 
	 /** 
	 * Current Warning Level
	 * Getter: getWarning()
	 * Setter: setWarning()
	 *
	 * @var Integer
	 * @access private
	 */
	var $myWarnLevel;   //Warning Level of the bot
	
	 /** 
	 * Auth Code
	 *
	 * @var Integer
	 * @access private
	 */
	var $myAuthCode;
	
	/** 
	 * Buddies
	 * Getter: getBuddies()
	 *
	 * @var Array
	 * @access private
	 */
	var $myBuddyList;
	
	/** 
	 * Blocked Buddies
	 * Getter: getBlocked()
	 *
	 * @var Array
	 * @access private
	 */
	var $myBlockedList;
	
	/** 
	 * Permited Buddies
	 * Getter: getBlocked()
	 *
	 * @var Array
	 * @access private
	 */
	var $myPermitList;
	
	/** 
	 * Permit/Deny Mode
	 * 1 - Allow All
	 * 2 - Deny All
	 * 3 - Permit only those on your permit list
	 * 4 - Permit all those not on your deny list
	 *
	 * @var Integer
	 * @access private
	 */
	var $myPdMode;
	
	//Below variables added 4-29 by Jeremy: Implementing chat

	/** 
	 * Contains Chat Room Info
	 * $myChatRooms['roomid'] = people in room
	 *
	 * @var Array
	 * @access private
	 */
	var $myChatRooms;
		
	//End of chat implementation
	

	/** 
	 * Event Handler Functions
	 *
	 * @var Array
	 * @access private
	 */
	var $myEventHandlers = array();
	
	/** 
	 * Array of direct connection objects(including file transfers)
	 *
	 * @var Array
	 * @access private
	 */
	var $myDirectConnections = array();
	
	/** 
	 * Array of the actual connections
	 *
	 * @var Array
	 * @access private
	 */
	var $myConnections = array();
	
	/**
	 * The current state of logging
	 * 
	 * @var Boolean
	 * @access private
	 */
	
	var $myLogging = false;
	
    /** 
	 * Constructor
	 *
	 * Permit/Deny Mode Options
	 * 1 - Allow All
	 * 2 - Deny All
	 * 3 - Permit only those on your permit list
	 * 4 - Permit all those not on your deny list
	 *
	 * @param String $sn AIM Screenname
	 * @param String $password AIM Password
	 * @param Integer $pdmode Permit/Deny Mode
	 * @access public
	 */
	function Aim($sn, $password, $pdmode)
    {
        //Constructor assignment
		$this->myScreenName = $this->normalize($sn);
		$this->myPassword = $password;
		$this->myRoastedPass = $this->roastPass($password);
		$this->mySeqNum = 1;
		$this->myConnection = 0;
		$this->myWarnLevel = 0;
		$this->myAuthCode = $this->makeCode();
		$this->myPdMode = $pdmode;
		$this->myFormatSN = $this->myScreenName;
		
		$this->log("PHPTOCLIB v" . PHPTOCLIB_VERSION . " Object Created");
		
	}

	/** 
	 * Enables debug logging (Logging is disabled by default)
	 *
	 * 
	 * @access public
	 * @return void
	 */

	function setLogging($enable)
	{
		$this->myLogging=$enable;
	}

	function log($data)
	{
	    if($this->myLogging){
            error_log($data);
        }
	}
	
	 /** 
	 * Logs a packet
	 *
	 * 
	 * @access private
	 * @param Array $packary Packet
	 * @param String $in Prepend
	 * @return void
	 */
	function logPacket($packary,$in)
	{
		if(!$this->myLogging || sizeof($packary)<=0 || (@strlen($packary['decoded'])<=0 && @isset($packary['decoded'])))
		   return;
		$towrite=$in . ":  ";
		foreach($packary as $k=>$d)
		{
			$towrite.=$k . ":" . $d . "\r\n";
		}
		$towrite.="\r\n\r\n";
		$this->log($towrite);
	}
	/** 
	 * Roasts/Hashes Password
	 *
	 * @param String $password Password
	 * @access private
	 * @return String Roasted Password
	 */
	function roastPass($password)
	{
		$roaststring = 'Tic/Toc';
		$roasted_password = '0x';
		for ($i = 0; $i < strlen($password); $i++)
			$roasted_password .= bin2hex($password[$i] ^ $roaststring[($i % 7)]);
		return $roasted_password;
	}
	
	/** 
	 * Access Method for myScreenName
	 *
	 * @access public
	 * @param $formated Returns formatted Screenname if true as returned by server
	 * @return String Screenname
	 */
	function getMyScreenName($formated = false)
	{
		if ($formated)
		{
			return $this->myFormatSN;
		}
		else
		{
			return $this->normalize($this->myScreenName);
		}
	}
	
	/** 
	 * Generated Authorization Code
	 *
	 * @access private
	 * @return Integer Auth Code
	 */
	function makeCode()
	{
		$sn = ord($this->myScreenName[0]) - 96;
		$pw = ord($this->myPassword[0]) - 96;
		$a = $sn * 7696 + 738816;
		$b = $sn * 746512;
		$c = $pw * $a;

		return $c - $a + $b + 71665152;
	}


	/** 
	 * Reads from Socket
	 *
	 * @access private
	 * @return String Data
	 */
	function sflapRead()
	{
		if ($this->socketcheck($this->myConnection))
		{
			$this->log("Disconnected.... Reconnecting in 60 seconds");
			sleep(60);
			$this->signon();
		}
		
		$header = fread($this->myConnection,SFLAP_HEADER_LEN);
		
		if (strlen($header) == 0)
		{
			$this->myLastReceived = "";
			return "";
		}
		$header_data = unpack("aast/Ctype/nseq/ndlen", $header);
		$this->log(" . ", false);
		$packet = fread($this->myConnection, $header_data['dlen']);
		if (strlen($packet) <= 0 && $sockinfo['blocked'])
			$this->derror("Could not read data");
		
		if ($header_data['type'] == SFLAP_TYPE_SIGNON)
		{
			$packet_data=unpack("Ndecoded", $packet);
		}
		
		if ($header_data['type'] == SFLAP_TYPE_KEEPALIVE)
		{
			$this->myLastReceived = '';
			return 0;
		} 
		else if (strlen($packet)>0)
		{
			$packet_data = unpack("a*decoded", $packet);
		}
		$this->log("socketcheck check now");
		if ($this->socketcheck($this->myConnection))
		{
			$this->derror("Connection ended unexpectedly");
		}
		
		$data = array_merge($header_data, $packet_data);
		$this->myLastReceived = $data;
		$this->logPacket($data,"in");
		return $data;
    }

	/** 
	 * Sends Data on Socket
	 *
	 * @param String $sflap_type Type
	 * @param String $sflap_data Data
	 * @param boolean $no_null No Null
	 * @param boolean $formatted Format
	 * @access private
	 * @return String Roasted Password
	 */
	function sflapSend($sflap_type, $sflap_data, $no_null, $formatted)
	{
		$packet = "";
		if (strlen($sflap_data) >= MAX_PACKLENGTH)
			$sflap_data = substr($sflap_data,0,MAX_PACKLENGTH);
			
		if ($formatted)
		{
			$len = strlen($sflap_len);
			$sflap_header = pack("aCnn",'*', $sflap_type, $this->mySeqNum, $len);
			$packet = $sflap_header . $sflap_data;
		} else {
			if (!$no_null)
			{
				$sflap_data = str_replace("\0","", trim($sflap_data));
				$sflap_data .= "\0";
			}
			$data = pack("a*", $sflap_data);
			$len = strlen($sflap_data);
			$header = pack("aCnn","*", $sflap_type, $this->mySeqNum, $len);
			$packet = $header . $data;
		}
		
		//Make sure we are still connected
		if ($this->socketcheck($this->myConnection))
		{
			$this->log("Disconnected.... reconnecting in 60 seconds");
			sleep(60);
			$this->signon();
		}
		$sent = fputs($this->myConnection, $packet) or $this->derror("Error sending packet to AIM");
		$this->mySeqNum++;
		sleep(ceil($this->myWarnLevel/10));
		$this->logPacket(array($sflap_type,$sflap_data),"out");
	}

	/** 
	 * Escape the thing that TOC doesn't like,that would be
	 * ",', $,{,},[,]
	 *
	 * @param String $data Data to Escape
	 * @see decodeData
	 * @access private
	 * @return String $data Escaped Data
	 */
	function encodeData($data)
	{
		$data = str_replace('"','\"', $data);
		$data = str_replace('$','\$', $data);
		$data = str_replace("'","\'", $data);
		$data = str_replace('{','\{', $data);
		$data = str_replace('}','\}', $data);
		$data = str_replace('[','\[', $data);
		$data = str_replace(']','\]', $data);
		return $data;
	}
	
	/** 
	 * Unescape data TOC has escaped
	 * ",', $,{,},[,]
	 *
	 * @param String $data Data to Unescape
	 * @see encodeData
	 * @access private
	 * @return String $data Unescape Data
	 */
	function decodeData($data)
	{
		$data = str_replace('\"','"', $data);
		$data = str_replace('\$','$', $data);
		$data = str_replace("\'","'", $data);
		$data = str_replace('\{','{', $data);
		$data = str_replace('\}','}', $data);
		$data = str_replace('\[','[', $data);
		$data = str_replace('\]',']', $data);
		$data = str_replace('&quot;','"', $data);
		$data = str_replace('&amp;','&', $data);
		return $data;
	}

	/** 
	 * Normalize ScreenName
	 * no spaces and all lowercase
	 *
	 * @param String $nick ScreenName
	 * @access public
	 * @return String $nick Normalized ScreenName
	 */
	function normalize($nick)
	{
		$nick = str_replace(" ","", $nick);
		$nick = strtolower($nick);
		return $nick;
	}

	/** 
	 * Sets internal info with update buddy
	 * Currently only sets warning level
	 * 
	 * @access public
	 * @return void
	 */
	function setMyInfo()
	{
		//Sets internal values bvase on the update buddy command
		$this->log("Setting my warning level ...");
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_get_status " . $this->normalize($this->myScreenName),0,0);
		//The rest of this will now be handled by the other functions. It is assumed
		//that we may have other data queued in the socket, do we should just add this
		//message to the queue instead of trying to set it in here
	}

	/** 
	 * Connects to AIM and Signs On Using Info Provided in Constructor
	 * 
	 * @access public
	 * @return void
	 */
	function signon()
	{
		$this->log("Ready to sign on to the server");
		$this->myConnection = fsockopen($this->myServer, $this->myPort, $errno, $errstr,10) or die("$errorno:$errstr");
		$this->log("Connected to server");
		$this->mySeqNum = (time() % 65536); //Select an arbitrary starting point for
										  //sequence numbers
		if (!$this->myConnection)
			$this->derror("Error connecting to toc.oscar.aol.com");
		$this->log("Connected to AOL");
		//Send the flapon packet
		fputs($this->myConnection,"FLAPON\r\n\n\0"); //send the initial handshake
		$this->log("Sent flapon");
		$this->sflapRead();  //Make sure the server responds with what we expect
		if (!$this->myLastReceived)
			$this->derror("Error sending the initialization string");

		//send the FLAP SIGNON packet back with what it needs
		//There are 2 parts to the signon packet. They are sent in succession, there
		//is no indication if either packet was correctly sent
		$signon_packet = pack("Nnna".strlen($this->myScreenName),1,1,strlen($this->myScreenName), $this->myScreenName);
		$this->sflapSend(SFLAP_TYPE_SIGNON, $signon_packet,1,0);
		$this->log("sent signon packet part one");
		
		$signon_packet_part2 = 'toc2_signon login.oscar.aol.com 29999 ' . $this->myScreenName . ' ' . $this->myRoastedPass . ' english-US "TIC:TOC2:REVISION" 160 ' . $this->myAuthCode;
		$this->log($signon_packet_part2 . "");
		$this->sflapSend(SFLAP_TYPE_DATA, $signon_packet_part2,0,0);
		$this->log("Sent signon packet part 2... Awaiting response...");

		$this->sflapRead();
		$this->log("Received Sign on packet, beginning initilization...");
		$message = $this->getLastReceived();
		$this->log($message . "\n");
		if (strstr($message,"ERROR:"))
		{
			$this->onError($message);
			die("Fatal signon error");
		}
		stream_set_timeout($this->myConnection,2);
		//The information sent before the config2 command is utterly useless to us
		//So we will just skim through them until we reach it
		
		//Add the first entry to the connection array
		$this->myConnections[] = $this->myConnection;
		
		
		//UPDATED 4/12/03: Now this will use the receive function and send the
		//received messaged to the assigned handlers. This is where the signon 
		//method has no more use
		
		$this->log("Done with signon proccess");
		//socket_set_blocking($this->myConnection,false);
	}
	
	/** 
	 * Sends Instant Message
	 *
	 * @param String $to Message Recipient SN
	 * @param String $message Message to Send
	 * @param boolean $auto Sent as Auto Response / Away Message Style
	 * @access public
	 * @return void
	 */
	function sendIM($to, $message, $auto = false)
	{
		if ($auto) $auto = "auto";
		else $auto = "";
		$to = $this->normalize($to);
		$message = $this->encodeData($message);
		$command = 'toc2_send_im "' . $to . '" "' . $message . '" ' .  $auto;
		$this->sflapSend(SFLAP_TYPE_DATA, trim($command),0,0);
		$cleanedmessage = str_replace("<br>", "   ", $this->decodeData($message));
		$cleanedmessage = strip_tags($cleanedmessage);
		$this->log("TO - " . $to . " : " . $cleanedmessage);
	}
	
	/** 
	 * Set Away Message
	 *
	 * @param String $message Away message (some HTML supported).
	 *   Use null to remove the away message
	 * @access public
	 * @return void
	 */
	function setAway($message)
	{
		$message = $this->encodeData($message);
		$command = 'toc_set_away "' . $message . '"';
		$this->sflapSend(SFLAP_TYPE_DATA, trim($command),0,0);
		$this->log("SET AWAY MESSAGE - " . $this->decodeData($message));
	}

	/** 
	 * Fills Buddy List
	 * Not implemented fully yet
	 *
	 * @access public
	 * @return void
	 */
	function setBuddyList()
	{
		//This better be the right message
		$message = $this->myLastReceived['decoded'];
		if (strpos($message,"CONFIG2:") === false)
		{
			$this->log("setBuddyList cannot be called at this time because I got $message");
			return false;
		}
		$people = explode("\n",trim($message,"\n"));
		//The first 3 elements of the array are who knows what, element 3 should be
		//a letter followed by a person
		for($i = 1; $i<sizeof($people); $i++)
		{
   			@list($mode, $name) = explode(":", $people[$i]);
			switch($mode)
			{
				case 'p':
					$this->myPermitList[] = $name;
					break;
				case 'd':
					$this->myBlockedList[] = $name;
					break;
				case 'b':
					$this->myBuddyList[] = $name;
					break;
				case 'done':
	 				break;
				default:
					//
			}
		}
	}
	
	/** 
	 * Adds buddy to Permit list
	 *
	 * @param String $buddy Buddy's Screenname
	 * @access public
	 * @return void
	 */
	function addPermit($buddy)
	{
		$this->sflapSend(SFLAP_TYPE_DATA,"toc2_add_permit " . $this->normalize($buddy),0,0);
		$this->myPermitList[] = $this->normalize($buddy);
		return 1;
	}
	
	/** 
	 * Blocks buddy
	 *
	 * @param String $buddy Buddy's Screenname
	 * @access public
	 * @return void
	 */
	function blockBuddy($buddy)
	{
		$this->sflapSend(SFLAP_TYPE_DATA,"toc2_add_deny " . $this->normalize($buddy),0,0);
		$this->myBlockedList[] = $this->normalize($buddy);
		return 1;
	}
	
	/** 
	 * Returns last message received from server
	 *
	 * @access private
	 * @return String Last Message from Server
	 */
	function getLastReceived()
	{
		if (@$instuff = $this->myLastReceived['decoded']){
			return $this->myLastReceived['decoded'];
		}else{
			return;
		}
	}
	
	/** 
	 * Returns Buddy List
	 *
	 * @access public
	 * @return array Buddy List
	 */
	function getBuddies()
	{
		return $this->myBuddyList;
	}
	
	/** 
	 * Returns Permit List
	 *
	 * @access public
	 * @return array Permit List
	 */
	function getPermit()
	{
		return $this->myPermitList;
	}
	
	/** 
	 * Returns Blocked Buddies
	 *
	 * @access public
	 * @return array Blocked List
	 */
	function getBlocked()
	{
		return $this->myBlockedList;
	}
	
	


	/** 
	 * Reads and returns data from server
	 *
	 * This is a wrapper for $Aim->sflap_read(), and only returns the $this->myLastReceived['data']
	 * portion of the message. It is preferred that you do not call $Aim->sflap_read() and use this
	 * function instead. This function has a return value. Calling this prevents the need to call
	 * $Aim->getLastReceived()
	 *
	 * @access public
	 * @return String Data recieved from server
	 */
	function read_from_aim()
	{
		$this->sflapRead();
		$returnme = $this->getLastReceived();
		return $returnme;
	}
	
	/** 
	 * Sets current internal warning level
	 * 
	 * This allows you to update the bots warning level when warned.
	 *
	 * @param int Warning Level %
	 * @access private
	 * @return void
	 */
	function setWarningLevel($warnlevel)
	{
		$this->myWarnLevel = $warnlevel;
	}
	
	/** 
	 * Warns / "Evils" a User
	 *
	 * To successfully warn another user they must have sent you a message.
	 * There is a limit on how much and how often you can warn another user.
	 * Normally when you warn another user they are aware who warned them,
	 * however there is the option to warn anonymously.  When warning anon.
	 * note that the warning is less severe.
	 *
	 * @param String $to Screenname to warn
	 * @param boolean $anon Warn's anonymously if true. (default = false)
	 * @access public
	 * @return void
	 */
	function warnUser($to, $anon = false)
	{
		if (!$anon)
			$anon = '"norm"';

		else
			$anon = '"anon"';
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_evil " . $this->normalize($to) . " $anon",0,0);
	}
	
	/** 
	 * Returns warning level of bot
	 *
	 * @access public
	 * @return void
	 */
	function getWarningLevel()
	{
		return $this->myWarningLevel;
	}
	
	/** 
	 * Sets bot's profile/info
	 *
	 * Limited to 1024 bytes.
	 *
	 * @param String $profiledata Profile Data (Can contain limited html: br,hr,font,b,i,u etc)
	 * @param boolean $poweredby If true, appends link to phpTOCLib project to profile
	 * @access public
	 * @return void
	 */
	function setProfile($profiledata, $poweredby = false)
	{
		if ($poweredby == false){
			$this->myProfile = $profiledata;
		}else{
			$this->myProfile = $profiledata . "<font size=1 face=tahoma><br><br>Powered by phpTOCLib<br>http://sourceforge.net/projects/phptoclib</font>";
		}
		
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_set_info \"" . $this->encodeData($this->myProfile) . "\"",0,0);
		$this->setMyInfo();
		$this->log("Profile has been updated...");
	}
	
	//6/29/04 by Jeremy:
	//Added mthod to accept a rvous,decline it, and
	//read from the rvous socket
	
	//Decline
	
	/** 
	 * Declines a direct connection request (rvous)
	 *
	 * @param String $nick ScreenName request was from
	 * @param String $cookie Request cookie (from server)
	 * @param String $uuid UUID
	 * 
	 * @access public
	 * @return void
	 */
	function declineRvous($nick, $cookie, $uuid)
	{
		$nick = $this->normalize($nick);
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_rvous_cancel $nick $cookie $uuid",0,0);
	}
	
	/** 
	 * Accepts a direct connection request (rvous)
	 *
	 * @param String $nick ScreenName request was from
	 * @param String $cookie Request cookie (from server)
	 * @param String $uuid UUID
	 * @param String $vip IP of User DC with
	 * @param int $port Port number to connect to
	 * 
	 * @access public
	 * @return void
	 */
	function acceptRvous($nick, $cookie, $uuid, $vip, $port)
	{
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_rvous_accept $nick $cookie $uuid",0,0);
		
		//Now open the connection to that user
		if ($uuid == IMAGE_UID)
		{	
			$dcon = new Dconnect($vip, $port);
		}
		else if ($uuid == FILE_SEND_UID)
		{
			$dcon = new FileSendConnect($vip, $port);
		}
		if (!$dcon->connected)
		{
			$this->log("The connection failed");				
			return false;
		}
		
		//Place this dcon object inside the array
		$this->myDirectConnections[] = $dcon;
		//Place the socket in an array to
		$this->myConnections[] = $dcon->sock;

		
		//Get rid of the first packet because its worthless
		//and confusing
		$dcon->readDIM();
		//Assign the cookie
		$dcon->cookie = $dcon->lastReceived['cookie'];
		$dcon->connectedTo = $this->normalize($nick);
		return $dcon;
	}	
	
	/** 
	 * Sends a Message over a Direct Connection
	 *
	 * Only works if a direct connection is already established with user
	 *
	 * @param String $to Message Recipient SN
	 * @param String $message Message to Send
	 * 
	 * @access public
	 * @return void
	 */
	function sendDim($to, $message)
	{
		//Find the connection
		for($i = 0;$i<sizeof($this->myDirectConnections);$i++)
		{
			if ($this->normalize($to) == $this->myDirectConnections[$i]->connectedTo && $this->myDirectConnections[$i]->type == CONN_TYPE_DC)
			{
				$dcon = $this->myDirectConnections[$i];
				break;
			}
		}
		if (!$dcon)
		{
			$this->log("Could not find a direct connection to $to");
			return false;
		}
		$dcon->sendMessage($message, $this->normalize($this->myScreenName));
		return true;
	}
	
	/** 
	 * Closes an established Direct Connection
	 *
	 * @param DConnect $dcon Direct Connection Object to Close
	 * 
	 * @access public
	 * @return void
	 */
	function closeDcon($dcon)
	{
		
		$nary = array();
		for($i = 0;$i<sizeof($this->myConnections);$i++)
		{
			if ($dcon->sock == $this->myConnections[$i])
				unset($this->myConnections[$i]);
		}
		
		$this->myConnections = array_values($this->myConnections);
		unset($nary);
		$nary2 = array();
		
		for($i = 0;$i<sizeof($this->myDirectConnections);$i++)
		{
			if ($dcon == $this->myDirectConnections[$i])
				unset($this->myDirectConnections[$i]);
		}
		$this->myDirectConnections = array_values($this->myDirectConnections);
		$dcon->close();
		unset($dcon);
	}
	
	//Added 4/29/04 by Jeremy:
	//Various chat related methods
	
	/** 
	 * Accepts a Chat Room Invitation (Joins room)
	 *
	 * @param String $chatid ID of Chat Room
	 * 
	 * @access public
	 * @return void
	 */
	function joinChat($chatid)
	{
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_chat_accept " . $chatid,0,0);
	}
	
	/** 
	 * Leaves a chat room
	 *
	 * @param String $chatid ID of Chat Room
	 * 
	 * @access public
	 * @return void
	 */
	function leaveChat($chatid)
	{
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_chat_leave " . $chatid,0,0);
	}
	
	/** 
	 * Sends a message in a chat room
	 *
	 * @param String $chatid ID of Chat Room
	 * @param String $message Message to send
	 * 
	 * @access public
	 * @return void
	 */
	function chatSay($chatid, $message)
	{
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_chat_send " . $chatid . " \"" . $this->encodeData($message) . "\"",0,0);
	}
	
	/** 
	 * Invites a user to a chat room
	 *
	 * @param String $chatid ID of Chat Room
	 * @param String $who Screenname of user
	 * @param String $message Note to include with invitiation
	 * 
	 * @access public
	 * @return void
	 */
	function chatInvite($chatid, $who, $message)
	{
		$who = $this->normalize($who);
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_chat_invite " . $chatid . " \"" . $this->encodeData($message) . "\" " . $who,0,0);
	}
	
	/** 
	 * Joins/Creates a new chat room
	 *
	 * @param String $name Name of the new chat room
	 * @param String $exchange Exchange of new chat room
	 * 
	 * @access public
	 * @return void
	 */
	function joinNewChat($name, $exchange)
	{
		//Creates a new chat
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_chat_join " . $exchange . " \"" . $name . "\"",0,0);
	}
	
	/** 
	 * Disconnect error handler, attempts to reconnect in 60 seconds
	 *
	 * @param String $message Error message (desc of where error encountered etc)
	 * 
	 * @access private
	 * @return void
	 */
	function derror($message)
	{
		$this->log($message);
		$this->log("Error");
		fclose($this->myConnection);
		if ((time() - $GLOBALS['errortime']) <  600){
			$this->log("Reconnecting in 60 Seconds");
			sleep(60);
		}
		$this->signon();
		$GLOBALS['errortime'] = time();
	}
	
	/** 
	 * Returns connection type of socket (main or rvous etc)
	 *
	 * Helper method for recieve()
	 *
	 * @param Resource $sock Socket to determine type for
	 * 
	 * @access private
	 * @return void
	 * @see receive
	 */
	function connectionType($sock)
	{
		//Is it the main connection?
		if ($sock == $this->myConnection)
		   return CONN_TYPE_NORMAL;
		else
		{
			for($i = 0;$i<sizeof($this->myDirectConnections);$i++)
			{
				if ($sock == $this->myDirectConnections[$i]->sock)
				    return $this->myDirectConnections[$i]->type;
			}
		}
		return false;
	}
	
	/** 
	 * Checks for new data and calls appropriate methods
	 *
	 * This method is usually called in an infinite loop to keep checking for new data
	 * 
	 * @access public
	 * @return void
	 * @see connectionType
	 */ 
	function receive()
	{
		//This function will be used to get the incoming data
		//and it will be used to call the event handlers
		
		//First, get an array of sockets that have data that is ready to be read
		$ready = array();
		$ready = $this->myConnections;
		$numrdy = stream_select($ready, $w = NULL, $x = NULL,NULL);
		
		//Now that we've waited for something, go through the $ready
		//array and read appropriately
		
		for($i = 0;$i<sizeof($ready);$i++)
		{
			//Get the type
			$type = $this->connectionType($ready[$i]);
			if ($type == CONN_TYPE_NORMAL)
			{
				//Next step:Get the data sitting in the socket
				$message = $this->read_from_aim();
				if (strlen($message) <= 0)
				{
					return;
				}
				
				//Third step: Get the command from the server
				@list($cmd, $rest) = explode(":", $message);
				
				//Fourth step, take the command, test the type, and pass it off
				//to the correct internal handler. The internal handler will
				//do what needs to be done on the class internals to allow
				//it to work, then proceed to pass it off to the user created handle
				//if there is one
				$this->log($cmd);
				switch($cmd)
				{
					case 'SIGN_ON':
						$this->onSignOn($message);
						break;
					case 'CONFIG2':
						$this->onConfig($message);
						break;
					case 'ERROR':
						$this->onError($message);
						break;
					case 'NICK':
						$this->onNick($message);
						break;
					case 'IM_IN2':
						$this->onImIn($message);
						break;
					case 'UPDATE_BUDDY2':
						$this->onUpdateBuddy($message);
						break;
					case 'EVILED':
						$this->onWarn($message);
						break;
					case 'CHAT_JOIN':
						$this->onChatJoin($message);
						break;
					case 'CHAT_IN':
						$this->onChatIn($message);
						break;
					case 'CHAT_UPDATE_BUDDY':
						$this->onChatUpdate($message);
						break;
					case 'CHAT_INVITE':
						$this->onChatInvite($message);
						break;
					case 'CHAT_LEFT':
						$this->onChatLeft($message);
						break;
					case 'GOTO_URL':
						$this->onGotoURL($message);
						break;
					case 'DIR_STATUS':
						$this->onDirStatus($message);
						break;
					case 'ADMIN_NICK_STATUS':
						$this->onAdminNick($message);
						break;
					case 'ADMIN_PASSWD_STATUS':
						$this->onAdminPasswd($message);
						break;
					case 'PAUSE':
						$this->onPause($message);
						break;
					case 'RVOUS_PROPOSE':
						$this->onRvous($message);
						break;
					default:
						$this->log("Fell through: $message");
						$this->CatchAll($message);
						break;
				}
			}
			else
			{
				for($j = 0;$j<sizeof($this->myDirectConnections);$j++)
				{
					if ($this->myDirectConnections[$j]->sock == $ready[$i])
					{
						$dcon = $this->myDirectConnections[$j];
						break;
					}
				}
				//Now read from the dcon
				if ($dcon->type == CONN_TYPE_DC)
				{
					if ($dcon->readDIM() == false)
					{
						$this->closeDcon($dcon);
						continue;
					}
					
					$message['message'] = $dcon->lastMessage;
					if ($message['message'] == "too big")
					{
						$this->sendDim("Connection dropped because you sent a message larger that " . MAX_DCON_SIZE . " bytes.", $dcon->connectedTo);
						$this->closeDcon($dcon);
						continue;
					}
					$message['from'] = $dcon->connectedTo;
					$this->onDimIn($message);
				}
			}
		}
        $this->conn->myLastReceived="";
		//Now get out of this function because the handlers should take care
		//of everything
	}
	
	//The next block of code is all the event handlers needed by the class
	//Some are left blank and only call the users handler because the class
	//either does not support the command, or cannot do anything with it
	// ---------------------------------------------------------------------

	/** 
	 * Direct IM In Event Handler
	 *
	 * Called when Direct IM is received.
	 * Call's user handler (if available) for DimIn.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onDimIn($data)
	{
		$this->callHandler("DimIn", $data);
	}
	
	/** 
	 * Sign On Event Handler
	 *
	 * Called when Sign On event occurs.
	 * Call's user handler (if available) for SIGN_ON.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onSignOn($data)
	{
		$this->callHandler("SignOn", $data);
	}
	
	/** 
	 * Config Event Handler
	 *
	 * Called when Config data received.
	 * Call's user handler (if available) for Config.
	 * 
	 * Loads buddy list and other info
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onConfig($data)
	{
		$this->log("onConfig Message: " . $data);
		
		if (strpos($data,"CONFIG2:") === false)
		{
			$this->log("get_buddy_list cannot be called at this time because I got $data");
			//return false;
		}
		$people = explode("\n",trim($data,"\n"));
		//The first 3 elements of the array are who knows what, element 3 should be
		//a letter followed by a person
		
		//AIM decided to add this wonderful new feature, the recent buddy thing, this kind of
		//messes this funtion up, so we need to adapt it... unfortuneately, its not really
		//clear how this works, so we are just going to add their name to the permit list.
		
		//Recent buddies I believe are in the format
		//number:name:number.... I think the first number counts down from 25 how long its
		//been... but I don't know the second number,,,,
		
		//TODO: Figure out the new recent buddies system
		
		//Note: adding that at the bottom is a quick hack and may have adverse consequences...
		for($i = 1;$i<sizeof($people);$i++)
		{
   			@list($mode, $name) = explode(":", $people[$i]);
			switch($mode)
			{
				case 'p':
					$this->myPermitList[] = $name;
					break;
				case 'd':
					$this->myBlockedList[] = $name;
					break;
				case 'b':
					$this->myBuddyList[] = $name;
					break;
				case 'done':
	 				break;
				default:
					//This is assumed to be recent buddies...
					$this->myPermitList[]=$name;
			}
		}
		
		//We only get the config message once, so now we should send our pd mode
		
		$this->sflapSend(SFLAP_TYPE_DATA,"toc2_set_pdmode " . $this->myPdMode,0,0);
		//Adds yourself to the permit list
		//This is to fix an odd behavior if you have nobody on your list
		//the server won't send the config command... so this takes care of it
		$this->sflapSend(SFLAP_TYPE_DATA,"toc2_add_permit " . $this->normalize($this->myScreenName),0,0); 
		
		//Now we allow the user to send a list, update anything they want, etc
		$this->callHandler("Config", $data);
		//Now that we have taken care of what the user wants, send the init_done message
		$this->sflapSend(SFLAP_TYPE_DATA,"toc_init_done",0,0);
		//'VOICE_UID' 
		//'FILE_GET_UID'
		//'IMAGE_UID'
		//'BUDDY_ICON_UID'
		//'STOCKS_UID'
		//'GAMES_UID'
		$this->sflapSend(SFLAP_TYPE_DATA, "toc_set_caps " . IMAGE_UID . " " .  FILE_SEND_UID ." " . FILE_GET_UID . " " . BUDDY_ICON_UID . "",0,0);
	}
	

	/** 
	 * Error Event Handler
	 *
	 * Called when an Error occurs.
	 * Call's user handler (if available) for Error.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onError($data)
	{
	    static $errarg = '';
		static $ERRORS = array(
            0=>'Success',
            901 =>'$errarg not currently available',
            902 =>'Warning of $errarg not currently available',
            903 =>'A message has been dropped, you are exceeding
                  the server speed limit',
            911 =>'Error validating input',
            912 =>'Invalid account',
            913 =>'Error encountered while processing request',
            914 =>'Service unavailable',
            950 =>'Chat in $errarg is unavailable.',
            960 =>'You are sending message too fast to $errarg',
            961 =>'You missed an im from $errarg because it was too big.',
            962 =>'You missed an im from $errarg because it was sent too fast.',
            970 =>'Failure',
            971 =>'Too many matches',
            972 =>'Need more qualifiers',
            973 =>'Dir service temporarily unavailable',
            974 =>'Email lookup restricted',
            975 =>'Keyword Ignored',
            976 =>'No Keywords',
            977 =>'Language not supported',
            978 =>'Country not supported',
            979 =>'Failure unknown $errarg',
            980 =>'Incorrect nickname or password.',
            981 =>'The service is temporarily unavailable.',
            982 =>'Your warning level is currently too high to sign on.',
            983 =>'You have been connecting and
               	   disconnecting too frequently.  Wait 10 minutes and try again.
	               If you continue to try, you will need to wait even longer.',
            989 =>'An unknown signon error has occurred $errarg'
            );
		$data_array = explode(":", $data);
		for($i=0; $i<count($data_array); $i++)
		{
            switch($i)
            {
                case 0:
                    $cmd = $data_array[$i];
                    break;
                case 1:
                    $errornum = $data_array[$i];
                    break;
                case 2:
                    $errargs = $data_array[$i];
                    break;
            }
		}
		eval("\$errorstring=\"\$ERRORS[" . $errornum . "]\";");
		$string = "\$errorstring=\"\$ERRORS[$errornum]\";";
		//This is important information! We need 
		// a A different outputter for errors
		// b Just to echo it
		//I'm just going to do a straight echo here, becuse we assume that
		//the user will NEED to see this error. An option to supress it will
		//come later I think. Perhaps if we did an error reporting level, similar
		//to PHP's, and we could probably even use PHP's error outputting system
		//I think that may be an idea.... 
		
		$this->log($errorstring . "\n");
		
		$this->callHandler("Error", $data);
	}
	
	/** 
	 * Nick Event Handler
	 *
	 * Called when formatted own ScreenName is receieved
	 * Call's user handler (if available) for Nick.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onNick($data)
	{
		//This is our nick, so set a field called "myFormatSN" which will represent
		//the actual name given by the server to us, NOT the normalized screen name
		@list($cmd, $nick) = explode(":", $data);
		$this->myFormatSN = $nick;
		
		$this->callHandler("Nick", $data);
	}
	
	/** 
	 * IM In Event Handler
	 *
	 * Called when an Instant Message is received.
	 * Call's user handler (if available) for IMIn.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onImIn($data)
	{
		//Perhaps we should add an internal log for debugging purposes??
		//But now, this should probably be handled by the user purely
		
		$this->callHandler("IMIn", $data);
	}
	
	/** 
	 * UpdateBuddy Event Handler
	 *
	 * Called when a Buddy Update is receieved.
	 * Call's user handler (if available) for UpdateBuddy.
	 * If info is about self, updates self info (Currently ownly warning).
	 *
	 * ToDo: Keep track of idle, warning etc on Buddy List
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onUpdateBuddy($data)
	{
		//Again, since our class currently does not deal with other people without
		//outside help, then this is also probably best left to the user. Though
		//we should probably allow this to replace the setMyInfo function above
		//by handling the input if and only if it is us
		//Check and see that this is the command expected
		if (strpos($data,"UPDATE_BUDDY2:") == -1)
		{
			$this->log("A different message than expected was received");
			return false;
		}
		
		//@list($cmd, $info['sn'], $info['online'], $info['warnlevel'], $info['signon'], $info['idle'], $info['uc']) = explode(":", $command['incoming']);

		//@list($cmd, $sn, $online, $warning, $starttime, $idletime, $uc) = explode(":", $data);
		$info = $this->getMessageInfo($data);
		if ($this->normalize($info['sn']) == $this->normalize($this->myScreenName))
		{
			$warning = rtrim($info['warnlevel'],"%");
			$this->myWarnLevel = $warning;
			$this->log("My warning level is $this->myWarnLevel %");
		}
		
		$this->callHandler("UpdateBuddy", $data);
	}
	
	/** 
	 * Warning Event Handler
	 *
	 * Called when bot is warned.
	 * Call's user handler (if available) for Warn.
	 * Updates internal warning level
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onWarn($data)
	{
		/*
		For reference:
			$command['incoming'] .= ":0";
			$it = explode(":", $command['incoming']);
			$info['warnlevel'] = $it[1];
			$info['from'] = $it[2];		
		*/
		//SImply update our warning level
		//@list($cmd, $newwarn, $user) = explode(":", $data);
		
		$info = $this->getMessageInfo($data);
		
		$this->setWarningLevel(trim($info['warnlevel'],"%"));
		$this->log("My warning level is $this->myWarnLevel %");
		
		$this->callHandler("Warned", $data);
	}
	
	/** 
	 * Chat Join Handler
	 *
	 * Called when bot joins a chat room.
	 * Call's user handler (if available) for ChatJoin.
	 * Adds chat room to internal chat room list.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onChatJoin($data)
	{
		@list($cmd, $rmid, $rmname) = explode(":", $data);
		$this->myChatRooms[$rmid] = 0;
		
		$this->callHandler("ChatJoin", $data);
	}
	
	/** 
	 * Returns number of chat rooms bot is in
	 * 
	 * @access public
	 * @param String $data Raw message from server
	 * @return int
	 */
	function getNumChats()
	{
		return count($this->myChatRooms);
	}
	
	/** 
	 * Chat Update Handler
	 *
	 * Called when bot received chat room data (user update).
	 * Call's user handler (if available) for ChatUpdate.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onChatUpdate($data)
	{
		$stuff = explode(":", $data);
		$people = sizeof($stuff);
		$people -= 2;
		
		$this->callHandler("ChatUpdate", $data);
	}
	
	/** 
	 * Chat Message In Handler
	 *
	 * Called when chat room message is received.
	 * Call's user handler (if available) for ChatIn.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onChatIn($data)
	{
		$this->callHandler("ChatIn", $data);
	}
	
	
	/** 
	 * Chat Invite Handler
	 *
	 * Called when bot is invited to a chat room.
	 * Call's user handler (if available) for ChatInvite.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onChatInvite($data)
	{
		//@list($cmd, $name, $id, $from, $data) = explode(":", $data,6);
		//$data = explode(":",$data,6);
		//$nm = array();
		//@list($nm['cmd'],$nm['name'],$nm['id'],$nm['from'],$nm['message']) = $data;
		
		
		$this->callHandler("ChatInvite", $data);
	}
	
	/** 
	 * Chat Left Handler
	 *
	 * Called when bot leaves a chat room
	 * Call's user handler (if available) for ChatLeft.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onChatLeft($data)
	{
		$info = $this->getMessageInfo($data);
		unset($this->myChatRooms[$info['chatid']]);
		$this->callHandler("ChatLeft", $data);
	}
	
	/** 
	 * Goto URL Handler
	 *
	 * Called on GotoURL.
	 * Call's user handler (if available) for GotoURL.
	 * No detailed info available for this / Unsupported.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onGotoURL($data)
	{
		//This is of no use to the internal class
		
		$this->callHandler("GotoURL", $data);
	}
	
	/** 
	 * Dir Status Handler
	 *
	 * Called on DirStatus.
	 * Call's user handler (if available) for DirStatus.
	 * No detailed info available for this / Unsupported.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onDirStatus($data)
	{
		//This is not currently suported
		
		$this->callHandler("DirStatus", $data);
	}
	
	/** 
	 * AdminNick Handler
	 *
	 * Called on AdminNick.
	 * Call's user handler (if available) for AdminNick.
	 * No detailed info available for this / Unsupported.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onAdminNick($data)
	{
		//NOt particularly useful to us		
		$this->callHandler("AdminNick", $data);
	}
	
	/** 
	 * AdminPasswd Handler
	 *
	 * Called on AdminPasswd.
	 * Call's user handler (if available) for AdminPasswd.
	 * No detailed info available for this / Unsupported.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onAdminPasswd($data)
	{
		//Also not particlualry useful to the internals
		$this->callHandler("AdminPasswd", $data);
	}
	
	/** 
	 * Pause Handler
	 *
	 * Called on Pause.
	 * Call's user handler (if available) for Pause.
	 * No detailed info available for this / Unsupported.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onPause($data)
	{
		//This is pretty useless to us too...
		
		$this->callHandler("Pause", $data);
	}
	
	/** 
	 * Direct Connection Handler
	 *
	 * Called on Direct Connection Request(Rvous).
	 * Call's user handler (if available) for Rvous.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function onRvous($data)
	{
		$this->callHandler("Rvous", $data);
	}
	
	/** 
	 * CatchAll Handler
	 *
	 * Called for unrecognized commands.
	 * Logs unsupported messages to array.
	 * Call's user handler (if available) for CatchAll.
	 * 
	 * @access private
	 * @param String $data Raw message from server
	 * @return void
	 */
	function CatchAll($data)
	{
		//Add to a log of unsupported messages.
		
		$this->unsupported[] = $data;
		//$this->log($data);
		//print_r($data);
		
		$this->callHandler("CatchAll", $data);
	}
	
	/** 
	 * Calls User Handler
	 *
	 * Calls registered handler for a specific event.
	 * 
	 * @access private
	 * @param String $event Command (event) name (Rvous etc)
	 * @param String $data Raw message from server
	 * @see registerHandler
	 * @return void
	 */
	function callHandler($event, $data)
	{
		
		if (isset($this->myEventHandlers[$event]))
		{
			//$function = $this->myEventHandlers[$event] . "(\$data);";
			//eval($function);
			call_user_func($this->myEventHandlers[$event], $data);
		}
		else
		{
			$this->noHandler($data);
		}
	}
	
	/** 
	 * Registers a user handler
	 * 
	 * Handler List
	 * SignOn, Config, ERROR, NICK, IMIn, UpdateBuddy, Eviled, Warned, ChatJoin
	 * ChatIn, ChatUpdate, ChatInvite, ChatLeft, GotoURL, DirStatus, AdminNick
	 * AdminPasswd, Pause, Rvous, DimIn, CatchAll
	 *
	 * @access private
	 * @param String $event Event name
	 * @param String $handler User function to call
	 * @see callHandler
	 * @return boolean Returns true if successful
	 */
	function registerHandler($event, $handler)
	{
		if (is_callable($handler))
		{
			$this->myEventHandlers[$event] = $handler;
			return true;
		}
		else
		{
			return false;
		}
	}

    /** 
     * No user handler method fall back.
     *
     * Does nothing with message.
     *
     * @access public
     * @param String $message Raw server message
     * @return void
     */
    function noHandler($message)
    {
	    //This function intentionally left blank
	    //This is where the handlers will fall to for now. I plan on including a more
	    //efficent check to avoid the apparent stack jumps that this code will produce
	    //But for now, just fall into here, and be happy
	    return;
    }

    //GLOBAL FUNCTIONS

    /** 
     * Finds type, and returns as part of array ['type']
     * Puts message in ['incoming']
     *
     * Helper method for getMessageInfo.
     *
     * @access public
     * @param String $message Raw server message
     * @see msg_parse
     * @see getMessageInfo
     * @return array
     */
    static function msg_type($message)
    {
	    $command = array();
	    @list($cmd, $rest) = explode(":", $message);
	    switch($cmd)
	    {
		    case 'IM_IN2':
			    $type = AIM_TYPE_MSG;
		    break;
		
		    case 'UPDATE_BUDDY2':
			    $type = AIM_TYPE_UPDATEBUDDY;
		    break;
		
		    case 'EVILED':
			    $type = AIM_TYPE_WARN;
		    break;
		
		    case 'SIGN_ON':
			    $type = AIM_TYPE_SIGNON;
		    break;
		
		    case 'NICK':
			    $type = AIM_TYPE_NICK;
		    break;
		
		    case 'ERROR':
			    $type = AIM_TYPE_ERROR;
		    break;
		
		    case 'CHAT_JOIN':
			    $type = AIM_TYPE_CHATJ;
		    break;
		
		    case 'CHAT_IN':
			    $type = AIM_TYPE_CHATI;
		    break;
		
		    case 'CHAT_UPDATE_BUDDY':
			    $type = AIM_TYPE_CHATUPDBUD;
		    break;
		
		    case 'CHAT_INVITE':
			    $type = AIM_TYPE_CHATINV;
		    break;
		
		    case 'CHAT_LEFT':
			    $type = AIM_TYPE_CHATLE;
		    break;
		
		    case 'GOTO_URL':
			    $type = AIM_TYPE_URL;
		    break;
		
		    case 'ADMIN_NICK_STATUS':
			    $type = AIM_TYPE_NICKSTAT;
		    break;
		
		    case 'ADMIN_PASSWD_STATUS':
			    $type = AIM_TYPE_PASSSTAT;
		    break;
		
		    case 'RVOUS_PROPOSE':
			    $type = AIM_TYPE_RVOUSP;
		    break;
		
		    default:
			    $type = AIM_TYPE_NOT_IMPLEMENTED;
		    break;
	    }
	    $command['type'] = $type;
	    $command['incoming'] = $message;
	    return $command;
    }

    /** 
     * Parses message and splits into info array
     *
     * Helper method for getMessageInfo.
     *
     * @access public
     * @param String $command Message and type (after msg_type)
     * @see msg_type
     * @see getMessageInfo
     * @return array
     */
    static function msg_parse($command)
    {
	    $info = array();
	    switch($command['type'])
	    {
		    case AIM_TYPE_WARN:
			    $command['incoming'] .= ":0";
			    $it = explode(":", $command['incoming']);
			    $info['warnlevel'] = $it[1];
			    $info['from'] = $it[2];

		    break;
		
		    case AIM_TYPE_MSG:
			    $it = explode(":", $command['incoming'],5);
			    $info['auto'] = $it[2];
			    $info['from'] = $it[1];
			    $info['message'] = $it[4];
		    break;
		
		    case AIM_TYPE_UPDATEBUDDY:
			    @list($cmd, $info['sn'], $info['online'], $info['warnlevel'], $info['signon'], $info['idle'], $info['uc']) = explode(":", $command['incoming']);
		    break;
		
		    case AIM_TYPE_SIGNON:
			    @list($cmd, $info['version']) = explode(":", $command['incoming']);		
		    break;
		
		    case AIM_TYPE_NICK:
			    @list($cmd, $info['nickname']) = explode(":", $command['incoming']);		
		    break;
		    case AIM_TYPE_ERROR:
			    @list($cmd, $info['errorcode'], $info['args']) = explode(":", $command['incoming']);
		    break;
		
		    case AIM_TYPE_CHATJ:
			    @list($cmd, $info['chatid'], $info['chatname']) = explode(":", $command['incoming']);
		    break;
		
		    case AIM_TYPE_CHATI:
			    @list($cmd, $info['chatid'], $info['user'], $info['whisper'], $info['message']) = explode(":", $command['incoming'],5);
		    break;
		
		    case AIM_TYPE_CHATUPDBUD:
			    @list($cmd, $info['chatid'], $info['inside'], $info['userlist']) = explode(":", $command['incoming'],3);	
		    break;
		
		    case AIM_TYPE_CHATINV:
			    @list($cmd, $info['chatname'], $info['chatid'], $info['from'], $info['message']) = explode(":", $command['incoming'],5);
		    break;
		
		    case AIM_TYPE_CHATLE:
			    @list($cmd, $info['chatid']) = explode(":", $command['incoming']);		
		    break;
		
		    case AIM_TYPE_URL:
			    @list($cmd, $info['windowname'], $info['url']) = explode(":", $command['incoming'],3);
		    break;
		
		    case AIM_TYPE_RVOUSP:
			    @list($cmd,$info['user'],$info['uuid'],$info['cookie'],$info['seq'],$info['rip'],$info['pip'],$info['vip'],$info['port'],$info['tlvs']) = explode(":",$command['incoming'],10);
		    break;
		
		    case AIM_TYPE_NICKSTAT:
		    case AIM_TYPE_PASSSTAT:
			    @list($cmd, $info['returncode'], $info['opt']) = explode(":", $command['incoming'],3);		
		    break;
		
		    default:
		    $info['command'] = $command['incoming'];
	    }
	    return $info;
    }

    /** 
     * Returns a parsed message
     *
     * Calls msg_parse(msg_type( to first determine message type and then parse accordingly
     *
     * @access public
     * @param String $command Raw server message
     * @see msg_type
     * @see msg_parse
     * @return array
     */
    static function getMessageInfo($message)
    {
	    return self::msg_parse(self::msg_type($message));
    }

    /** 
     * Checks socket for end of file
     *
     * @access public
     * @param Resource $socket Socket to check
     * @return boolean true if end of file (socket) 
     */
    static function socketcheck($socket){
	    $info = stream_get_meta_data($socket);
	    return $info['eof'];
	    //return(feof($socket));
    }
}

?>
