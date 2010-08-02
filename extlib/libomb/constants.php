<?php
/**
 * Constants for libomb
 *
 * This file contains constant definitions for libomb. The defined constants
 * are service and namespace URIs for OAuth and OMB as used in XRDS.
 *
 * PHP version 5
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
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
 * @package OMB
 * @author  Adrian Lang <mail@adrianlang.de>
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL 3.0
 * @version 0.1a-20090828
 * @link    http://adrianlang.de/libomb
 */

/**
 * The OMB constants.
 */

define('OMB_VERSION_01', 'http://openmicroblogging.org/protocol/0.1');

/* The OMB version supported by this libomb version. */
define('OMB_VERSION', OMB_VERSION_01);

define('OMB_ENDPOINT_UPDATEPROFILE', OMB_VERSION . '/updateProfile');
define('OMB_ENDPOINT_POSTNOTICE', OMB_VERSION . '/postNotice');

/**
 * The OAuth constants.
 */

define('OAUTH_NAMESPACE', 'http://oauth.net/core/1.0/');

define('OAUTH_ENDPOINT_REQUEST', OAUTH_NAMESPACE.'endpoint/request');
define('OAUTH_ENDPOINT_AUTHORIZE', OAUTH_NAMESPACE.'endpoint/authorize');
define('OAUTH_ENDPOINT_ACCESS', OAUTH_NAMESPACE.'endpoint/access');
define('OAUTH_ENDPOINT_RESOURCE', OAUTH_NAMESPACE.'endpoint/resource');

define('OAUTH_AUTH_HEADER', OAUTH_NAMESPACE.'parameters/auth-header');
define('OAUTH_POST_BODY', OAUTH_NAMESPACE.'parameters/post-body');

define('OAUTH_HMAC_SHA1', OAUTH_NAMESPACE.'signature/HMAC-SHA1');

define('OAUTH_DISCOVERY', 'http://oauth.net/discovery/1.0');
?>
