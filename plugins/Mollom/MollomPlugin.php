<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to check submitted notices with Mollom
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * Mollom is a bayesian spam checker, wrapped into a webservice
 * This plugin is based on the Drupal Mollom module
 *
 * @category  Plugin
 * @package   Laconica
 * @author    Brenda Wallace <brenda@cpan.org>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 *
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define('MOLLOMPLUGIN_VERSION', '0.1');
define('MOLLOM_API_VERSION', '1.0');

define('MOLLOM_ANALYSIS_UNKNOWN' , 0);
define('MOLLOM_ANALYSIS_HAM'     , 1);
define('MOLLOM_ANALYSIS_SPAM'    , 2);
define('MOLLOM_ANALYSIS_UNSURE'  , 3);

define('MOLLOM_MODE_DISABLED', 0);
define('MOLLOM_MODE_CAPTCHA' , 1);
define('MOLLOM_MODE_ANALYSIS', 2);

define('MOLLOM_FALLBACK_BLOCK' , 0);
define('MOLLOM_FALLBACK_ACCEPT', 1);

define('MOLLOM_ERROR'   , 1000);
define('MOLLOM_REFRESH' , 1100);
define('MOLLOM_REDIRECT', 1200);

/**
 * Plugin to check submitted notices with Mollom
 *
 * Mollom is a bayesian spam filter provided by webservice.
 *
 * @category Plugin
 * @package  Laconica
 * @author   Brenda Wallace <shiny@cpan.org>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 *
 * @see      Event
 */
class MollomPlugin extends Plugin
{
    public $public_key;
    public $private_key;
    public $servers;

    function onStartNoticeSave($notice)
    {
      if ( $this->public_key ) {
        //Check spam
        $data = array(
            'post_body'      => $notice->content,
            'author_name'    => $profile->nickname,
            'author_url'     => $profile->homepage,
            'author_id'      => $profile->id,
            'author_ip'      => $this->getClientIp(),
        );
        $response = $this->mollom('mollom.checkContent', $data);
        if ($response['spam'] == MOLLOM_ANALYSIS_SPAM) {
          // TRANS: Client exception thrown when notice content triggers the spam filter.
          throw new ClientException(_m('Spam Detected.'), 400);
        }
        if ($response['spam'] == MOLLOM_ANALYSIS_UNSURE) {
          //if unsure, let through
        }
        if($response['spam'] == MOLLOM_ANALYSIS_HAM) {
          // all good! :-)
        }
      }

      return true;
    }

