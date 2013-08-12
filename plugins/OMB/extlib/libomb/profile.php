<?php
/**
 * This file is part of libomb
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

require_once 'invalidparameterexception.php';
require_once 'Validate.php';
require_once 'helper.php';

/**
 * OMB profile representation
 *
 * This class represents an OMB profile.
 *
 * Do not call the setters with null values. Instead, if you want to delete a
 * field, pass an empty string. The getters will return null for empty fields.
 */
class OMB_Profile
{
    protected $identifier_uri;
    protected $profile_url;
    protected $nickname;
    protected $license_url;
    protected $fullname;
    protected $homepage;
    protected $bio;
    protected $location;
    protected $avatar_url;

    /* The profile as OMB param array. Cached and rebuild on usage.
       false while outdated. */
    protected $param_array;

    /**
     * Constructor for OMB_Profile
     *
     * Initializes the OMB_Profile object with an identifier uri.
     *
     * @param string $identifier_uri The profile URI as defined by the OMB;
     *                               A unique and never changing identifier for
     *                               a profile
     *
     * @access public
     */
    public function __construct($identifier_uri)
    {
        if (!Validate::uri($identifier_uri)) {
            throw new OMB_InvalidParameterException($identifier_uri, 'profile',
                                                'omb_listenee or omb_listener');
        }
        $this->identifier_uri = $identifier_uri;
        $this->param_array    = false;
    }

    /**
     * Return the profile as array
     *
     * Returns an array which contains the whole profile as array.
     * The array is cached and only rebuilt on changes of the profile.
     *
     * @param string $prefix    The common prefix to the key for all parameters
     * @param bool   $force_all Specifies whether empty fields should be added
     *                          to the array as well; This is necessary to
     *                          clear fields via updateProfile
     *
     * @access public
     *
     * @return array The profile as parameter array
     */
    public function asParameters($prefix, $force_all = false)
    {
        if ($this->param_array === false) {
            $this->param_array = array('' => $this->identifier_uri);

            if ($force_all || !is_null($this->profile_url)) {
                $this->param_array['_profile'] = $this->profile_url;
            }

            if ($force_all || !is_null($this->homepage)) {
                $this->param_array['_homepage'] = $this->homepage;
            }

            if ($force_all || !is_null($this->nickname)) {
                $this->param_array['_nickname'] = $this->nickname;
            }

            if ($force_all || !is_null($this->license_url)) {
                $this->param_array['_license'] = $this->license_url;
            }

            if ($force_all || !is_null($this->fullname)) {
                $this->param_array['_fullname'] = $this->fullname;
            }

            if ($force_all || !is_null($this->bio)) {
                $this->param_array['_bio'] = $this->bio;
            }

            if ($force_all || !is_null($this->location)) {
                $this->param_array['_location'] = $this->location;
            }

            if ($force_all || !is_null($this->avatar_url)) {
                $this->param_array['_avatar'] = $this->avatar_url;
            }

        }
        $ret = array();
        foreach ($this->param_array as $k => $v) {
            $ret[$prefix . $k] = $v;
        }
        return $ret;
    }

    /**
     * Build an OMB_Profile object from array
     *
     * Builds an OMB_Profile object from the passed parameters array. The
     * array MUST provide a profile URI. The array fields HAVE TO be named
     * according to the OMB standard. The prefix (omb_listener or omb_listenee)
     * is passed as a parameter.
     *
     * @param string $parameters An array containing the profile parameters
     * @param string $prefix     The common prefix of the profile parameter keys
     *
     * @access public
     *
     * @returns OMB_Profile The built OMB_Profile
     */
    public static function fromParameters($parameters, $prefix)
    {
        if (!isset($parameters[$prefix])) {
            throw new OMB_InvalidParameterException('', 'profile', $prefix);
        }

        $profile = new OMB_Profile($parameters[$prefix]);
        $profile->updateFromParameters($parameters, $prefix);
        return $profile;
    }

