<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * utility functions for i18n
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
 * @category I18n
 * @package  StatusNet
 * @author   Matthew Gregg <matthew.gregg@gmail.com>
 * @author   Ciaran Gultnieks <ciaran@ciarang.com>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

// Locale category constants are usually predefined, but may not be
// on some systems such as Win32.
$LC_CATEGORIES = array('LC_CTYPE',
                       'LC_NUMERIC',
                       'LC_TIME',
                       'LC_COLLATE',
                       'LC_MONETARY',
                       'LC_MESSAGES',
                       'LC_ALL');
foreach ($LC_CATEGORIES as $key => $name) {
    if (!defined($name)) {
        define($name, $key);
    }
}

if (!function_exists('gettext')) {
    require_once("php-gettext/gettext.inc");
}


if (!function_exists('dpgettext')) {
    /**
     * Context-aware dgettext wrapper; use when messages in different contexts
     * won't be distinguished from the English source but need different translations.
     * The context string will appear as msgctxt in the .po files.
     *
     * Not currently exposed in PHP's gettext module; implemented to be compat
     * with gettext.h's macros.
     *
     * @param string $domain domain identifier
     * @param string $context context identifier, should be some key like "menu|file"
     * @param string $msgid English source text
     * @return string original or translated message
     */
    function dpgettext($domain, $context, $msg)
    {
        $msgid = $context . "\004" . $msg;
        $out = dcgettext($domain, $msgid, LC_MESSAGES);
        if ($out == $msgid) {
            return $msg;
        } else {
            return $out;
        }
    }
}

if (!function_exists('pgettext')) {
    /**
     * Context-aware gettext wrapper; use when messages in different contexts
     * won't be distinguished from the English source but need different translations.
     * The context string will appear as msgctxt in the .po files.
     *
     * Not currently exposed in PHP's gettext module; implemented to be compat
     * with gettext.h's macros.
     *
     * @param string $context context identifier, should be some key like "menu|file"
     * @param string $msgid English source text
     * @return string original or translated message
     */
    function pgettext($context, $msg)
    {
        return dpgettext(textdomain(NULL), $context, $msg);
    }
}

if (!function_exists('dnpgettext')) {
    /**
     * Context-aware dngettext wrapper; use when messages in different contexts
     * won't be distinguished from the English source but need different translations.
     * The context string will appear as msgctxt in the .po files.
     *
     * Not currently exposed in PHP's gettext module; implemented to be compat
     * with gettext.h's macros.
     *
     * @param string $domain domain identifier
     * @param string $context context identifier, should be some key like "menu|file"
     * @param string $msg singular English source text
     * @param string $plural plural English source text
     * @param int $n number of items to control plural selection
     * @return string original or translated message
     */
    function dnpgettext($domain, $context, $msg, $plural, $n)
    {
        $msgid = $context . "\004" . $msg;
        $out = dcngettext($domain, $msgid, $plural, $n, LC_MESSAGES);
        if ($out == $msgid) {
            return $msg;
        } else {
            return $out;
        }
    }
}

if (!function_exists('npgettext')) {
    /**
     * Context-aware ngettext wrapper; use when messages in different contexts
     * won't be distinguished from the English source but need different translations.
     * The context string will appear as msgctxt in the .po files.
     *
     * Not currently exposed in PHP's gettext module; implemented to be compat
     * with gettext.h's macros.
     *
     * @param string $context context identifier, should be some key like "menu|file"
     * @param string $msg singular English source text
     * @param string $plural plural English source text
     * @param int $n number of items to control plural selection
     * @return string original or translated message
     */
    function npgettext($context, $msg, $plural, $n)
    {
        return dnpgettext(textdomain(NULL), $msgid, $plural, $n, LC_MESSAGES);
    }
}

