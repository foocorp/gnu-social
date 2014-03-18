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

class Nickname
{
    /**
     * Regex fragment for pulling a formated nickname *OR* ID number.
     * Suitable for router def of 'id' parameters on API actions.
     *
     * Not guaranteed to be valid after normalization; run the string through
     * Nickname::normalize() to get the canonical form, or Nickname::isValid()
     * if you just need to check if it's properly formatted.
     *
     * This, DISPLAY_FMT, and CANONICAL_FMT should not be enclosed in []s.
     *
     * @fixme would prefer to define in reference to the other constants
     */
    const INPUT_FMT = '(?:[0-9]+|[0-9a-zA-Z_]{1,64})';

    /**
     * Regex fragment for acceptable user-formatted variant of a nickname.
     *
     * This includes some chars such as underscore which will be removed
     * from the normalized canonical form, but still must fit within
     * field length limits.
     *
     * Not guaranteed to be valid after normalization; run the string through
     * Nickname::normalize() to get the canonical form, or Nickname::isValid()
     * if you just need to check if it's properly formatted.
     *
     * This, INPUT_FMT and CANONICAL_FMT should not be enclosed in []s.
     */
    const DISPLAY_FMT = '[0-9a-zA-Z_]{1,64}';

    /**
     * Regex fragment for checking a canonical nickname.
     *
     * Any non-matching string is not a valid canonical/normalized nickname.
     * Matching strings are valid and canonical form, but may still be
     * unavailable for registration due to blacklisting et.
     *
     * Only the canonical forms should be stored as keys in the database;
     * there are multiple possible denormalized forms for each valid
     * canonical-form name.
     *
     * This, INPUT_FMT and DISPLAY_FMT should not be enclosed in []s.
     */
    const CANONICAL_FMT = '[0-9a-z]{1,64}';

    /**
     * Maximum number of characters in a canonical-form nickname.
     */
    const MAX_LEN = 64;

    /**
     * Nice simple check of whether the given string is a valid input nickname,
     * which can be normalized into an internally canonical form.
     *
     * Note that valid nicknames may be in use or reserved.
     *
     * @param string    $str       The nickname string to test
     * @param boolean   $checkuse  Check if it's in use (return false if it is)
     *
     * @return boolean  True if nickname is valid. False if invalid (or taken if checkuse==true).
     */
    public static function isValid($str, $checkuse=false)
    {
        try {
            self::normalize($str, $checkuse);
        } catch (NicknameException $e) {
            return false;
        }

        return true;
    }

    /**
     * Validate an input nickname string, and normalize it to its canonical form.
     * The canonical form will be returned, or an exception thrown if invalid.
     *
     * @param string    $str       The nickname string to test
     * @param boolean   $checkuse  Check if it's in use (return false if it is)
     * @return string Normalized canonical form of $str
     *
     * @throws NicknameException (base class)
     * @throws   NicknameBlacklistedException
     * @throws   NicknameEmptyException
     * @throws   NicknameInvalidException
     * @throws   NicknamePathCollisionException
     * @throws   NicknameTakenException
     * @throws   NicknameTooLongException
     */
    public static function normalize($str, $checkuse=false)
    {
        // We should also have UTF-8 normalization (å to a etc.)
        $str = trim($str);
        $str = str_replace('_', '', $str);
        $str = mb_strtolower($str);

        if (mb_strlen($str) > self::MAX_LEN) {
            // Display forms must also fit!
            throw new NicknameTooLongException();
        } elseif (mb_strlen($str) < 1) {
            throw new NicknameEmptyException();
        } elseif (!self::isCanonical($str)) {
            throw new NicknameInvalidException();
        } elseif (self::isBlacklisted($str)) {
            throw new NicknameBlacklistedException();
        } elseif (self::isSystemPath($str)) {
            throw new NicknamePathCollisionException();
        } elseif ($checkuse) {
            $profile = self::isTaken($str);
            if ($profile instanceof Profile) {
                throw new NicknameTakenException($profile);
            }
        }

        return $str;
    }