    /**
     * Update from array
     *
     * Updates from the passed parameters array. The array does not have to
     * provide a profile URI. The array fields HAVE TO be named according to the
     * OMB standard. The prefix (omb_listener or omb_listenee) is passed as a
     * parameter.
     *
     * @param string $parameters An array containing the profile parameters
     * @param string $prefix     The common prefix of the profile parameter keys
     *
     * @access public
     */
    public function updateFromParameters($parameters, $prefix)
    {
        if (isset($parameters[$prefix.'_profile'])) {
            $this->setProfileURL($parameters[$prefix.'_profile']);
        }

        if (isset($parameters[$prefix.'_license'])) {
            $this->setLicenseURL($parameters[$prefix.'_license']);
        }

        if (isset($parameters[$prefix.'_nickname'])) {
            $this->setNickname($parameters[$prefix.'_nickname']);
        }

        if (isset($parameters[$prefix.'_fullname'])) {
            $this->setFullname($parameters[$prefix.'_fullname']);
        }

        if (isset($parameters[$prefix.'_homepage'])) {
            $this->setHomepage($parameters[$prefix.'_homepage']);
        }

        if (isset($parameters[$prefix.'_bio'])) {
            $this->setBio($parameters[$prefix.'_bio']);
        }

        if (isset($parameters[$prefix.'_location'])) {
            $this->setLocation($parameters[$prefix.'_location']);
        }

        if (isset($parameters[$prefix.'_avatar'])) {
            $this->setAvatarURL($parameters[$prefix.'_avatar']);
        }
    }

    public function getIdentifierURI()
    {
        return $this->identifier_uri;
    }

    public function getProfileURL()
    {
        return $this->profile_url;
    }

    public function getHomepage()
    {
        return $this->homepage;
    }

    public function getNickname()
    {
        return $this->nickname;
    }

    public function getLicenseURL()
    {
        return $this->license_url;
    }

    public function getFullname()
    {
        return $this->fullname;
    }

    public function getBio()
    {
        return $this->bio;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function getAvatarURL()
    {
        return $this->avatar_url;
    }

    public function setProfileURL($profile_url)
    {
        $this->setVal('profile', $profile_url, 'OMB_Helper::validateURL',
                      'profile_url');
    }

    public function setNickname($nickname)
    {
        $this->setVal('nickname', $nickname, 'OMB_Profile::validateNickname',
                      'nickname', true);
    }

    public function setLicenseURL($license_url)
    {
        $this->setVal('license', $license_url, 'OMB_Helper::validateURL',
                      'license_url');
    }

    public function setFullname($fullname)
    {
        $this->setVal('fullname', $fullname, 'OMB_Profile::validate255');
    }

    public function setHomepage($homepage)
    {
        $this->setVal('homepage', $homepage, 'OMB_Helper::validateURL');
    }

    public function setBio($bio)
    {
        $this->setVal('bio', $bio, 'OMB_Profile::validate140');
    }

    public function setLocation($location)
    {
        $this->setVal('location', $location, 'OMB_Profile::validate255');
    }

    public function setAvatarURL($avatar_url)
    {
        $this->setVal('avatar', $avatar_url, 'OMB_Helper::validateURL',
                      'avatar_url');
    }

    protected static function validate255($str)
    {
        return Validate::string($str, array('max_length' => 255));
    }

    protected static function validate140($str)
    {
        return Validate::string($str, array('max_length' => 140));
    }

    protected static function validateNickname($str)
    {
        return Validate::string($str,
                              array('min_length' => 1,
                                    'max_length' => 64,
                                    'format' => VALIDATE_NUM . VALIDATE_ALPHA));
    }

    /**
     * Set a value
     *
     * Updates a value specified by a parameter name and the new value.
     *
     * @param string   $param     The parameter name according to OMB
     * @param string   $value     The new value
     * @param callback $validator A validator function for the parameter
     * @param string   $field     The name of the field in OMB_Profile
     * @param bool     $force     Whether null values should be checked as well
     */
    protected function setVal($param, $value, $validator, $field = null,
                              $force = false)
    {
        if (is_null($field)) {
            $field = $param;
        }
        if ($value === '' && !$force) {
            $value = null;
        } elseif (!call_user_func($validator, $value)) {
            throw new OMB_InvalidParameterException($value, 'profile', $param);
        }
        if ($this->$field !== $value) {
            $this->$field      = $value;
            $this->param_array = false;
        }
    }
}
?>