/**
 * Shortcut for *gettext functions with smart domain detection.
 *
 * If calling from a plugin, this function checks which plugin was
 * being called from and uses that as text domain, which will have
 * been set up during plugin initialization.
 *
 * Also handles plurals and contexts depending on what parameters
 * are passed to it:
 *
 *   gettext -> _m($msg)
 *  ngettext -> _m($msg1, $msg2, $n)
 *  pgettext -> _m($ctx, $msg)
 * npgettext -> _m($ctx, $msg1, $msg2, $n)
 *
 * @fixme may not work properly in eval'd code
 *
 * @param string $msg
 * @return string
 */
function _m($msg/*, ...*/)
{
    $domain = _mdomain(debug_backtrace());
    $args = func_get_args();
    switch(count($args)) {
    case 1: return dgettext($domain, $msg);
    case 2: return dpgettext($domain, $args[0], $args[1]);
    case 3: return dngettext($domain, $args[0], $args[1], $args[2]);
    case 4: return dnpgettext($domain, $args[0], $args[1], $args[2], $args[3]);
    default: throw new Exception("Bad parameter count to _m()");
    }
}

/**
 * Looks for which plugin we've been called from to set the gettext domain;
 * if not in a plugin subdirectory, we'll use the default 'statusnet'.
 *
 * Note: we can't return null for default domain since most of the PHP gettext
 * wrapper functions turn null into "" before passing to the backend library.
 *
 * @param array $backtrace debug_backtrace() output
 * @return string
 * @private
 * @fixme could explode if SN is under a 'plugins' folder or share name.
 */
function _mdomain($backtrace)
{
    /*
      0 =>
        array
          'file' => string '/var/www/mublog/plugins/FeedSub/FeedSubPlugin.php' (length=49)
          'line' => int 77
          'function' => string '_m' (length=2)
          'args' =>
            array
              0 => &string 'Feeds' (length=5)
    */
    static $cached;
    $path = $backtrace[0]['file'];
    if (!isset($cached[$path])) {
        $final = 'statusnet'; // assume default domain
        if (DIRECTORY_SEPARATOR !== '/') {
            $path = strtr($path, DIRECTORY_SEPARATOR, '/');
        }
        $plug = strpos($path, '/plugins/');
        if ($plug === false) {
            // We're not in a plugin; return default domain.
            $final = 'statusnet';
        } else {
            $cut = $plug + 9;
            $cut2 = strpos($path, '/', $cut);
            if ($cut2) {
                $final = substr($path, $cut, $cut2 - $cut);
            } else {
                // We might be running directly from the plugins dir?
                // If so, there's no place to store locale info.
                $final = 'statusnet';
            }
        }
        $cached[$path] = $final;
    }
    return $cached[$path];
}


/**
 * Content negotiation for language codes
 *
 * @param string $httplang HTTP Accept-Language header
 *
 * @return string language code for best language match
 */

function client_prefered_language($httplang)
{
    $client_langs = array();

    $all_languages = common_config('site', 'languages');

    preg_match_all('"(((\S\S)-?(\S\S)?)(;q=([0-9.]+))?)\s*(,\s*|$)"',
                   strtolower($httplang), $httplang);

    for ($i = 0; $i < count($httplang); $i++) {
        if (!empty($httplang[2][$i])) {
            // if no q default to 1.0
            $client_langs[$httplang[2][$i]] =
              ($httplang[6][$i]? (float) $httplang[6][$i] : 1.0 - ($i*0.01));
        }
        if (!empty($httplang[3][$i]) && empty($client_langs[$httplang[3][$i]])) {
            // if a catchall default 0.01 lower
            $client_langs[$httplang[3][$i]] =
              ($httplang[6][$i]? (float) $httplang[6][$i]-0.01 : 0.99);
        }
    }
    // sort in decending q
    arsort($client_langs);

    foreach ($client_langs as $lang => $q) {
        if (isset($all_languages[$lang])) {
            return($all_languages[$lang]['lang']);
        }
    }
    return false;
}

/**
 * returns a simple code -> name mapping for languages
 *
 * @return array map of available languages by code to language name.
 */

