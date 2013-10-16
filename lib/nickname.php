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
     * This, DISPLAY_FMT, and CANONICAL_FMT replace the old NICKNAME_FMT,
     * but be aware that these should not be enclosed in []s.
     *
     * @fixme would prefer to define in reference to the other constants
     */
    const INPUT_FMT = '(?:[0-9]+|[0-9a-zA-Z_]{1,64})';

    /**
     * Regex fragment for acceptable user-formatted variant of a nickname.
     * This includes some chars such as underscore which will be removed
     * from the normalized canonical form, but still must fit within
     * field length limits.
     *
     * Not guaranteed to be valid after normalization; run the string through
     * Nickname::normalize() to get the canonical form, or Nickname::isValid()
     * if you just need to check if it's properly formatted.
     *
     * This and CANONICAL_FMT replace the old NICKNAME_FMT, but be aware
     * that these should not be enclosed in []s.
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
     * This and DISPLAY_FMT replace the old NICKNAME_FMT, but be aware
     * that these should not be enclosed in []s.
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
     * @param string $str
     * @return boolean
     */
    public static function isValid($str)
    {
        try {
            self::normalize($str);
            return true;
        } catch (NicknameException $e) {
            return false;
        }
    }

    /**
     * Validate an input nickname string, and normalize it to its canonical form.
     * The canonical form will be returned, or an exception thrown if invalid.
     *
     * @param string $str
     * @return string Normalized canonical form of $str
     *
     * @throws NicknameException (base class)
     * @throws   NicknameInvalidException
     * @throws   NicknameEmptyException
     * @throws   NicknameTooLongException
     */
    public static function normalize($str)
    {
        if (mb_strlen($str) > self::MAX_LEN) {
            // Display forms must also fit!
            throw new NicknameTooLongException();
        }

        $str = trim($str);
        $str = str_replace('_', '', $str);
        $str = mb_strtolower($str);

        if (mb_strlen($str) < 1) {
            throw new NicknameEmptyException();
        }
        if (!self::isCanonical($str)) {
            throw new NicknameInvalidException();
        }
        if (!User::allowed_nickname($str)) {
            throw new NicknameBlacklistException();
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

class NicknameEmptyException extends NicknameException
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

class NicknameBlacklistException extends NicknameInvalidException
{
    protected function defaultMessage()
    {
        // TRANS: Validation error in form for registration, profile and group settings, etc.
        return _('Nickname is disallowed through blacklist or system path list.');
    }
}
