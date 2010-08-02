<?php
/*
 *  Wordpress Teams plugin
 *  Copyright (C) 2009-2010 Canonical Ltd.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Provides an example OpenID extension to query user team/group membership
 *
 * This code is based on code supplied with the openid library for simple
 * registration data.
 */

/**
 * Require the Message implementation.
 */
require_once 'Auth/OpenID/Message.php';
require_once 'Auth/OpenID/Extension.php';

/**
 * The team/group extension base class
 */
class Auth_OpenID_TeamsExtension extends Auth_OpenID_Extension {
  var $ns_uri = 'http://ns.launchpad.net/2007/openid-teams';
  var $ns_alias = 'lp';
  var $request_field = 'query_membership';
  var $response_field = 'is_member';
  
  /**
   * Get the string arguments that should be added to an OpenID
   * message for this extension.
   */
  function getExtensionArgs() {
    $args = array();

    if ($this->_teams) {
      $args[$this->request_field] = implode(',', $this->_teams);
    }

    return $args;
  }

  /**
   * Add the arguments from this extension to the provided message.
   *
   * Returns the message with the extension arguments added.
   */
  function toMessage(&$message) {
    if ($message->namespaces->addAlias($this->ns_uri, $this->ns_alias) === null) {
      if ($message->namespaces->getAlias($this->ns_uri) != $this->ns_alias) {
        return null;
      }
    }

    $message->updateArgs($this->ns_uri, $this->getExtensionArgs());
    return $message;
  }
  
  /**
   * Extract the team/group namespace URI from the given OpenID message.
   * Handles OpenID 1 and 2.
   *
   * $message: The OpenID message from which to parse team/group data.
   * This may be a request or response message.
   *
   * Returns the sreg namespace URI for the supplied message.
   *
   * @access private
   */
  function _getExtensionNS(&$message) {
    $alias = null;
    $found_ns_uri = null;

    // See if there exists an alias for the namespace
    $alias = $message->namespaces->getAlias($this->ns_uri);
    
    if ($alias !== null) {
      $found_ns_uri = $this->ns_uri;
    }

    if ($alias === null) {
      // There is no alias for this extension, so try to add one.
      $found_ns_uri = Auth_OpenID_TYPE_1_0;
      
      if ($message->namespaces->addAlias($this->ns_uri, $this->ns_alias) === null) {
        // An alias for the string 'lp' already exists, but
        // it's defined for something other than team/group membership
        return null;
      }
    }
    
    return $found_ns_uri;
  }
}

/**
 * The team/group extension request class
 */
class Auth_OpenID_TeamsRequest extends Auth_OpenID_TeamsExtension {
  function __init($teams) {
    if (!is_array($teams)) {
      if (!empty($teams)) {
        $teams = explode(',', $teams);
      } else {
        $teams = Array();
      }
    }
    
    $this->_teams = $teams;
  }
  
  function Auth_OpenID_TeamsRequest($teams) {
    $this->__init($teams);
  }
}

/**
 * The team/group extension response class
 */
class Auth_OpenID_TeamsResponse extends Auth_OpenID_TeamsExtension {
  var $_teams = array();
  
  function __init(&$resp, $signed_only=true) {
    $this->ns_uri = $this->_getExtensionNS($resp->message);
    
    if ($signed_only) {
      $args = $resp->getSignedNS($this->ns_uri);
    } else {
      $args = $resp->message->getArgs($this->ns_uri);
    }
    
    if ($args === null) {
      return null;
    }
    
    // An OpenID 2.0 response will handle the namespaces
    if (in_array($this->response_field, array_keys($args)) && !empty($args[$this->response_field])) {
      $this->_teams = explode(',', $args[$this->response_field]);
    }
    
    // Piggybacking on a 1.x request, however, won't so the field name will
    // be different
    elseif (in_array($this->ns_alias.'.'.$this->response_field, array_keys($args)) && !empty($args[$this->ns_alias.'.'.$this->response_field])) {
      $this->_teams = explode(',', $args[$this->ns_alias.'.'.$this->response_field]);
    }
  }
  
  function Auth_OpenID_TeamsResponse(&$resp, $signed_only=true) {
    $this->__init($resp, $signed_only);
  }
  
  /**
   * Get the array of teams the user is a member of
   *
   * @return array
   */
  function getTeams() {
    return $this->_teams;
  }
}

?>