function get_nice_language_list()
{
    $nice_lang = array();

    $all_languages = common_config('site', 'languages');

    foreach ($all_languages as $lang) {
        $nice_lang = $nice_lang + array($lang['lang'] => $lang['name']);
    }
    return $nice_lang;
}

/*
 * Check whether a language is right-to-left
 *
 * @param string $lang language code of the language to check
 *
 * @return boolean true if language is rtl
 */

function is_rtl($lang)
{
    $all_languages = common_config('site', 'languages');
    $lang = $all_languages[$lang];
    return ($lang['direction'] == 'rtl');
}

/**
 * Get a list of all languages that are enabled in the default config
 *
 * This should ONLY be called when setting up the default config in common.php.
 * Any other attempt to get a list of languages should instead call
 * common_config('site','languages')
 *
 * @return array mapping of language codes to language info
 */
function get_all_languages() {
    return array(
        'af'      => array('q' => 0.8, 'lang' => 'af', 'name' => 'Afrikaans', 'direction' => 'ltr'),
        'ar'      => array('q' => 0.8, 'lang' => 'ar', 'name' => 'Arabic', 'direction' => 'rtl'),
        'arz'     => array('q' => 0.8, 'lang' => 'arz', 'name' => 'Egyptian Spoken Arabic', 'direction' => 'rtl'),
        'be-tarask' => array('q' => 0.5, 'lang' => 'be-tarask', 'name' => 'Belarusian (Taraškievica orthography)', 'direction' => 'ltr'),
        'bg'      => array('q' => 0.8, 'lang' => 'bg', 'name' => 'Bulgarian', 'direction' => 'ltr'),
        'br'      => array('q' => 0.8, 'lang' => 'br', 'name' => 'Breton', 'direction' => 'ltr'),
        'ca'      => array('q' => 0.5, 'lang' => 'ca', 'name' => 'Catalan', 'direction' => 'ltr'),
        'cs'      => array('q' => 0.5, 'lang' => 'cs', 'name' => 'Czech', 'direction' => 'ltr'),
        'da'      => array('q' => 0.8, 'lang' => 'da', 'name' => 'Danish', 'direction' => 'ltr'),
        'de'      => array('q' => 0.8, 'lang' => 'de', 'name' => 'German', 'direction' => 'ltr'),
        'el'      => array('q' => 0.1, 'lang' => 'el',    'name' => 'Greek', 'direction' => 'ltr'),
        'eo'      => array('q' => 0.8, 'lang' => 'eo',    'name' => 'Esperanto', 'direction' => 'ltr'),
        'en-us'   => array('q' => 1, 'lang' => 'en', 'name' => 'English (US)', 'direction' => 'ltr'),
        'en-gb'   => array('q' => 1, 'lang' => 'en_GB', 'name' => 'English (British)', 'direction' => 'ltr'),
        'en'      => array('q' => 1, 'lang' => 'en',    'name' => 'English (US)', 'direction' => 'ltr'),
        'es'      => array('q' => 1, 'lang' => 'es',    'name' => 'Spanish', 'direction' => 'ltr'),
        'eu'      => array('q' => 1, 'lang' => 'eu',    'name' => 'Basque', 'direction' => 'ltr'),
        'fa'      => array('q' => 1, 'lang' => 'fa', 'name' => 'Persian', 'direction' => 'rtl'),
        'fi'      => array('q' => 1, 'lang' => 'fi', 'name' => 'Finnish', 'direction' => 'ltr'),
        'fr-fr'   => array('q' => 1, 'lang' => 'fr', 'name' => 'French', 'direction' => 'ltr'),
        'fur'     => array('q' => 0.8, 'lang' => 'fur', 'name' => 'Friulian', 'direction' => 'ltr'),
        'ga'      => array('q' => 0.5, 'lang' => 'ga', 'name' => 'Irish', 'direction' => 'ltr'),
        'gl'      => array('q' => 0.8, 'lang' => 'gl', 'name' => 'Galician', 'direction' => 'ltr'),
        'he'      => array('q' => 0.5, 'lang' => 'he', 'name' => 'Hebrew', 'direction' => 'rtl'),
        'hsb'     => array('q' => 0.8, 'lang' => 'hsb', 'name' => 'Upper Sorbian', 'direction' => 'ltr'),
        'hu'      => array('q' => 0.8, 'lang' => 'hu', 'name' => 'Hungarian', 'direction' => 'ltr'),
        'ia'      => array('q' => 0.8, 'lang' => 'ia', 'name' => 'Interlingua', 'direction' => 'ltr'),
        'is'      => array('q' => 0.1, 'lang' => 'is', 'name' => 'Icelandic', 'direction' => 'ltr'),
        'it'      => array('q' => 1, 'lang' => 'it', 'name' => 'Italian', 'direction' => 'ltr'),
        'jp'      => array('q' => 0.5, 'lang' => 'ja', 'name' => 'Japanese', 'direction' => 'ltr'),
        'ka'      => array('q' => 0.8, 'lang' => 'ka',    'name' => 'Georgian', 'direction' => 'ltr'),
        'ko'      => array('q' => 0.9, 'lang' => 'ko',    'name' => 'Korean', 'direction' => 'ltr'),
        'mk'      => array('q' => 0.5, 'lang' => 'mk', 'name' => 'Macedonian', 'direction' => 'ltr'),
        'ml'      => array('q' => 0.5, 'lang' => 'ml', 'name' => 'Malayalam', 'direction' => 'ltr'),
        'nb'      => array('q' => 0.1, 'lang' => 'nb', 'name' => 'Norwegian (Bokmål)', 'direction' => 'ltr'),
        'no'      => array('q' => 0.1, 'lang' => 'nb', 'name' => 'Norwegian (Bokmål)', 'direction' => 'ltr'),
        'nn'      => array('q' => 1, 'lang' => 'nn', 'name' => 'Norwegian (Nynorsk)', 'direction' => 'ltr'),
        'nl'      => array('q' => 0.5, 'lang' => 'nl', 'name' => 'Dutch', 'direction' => 'ltr'),
        'pl'      => array('q' => 0.5, 'lang' => 'pl', 'name' => 'Polish', 'direction' => 'ltr'),
        'pt'      => array('q' => 0.1, 'lang' => 'pt',    'name' => 'Portuguese', 'direction' => 'ltr'),
        'pt-br'   => array('q' => 0.9, 'lang' => 'pt_BR', 'name' => 'Portuguese Brazil', 'direction' => 'ltr'),
        'ru'      => array('q' => 0.9, 'lang' => 'ru', 'name' => 'Russian', 'direction' => 'ltr'),
        'sv'      => array('q' => 0.8, 'lang' => 'sv', 'name' => 'Swedish', 'direction' => 'ltr'),
        'te'      => array('q' => 0.3, 'lang' => 'te', 'name' => 'Telugu', 'direction' => 'ltr'),
        'tl'      => array('q' => 0.8, 'lang' => 'tl', 'name' => 'Tagalog', 'direction' => 'ltr'),
        'tr'      => array('q' => 0.5, 'lang' => 'tr', 'name' => 'Turkish', 'direction' => 'ltr'),
        'uk'      => array('q' => 1, 'lang' => 'uk', 'name' => 'Ukrainian', 'direction' => 'ltr'),
        'vi'      => array('q' => 0.8, 'lang' => 'vi', 'name' => 'Vietnamese', 'direction' => 'ltr'),
        'zh-cn'   => array('q' => 0.9, 'lang' => 'zh_CN', 'name' => 'Chinese (Simplified)', 'direction' => 'ltr'),
        'zh-hant' => array('q' => 0.2, 'lang' => 'zh_TW', 'name' => 'Chinese (Taiwanese)', 'direction' => 'ltr'),
    );
}