    /**
     * Is the given string a valid canonical nickname form?
     *
     * @param string $str
     * @return boolean
     */
    public static function isCanonical($str)
    {
        return preg_match('/^(?:' . self::CANONICAL_FMT . ')$/', $str);
    }

    /**
     * Is the given string in our nickname blacklist?
     *
     * @param string $str
     * @return boolean
     */
     public static function isBlacklisted($str)
     {
         $blacklist = common_config('nickname', 'blacklist');
         return in_array($str, $blacklist);
     }

    /**
     * Is the given string identical to a system path or route?
     * This could probably be put in some other class, but at
     * at the moment, only Nickname requires this functionality.
     *
     * @param string $str
     * @return boolean
     */
     public static function isSystemPath($str)
     {
        $paths = array();

        // All directory and file names in site root should be blacklisted
        $d = dir(INSTALLDIR);
        while (false !== ($entry = $d->read())) {
            $paths[] = $entry;
        }
        $d->close();

        // All top level names in the router should be blacklisted
        $router = Router::get();
        foreach (array_keys($router->m->getPaths()) as $path) {
            if (preg_match('/^\/(.*?)[\/\?]/',$path,$matches)) {
                $paths[] = $matches[1];
            }
        }
        return in_array($str, $paths);
    }

    /**
     * Is the nickname already in use locally? Checks the User table.
     *
     * @param   string $str
     * @return  Profile|null   Returns Profile if nickname found, otherwise null
     */
    public static function isTaken($str)
    {
        $found = User::getKV('nickname', $str);
        if ($found instanceof User) {
            return $found->getProfile();
        }

        $found = Local_group::getKV('nickname', $str);
        if ($found instanceof Local_group) {
            return $found->getProfile();
        }

        $found = Group_alias::getKV('alias', $str);
        if ($found instanceof Group_alias) {
            return $found->getProfile();
        }

        return null;
    }
}

class NicknameException extends ClientException
{
    function __construct($msg=null, $code=400)
    {
        if ($msg === null) {
            $msg = $this->defaultMessage();
        }
        parent::__construct($msg, $code);
    }

    /**
     * Default localized message for this type of exception.
     * @return string
     */
    protected function defaultMessage()
    {
        return null;
    }
}

class NicknameInvalidException extends NicknameException {
    /**
     * Default localized message for this type of exception.
     * @return string
     */
    protected function defaultMessage()
    {
        // TRANS: Validation error in form for registration, profile and group settings, etc.
        return _('Nickname must have only lowercase letters and numbers and no spaces.');
    }
}

class NicknameEmptyException extends NicknameInvalidException
{
    /**
     * Default localized message for this type of exception.
     * @return string
     */
    protected function defaultMessage()
    {
        // TRANS: Validation error in form for registration, profile and group settings, etc.
        return _('Nickname cannot be empty.');
    }
}

class NicknameTooLongException extends NicknameInvalidException
{
    /**
     * Default localized message for this type of exception.
     * @return string
     */
    protected function defaultMessage()
    {
        // TRANS: Validation error in form for registration, profile and group settings, etc.
        return sprintf(_m('Nickname cannot be more than %d character long.',
                          'Nickname cannot be more than %d characters long.',
                          Nickname::MAX_LEN),
                       Nickname::MAX_LEN);
    }
}

class NicknameBlacklistedException extends NicknameException
{
    protected function defaultMessage()
    {
        // TRANS: Validation error in form for registration, profile and group settings, etc.
        return _('Nickname is disallowed through blacklist.');
    }
}

class NicknamePathCollisionException extends NicknameException
{
    protected function defaultMessage()
    {
        // TRANS: Validation error in form for registration, profile and group settings, etc.
        return _('Nickname is identical to system path names.');
    }
}

class NicknameTakenException extends NicknameException
{
    public $profile = null;    // the Profile which occupies the nickname

    public function __construct(Profile $profile, $msg=null, $code=400)
    {
        $this->profile = $profile;

        if ($msg === null) {
            $msg = $this->defaultMessage();
        }

        parent::__construct($msg, $code);
    }

    protected function defaultMessage()
    {
        // TRANS: Validation error in form for registration, profile and group settings, etc.
        return _('Nickname is already in use on this server.');
    }
}
