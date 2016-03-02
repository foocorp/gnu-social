<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Class for configuration exceptions
 *
 * Subclass of ServerException for when the site's configuration is malformed.
 *
 * @category Exception
 * @package  GNUsocial
 * @author   Mikael Nordfeldth <mmn@hethane.se>
 * @license  https://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     https://gnu.io/social
 */

class ConfigException extends ServerException
{
    public function __construct($message=null) {
        parent::__construct($message, 500);
    }
}