    function getClientIP() {
        if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
            // Note: order matters here; use proxy-forwarded stuff first
            foreach (array('HTTP_X_FORWARDED_FOR', 'CLIENT-IP', 'REMOTE_ADDR') as $k) {
                if (isset($_SERVER[$k])) {
                    return $_SERVER[$k];
                }
            }
        }
        return '127.0.0.1';
    }

    /**
     * Call a remote procedure at the Mollom server.  This function will
     * automatically add the information required to authenticate against
     * Mollom.
     */
    function mollom($method, $data = array()) {
        if (!extension_loaded('xmlrpc')) {
            if (!dl('xmlrpc.so')) {
                common_log(LOG_ERR, "Can't pingback; xmlrpc extension not available.");
            }
        }

      // Construct the server URL:
      $public_key = $this->public_key;
      // Retrieve the list of Mollom servers from the database:
      $servers = $this->servers;

      if ($servers == NULL) {
        // Retrieve a list of valid Mollom servers from mollom.com:
        $servers = $this->xmlrpc('http://xmlrpc.mollom.com/'. MOLLOM_API_VERSION, 'mollom.getServerList', $this->authentication());

        // Store the list of servers in the database:
        // TODO!    variable_set('mollom_servers', $servers);
      }

      if (is_array($servers)) {
        // Send the request to the first server, if that fails, try the other servers in the list:
        foreach ($servers as $server) {
          $auth = $this->authentication();
          $data = array_merge($data, $auth);
          $result = $this->xmlrpc($server .'/'. MOLLOM_API_VERSION, $method, $data);

          // Debug output:
          if (isset($data['session_id'])) {
            common_debug("called $method at server $server with session ID '". $data['session_id'] ."'");
          }
          else {
            common_debug("called $method at server $server with no session ID");
          }

          if ($errno = $this->xmlrpc_errno()) {
            common_log(LOG_ERR, sprintf('Error @errno: %s - %s - %s - <pre>%s</pre>', $this->xmlrpc_errno(), $server, $this->xmlrpc_error_msg(), $method, print_r($data, TRUE)));

            if ($errno == MOLLOM_REFRESH) {
              // Retrieve a list of valid Mollom servers from mollom.com:
              $servers = $this->xmlrpc('http://xmlrpc.mollom.com/'. MOLLOM_API_VERSION, 'mollom.getServerList', $this->authentication());

              // Store the updated list of servers in the database:
              //tODO variable_set('mollom_servers', $servers);
            }
            else if ($errno == MOLLOM_ERROR) {
              return $result;
            }
            else if ($errno == MOLLOM_REDIRECT) {
              // Do nothing, we select the next client automatically.
            }

            // Reset the XMLRPC error:
            $this->xmlrpc_error(0);  // FIXME: this is crazy.
          }
          else {
            common_debug("Result = " . print_r($result, TRUE));
            return $result;
          }
        }
      }

      // If none of the servers worked, activate the fallback mechanism:
      common_debug("none of the servers worked");
      //   _mollom_fallback();

      // If everything failed, we reset the server list to force Mollom to request a new list:
      //TODO variable_set('mollom_servers', array());
    }

    /**
     * This function generate an array with all the information required to
     * authenticate against Mollom. To prevent that requests are forged and
     * that you are impersonated, each request is signed with a hash computed
     * based on a private key and a timestamp.
     *
     * Both the client and the server share the secret key that is used to
     * create the authentication hash based on a timestamp.  They both hash
     * the timestamp with the secret key, and if the hashes match, the
     * authenticity of the message has been validated.
     *
     * To avoid that someone can intercept a (hash, timestamp)-pair and
     * use that to impersonate a client, Mollom will reject the request
     * when the timestamp is more than 15 minutes off.
     *
     * Make sure your server's time is synchronized with the world clocks,
     * and that you don't share your private key with anyone else.
     */
    private function authentication() {

      $public_key = $this->public_key;
      $private_key = $this->private_key;

      // Generate a timestamp according to the dateTime format (http://www.w3.org/TR/xmlschema-2/#dateTime):
      $time = gmdate("Y-m-d\TH:i:s.\\0\\0\\0O", time());

      // Calculate a HMAC-SHA1 according to RFC2104 (http://www.ietf.org/rfc/rfc2104.txt):
      $hash =  base64_encode(
      pack("H*", sha1((str_pad($private_key, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
      pack("H*", sha1((str_pad($private_key, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) .
      $time))))
      );

      // Store everything in an array. Elsewhere in the code, we'll add the
      // acutal data before we pass it onto the XML-RPC library:
      $data['public_key'] = $public_key;
      $data['time'] = $time;
      $data['hash'] = $hash;

      return $data;
    }

    function xmlrpc($url) {
      //require_once './includes/xmlrpc.inc';
      $args = func_get_args();
      return call_user_func_array(array('MollomPlugin', '_xmlrpc'), $args);
    }

    /**
     * Recursively turn a data structure into objects with 'data' and 'type' attributes.
     *
     * @param $data
     *   The data structure.
     * @param  $type
     *   Optional type assign to $data.
     * @return
     *   Object.
     */
    function xmlrpc_value($data, $type = FALSE) {
      $xmlrpc_value = new stdClass();
      $xmlrpc_value->data = $data;
      if (!$type) {
        $type = $this->xmlrpc_value_calculate_type($xmlrpc_value);
      }
      $xmlrpc_value->type = $type;
      if ($type == 'struct') {
        // Turn all the values in the array into new xmlrpc_values
        foreach ($xmlrpc_value->data as $key => $value) {
          $xmlrpc_value->data[$key] = $this->xmlrpc_value($value);
        }
      }
      if ($type == 'array') {
        for ($i = 0, $j = count($xmlrpc_value->data); $i < $j; $i++) {
          $xmlrpc_value->data[$i] = $this->xmlrpc_value($xmlrpc_value->data[$i]);
        }
      }
      return $xmlrpc_value;
    }

    /**
     * Map PHP type to XML-RPC type.
     *
     * @param $xmlrpc_value
     *   Variable whose type should be mapped.
     * @return
     *   XML-RPC type as string.
     * @see
     *   http://www.xmlrpc.com/spec#scalars
     */
    function xmlrpc_value_calculate_type(&$xmlrpc_value) {
      // http://www.php.net/gettype: Never use gettype() to test for a certain type [...] Instead, use the is_* functions.
      if (is_bool($xmlrpc_value->data)) {
        return 'boolean';
      }
      if (is_double($xmlrpc_value->data)) {
        return 'double';
      }
      if (is_int($xmlrpc_value->data)) {
          return 'int';
      }
      if (is_array($xmlrpc_value->data)) {
        // empty or integer-indexed arrays are 'array', string-indexed arrays 'struct'
        return empty($xmlrpc_value->data) || range(0, count($xmlrpc_value->data) - 1) === array_keys($xmlrpc_value->data) ? 'array' : 'struct';
      }
      if (is_object($xmlrpc_value->data)) {
        if ($xmlrpc_value->data->is_date) {
          return 'date';
        }
        if ($xmlrpc_value->data->is_base64) {
          return 'base64';
        }
        $xmlrpc_value->data = get_object_vars($xmlrpc_value->data);
        return 'struct';
      }
      // default
      return 'string';
    }

    /**
     * Generate XML representing the given value.
     *
     * @param $xmlrpc_value
     * @return
     *   XML representation of value.
     */
    function xmlrpc_value_get_xml($xmlrpc_value) {
      switch ($xmlrpc_value->type) {
        case 'boolean':
          return '<boolean>'. (($xmlrpc_value->data) ? '1' : '0') .'</boolean>';
          break;
        case 'int':
          return '<int>'. $xmlrpc_value->data .'</int>';
          break;
        case 'double':
          return '<double>'. $xmlrpc_value->data .'</double>';
          break;
        case 'string':
          // Note: we don't escape apostrophes because of the many blogging clients
          // that don't support numerical entities (and XML in general) properly.
          return '<string>'. htmlspecialchars($xmlrpc_value->data) .'</string>';
          break;
        case 'array':
          $return = '<array><data>'."\n";
          foreach ($xmlrpc_value->data as $item) {
            $return .= '  <value>'. $this->xmlrpc_value_get_xml($item) ."</value>\n";
          }
          $return .= '</data></array>';
          return $return;
          break;
        case 'struct':
          $return = '<struct>'."\n";
          foreach ($xmlrpc_value->data as $name => $value) {
            $return .= "  <member><name>". htmlentities($name) ."</name><value>";
            $return .= $this->xmlrpc_value_get_xml($value) ."</value></member>\n";
          }
          $return .= '</struct>';
          return $return;
          break;
        case 'date':
          return $this->xmlrpc_date_get_xml($xmlrpc_value->data);
          break;
        case 'base64':
          return $this->xmlrpc_base64_get_xml($xmlrpc_value->data);
          break;
      }
      return FALSE;
    }

    /**
     * Perform an HTTP request.
     *
     * This is a flexible and powerful HTTP client implementation. Correctly handles
     * GET, POST, PUT or any other HTTP requests. Handles redirects.
     *
     * @param $url
     *   A string containing a fully qualified URI.
     * @param $headers
     *   An array containing an HTTP header => value pair.
     * @param $method
     *   A string defining the HTTP request to use.
     * @param $data
     *   A string containing data to include in the request.
     * @param $retry
     *   An integer representing how many times to retry the request in case of a
     *   redirect.
     * @return
     *   An object containing the HTTP request headers, response code, headers,
     *   data and redirect status.
     */
    function http_request($url, $headers = array(), $method = 'GET', $data = NULL, $retry = 3) {
      global $db_prefix;

      $result = new stdClass();

      // Parse the URL and make sure we can handle the schema.
      $uri = parse_url($url);

      if ($uri == FALSE) {
        $result->error = 'unable to parse URL';
        return $result;
      }

      if (!isset($uri['scheme'])) {
        $result->error = 'missing schema';
        return $result;
      }

      switch ($uri['scheme']) {
        case 'http':
          $port = isset($uri['port']) ? $uri['port'] : 80;
          $host = $uri['host'] . ($port != 80 ? ':'. $port : '');
          $fp = @fsockopen($uri['host'], $port, $errno, $errstr, 15);
          break;
        case 'https':
          // Note: Only works for PHP 4.3 compiled with OpenSSL.
          $port = isset($uri['port']) ? $uri['port'] : 443;
          $host = $uri['host'] . ($port != 443 ? ':'. $port : '');
          $fp = @fsockopen('ssl://'. $uri['host'], $port, $errno, $errstr, 20);
          break;
        default:
          $result->error = 'invalid schema '. $uri['scheme'];
          return $result;
      }

      // Make sure the socket opened properly.
      if (!$fp) {
        // When a network error occurs, we use a negative number so it does not
        // clash with the HTTP status codes.
        $result->code = -$errno;
        $result->error = trim($errstr);

        // Mark that this request failed. This will trigger a check of the web
        // server's ability to make outgoing HTTP requests the next time that
        // requirements checking is performed.
        // @see system_requirements()
        //TODO variable_set('drupal_http_request_fails', TRUE);

        return $result;
      }

      // Construct the path to act on.
      $path = isset($uri['path']) ? $uri['path'] : '/';
      if (isset($uri['query'])) {
        $path .= '?'. $uri['query'];
      }

      // Create HTTP request.
      $defaults = array(
        // RFC 2616: "non-standard ports MUST, default ports MAY be included".
        // We don't add the port to prevent from breaking rewrite rules checking the
        // host that do not take into account the port number.
        'Host' => "Host: $host",
        'User-Agent' => 'User-Agent: Drupal (+http://drupal.org/)',
        'Content-Length' => 'Content-Length: '. strlen($data)
      );

      // If the server url has a user then attempt to use basic authentication
      if (isset($uri['user'])) {
        $defaults['Authorization'] = 'Authorization: Basic '. base64_encode($uri['user'] . (!empty($uri['pass']) ? ":". $uri['pass'] : ''));
      }

      // If the database prefix is being used by SimpleTest to run the tests in a copied
      // database then set the user-agent header to the database prefix so that any
      // calls to other Drupal pages will run the SimpleTest prefixed database. The
      // user-agent is used to ensure that multiple testing sessions running at the
      // same time won't interfere with each other as they would if the database
      // prefix were stored statically in a file or database variable.
      if (is_string($db_prefix) && preg_match("/^simpletest\d+$/", $db_prefix, $matches)) {
        $defaults['User-Agent'] = 'User-Agent: ' . $matches[0];
      }

      foreach ($headers as $header => $value) {
        $defaults[$header] = $header .': '. $value;
      }

      $request = $method .' '. $path ." HTTP/1.0\r\n";
      $request .= implode("\r\n", $defaults);
      $request .= "\r\n\r\n";
      $request .= $data;

      $result->request = $request;

      fwrite($fp, $request);

      // Fetch response.
      $response = '';
      while (!feof($fp) && $chunk = fread($fp, 1024)) {
        $response .= $chunk;
      }
      fclose($fp);

      // Parse response.
      list($split, $result->data) = explode("\r\n\r\n", $response, 2);
      $split = preg_split("/\r\n|\n|\r/", $split);

      list($protocol, $code, $text) = explode(' ', trim(array_shift($split)), 3);
      $result->headers = array();

      // Parse headers.
      while ($line = trim(array_shift($split))) {
        list($header, $value) = explode(':', $line, 2);
        if (isset($result->headers[$header]) && $header == 'Set-Cookie') {
          // RFC 2109: the Set-Cookie response header comprises the token Set-
          // Cookie:, followed by a comma-separated list of one or more cookies.
          $result->headers[$header] .= ','. trim($value);
        }
        else {
          $result->headers[$header] = trim($value);
        }
      }

      $responses = array(
        100 => 'Continue', 101 => 'Switching Protocols',
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content',
        300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Time-out', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large', 414 => 'Request-URI Too Large', 415 => 'Unsupported Media Type', 416 => 'Requested range not satisfiable', 417 => 'Expectation Failed',
        500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Time-out', 505 => 'HTTP Version not supported'
      );
      // RFC 2616 states that all unknown HTTP codes must be treated the same as the
      // base code in their class.
      if (!isset($responses[$code])) {
        $code = floor($code / 100) * 100;
      }

      switch ($code) {
        case 200: // OK
        case 304: // Not modified
          break;
        case 301: // Moved permanently
        case 302: // Moved temporarily
        case 307: // Moved temporarily
          $location = $result->headers['Location'];

          if ($retry) {
            $result = drupal_http_request($result->headers['Location'], $headers, $method, $data, --$retry);
            $result->redirect_code = $result->code;
          }
          $result->redirect_url = $location;

          break;
        default:
          $result->error = $text;
      }

      $result->code = $code;
      return $result;
    }

    /**
     * Construct an object representing an XML-RPC message.
     *
     * @param $message
     *   String containing XML as defined at http://www.xmlrpc.com/spec
     * @return
     *   Object
     */
    function xmlrpc_message($message) {
      $xmlrpc_message = new stdClass();
      $xmlrpc_message->array_structs = array();   // The stack used to keep track of the current array/struct
      $xmlrpc_message->array_structs_types = array(); // The stack used to keep track of if things are structs or array
      $xmlrpc_message->current_struct_name = array();  // A stack as well
      $xmlrpc_message->message = $message;
      return $xmlrpc_message;
    }

    /**
     * Parse an XML-RPC message. If parsing fails, the faultCode and faultString
     * will be added to the message object.
     *
     * @param $xmlrpc_message
     *   Object generated by xmlrpc_message()
     * @return
     *   TRUE if parsing succeeded; FALSE otherwise
     */
    function xmlrpc_message_parse(&$xmlrpc_message) {
      // First remove the XML declaration
      $xmlrpc_message->message = preg_replace('/<\?xml(.*)?\?'.'>/', '', $xmlrpc_message->message);
      if (trim($xmlrpc_message->message) == '') {
        return FALSE;
      }
      $xmlrpc_message->_parser = xml_parser_create();
      // Set XML parser to take the case of tags into account.
      xml_parser_set_option($xmlrpc_message->_parser, XML_OPTION_CASE_FOLDING, FALSE);
      // Set XML parser callback functions
      xml_set_element_handler($xmlrpc_message->_parser, array('MollomPlugin', 'xmlrpc_message_tag_open'), array('MollomPlugin', 'xmlrpc_message_tag_close'));
      xml_set_character_data_handler($xmlrpc_message->_parser, array('MollomPlugin', 'xmlrpc_message_cdata'));
      $this->xmlrpc_message_set($xmlrpc_message);
      if (!xml_parse($xmlrpc_message->_parser, $xmlrpc_message->message)) {
        return FALSE;
      }
      xml_parser_free($xmlrpc_message->_parser);
      // Grab the error messages, if any
      $xmlrpc_message = $this->xmlrpc_message_get();
      if ($xmlrpc_message->messagetype == 'fault') {
        $xmlrpc_message->fault_code = $xmlrpc_message->params[0]['faultCode'];
        $xmlrpc_message->fault_string = $xmlrpc_message->params[0]['faultString'];
      }
      return TRUE;
    }

    /**
     * Store a copy of the $xmlrpc_message object temporarily.
     *
     * @param $value
     *   Object
     * @return
     *   The most recently stored $xmlrpc_message
     */
    function xmlrpc_message_set($value = NULL) {
      static $xmlrpc_message;
      if ($value) {
        $xmlrpc_message = $value;
      }
      return $xmlrpc_message;
    }

    function xmlrpc_message_get() {
      return $this->xmlrpc_message_set();
    }

    function xmlrpc_message_tag_open($parser, $tag, $attr) {
      $xmlrpc_message = $this->xmlrpc_message_get();
      $xmlrpc_message->current_tag_contents = '';
      $xmlrpc_message->last_open = $tag;
      switch ($tag) {
        case 'methodCall':
        case 'methodResponse':
        case 'fault':
          $xmlrpc_message->messagetype = $tag;
          break;
        // Deal with stacks of arrays and structs
        case 'data':
          $xmlrpc_message->array_structs_types[] = 'array';
          $xmlrpc_message->array_structs[] = array();
          break;
        case 'struct':
          $xmlrpc_message->array_structs_types[] = 'struct';
          $xmlrpc_message->array_structs[] = array();
          break;
      }
      $this->xmlrpc_message_set($xmlrpc_message);
    }

    function xmlrpc_message_cdata($parser, $cdata) {
      $xmlrpc_message = $this->xmlrpc_message_get();
      $xmlrpc_message->current_tag_contents .= $cdata;
      $this->xmlrpc_message_set($xmlrpc_message);
    }

    function xmlrpc_message_tag_close($parser, $tag) {
      $xmlrpc_message = $this->xmlrpc_message_get();
      $value_flag = FALSE;
      switch ($tag) {
        case 'int':
        case 'i4':
          $value = (int)trim($xmlrpc_message->current_tag_contents);
          $value_flag = TRUE;
          break;
        case 'double':
          $value = (double)trim($xmlrpc_message->current_tag_contents);
          $value_flag = TRUE;
          break;
        case 'string':
          $value = $xmlrpc_message->current_tag_contents;
          $value_flag = TRUE;
          break;
        case 'dateTime.iso8601':
          $value = xmlrpc_date(trim($xmlrpc_message->current_tag_contents));
          // $value = $iso->getTimestamp();
          $value_flag = TRUE;
          break;
        case 'value':
          // If no type is indicated, the type is string
          // We take special care for empty values
          if (trim($xmlrpc_message->current_tag_contents) != '' || (isset($xmlrpc_message->last_open) && ($xmlrpc_message->last_open == 'value'))) {
            $value = (string)$xmlrpc_message->current_tag_contents;
            $value_flag = TRUE;
          }
          unset($xmlrpc_message->last_open);
          break;
        case 'boolean':
          $value = (boolean)trim($xmlrpc_message->current_tag_contents);
          $value_flag = TRUE;
          break;
        case 'base64':
          $value = base64_decode(trim($xmlrpc_message->current_tag_contents));
          $value_flag = TRUE;
          break;
        // Deal with stacks of arrays and structs
        case 'data':
        case 'struct':
          $value = array_pop($xmlrpc_message->array_structs );
          array_pop($xmlrpc_message->array_structs_types);
          $value_flag = TRUE;
          break;
        case 'member':
          array_pop($xmlrpc_message->current_struct_name);
          break;
        case 'name':
          $xmlrpc_message->current_struct_name[] = trim($xmlrpc_message->current_tag_contents);
          break;
        case 'methodName':
          $xmlrpc_message->methodname = trim($xmlrpc_message->current_tag_contents);
          break;
      }

      if ($value_flag) {
        if (count($xmlrpc_message->array_structs ) > 0) {
          // Add value to struct or array
          if ($xmlrpc_message->array_structs_types[count($xmlrpc_message->array_structs_types)-1] == 'struct') {
            // Add to struct
            $xmlrpc_message->array_structs [count($xmlrpc_message->array_structs )-1][$xmlrpc_message->current_struct_name[count($xmlrpc_message->current_struct_name)-1]] = $value;
          }
          else {
            // Add to array
            $xmlrpc_message->array_structs [count($xmlrpc_message->array_structs )-1][] = $value;
          }
        }
        else {
          // Just add as a parameter
          $xmlrpc_message->params[] = $value;
        }
      }
      if (!in_array($tag, array("data", "struct", "member"))) {
        $xmlrpc_message->current_tag_contents = '';
      }
      $this->xmlrpc_message_set($xmlrpc_message);
    }

    /**
     * Construct an object representing an XML-RPC request
     *
     * @param $method
     *   The name of the method to be called
     * @param $args
     *   An array of parameters to send with the method.
     * @return
     *   Object
     */
    function xmlrpc_request($method, $args) {
      $xmlrpc_request = new stdClass();
      $xmlrpc_request->method = $method;
      $xmlrpc_request->args = $args;
      $xmlrpc_request->xml = <<<EOD
    <?xml version="1.0"?>
    <methodCall>
    <methodName>{$xmlrpc_request->method}</methodName>
    <params>

EOD;

      foreach ($xmlrpc_request->args as $arg) {
        $xmlrpc_request->xml .= '<param><value>';
        $v = $this->xmlrpc_value($arg);
        $xmlrpc_request->xml .= $this->xmlrpc_value_get_xml($v);
        $xmlrpc_request->xml .= "</value></param>\n";
      }
      $xmlrpc_request->xml .= '</params></methodCall>';
      return $xmlrpc_request;
    }


    function xmlrpc_error($code = NULL, $message = NULL, $reset = FALSE) {
      static $xmlrpc_error;
      if (isset($code)) {
        $xmlrpc_error = new stdClass();
        $xmlrpc_error->is_error = TRUE;
        $xmlrpc_error->code = $code;
        $xmlrpc_error->message = $message;
      }
      elseif ($reset) {
        $xmlrpc_error = NULL;
      }
      return $xmlrpc_error;
    }

    function xmlrpc_error_get_xml($xmlrpc_error) {
      return <<<EOD
    <methodResponse>
      <fault>
      <value>
        <struct>
        <member>
          <name>faultCode</name>
          <value><int>{$xmlrpc_error->code}</int></value>
        </member>
        <member>
          <name>faultString</name>
          <value><string>{$xmlrpc_error->message}</string></value>
        </member>
        </struct>
      </value>
      </fault>
    </methodResponse>

EOD;
    }

    function xmlrpc_date($time) {
      $xmlrpc_date = new stdClass();
      $xmlrpc_date->is_date = TRUE;
      // $time can be a PHP timestamp or an ISO one
      if (is_numeric($time)) {
        $xmlrpc_date->year = gmdate('Y', $time);
        $xmlrpc_date->month = gmdate('m', $time);
        $xmlrpc_date->day = gmdate('d', $time);
        $xmlrpc_date->hour = gmdate('H', $time);
        $xmlrpc_date->minute = gmdate('i', $time);
        $xmlrpc_date->second = gmdate('s', $time);
        $xmlrpc_date->iso8601 = gmdate('Ymd\TH:i:s', $time);
      }
      else {
        $xmlrpc_date->iso8601 = $time;
        $time = str_replace(array('-', ':'), '', $time);
        $xmlrpc_date->year = substr($time, 0, 4);
        $xmlrpc_date->month = substr($time, 4, 2);
        $xmlrpc_date->day = substr($time, 6, 2);
        $xmlrpc_date->hour = substr($time, 9, 2);
        $xmlrpc_date->minute = substr($time, 11, 2);
        $xmlrpc_date->second = substr($time, 13, 2);
      }
      return $xmlrpc_date;
    }

    function xmlrpc_date_get_xml($xmlrpc_date) {
      return '<dateTime.iso8601>'. $xmlrpc_date->year . $xmlrpc_date->month . $xmlrpc_date->day .'T'. $xmlrpc_date->hour .':'. $xmlrpc_date->minute .':'. $xmlrpc_date->second .'</dateTime.iso8601>';
    }

    function xmlrpc_base64($data) {
      $xmlrpc_base64 = new stdClass();
      $xmlrpc_base64->is_base64 = TRUE;
      $xmlrpc_base64->data = $data;
      return $xmlrpc_base64;
    }

    function xmlrpc_base64_get_xml($xmlrpc_base64) {
      return '<base64>'. base64_encode($xmlrpc_base64->data) .'</base64>';
    }

    /**
    * Execute an XML remote procedural call. This is private function; call xmlrpc()
    * in common.inc instead of this function.
    *
    * @return
    *   A $xmlrpc_message object if the call succeeded; FALSE if the call failed
    */
    function _xmlrpc() {
      $args = func_get_args();
      $url = array_shift($args);
      $this->xmlrpc_clear_error();
      if (is_array($args[0])) {
        $method = 'system.multicall';
        $multicall_args = array();
        foreach ($args[0] as $call) {
          $multicall_args[] = array('methodName' => array_shift($call), 'params' => $call);
        }
        $args = array($multicall_args);
      }
      else {
        $method = array_shift($args);
      }
      $xmlrpc_request = $this->xmlrpc_request($method, $args);
      $result = $this->http_request($url, array("Content-Type" => "text/xml"), 'POST', $xmlrpc_request->xml);
      if ($result->code != 200) {
        $this->xmlrpc_error($result->code, $result->error);
        return FALSE;
      }
      $message = $this->xmlrpc_message($result->data);
      // Now parse what we've got back
      if (!$this->xmlrpc_message_parse($message)) {
        // XML error
        $this->xmlrpc_error(-32700, t('Parse error. Not well formed'));
        return FALSE;
      }
      // Is the message a fault?
      if ($message->messagetype == 'fault') {
        $this->xmlrpc_error($message->fault_code, $message->fault_string);
        return FALSE;
      }
      // Message must be OK
      return $message->params[0];
    }

    /**
     * Returns the last XML-RPC client error number
     */
    function xmlrpc_errno() {
      $error = $this->xmlrpc_error();
      return ($error != NULL ? $error->code : NULL);
    }

    /**
     * Returns the last XML-RPC client error message
     */
    function xmlrpc_error_msg() {
      $error = xmlrpc_error();
      return ($error != NULL ? $error->message : NULL);
    }

    /**
     * Clears any previous error.
     */
    function xmlrpc_clear_error() {
      $this->xmlrpc_error(NULL, NULL, TRUE);
    }
}
