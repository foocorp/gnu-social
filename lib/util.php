<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2011, StatusNet, Inc.
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

/* XXX: break up into separate modules (HTTP, user, files) */

/**
 * Show a server error.
 */
function common_server_error($msg, $code=500)
{
    $err = new ServerErrorAction($msg, $code);
    $err->showPage();
}

/**
 * Show a user error.
 */
function common_user_error($msg, $code=400)
{
    $err = new ClientErrorAction($msg, $code);
    $err->showPage();
}

/**
 * This should only be used at setup; processes switching languages
 * to send text to other users should use common_switch_locale().
 *
 * @param string $language Locale language code (optional; empty uses
 *                         current user's preference or site default)
 * @return mixed success
 */
function common_init_locale($language=null)
{
    if(!$language) {
        $language = common_language();
    }
    putenv('LANGUAGE='.$language);
    putenv('LANG='.$language);
    $ok =  setlocale(LC_ALL, $language . ".utf8",
                     $language . ".UTF8",
                     $language . ".utf-8",
                     $language . ".UTF-8",
                     $language);

    return $ok;
}

/**
 * Initialize locale and charset settings and gettext with our message catalog,
 * using the current user's language preference or the site default.
 *
 * This should generally only be run at framework initialization; code switching
 * languages at runtime should call common_switch_language().
 *
 * @access private
 */
function common_init_language()
{
    mb_internal_encoding('UTF-8');

    // Note that this setlocale() call may "fail" but this is harmless;
    // gettext will still select the right language.
    $language = common_language();
    $locale_set = common_init_locale($language);

    if (!$locale_set) {
        // The requested locale doesn't exist on the system.
        //
        // gettext seems very picky... We first need to setlocale()
        // to a locale which _does_ exist on the system, and _then_
        // we can set in another locale that may not be set up
        // (say, ga_ES for Galego/Galician) it seems to take it.
        //
        // For some reason C and POSIX which are guaranteed to work
        // don't do the job. en_US.UTF-8 should be there most of the
        // time, but not guaranteed.
        $ok = common_init_locale("en_US");
        if (!$ok && strtolower(substr(PHP_OS, 0, 3)) != 'win') {
            // Try to find a complete, working locale on Unix/Linux...
            // @fixme shelling out feels awfully inefficient
            // but I don't think there's a more standard way.
            $all = `locale -a`;
            foreach (explode("\n", $all) as $locale) {
                if (preg_match('/\.utf[-_]?8$/i', $locale)) {
                    $ok = setlocale(LC_ALL, $locale);
                    if ($ok) {
                        break;
                    }
                }
            }
        }
        if (!$ok) {
            common_log(LOG_ERR, "Unable to find a UTF-8 locale on this system; UI translations may not work.");
        }
        $locale_set = common_init_locale($language);
    }

    common_init_gettext();
}

/**
 * @access private
 */
function common_init_gettext()
{
    setlocale(LC_CTYPE, 'C');
    // So we do not have to make people install the gettext locales
    $path = common_config('site','locale_path');
    bindtextdomain("statusnet", $path);
    bind_textdomain_codeset("statusnet", "UTF-8");
    textdomain("statusnet");
}

/**
 * Switch locale during runtime, and poke gettext until it cries uncle.
 * Otherwise, sometimes it doesn't actually switch away from the old language.
 *
 * @param string $language code for locale ('en', 'fr', 'pt_BR' etc)
 */
function common_switch_locale($language=null)
{
    common_init_locale($language);

    setlocale(LC_CTYPE, 'C');
    // So we do not have to make people install the gettext locales
    $path = common_config('site','locale_path');
    bindtextdomain("statusnet", $path);
    bind_textdomain_codeset("statusnet", "UTF-8");
    textdomain("statusnet");
}

function common_timezone()
{
    if (common_logged_in()) {
        $user = common_current_user();
        if ($user->timezone) {
            return $user->timezone;
        }
    }

    return common_config('site', 'timezone');
}

function common_valid_language($lang)
{
    if ($lang) {
        // Validate -- we don't want to end up with a bogus code
        // left over from some old junk.
        foreach (common_config('site', 'languages') as $code => $info) {
            if ($info['lang'] == $lang) {
                return true;
            }
        }
    }
    return false;
}

function common_language()
{
    // Allow ?uselang=xx override, very useful for debugging
    // and helping translators check usage and context.
    if (isset($_GET['uselang'])) {
        $uselang = strval($_GET['uselang']);
        if (common_valid_language($uselang)) {
            return $uselang;
        }
    }

    // If there is a user logged in and they've set a language preference
    // then return that one...
    if (_have_config() && common_logged_in()) {
        $user = common_current_user();

        if (common_valid_language($user->language)) {
            return $user->language;
        }
    }

    // Otherwise, find the best match for the languages requested by the
    // user's browser...
    if (common_config('site', 'langdetect')) {
        $httplang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null;
        if (!empty($httplang)) {
            $language = client_prefered_language($httplang);
            if ($language)
              return $language;
        }
    }

    // Finally, if none of the above worked, use the site's default...
    return common_config('site', 'language');
}

/**
 * Salted, hashed passwords are stored in the DB.
 */
function common_munge_password($password, Profile $profile=null)
{
    $hashed = null;

    if (Event::handle('StartHashPassword', array(&$hashed, $password, $profile))) {
        Event::handle('EndHashPassword', array(&$hashed, $password, $profile));
    }
    if (empty($hashed)) {
        throw new PasswordHashException();
    }

    return $hashed;
}

/**
 * Check if a username exists and has matching password.
 */
function common_check_user($nickname, $password)
{
    // empty nickname always unacceptable
    if (empty($nickname)) {
        return false;
    }

    $authenticatedUser = false;

    if (Event::handle('StartCheckPassword', array($nickname, $password, &$authenticatedUser))) {

        if (common_is_email($nickname)) {
            $user = User::getKV('email', common_canonical_email($nickname));
        } else {
            $user = User::getKV('nickname', Nickname::normalize($nickname));
        }

        if ($user instanceof User && !empty($password)) {
            if (0 == strcmp(common_munge_password($password, $user->getProfile()), $user->password)) {
                //internal checking passed
                $authenticatedUser = $user;
            }
        }
    }
    Event::handle('EndCheckPassword', array($nickname, $password, $authenticatedUser));

    return $authenticatedUser;
}

/**
 * Is the current user logged in?
 */
function common_logged_in()
{
    return (!is_null(common_current_user()));
}

function common_local_referer()
{
    return parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === common_config('site', 'server');
}

function common_have_session()
{
    return (0 != strcmp(session_id(), ''));
}

function common_ensure_session()
{
    $c = null;
    if (array_key_exists(session_name(), $_COOKIE)) {
        $c = $_COOKIE[session_name()];
    }
    if (!common_have_session()) {
        if (common_config('sessions', 'handle')) {
            Session::setSaveHandler();
        }
	if (array_key_exists(session_name(), $_GET)) {
	    $id = $_GET[session_name()];
	} else if (array_key_exists(session_name(), $_COOKIE)) {
	    $id = $_COOKIE[session_name()];
	}
	if (isset($id)) {
	    session_id($id);
	}
        @session_start();
        if (!isset($_SESSION['started'])) {
            $_SESSION['started'] = time();
            if (!empty($id)) {
                common_log(LOG_WARNING, 'Session cookie "' . $_COOKIE[session_name()] . '" ' .
                           ' is set but started value is null');
            }
        }
    }
}

// Three kinds of arguments:
// 1) a user object
// 2) a nickname
// 3) null to clear

// Initialize to false; set to null if none found
$_cur = false;

function common_set_user($user)
{
    global $_cur;

    if (is_null($user) && common_have_session()) {
        $_cur = null;
        unset($_SESSION['userid']);
        return true;
    } else if (is_string($user)) {
        $nickname = $user;
        $user = User::getKV('nickname', $nickname);
    } else if (!$user instanceof User) {
        return false;
    }

    if ($user) {
        if (Event::handle('StartSetUser', array(&$user))) {
            if (!empty($user)) {
                if (!$user->hasRight(Right::WEBLOGIN)) {
                    // TRANS: Authorisation exception thrown when a user a not allowed to login.
                    throw new AuthorizationException(_('Not allowed to log in.'));
                }
                common_ensure_session();
                $_SESSION['userid'] = $user->id;
                $_cur = $user;
                Event::handle('EndSetUser', array($user));
                return $_cur;
            }
        }
    }
    return false;
}

function common_set_cookie($key, $value, $expiration=0)
{
    $path = common_config('site', 'path');
    $server = common_config('site', 'server');

    if ($path && ($path != '/')) {
        $cookiepath = '/' . $path . '/';
    } else {
        $cookiepath = '/';
    }
    return setcookie($key,
                     $value,
                     $expiration,
                     $cookiepath,
                     $server,
                     GNUsocial::useHTTPS());
}

define('REMEMBERME', 'rememberme');
define('REMEMBERME_EXPIRY', 30 * 24 * 60 * 60); // 30 days

function common_rememberme($user=null)
{
    if (!$user) {
        $user = common_current_user();
        if (!$user) {
            return false;
        }
    }

    $rm = new Remember_me();

    $rm->code = common_random_hexstr(16);
    $rm->user_id = $user->id;

    // Wrap the insert in some good ol' fashioned transaction code

    $rm->query('BEGIN');

    $result = $rm->insert();

    if (!$result) {
        common_log_db_error($rm, 'INSERT', __FILE__);
        $rm->query('ROLLBACK');
        return false;
    }

    $rm->query('COMMIT');

    $cookieval = $rm->user_id . ':' . $rm->code;

    common_log(LOG_INFO, 'adding rememberme cookie "' . $cookieval . '" for ' . $user->nickname);

    common_set_cookie(REMEMBERME, $cookieval, time() + REMEMBERME_EXPIRY);

    return true;
}

function common_remembered_user()
{
    $user = null;

    $packed = isset($_COOKIE[REMEMBERME]) ? $_COOKIE[REMEMBERME] : null;

    if (!$packed) {
        return null;
    }

    list($id, $code) = explode(':', $packed);

    if (!$id || !$code) {
        common_log(LOG_WARNING, 'Malformed rememberme cookie: ' . $packed);
        common_forgetme();
        return null;
    }

    $rm = Remember_me::getKV('code', $code);

    if (!$rm) {
        common_log(LOG_WARNING, 'No such remember code: ' . $code);
        common_forgetme();
        return null;
    }

    if ($rm->user_id != $id) {
        common_log(LOG_WARNING, 'Rememberme code for wrong user: ' . $rm->user_id . ' != ' . $id);
        common_forgetme();
        return null;
    }

    $user = User::getKV('id', $rm->user_id);

    if (!$user instanceof User) {
        common_log(LOG_WARNING, 'No such user for rememberme: ' . $rm->user_id);
        common_forgetme();
        return null;
    }

    // successful!
    $result = $rm->delete();

    if (!$result) {
        common_log_db_error($rm, 'DELETE', __FILE__);
        common_log(LOG_WARNING, 'Could not delete rememberme: ' . $code);
        common_forgetme();
        return null;
    }

    common_log(LOG_INFO, 'logging in ' . $user->nickname . ' using rememberme code ' . $rm->code);

    common_set_user($user);
    common_real_login(false);

    // We issue a new cookie, so they can log in
    // automatically again after this session

    common_rememberme($user);

    return $user;
}

/**
 * must be called with a valid user!
 */
function common_forgetme()
{
    common_set_cookie(REMEMBERME, '', 0);
}

/**
 * Who is the current user?
 */
function common_current_user()
{
    global $_cur;

    if (!_have_config()) {
        return null;
    }

    if ($_cur === false) {

        if (isset($_COOKIE[session_name()]) || isset($_GET[session_name()])
            || (isset($_SESSION['userid']) && $_SESSION['userid'])) {
            common_ensure_session();
            $id = isset($_SESSION['userid']) ? $_SESSION['userid'] : false;
            if ($id) {
                $user = User::getKV('id', $id);
                if ($user instanceof User) {
                	$_cur = $user;
                	return $_cur;
                }
            }
        }

        // that didn't work; try to remember; will init $_cur to null on failure
        $_cur = common_remembered_user();

        if ($_cur) {
            // XXX: Is this necessary?
            $_SESSION['userid'] = $_cur->id;
        }
    }

    return $_cur;
}

/**
 * Logins that are 'remembered' aren't 'real' -- they're subject to
 * cookie-stealing. So, we don't let them do certain things. New reg,
 * OpenID, and password logins _are_ real.
 */
function common_real_login($real=true)
{
    common_ensure_session();
    $_SESSION['real_login'] = $real;
}

function common_is_real_login()
{
    return common_logged_in() && $_SESSION['real_login'];
}

/**
 * Get a hash portion for HTTP caching Etags and such including
 * info on the current user's session. If login/logout state changes,
 * or we've changed accounts, or we've renamed the current user,
 * we'll get a new hash value.
 *
 * This should not be considered secure information.
 *
 * @param User $user (optional; uses common_current_user() if left out)
 * @return string
 */
function common_user_cache_hash($user=false)
{
    if ($user === false) {
        $user = common_current_user();
    }
    if ($user) {
        return crc32($user->id . ':' . $user->nickname);
    } else {
        return '0';
    }
}

/**
 * get canonical version of nickname for comparison
 *
 * @param string $nickname
 * @return string
 *
 * @throws NicknameException on invalid input
 * @deprecated call Nickname::normalize() directly.
 */
function common_canonical_nickname($nickname)
{
    return Nickname::normalize($nickname);
}

/**
 * get canonical version of email for comparison
 *
 * @fixme actually normalize
 * @fixme reject invalid input
 *
 * @param string $email
 * @return string
 */
function common_canonical_email($email)
{
    // XXX: canonicalize UTF-8
    // XXX: lcase the domain part
    return $email;
}

function common_to_alphanumeric($str)
{
    $filtered = preg_replace('/[^A-Za-z0-9]\s*/', '', $str);
    if (strlen($filtered) < 1) {
        throw new Exception('Filtered string was zero-length.');
    }
    return $filtered;
}

function common_purify($html)
{
    require_once INSTALLDIR.'/extlib/HTMLPurifier/HTMLPurifier.auto.php';

    $cfg = HTMLPurifier_Config::createDefault();
    $cfg->set('Attr.AllowedRel', ['bookmark', 'directory', 'enclosure', 'home', 'license', 'nofollow', 'payment', 'tag']);  // http://microformats.org/wiki/rel
    $cfg->set('HTML.ForbiddenAttributes', array('style'));  // id, on* etc. are already filtered by default
    $cfg->set('URI.AllowedSchemes', array_fill_keys(common_url_schemes(), true));

    // Remove more elements than what the default filter removes, default in GNU social are remotely
    // linked resources such as img, video, audio
    $forbiddenElements = array();
    foreach (common_config('htmlfilter') as $tag=>$filter) {
        if ($filter === true) {
            $forbiddenElements[] = $tag;
        }
    }
    $cfg->set('HTML.ForbiddenElements', $forbiddenElements);

    $html = common_remove_unicode_formatting($html);

    $purifier = new HTMLPurifier($cfg);
    $purified = $purifier->purify($html);
    Event::handle('EndCommonPurify', array(&$purified, $html));
    
    return $purified;
}

function common_remove_unicode_formatting($text)
{
    // Strip Unicode text formatting/direction codes
    // this is pretty dangerous for visualisation of text and can be used for mischief
    return preg_replace('/[\\x{200b}-\\x{200f}\\x{202a}-\\x{202e}]/u', '', $text);
}

/**
 * Partial notice markup rendering step: build links to !group references.
 *
 * @param string    $text partially rendered HTML
 * @param Profile   $author the Profile that is composing the current notice
 * @param Notice    $parent the Notice this is sent in reply to, if any
 * @return string partially rendered HTML
 */
function common_render_content($text, Profile $author, Notice $parent=null)
{
    $text = common_render_text($text);
    $text = common_linkify_mentions($text, $author, $parent);
    return $text;
}

/**
 * Finds @-mentions within the partially-rendered text section and
 * turns them into live links.
 *
 * Should generally not be called except from common_render_content().
 *
 * @param string    $text   partially-rendered HTML
 * @param Profile   $author the Profile that is composing the current notice
 * @param Notice    $parent the Notice this is sent in reply to, if any
 * @return string partially-rendered HTML
 */
function common_linkify_mentions($text, Profile $author, Notice $parent=null)
{
    $mentions = common_find_mentions($text, $author, $parent);

    // We need to go through in reverse order by position,
    // so our positions stay valid despite our fudging with the
    // string!

    $points = array();

    foreach ($mentions as $mention)
    {
        $points[$mention['position']] = $mention;
    }

    krsort($points);

    foreach ($points as $position => $mention) {

        $linkText = common_linkify_mention($mention);

        $text = substr_replace($text, $linkText, $position, $mention['length']);
    }

    return $text;
}

function common_linkify_mention(array $mention)
{
    $output = null;

    if (Event::handle('StartLinkifyMention', array($mention, &$output))) {

        $xs = new XMLStringer(false);

        $attrs = array('href' => $mention['url'],
                       'class' => 'h-card '.$mention['type']);

        if (!empty($mention['title'])) {
            $attrs['title'] = $mention['title'];
        }

        $xs->element('a', $attrs, $mention['text']);

        $output = $xs->getString();

        Event::handle('EndLinkifyMention', array($mention, &$output));
    }

    return $output;
}

function common_get_attentions($text, Profile $sender, Notice $parent=null)
{
    $mentions = common_find_mentions($text, $sender, $parent);
    $atts = array();
    foreach ($mentions as $mention) {
        foreach ($mention['mentioned'] as $mentioned) {
            $atts[$mentioned->getUri()] = $mentioned->getObjectType();
        }
    }
    if ($parent instanceof Notice) {
        $parentAuthor = $parent->getProfile();
        // afaik groups can't be authors
        $atts[$parentAuthor->getUri()] = ActivityObject::PERSON;
    }
    return $atts;
}

/**
 * Find @-mentions in the given text, using the given notice object as context.
 * References will be resolved with common_relative_profile() against the user
 * who posted the notice.
 *
 * Note the return data format is internal, to be used for building links and
 * such. Should not be used directly; rather, call common_linkify_mentions().
 *
 * @param string    $text
 * @param Profile   $sender the Profile that is sending the current text
 * @param Notice    $parent the Notice this text is in reply to, if any
 *
 * @return array
 *
 * @access private
 */
function common_find_mentions($text, Profile $sender, Notice $parent=null)
{
    $mentions = array();

    if (Event::handle('StartFindMentions', array($sender, $text, &$mentions))) {
        // Get the context of the original notice, if any
        $origMentions = array();
        // Does it have a parent notice for context?
        if ($parent instanceof Notice) {
            foreach ($parent->getAttentionProfiles() as $repliedTo) {
                if (!$repliedTo->isPerson()) {
                    continue;
                }
                $origMentions[$repliedTo->id] = $repliedTo;
            }
        }

        $matches = common_find_mentions_raw($text);

        foreach ($matches as $match) {
            try {
                $nickname = Nickname::normalize($match[0]);
            } catch (NicknameException $e) {
                // Bogus match? Drop it.
                continue;
            }

			// primarily mention the profiles mentioned in the parent
            $mention_found_in_origMentions = false;
            foreach($origMentions as $origMentionsId=>$origMention) {
                if($origMention->getNickname() == $nickname) {
                    $mention_found_in_origMentions = $origMention;
                    // don't mention same twice! the parent might have mentioned 
                    // two users with same nickname on different instances
                    unset($origMentions[$origMentionsId]);
                    break;
                }
            }

            // Try to get a profile for this nickname.
            // Start with parents mentions, then go to parents sender context
            if ($mention_found_in_origMentions) {
                $mentioned = $mention_found_in_origMentions;            
            } else if ($parent instanceof Notice && $parent->getProfile()->getNickname() === $nickname) {
                $mentioned = $parent->getProfile();
            } else {
                // sets to null if no match
                $mentioned = common_relative_profile($sender, $nickname);
            }

            if ($mentioned instanceof Profile) {
                try {
                    $url = $mentioned->getUri();    // prefer the URI as URL, if it is one.
                    if (!common_valid_http_url($url)) {
                        $url = $mentioned->getUrl();
                    }
                } catch (InvalidUrlException $e) {
                    $url = common_local_url('userbyid', array('id' => $mentioned->getID()));
                }

                $mention = array('mentioned' => array($mentioned),
                                 'type' => 'mention',
                                 'text' => $match[0],
                                 'position' => $match[1],
                                 'length' => mb_strlen($match[0]),
                                 'title' => $mentioned->getFullname(),
                                 'url' => $url);

                $mentions[] = $mention;
            }
        }

        // @#tag => mention of all subscriptions tagged 'tag'

        preg_match_all('/(?:^|[\s\.\,\:\;]+)@#([\pL\pN_\-\.]{1,64})/',
                       $text, $hmatches, PREG_OFFSET_CAPTURE);
        foreach ($hmatches[1] as $hmatch) {
            $tag = common_canonical_tag($hmatch[0]);
            $plist = Profile_list::getByTaggerAndTag($sender->getID(), $tag);
            if (!$plist instanceof Profile_list || $plist->private) {
                continue;
            }
            $tagged = $sender->getTaggedSubscribers($tag);

            $url = common_local_url('showprofiletag',
                                    array('nickname' => $sender->getNickname(),
                                          'tag' => $tag));

            $mentions[] = array('mentioned' => $tagged,
                                'type'      => 'list',
                                'text' => $hmatch[0],
                                'position' => $hmatch[1],
                                'length' => mb_strlen($hmatch[0]),
                                'url' => $url);
        }

        preg_match_all('/(?:^|[\s\.\,\:\;]+)!(' . Nickname::DISPLAY_FMT . ')/',
                       $text, $hmatches, PREG_OFFSET_CAPTURE);
        foreach ($hmatches[1] as $hmatch) {
            $nickname = Nickname::normalize($hmatch[0]);
            $group = User_group::getForNickname($nickname, $sender);

            if (!$group instanceof User_group || !$sender->isMember($group)) {
                continue;
            }

            $profile = $group->getProfile();

            $mentions[] = array('mentioned' => array($profile),
                                'type'      => 'group',
                                'text'      => $hmatch[0],
                                'position'  => $hmatch[1],
                                'length'    => mb_strlen($hmatch[0]),
                                'url'       => $group->permalink(),
                                'title'     => $group->getFancyName());
        }

        Event::handle('EndFindMentions', array($sender, $text, &$mentions));
    }

    return $mentions;
}

/**
 * Does the actual regex pulls to find @-mentions in text.
 * Should generally not be called directly; for use in common_find_mentions.
 *
 * @param string $text
 * @return array of PCRE match arrays
 */
function common_find_mentions_raw($text)
{
    $tmatches = array();
    preg_match_all('/^T (' . Nickname::DISPLAY_FMT . ') /',
                   $text,
                   $tmatches,
                   PREG_OFFSET_CAPTURE);

    $atmatches = array();
    // the regexp's "(?!\@)" makes sure it doesn't matches the single "@remote" in "@remote@server.com"
    preg_match_all('/(?:^|\s+)@(' . Nickname::DISPLAY_FMT . ')\b(?!\@)/',
                   $text,
                   $atmatches,
                   PREG_OFFSET_CAPTURE);

    $matches = array_merge($tmatches[1], $atmatches[1]);
    return $matches;
}

function common_render_text($text)
{
    $text = common_remove_unicode_formatting($text);
    $text = nl2br(htmlspecialchars($text));

    $text = preg_replace('/[\x{0}-\x{8}\x{b}-\x{c}\x{e}-\x{19}]/', '', $text);
    $text = common_replace_urls_callback($text, 'common_linkify');
    $text = preg_replace_callback('/(^|\&quot\;|\'|\(|\[|\{|\s+)#([\pL\pN_\-\.]{1,64})/u',
                function ($m) { return "{$m[1]}#".common_tag_link($m[2]); }, $text);
    // XXX: machine tags
    return $text;
}

define('_URL_SCHEME_COLON_DOUBLE_SLASH', 1);
define('_URL_SCHEME_SINGLE_COLON', 2);
define('_URL_SCHEME_NO_DOMAIN', 4);
define('_URL_SCHEME_COLON_COORDINATES', 8);

function common_url_schemes($filter=null)
{
    // TODO: move these to $config
    $schemes = [
                'http'      => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'https'     => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'ftp'       => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'ftps'      => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'mms'       => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'rtsp'      => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'gopher'    => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'news'      => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'nntp'      => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'telnet'    => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'wais'      => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'file'      => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'prospero'  => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'webcal'    => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'irc'       => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'ircs'      => _URL_SCHEME_COLON_DOUBLE_SLASH,
                'aim'       => _URL_SCHEME_SINGLE_COLON,
                'bitcoin'   => _URL_SCHEME_SINGLE_COLON,
                'fax'       => _URL_SCHEME_SINGLE_COLON,
                'jabber'    => _URL_SCHEME_SINGLE_COLON,
                'mailto'    => _URL_SCHEME_SINGLE_COLON,
                'tel'       => _URL_SCHEME_SINGLE_COLON,
                'xmpp'      => _URL_SCHEME_SINGLE_COLON,
                'magnet'    => _URL_SCHEME_NO_DOMAIN,
                'geo'       => _URL_SCHEME_COLON_COORDINATES,
                ];

    return array_keys(
            array_filter($schemes,
                function ($scheme) use ($filter) {
                    return is_null($filter) || ($scheme & $filter);
                })
            );
}

/**
 * Find links in the given text and pass them to the given callback function.
 *
 * @param string $text
 * @param function($text, $arg) $callback: return replacement text
 * @param mixed $arg: optional argument will be passed on to the callback
 */
function common_replace_urls_callback($text, $callback, $arg = null) {
    $geouri_labeltext_regex = '\pN\pL\-';
    $geouri_mark_regex = '\-\_\.\!\~\*\\\'\(\)';    // the \\\' is really pretty
    $geouri_unreserved_regex = '\pN\pL' . $geouri_mark_regex;
    $geouri_punreserved_regex = '\[\]\:\&\+\$';
    $geouri_pctencoded_regex = '(?:\%[0-9a-fA-F][0-9a-fA-F])';
    $geouri_paramchar_regex = $geouri_unreserved_regex . $geouri_punreserved_regex; //FIXME: add $geouri_pctencoded_regex here so it works

    // Start off with a regex
    $regex = '#'.
    '(?:^|[\s\<\>\(\)\[\]\{\}\\\'\\\";]+)(?![\@\!\#])'.
    '('.
        '(?:'.
            '(?:'. //Known protocols
                '(?:'.
                    '(?:(?:' . implode('|', common_url_schemes(_URL_SCHEME_COLON_DOUBLE_SLASH)) . ')://)'.
                    '|'.
                    '(?:(?:' . implode('|', common_url_schemes(_URL_SCHEME_SINGLE_COLON)) . '):)'.
                ')'.
                '(?:[\pN\pL\-\_\+\%\~]+(?::[\pN\pL\-\_\+\%\~]+)?\@)?'. //user:pass@
                '(?:'.
                    '(?:'.
                        '\[[\pN\pL\-\_\:\.]+(?<![\.\:])\]'. //[dns]
                    ')|(?:'.
                        '[\pN\pL\-\_\:\.]+(?<![\.\:])'. //dns
                    ')'.
                ')'.
            ')'.
            '|(?:'.
                '(?:' . implode('|', common_url_schemes(_URL_SCHEME_COLON_COORDINATES)) . '):'.
                // There's an order that must be followed here too, if ;crs= is used, it must precede ;u=
                // Also 'crsp' (;crs=$crsp) must match $geouri_labeltext_regex
                // Also 'uval' (;u=$uval) must be a pnum: \-?[0-9]+
                '(?:'.
                    '(?:[0-9]+(?:\.[0-9]+)?(?:\,[0-9]+(?:\.[0-9]+)?){1,2})'.    // 1(.23)?(,4(.56)){1,2}
                    '(?:\;(?:['.$geouri_labeltext_regex.']+)(?:\=['.$geouri_paramchar_regex.']+)*)*'.
                ')'.
            ')'.
            // URLs without domain name, like magnet:?xt=...
            '|(?:(?:' . implode('|', common_url_schemes(_URL_SCHEME_NO_DOMAIN)) . '):(?=\?))'.  // zero-length lookahead requires ? after :
            (common_config('linkify', 'bare_ipv4')   // Convert IPv4 addresses to hyperlinks
                ? '|(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)'
                : '').
            (common_config('linkify', 'bare_ipv6')   // Convert IPv6 addresses to hyperlinks
                ? '|(?:'. //IPv6
                    '\[?(?:(?:(?:[0-9A-Fa-f]{1,4}:){7}(?:(?:[0-9A-Fa-f]{1,4})|:))|(?:(?:[0-9A-Fa-f]{1,4}:){6}(?::|(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})|(?::[0-9A-Fa-f]{1,4})))|(?:(?:[0-9A-Fa-f]{1,4}:){5}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){4}(?::[0-9A-Fa-f]{1,4}){0,1}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){3}(?::[0-9A-Fa-f]{1,4}){0,2}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:){2}(?::[0-9A-Fa-f]{1,4}){0,3}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:[0-9A-Fa-f]{1,4}:)(?::[0-9A-Fa-f]{1,4}){0,4}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?::(?::[0-9A-Fa-f]{1,4}){0,5}(?:(?::(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|(?:(?::[0-9A-Fa-f]{1,4}){1,2})))|(?:(?:(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})(?:\.(?:25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})))\]?(?<!:)'.
                    ')'
                : '').
            (common_config('linkify', 'bare_domains')
                ? '|(?:'. //DNS
                    '(?:[\pN\pL\-\_\+\%\~]+(?:\:[\pN\pL\-\_\+\%\~]+)?\@)?'. //user:pass@
                    '[\pN\pL\-\_]+(?:\.[\pN\pL\-\_]+)*\.'.
                    //tld list from http://data.iana.org/TLD/tlds-alpha-by-domain.txt, also added local, loc, and onion
                    '(?:AC|AD|AE|AERO|AF|AG|AI|AL|AM|AN|AO|AQ|AR|ARPA|AS|ASIA|AT|AU|AW|AX|AZ|BA|BB|BD|BE|BF|BG|BH|BI|BIZ|BJ|BM|BN|BO|BR|BS|BT|BV|BW|BY|BZ|CA|CAT|CC|CD|CF|CG|CH|CI|CK|CL|CM|CN|CO|COM|COOP|CR|CU|CV|CX|CY|CZ|DE|DJ|DK|DM|DO|DZ|EC|EDU|EE|EG|ER|ES|ET|EU|FI|FJ|FK|FM|FO|FR|GA|GB|GD|GE|GF|GG|GH|GI|GL|GM|GN|GOV|GP|GQ|GR|GS|GT|GU|GW|GY|HK|HM|HN|HR|HT|HU|ID|IE|IL|IM|IN|INFO|INT|IO|IQ|IR|IS|IT|JE|JM|JO|JOBS|JP|KE|KG|KH|KI|KM|KN|KP|KR|KW|KY|KZ|LA|LB|LC|LI|LK|LR|LS|LT|LU|LV|LY|MA|MC|MD|ME|MG|MH|MIL|MK|ML|MM|MN|MO|MOBI|MP|MQ|MR|MS|MT|MU|MUSEUM|MV|MW|MX|MY|MZ|NA|NAME|NC|NE|NET|NF|NG|NI|NL|NO|NP|NR|NU|NZ|OM|ORG|PA|PE|PF|PG|PH|PK|PL|PM|PN|PR|PRO|PS|PT|PW|PY|QA|RE|RO|RS|RU|RW|SA|SB|SC|SD|SE|SG|SH|SI|SJ|SK|SL|SM|SN|SO|SR|ST|SU|SV|SY|SZ|TC|TD|TEL|TF|TG|TH|TJ|TK|TL|TM|TN|TO|TP|TR|TRAVEL|TT|TV|TW|TZ|UA|UG|UK|US|UY|UZ|VA|VC|VE|VG|VI|VN|VU|WF|WS|XN--0ZWM56D|测试|XN--11B5BS3A9AJ6G|परीक्षा|XN--80AKHBYKNJ4F|испытание|XN--9T4B11YI5A|테스트|XN--DEBA0AD|טעסט|XN--G6W251D|測試|XN--HGBK6AJ7F53BBA|آزمایشی|XN--HLCJ6AYA9ESC7A|பரிட்சை|XN--JXALPDLP|δοκιμή|XN--KGBECHTV|إختبار|XN--ZCKZAH|テスト|YE|YT|YU|ZA|ZM|ZONE|ZW|local|loc|onion)'.
            ')(?![\pN\pL\-\_])'
                : '') . // if common_config('linkify', 'bare_domains') is false, don't add anything here
        ')'.
        '(?:'.
            '(?:\:\d+)?'. //:port
            '(?:/[\pN\pL$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'@]*)?'. // /path
            '(?:\?[\pN\pL\$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'@\/]*)?'. // ?query string
            '(?:\#[\pN\pL$\,\!\(\)\.\:\-\_\+\/\=\&\;\%\~\*\$\+\'\@/\?\#]*)?'. // #fragment
        ')(?<![\?\.\,\#\,])'.
    ')'.
    '#ixu';
    //preg_match_all($regex,$text,$matches);
    //print_r($matches);
    return preg_replace_callback($regex, curry('callback_helper',$callback,$arg) ,$text);
}

/**
 * Intermediate callback for common_replace_links(), helps resolve some
 * ambiguous link forms before passing on to the final callback.
 *
 * @param array $matches
 * @param callable $callback
 * @param mixed $arg optional argument to pass on as second param to callback
 * @return string
 *
 * @access private
 */
function callback_helper($matches, $callback, $arg=null) {
    $url=$matches[1];
    $left = strpos($matches[0],$url);
    $right = $left+strlen($url);

    $groupSymbolSets=array(
        array(
            'left'=>'(',
            'right'=>')'
        ),
        array(
            'left'=>'[',
            'right'=>']'
        ),
        array(
            'left'=>'{',
            'right'=>'}'
        ),
        array(
            'left'=>'<',
            'right'=>'>'
        )
    );
    $cannotEndWith=array('.','?',',','#');
    $original_url=$url;
    do{
        $original_url=$url;
        foreach($groupSymbolSets as $groupSymbolSet){
            if(substr($url,-1)==$groupSymbolSet['right']){
                $group_left_count = substr_count($url,$groupSymbolSet['left']);
                $group_right_count = substr_count($url,$groupSymbolSet['right']);
                if($group_left_count<$group_right_count){
                    $right-=1;
                    $url=substr($url,0,-1);
                }
            }
        }
        if(in_array(substr($url,-1),$cannotEndWith)){
            $right-=1;
            $url=substr($url,0,-1);
        }
    }while($original_url!=$url);

    $result = call_user_func_array($callback, array($url, $arg));
    return substr($matches[0],0,$left) . $result . substr($matches[0],$right);
}

require_once INSTALLDIR . "/lib/curry.php";

function common_linkify($url) {
    // It comes in special'd, so we unspecial it before passing to the stringifying
    // functions
    $url = htmlspecialchars_decode($url);

    if (strpos($url, '@') !== false && strpos($url, ':') === false && Validate::email($url)) {
        //url is an email address without the mailto: protocol
        $canon = "mailto:$url";
        $longurl = "mailto:$url";
    } else {
        $canon = File_redirection::_canonUrl($url);
        $longurl_data = File_redirection::where($canon, common_config('attachments', 'process_links'));
        
        if(isset($longurl_data->redir_url)) {
			$longurl = $longurl_data->redir_url;
        } else {
            // e.g. local files
	        $longurl = $longurl_data->url;
        }
    }
    
    $attrs = array('href' => $longurl, 'title' => $longurl);

    $is_attachment = false;
    $attachment_id = null;
    $has_thumb = false;

    // Check to see whether this is a known "attachment" URL.

    try {
        $f = File::getByUrl($longurl);
    } catch (NoResultException $e) {
        if (common_config('attachments', 'process_links')) {
            // XXX: this writes to the database. :<
            try {
                $f = File::processNew($longurl);
            } catch (ServerException $e) {
                $f = null;
            }
        }
    }

    if ($f instanceof File) {
        try {
            $enclosure = $f->getEnclosure();
            $is_attachment = true;
            $attachment_id = $f->id;

            $thumb = File_thumbnail::getKV('file_id', $f->id);
            $has_thumb = ($thumb instanceof File_thumbnail);
        } catch (ServerException $e) {
            // There was not enough metadata available
        }
    }

    // Add clippy
    if ($is_attachment) {
        $attrs['class'] = 'attachment';
        if ($has_thumb) {
            $attrs['class'] = 'attachment thumbnail';
        }
        $attrs['id'] = "attachment-{$attachment_id}";
    }

    // Whether to nofollow

    $nf = common_config('nofollow', 'external');

    if ($nf == 'never') {
        $attrs['rel'] = 'external';
    } else {
        $attrs['rel'] = 'nofollow external';
    }

    return XMLStringer::estring('a', $attrs, $url);
}

/**
 * Find and shorten links in a given chunk of text if it's longer than the
 * configured notice content limit (or unconditionally).
 *
 * Side effects: may save file and file_redirection records for referenced URLs.
 *
 * Pass the $user option or call $user->shortenLinks($text) to ensure the proper
 * user's options are used; otherwise the current web session user's setitngs
 * will be used or ur1.ca if there is no active web login.
 *
 * @param string $text
 * @param boolean $always (optional)
 * @param User $user (optional)
 *
 * @return string
 */
function common_shorten_links($text, $always = false, User $user=null)
{
    if ($user === null) {
        $user = common_current_user();
    }

    $maxLength = User_urlshortener_prefs::maxNoticeLength($user);

    if ($always || ($maxLength != -1 && mb_strlen($text) > $maxLength)) {
        return common_replace_urls_callback($text, array('File_redirection', 'forceShort'), $user);
    } else {
        return common_replace_urls_callback($text, array('File_redirection', 'makeShort'), $user);
    }
}

/**
 * Very basic stripping of invalid UTF-8 input text.
 *
 * @param string $str
 * @return mixed string or null if invalid input
 *
 * @todo ideally we should drop bad chars, and maybe do some of the checks
 *       from common_xml_safe_str. But we can't strip newlines, etc.
 * @todo Unicode normalization might also be useful, but not needed now.
 */
function common_validate_utf8($str)
{
    // preg_replace will return NULL on invalid UTF-8 input.
    //
    // Note: empty regex //u also caused NULL return on some
    // production machines, but none of our test machines.
    //
    // This should be replaced with a more reliable check.
    return preg_replace('/\x00/u', '', $str);
}

/**
 * Make sure an arbitrary string is safe for output in XML as a single line.
 *
 * @param string $str
 * @return string
 */
function common_xml_safe_str($str)
{
    // Replace common eol and extra whitespace input chars
    $unWelcome = array(
        "\t",  // tab
        "\n",  // newline
        "\r",  // cr
        "\0",  // null byte eos
        "\x0B" // vertical tab
    );

    $replacement = array(
        ' ', // single space
        ' ',
        '',  // nothing
        '',
        ' '
    );

    $str = str_replace($unWelcome, $replacement, $str);

    // Neutralize any additional control codes and UTF-16 surrogates
    // (Twitter uses '*')
    return preg_replace('/[\p{Cc}\p{Cs}]/u', '*', $str);
}

function common_slugify($str)
{
    // php5-intl is highly recommended...
    if (!function_exists('transliterator_transliterate')) {
        $str = preg_replace('/[^\pL\pN]/u', '', $str);
        $str = mb_convert_case($str, MB_CASE_LOWER, 'UTF-8');
        $str = substr($str, 0, 64);
        return $str;
    }
    $str = transliterator_transliterate(
                        'Any-Latin;' .      // any charset to latin compatible
                            'NFD;' .        // decompose
                            '[:Nonspacing Mark:] Remove;' . // remove nonspacing marks (accents etc.)
                            'NFC;' .        // composite again
                            '[:Punctuation:] Remove;' . // remove punctuation (.,¿? etc.)
                            'Lower();' .    // turn into lowercase
                            'Latin-ASCII;',  // get ASCII equivalents (ð to d for example)
                        $str);
    return preg_replace('/[^\pL\pN]/', '', $str);
}

function common_tag_link($tag)
{
    $canonical = common_canonical_tag($tag);
    if (common_config('singleuser', 'enabled')) {
        // regular TagAction isn't set up in 1user mode
        $nickname = User::singleUserNickname();
        $url = common_local_url('showstream',
                                array('nickname' => $nickname,
                                      'tag' => $canonical));
    } else {
        $url = common_local_url('tag', array('tag' => $canonical));
    }
    $xs = new XMLStringer();
    $xs->elementStart('span', 'tag');
    $xs->element('a', array('href' => $url,
                            'rel' => 'tag'),
                 $tag);
    $xs->elementEnd('span');
    return $xs->getString();
}

function common_canonical_tag($tag)
{
    $tag = common_slugify($tag);
    $tag = substr($tag, 0, 64);
    return $tag;
}

function common_valid_profile_tag($str)
{
    return preg_match('/^[A-Za-z0-9_\-\.]{1,64}$/', $str);
}

/**
 * Resolve an ambiguous profile nickname reference, checking in following order:
 * - profiles that $sender subscribes to
 * - profiles that subscribe to $sender
 * - local user profiles
 *
 * WARNING: does not validate or normalize $nickname -- MUST BE PRE-VALIDATED
 * OR THERE MAY BE A RISK OF SQL INJECTION ATTACKS. THIS FUNCTION DOES NOT
 * ESCAPE SQL.
 *
 * @fixme validate input
 * @fixme escape SQL
 * @fixme fix or remove mystery third parameter
 * @fixme is $sender a User or Profile?
 *
 * @param <type> $sender the user or profile in whose context we're looking
 * @param string $nickname validated nickname of
 * @param <type> $dt unused mystery parameter; in Notice reply-to handling a timestamp is passed.
 *
 * @return Profile or null
 */
function common_relative_profile($sender, $nickname, $dt=null)
{
    // Will throw exception on invalid input.
    $nickname = Nickname::normalize($nickname);

    // Try to find profiles this profile is subscribed to that have this nickname
    $recipient = new Profile();
    // XXX: use a join instead of a subquery
    $recipient->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = '.intval($sender->id).' and subscribed = id)', 'AND');
    $recipient->whereAdd("nickname = '" . $recipient->escape($nickname) . "'", 'AND');
    if ($recipient->find(true)) {
        // XXX: should probably differentiate between profiles with
        // the same name by date of most recent update
        return $recipient;
    }
    // Try to find profiles that listen to this profile and that have this nickname
    $recipient = new Profile();
    // XXX: use a join instead of a subquery
    $recipient->whereAdd('EXISTS (SELECT subscriber from subscription where subscribed = '.intval($sender->id).' and subscriber = id)', 'AND');
    $recipient->whereAdd("nickname = '" . $recipient->escape($nickname) . "'", 'AND');
    if ($recipient->find(true)) {
        // XXX: should probably differentiate between profiles with
        // the same name by date of most recent update
        return $recipient;
    }
    // If this is a local user, try to find a local user with that nickname.
    $sender = User::getKV('id', $sender->id);
    if ($sender instanceof User) {
        $recipient_user = User::getKV('nickname', $nickname);
        if ($recipient_user instanceof User) {
            return $recipient_user->getProfile();
        }
    }
    // Otherwise, no links. @messages from local users to remote users,
    // or from remote users to other remote users, are just
    // outside our ability to make intelligent guesses about
    return null;
}

function common_local_url($action, $args=null, $params=null, $fragment=null, $addSession=true)
{
    if (Event::handle('StartLocalURL', array(&$action, &$params, &$fragment, &$addSession, &$url))) {
        $r = Router::get();
        $path = $r->build($action, $args, $params, $fragment);

        $ssl = GNUsocial::useHTTPS();

        if (common_config('site','fancy')) {
            $url = common_path($path, $ssl, $addSession);
        } else {
            if (mb_strpos($path, '/index.php') === 0) {
                $url = common_path($path, $ssl, $addSession);
            } else {
                $url = common_path('index.php/'.$path, $ssl, $addSession);
            }
        }
        Event::handle('EndLocalURL', array(&$action, &$params, &$fragment, &$addSession, &$url));
    }
    return $url;
}

function common_path($relative, $ssl=false, $addSession=true)
{
    $pathpart = (common_config('site', 'path')) ? common_config('site', 'path')."/" : '';

    if ($ssl && GNUsocial::useHTTPS()) {
        $proto = 'https';
        if (is_string(common_config('site', 'sslserver')) &&
            mb_strlen(common_config('site', 'sslserver')) > 0) {
            $serverpart = common_config('site', 'sslserver');
        } else if (common_config('site', 'server')) {
            $serverpart = common_config('site', 'server');
        } else {
            common_log(LOG_ERR, 'Site server not configured, unable to determine site name.');
        }
    } else {
        $proto = 'http';
        if (common_config('site', 'server')) {
            $serverpart = common_config('site', 'server');
        } else {
            common_log(LOG_ERR, 'Site server not configured, unable to determine site name.');
        }
    }

    if ($addSession) {
        $relative = common_inject_session($relative, $serverpart);
    }

    return $proto.'://'.$serverpart.'/'.$pathpart.$relative;
}

// FIXME: Maybe this should also be able to handle non-fancy URLs with index.php?p=...
function common_fake_local_fancy_url($url)
{
    /**
     * This is a hacky fix to make URIs generated with "index.php/" match against
     * locally stored URIs without that. So for example if the remote site is looking
     * up the webfinger for some user and for some reason knows about https://some.example/user/1
     * but we locally store and report only https://some.example/index.php/user/1 then they would
     * dismiss the profile for not having an identified alias.
     *
     * There are various live instances where these issues occur, for various reasons.
     * Most of them being users fiddling with configuration while already having
     * started federating (distributing the URI to other servers) or maybe manually
     * editing the local database.
     */
    if (!preg_match(
                // [1] protocol part, we can only rewrite http/https anyway.
                '/^(https?:\/\/)' .
                // [2] site name.
                // FIXME: Dunno how this acts if we're aliasing ourselves with a .onion domain etc.
                '('.preg_quote(common_config('site', 'server'), '/').')' .
                // [3] site path, or if that is empty just '/' (to retain the /)
                '('.preg_quote(common_config('site', 'path') ?: '/', '/').')' .
                // [4] + [5] extract index.php (+ possible leading double /) and the rest of the URL separately.
                '(\/?index\.php\/)(.*)$/', $url, $matches)) {
        // if preg_match failed to match
        throw new Exception('No known change could be made to the URL.');
    }

    // now reconstruct the URL with everything except the "index.php/" part
    $fancy_url = '';
    foreach ([1,2,3,5] as $idx) {
        $fancy_url .= $matches[$idx];
    }
    return $fancy_url;
}

// FIXME: Maybe this should also be able to handle non-fancy URLs with index.php?p=...
function common_fake_local_nonfancy_url($url)
{
    /**
     * This is a hacky fix to make URIs NOT generated with "index.php/" match against
     * locally stored URIs WITH that. The reverse from the above.
     *
     * It will also "repair" index.php URLs with multiple / prepended. Like https://some.example///index.php/user/1
     */
    if (!preg_match(
                // [1] protocol part, we can only rewrite http/https anyway.
                '/^(https?:\/\/)' .
                // [2] site name.
                // FIXME: Dunno how this acts if we're aliasing ourselves with a .onion domain etc.
                '('.preg_quote(common_config('site', 'server'), '/').')' .
                // [3] site path, or if that is empty just '/' (to retain the /)
                '('.preg_quote(common_config('site', 'path') ?: '/', '/').')' .
                // [4] should be empty (might contain one or more / and then maybe also index.php). Will be overwritten.
                // [5] will have the extracted actual URL part (besides site path)
                '((?!index.php\/)\/*(?:index.php\/)?)(.*)$/', $url, $matches)) {
        // if preg_match failed to match
        throw new Exception('No known change could be made to the URL.');
    }

    $matches[4] = 'index.php/'; // inject the index.php/ rewritethingy

    // remove the first element, which is the full matching string
    array_shift($matches);
    return implode($matches);
}

function common_inject_session($url, $serverpart = null)
{
    if (!common_have_session()) {
        return $url;
    }

    if (empty($serverpart)) {
        $serverpart = parse_url($url, PHP_URL_HOST);
    }

    $currentServer = (array_key_exists('HTTP_HOST', $_SERVER)) ? $_SERVER['HTTP_HOST'] : null;

    // Are we pointing to another server (like an SSL server?)

    if (!empty($currentServer) && 0 != strcasecmp($currentServer, $serverpart)) {
        // Pass the session ID as a GET parameter
        $sesspart = session_name() . '=' . session_id();
        $i = strpos($url, '?');
        if ($i === false) { // no GET params, just append
            $url .= '?' . $sesspart;
        } else {
            $url = substr($url, 0, $i + 1).$sesspart.'&'.substr($url, $i + 1);
        }
    }

    return $url;
}

function common_date_string($dt)
{
    // XXX: do some sexy date formatting
    // return date(DATE_RFC822, $dt);
    $t = strtotime($dt);
    $now = time();
    $diff = $now - $t;

    if ($now < $t) { // that shouldn't happen!
        return common_exact_date($dt);
    } else if ($diff < 60) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('a few seconds ago');
    } else if ($diff < 92) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about a minute ago');
    } else if ($diff < 3300) {
        $minutes = round($diff/60);
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return sprintf( _m('about one minute ago', 'about %d minutes ago', $minutes), $minutes);
    } else if ($diff < 5400) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about an hour ago');
    } else if ($diff < 22 * 3600) {
        $hours = round($diff/3600);
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return sprintf( _m('about one hour ago', 'about %d hours ago', $hours), $hours);
    } else if ($diff < 37 * 3600) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about a day ago');
    } else if ($diff < 24 * 24 * 3600) {
        $days = round($diff/(24*3600));
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return sprintf( _m('about one day ago', 'about %d days ago', $days), $days);
    } else if ($diff < 46 * 24 * 3600) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about a month ago');
    } else if ($diff < 330 * 24 * 3600) {
        $months = round($diff/(30*24*3600));
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return sprintf( _m('about one month ago', 'about %d months ago',$months), $months);
    } else if ($diff < 480 * 24 * 3600) {
        // TRANS: Used in notices to indicate when the notice was made compared to now.
        return _('about a year ago');
    } else {
        return common_exact_date($dt);
    }
}

function common_exact_date($dt)
{
    static $_utc;
    static $_siteTz;

    if (!$_utc) {
        $_utc = new DateTimeZone('UTC');
        $_siteTz = new DateTimeZone(common_timezone());
    }

    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, $_utc);
    $d->setTimezone($_siteTz);
    // TRANS: Human-readable full date-time specification (formatting on http://php.net/date)
    return $d->format(_('l, d-M-Y H:i:s T'));
}

function common_date_w3dtf($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format(DATE_W3C);
}

function common_date_rfc2822($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format('r');
}

function common_date_iso8601($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format('c');
}

function common_sql_now()
{
    return common_sql_date(time());
}

function common_sql_date($datetime)
{
    return strftime('%Y-%m-%d %H:%M:%S', $datetime);
}

/**
 * Return an SQL fragment to calculate an age-based weight from a given
 * timestamp or datetime column.
 *
 * @param string $column name of field we're comparing against current time
 * @param integer $dropoff divisor for age in seconds before exponentiation
 * @return string SQL fragment
 */
function common_sql_weight($column, $dropoff)
{
    if (common_config('db', 'type') == 'pgsql') {
        // PostgreSQL doesn't support timestampdiff function.
        // @fixme will this use the right time zone?
        // @fixme does this handle cross-year subtraction correctly?
        return "sum(exp(-extract(epoch from (now() - $column)) / $dropoff))";
    } else {
        return "sum(exp(timestampdiff(second, utc_timestamp(), $column) / $dropoff))";
    }
}

function common_redirect($url, $code=307)
{
    static $status = array(301 => "Moved Permanently",
                           302 => "Found",
                           303 => "See Other",
                           307 => "Temporary Redirect");

    header('HTTP/1.1 '.$code.' '.$status[$code]);
    header("Location: $url");
    header("Connection: close");

    $xo = new XMLOutputter();
    $xo->startXML('a',
                  '-//W3C//DTD XHTML 1.0 Strict//EN',
                  'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');
    $xo->element('a', array('href' => $url), $url);
    $xo->endXML();
    exit;
}

// Stick the notice on the queue

function common_enqueue_notice($notice)
{
    static $localTransports = array('ping');

    $transports = array();
    if (common_config('sms', 'enabled')) {
        $transports[] = 'sms';
    }
    if (Event::hasHandler('HandleQueuedNotice')) {
        $transports[] = 'plugin';
    }

    // We can skip these for gatewayed notices.
    if ($notice->isLocal()) {
        $transports = array_merge($transports, $localTransports);
    }

    if (Event::handle('StartEnqueueNotice', array($notice, &$transports))) {

        $qm = QueueManager::get();

        foreach ($transports as $transport)
        {
            $qm->enqueue($notice, $transport);
        }

        Event::handle('EndEnqueueNotice', array($notice, $transports));
    }

    return true;
}

function common_profile_url($nickname)
{
    return common_local_url('showstream', array('nickname' => $nickname),
                            null, null, false);
}

/**
 * Should make up a reasonable root URL
 */
function common_root_url($ssl=false)
{
    $url = common_path('', $ssl, false);
    $i = strpos($url, '?');
    if ($i !== false) {
        $url = substr($url, 0, $i);
    }
    return $url;
}

/**
 * returns $bytes bytes of raw random data
 */
function common_random_rawstr($bytes)
{
    $rawstr = @file_exists('/dev/urandom')
            ? common_urandom($bytes)
            : common_mtrand($bytes);

    return $rawstr;
}

/**
 * returns $bytes bytes of random data as a hexadecimal string
 */
function common_random_hexstr($bytes)
{
    $str = common_random_rawstr($bytes);

    $hexstr = '';
    for ($i = 0; $i < $bytes; $i++) {
        $hexstr .= sprintf("%02x", ord($str[$i]));
    }
    return $hexstr;
}

function common_urandom($bytes)
{
    $h = fopen('/dev/urandom', 'rb');
    // should not block
    $src = fread($h, $bytes);
    fclose($h);
    return $src;
}

function common_mtrand($bytes)
{
    $str = '';
    for ($i = 0; $i < $bytes; $i++) {
        $str .= chr(mt_rand(0, 255));
    }
    return $str;
}

/**
 * Record the given URL as the return destination for a future
 * form submission, to be read by common_get_returnto().
 *
 * @param string $url
 *
 * @fixme as a session-global setting, this can allow multiple forms
 * to conflict and overwrite each others' returnto destinations if
 * the user has multiple tabs or windows open.
 *
 * Should refactor to index with a token or otherwise only pass the
 * data along its intended path.
 */
function common_set_returnto($url)
{
    common_ensure_session();
    $_SESSION['returnto'] = $url;
}

/**
 * Fetch a return-destination URL previously recorded by
 * common_set_returnto().
 *
 * @return mixed URL string or null
 *
 * @fixme as a session-global setting, this can allow multiple forms
 * to conflict and overwrite each others' returnto destinations if
 * the user has multiple tabs or windows open.
 *
 * Should refactor to index with a token or otherwise only pass the
 * data along its intended path.
 */
function common_get_returnto()
{
    common_ensure_session();
    return (array_key_exists('returnto', $_SESSION)) ? $_SESSION['returnto'] : null;
}

function common_timestamp()
{
    return date('YmdHis');
}

function common_ensure_syslog()
{
    static $initialized = false;
    if (!$initialized) {
        openlog(common_config('syslog', 'appname'), 0,
            common_config('syslog', 'facility'));
        $initialized = true;
    }
}

function common_log_line($priority, $msg)
{
    static $syslog_priorities = array('LOG_EMERG', 'LOG_ALERT', 'LOG_CRIT', 'LOG_ERR',
                                      'LOG_WARNING', 'LOG_NOTICE', 'LOG_INFO', 'LOG_DEBUG');
    return date('Y-m-d H:i:s') . ' ' . $syslog_priorities[$priority] . ': ' . $msg . PHP_EOL;
}

function common_request_id()
{
    $pid = getmypid();
    $server = common_config('site', 'server');
    if (php_sapi_name() == 'cli') {
        $script = basename($_SERVER['PHP_SELF']);
        return "$server:$script:$pid";
    } else {
        static $req_id = null;
        if (!isset($req_id)) {
            $req_id = substr(md5(mt_rand()), 0, 8);
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            $url = $_SERVER['REQUEST_URI'];
        }
        $method = $_SERVER['REQUEST_METHOD'];
        return "$server:$pid.$req_id $method $url";
    }
}

function common_log($priority, $msg, $filename=null)
{
    if(Event::handle('StartLog', array(&$priority, &$msg, &$filename))){
	$msg = (empty($filename)) ? $msg : basename($filename) . ' - ' . $msg;
        $msg = '[' . common_request_id() . '] ' . $msg;
        $logfile = common_config('site', 'logfile');
        if ($logfile) {
            $log = fopen($logfile, "a");
            if ($log) {
                $output = common_log_line($priority, $msg);
                fwrite($log, $output);
                fclose($log);
            }
        } else {
            common_ensure_syslog();
            syslog($priority, $msg);
        }
        Event::handle('EndLog', array($priority, $msg, $filename));
    }
}

function common_debug($msg, $filename=null)
{
    if ($filename) {
        common_log(LOG_DEBUG, basename($filename).' - '.$msg);
    } else {
        common_log(LOG_DEBUG, $msg);
    }
}

function common_log_db_error(&$object, $verb, $filename=null)
{
    global $_PEAR;

    $objstr = common_log_objstring($object);
    $last_error = &$_PEAR->getStaticProperty('DB_DataObject','lastError');
    if (is_object($last_error)) {
        $msg = $last_error->message;
    } else {
        $msg = 'Unknown error (' . var_export($last_error, true) . ')';
    }
    common_log(LOG_ERR, $msg . '(' . $verb . ' on ' . $objstr . ')', $filename);
}

function common_log_objstring(&$object)
{
    if (is_null($object)) {
        return "null";
    }
    if (!($object instanceof DB_DataObject)) {
        return "(unknown)";
    }
    $arr = $object->toArray();
    $fields = array();
    foreach ($arr as $k => $v) {
        if (is_object($v)) {
            $fields[] = "$k='".get_class($v)."'";
        } else {
            $fields[] = "$k='$v'";
        }
    }
    $objstring = $object->tableName() . '[' . implode(',', $fields) . ']';
    return $objstring;
}

function common_valid_http_url($url, $secure=false)
{
    // If $secure is true, only allow https URLs to pass
    // (if false, we use '?' in 'https?' to say the 's' is optional)
    $regex = $secure ? '/^https$/' : '/^https?$/';
    return filter_var($url, FILTER_VALIDATE_URL)
            && preg_match($regex, parse_url($url, PHP_URL_SCHEME));
}

function common_valid_tag($tag)
{
    if (preg_match('/^tag:(.*?),(\d{4}(-\d{2}(-\d{2})?)?):(.*)$/', $tag, $matches)) {
        return (Validate::email($matches[1]) ||
                preg_match('/^([\w-\.]+)$/', $matches[1]));
    }
    return false;
}

/**
 * Determine if given domain or address literal is valid
 * eg for use in JIDs and URLs. Does not check if the domain
 * exists!
 *
 * @param string $domain
 * @return boolean valid or not
 */
function common_valid_domain($domain)
{
    $octet = "(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9])";
    $ipv4 = "(?:$octet(?:\.$octet){3})";
    if (preg_match("/^$ipv4$/u", $domain)) return true;

    $group = "(?:[0-9a-f]{1,4})";
    $ipv6 = "(?:\[($group(?::$group){0,7})?(::)?($group(?::$group){0,7})?\])"; // http://tools.ietf.org/html/rfc3513#section-2.2

    if (preg_match("/^$ipv6$/ui", $domain, $matches)) {
        $before = explode(":", $matches[1]);
        $zeroes = $matches[2];
        $after = explode(":", $matches[3]);
        if ($zeroes) {
            $min = 0;
            $max = 7;
        } else {
            $min = 1;
            $max = 8;
        }
        $explicit = count($before) + count($after);
        if ($explicit < $min || $explicit > $max) {
            return false;
        }
        return true;
    }

    try {
        require_once "Net/IDNA.php";
        $idn = Net_IDNA::getInstance();
        $domain = $idn->encode($domain);
    } catch (Exception $e) {
        return false;
    }

    $subdomain = "(?:[a-z0-9][a-z0-9-]*)"; // @fixme
    $fqdn = "(?:$subdomain(?:\.$subdomain)*\.?)";

    return preg_match("/^$fqdn$/ui", $domain);
}

/* Following functions are copied from MediaWiki GlobalFunctions.php
 * and written by Evan Prodromou. */

function common_accept_to_prefs($accept, $def = '*/*')
{
    // No arg means accept anything (per HTTP spec)
    if(!$accept) {
        return array($def => 1);
    }

    $prefs = array();

    $parts = explode(',', $accept);

    foreach($parts as $part) {
        // FIXME: doesn't deal with params like 'text/html; level=1'
        @list($value, $qpart) = explode(';', trim($part));
        $match = array();
        if(!isset($qpart)) {
            $prefs[$value] = 1;
        } elseif(preg_match('/q\s*=\s*(\d*\.\d+)/', $qpart, $match)) {
            $prefs[$value] = $match[1];
        }
    }

    return $prefs;
}

// Match by our supported file extensions
function common_supported_ext_to_mime($fileext)
{
    // Accept a filename and take out the extension
    if (strpos($fileext, '.') !== false) {
        $fileext = substr(strrchr($fileext, '.'), 1);
    }

    $supported = common_config('attachments', 'supported');
    if ($supported === true) {
        throw new ServerException('Supported extension but unknown mimetype relation.');
    }
    foreach($supported as $type => $ext) {
        if ($ext === $fileext) {
            return $type;
        }
    }

    throw new ServerException('Unsupported file extension');
}

// Match by our supported mime types
function common_supported_mime_to_ext($mimetype)
{
    $supported = common_config('attachments', 'supported');
    if ($supported === true) {
        throw new ServerException('Supported mimetype but unknown extension relation.');
    }
    foreach($supported as $type => $ext) {
        if ($mimetype === $type) {
            return $ext;
        }
    }

    throw new ServerException('Unsupported MIME type');
}

// The MIME "media" is the part before the slash (video in video/webm)
function common_get_mime_media($type)
{
    $tmp = explode('/', $type);
    return strtolower($tmp[0]);
}

// Get only the mimetype and not additional info (separated from bare mime with semi-colon)
function common_bare_mime($mimetype)
{
    $mimetype = mb_strtolower($mimetype);
    if ($semicolon = mb_strpos($mimetype, ';')) {
        $mimetype = mb_substr($mimetype, 0, $semicolon);
    }
    return $mimetype;
}

function common_mime_type_match($type, $avail)
{
    if(array_key_exists($type, $avail)) {
        return $type;
    } else {
        $parts = explode('/', $type);
        if(array_key_exists($parts[0] . '/*', $avail)) {
            return $parts[0] . '/*';
        } elseif(array_key_exists('*/*', $avail)) {
            return '*/*';
        } else {
            return null;
        }
    }
}

function common_negotiate_type($cprefs, $sprefs)
{
    $combine = array();

    foreach(array_keys($sprefs) as $type) {
        $parts = explode('/', $type);
        if($parts[1] != '*') {
            $ckey = common_mime_type_match($type, $cprefs);
            if($ckey) {
                $combine[$type] = $sprefs[$type] * $cprefs[$ckey];
            }
        }
    }

    foreach(array_keys($cprefs) as $type) {
        $parts = explode('/', $type);
        if($parts[1] != '*' && !array_key_exists($type, $sprefs)) {
            $skey = common_mime_type_match($type, $sprefs);
            if($skey) {
                $combine[$type] = $sprefs[$skey] * $cprefs[$type];
            }
        }
    }

    $bestq = 0;
    $besttype = 'text/html';

    foreach(array_keys($combine) as $type) {
        if($combine[$type] > $bestq) {
            $besttype = $type;
            $bestq = $combine[$type];
        }
    }

    if ('text/html' === $besttype) {
        return "text/html; charset=utf-8";
    }
    return $besttype;
}

function common_config($main, $sub=null)
{
    global $config;
    if (is_null($sub)) {
        // Return the config category array
        return array_key_exists($main, $config) ? $config[$main] : array();
    }
    // Return the config value
    return (array_key_exists($main, $config) &&
            array_key_exists($sub, $config[$main])) ? $config[$main][$sub] : false;
}

function common_config_set($main, $sub, $value)
{
    global $config;
    if (!array_key_exists($main, $config)) {
        $config[$main] = array();
    }
    $config[$main][$sub] = $value;
}

function common_config_append($main, $sub, $value)
{
    global $config;
    if (!array_key_exists($main, $config)) {
        $config[$main] = array();
    }
    if (!array_key_exists($sub, $config[$main])) {
        $config[$main][$sub] = array();
    }
    if (!is_array($config[$main][$sub])) {
        $config[$main][$sub] = array($config[$main][$sub]);
    }
    array_push($config[$main][$sub], $value);
}

/**
 * Pull arguments from a GET/POST/REQUEST array with first-level input checks:
 * strips "magic quotes" slashes if necessary, and kills invalid UTF-8 strings.
 *
 * @param array $from
 * @return array
 */
function common_copy_args($from)
{
    $to = array();
    $strip = get_magic_quotes_gpc();
    foreach ($from as $k => $v) {
        if(is_array($v)) {
            $to[$k] = common_copy_args($v);
        } else {
            if ($strip) {
                $v = stripslashes($v);
            }
            $to[$k] = strval(common_validate_utf8($v));
        }
    }
    return $to;
}

/**
 * Neutralise the evil effects of magic_quotes_gpc in the current request.
 * This is used before handing a request off to OAuthRequest::from_request.
 * @fixme Doesn't consider vars other than _POST and _GET?
 * @fixme Can't be undone and could corrupt data if run twice.
 */
function common_remove_magic_from_request()
{
    if(get_magic_quotes_gpc()) {
        $_POST=array_map('stripslashes',$_POST);
        $_GET=array_map('stripslashes',$_GET);
    }
}

function common_user_uri(&$user)
{
    return common_local_url('userbyid', array('id' => $user->id),
                            null, null, false);
}

// 36 alphanums - lookalikes (0, O, 1, I) = 32 chars = 5 bits

function common_confirmation_code($bits)
{
    // 36 alphanums - lookalikes (0, O, 1, I) = 32 chars = 5 bits
    static $codechars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $chars = ceil($bits/5);
    $code = '';
    for ($i = 0; $i < $chars; $i++) {
        // XXX: convert to string and back
        $num = hexdec(common_random_hexstr(1));
        // XXX: randomness is too precious to throw away almost
        // 40% of the bits we get!
        $code .= $codechars[$num%32];
    }
    return $code;
}

// convert markup to HTML
function common_markup_to_html($c, $args=null)
{
    if ($c === null) {
        return '';
    }

    if (is_null($args)) {
        $args = array();
    }

    // XXX: not very efficient

    foreach ($args as $name => $value) {
        $c = preg_replace('/%%arg.'.$name.'%%/', $value, $c);
    }

    $c = preg_replace_callback('/%%user.(\w+)%%/', function ($m) { return common_user_property($m[1]); }, $c);
    $c = preg_replace_callback('/%%action.(\w+)%%/', function ($m) { return common_local_url($m[1]); }, $c);
    $c = preg_replace_callback('/%%doc.(\w+)%%/', function ($m) { return common_local_url('doc', array('title'=>$m[1])); }, $c);
    $c = preg_replace_callback('/%%(\w+).(\w+)%%/', function ($m) { return common_config($m[1], $m[2]); }, $c);

    return \Michelf\Markdown::defaultTransform($c);
}

function common_user_property($property)
{
    $profile = Profile::current();

    if (empty($profile)) {
        return null;
    }

    switch ($property) {
    case 'profileurl':
    case 'nickname':
    case 'fullname':
    case 'location':
    case 'bio':
        return $profile->$property;
        break;
    case 'avatar':
        try {
            return $profile->getAvatar(AVATAR_STREAM_SIZE);
        } catch (Exception $e) {
            return null;
        }
        break;
    case 'bestname':
        return $profile->getBestName();
        break;
    default:
        return null;
    }
}

function common_profile_uri($profile)
{
    $uri = null;

    if (!empty($profile)) {
        if (Event::handle('StartCommonProfileURI', array($profile, &$uri))) {
            $user = User::getKV('id', $profile->id);
            if ($user instanceof User) {
                $uri = $user->getUri();
            }
            Event::handle('EndCommonProfileURI', array($profile, &$uri));
        }
    }

    // XXX: this is a very bad profile!
    return $uri;
}

function common_canonical_sms($sms)
{
    // strip non-digits
    preg_replace('/\D/', '', $sms);
    return $sms;
}

function common_error_handler($errno, $errstr, $errfile, $errline, $errcontext)
{
    switch ($errno) {

     case E_ERROR:
     case E_COMPILE_ERROR:
     case E_CORE_ERROR:
     case E_USER_ERROR:
     case E_PARSE:
     case E_RECOVERABLE_ERROR:
        common_log(LOG_ERR, "[$errno] $errstr ($errfile:$errline) [ABORT]");
        die();
        break;

     case E_WARNING:
     case E_COMPILE_WARNING:
     case E_CORE_WARNING:
     case E_USER_WARNING:
        common_log(LOG_WARNING, "[$errno] $errstr ($errfile:$errline)");
        break;

     case E_NOTICE:
     case E_USER_NOTICE:
        common_log(LOG_NOTICE, "[$errno] $errstr ($errfile:$errline)");
        break;

     case E_STRICT:
     case E_DEPRECATED:
     case E_USER_DEPRECATED:
        // XXX: config variable to log this stuff, too
        break;

     default:
        common_log(LOG_ERR, "[$errno] $errstr ($errfile:$errline) [UNKNOWN LEVEL, die()'ing]");
        die();
        break;
    }

    // FIXME: show error page if we're on the Web
    /* Don't execute PHP internal error handler */
    return true;
}

function common_session_token()
{
    common_ensure_session();
    if (!array_key_exists('token', $_SESSION)) {
        $_SESSION['token'] = common_random_hexstr(64);
    }
    return $_SESSION['token'];
}

function common_license_terms($uri)
{
    if(preg_match('/creativecommons.org\/licenses\/([^\/]+)/', $uri, $matches)) {
        return explode('-',$matches[1]);
    }
    return array($uri);
}

function common_compatible_license($from, $to)
{
    $from_terms = common_license_terms($from);
    // public domain and cc-by are compatible with everything
    if(count($from_terms) == 1 && ($from_terms[0] == 'publicdomain' || $from_terms[0] == 'by')) {
        return true;
    }
    $to_terms = common_license_terms($to);
    // sa is compatible across versions. IANAL
    if(in_array('sa',$from_terms) || in_array('sa',$to_terms)) {
        return count(array_diff($from_terms, $to_terms)) == 0;
    }
    // XXX: better compatibility check needed here!
    // Should at least normalise URIs
    return ($from == $to);
}

/**
 * returns a quoted table name, if required according to config
 */
function common_database_tablename($tablename)
{
  if(common_config('db','quote_identifiers')) {
      $tablename = '"'. $tablename .'"';
  }
  //table prefixes could be added here later
  return $tablename;
}

/**
 * Shorten a URL with the current user's configured shortening service,
 * or ur1.ca if configured, or not at all if no shortening is set up.
 *
 * @param string  $long_url original URL
 * @param User $user to specify a particular user's options
 * @param boolean $force    Force shortening (used when notice is too long)
 * @return string may return the original URL if shortening failed
 *
 * @fixme provide a way to specify a particular shortener
 */
function common_shorten_url($long_url, User $user=null, $force = false)
{
    $long_url = trim($long_url);

    $user = common_current_user();

    $maxUrlLength = User_urlshortener_prefs::maxUrlLength($user);

    // $force forces shortening even if it's not strictly needed
    // I doubt URL shortening is ever 'strictly' needed. - ESP

    if (($maxUrlLength == -1 || mb_strlen($long_url) < $maxUrlLength) && !$force) {
        return $long_url;
    }

    $shortenerName = User_urlshortener_prefs::urlShorteningService($user);

    if (Event::handle('StartShortenUrl',
                      array($long_url, $shortenerName, &$shortenedUrl))) {
        if ($shortenerName == 'internal') {
            try {
                $f = File::processNew($long_url);
                $shortenedUrl = common_local_url('redirecturl', array('id' => $f->id));
                if ((mb_strlen($shortenedUrl) < mb_strlen($long_url)) || $force) {
                    return $shortenedUrl;
                } else {
                    return $long_url;
                }
            } catch (ServerException $e) {
                return $long_url;
            }
        } else {
            return $long_url;
        }
    } else {
        //URL was shortened, so return the result
        return trim($shortenedUrl);
    }
}

/**
 * @return mixed array($proxy, $ip) for web requests; proxy may be null
 *               null if not a web request
 *
 * @fixme X-Forwarded-For can be chained by multiple proxies;
          we should parse the list and provide a cleaner array
 * @fixme X-Forwarded-For can be forged by clients; only use them if trusted
 * @fixme X_Forwarded_For headers will override X-Forwarded-For read through $_SERVER;
 *        use function to get exact request headers from Apache if possible.
 */
function common_client_ip()
{
    if (!isset($_SERVER) || !array_key_exists('REQUEST_METHOD', $_SERVER)) {
        return null;
    }

    if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
        if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
            $proxy = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $proxy = $_SERVER['REMOTE_ADDR'];
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $proxy = null;
        if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    }

    return array($proxy, $ip);
}

function common_url_to_nickname($url)
{
    static $bad = array('query', 'user', 'password', 'port', 'fragment');

    $parts = parse_url($url);

    // If any of these parts exist, this won't work

    foreach ($bad as $badpart) {
        if (array_key_exists($badpart, $parts)) {
            return null;
        }
    }

    // We just have host and/or path

    // If it's just a host...
    if (array_key_exists('host', $parts) &&
        (!array_key_exists('path', $parts) || strcmp($parts['path'], '/') == 0))
    {
        $hostparts = explode('.', $parts['host']);

        // Try to catch common idiom of nickname.service.tld

        if ((count($hostparts) > 2) &&
            (strlen($hostparts[count($hostparts) - 2]) > 3) && # try to skip .co.uk, .com.au
            (strcmp($hostparts[0], 'www') != 0))
        {
            return common_nicknamize($hostparts[0]);
        } else {
            // Do the whole hostname
            return common_nicknamize($parts['host']);
        }
    } else {
        if (array_key_exists('path', $parts)) {
            // Strip starting, ending slashes
            $path = preg_replace('@/$@', '', $parts['path']);
            $path = preg_replace('@^/@', '', $path);
            $path = basename($path);

            // Hack for MediaWiki user pages, in the form:
            // http://example.com/wiki/User:Myname
            // ('User' may be localized.)
            if (strpos($path, ':')) {
                $parts = array_filter(explode(':', $path));
                $path = $parts[count($parts) - 1];
            }

            if ($path) {
                return common_nicknamize($path);
            }
        }
    }

    return null;
}

function common_nicknamize($str)
{
    try {
        return Nickname::normalize($str);
    } catch (NicknameException $e) {
        return null;
    }
}

function common_perf_counter($key, $val=null)
{
    global $_perfCounters;
    if (isset($_perfCounters)) {
        if (common_config('site', 'logperf')) {
            if (array_key_exists($key, $_perfCounters)) {
                $_perfCounters[$key][] = $val;
            } else {
                $_perfCounters[$key] = array($val);
            }
            if (common_config('site', 'logperf_detail')) {
                common_log(LOG_DEBUG, "PERF COUNTER HIT: $key $val");
            }
        }
    }
}

function common_log_perf_counters()
{
    if (common_config('site', 'logperf')) {
        global $_startTime, $_perfCounters;

        if (isset($_startTime)) {
            $endTime = microtime(true);
            $diff = round(($endTime - $_startTime) * 1000);
            common_log(LOG_DEBUG, "PERF runtime: ${diff}ms");
        }
        $counters = $_perfCounters;
        ksort($counters);
        foreach ($counters as $key => $values) {
            $count = count($values);
            $unique = count(array_unique($values));
            common_log(LOG_DEBUG, "PERF COUNTER: $key $count ($unique unique)");
        }
    }
}

function common_is_email($str)
{
    return (strpos($str, '@') !== false);
}

function common_init_stats()
{
    global $_mem, $_ts;

    $_mem = memory_get_usage(true);
    $_ts  = microtime(true);
}

function common_log_delta($comment=null)
{
    global $_mem, $_ts;

    $mold = $_mem;
    $told = $_ts;

    $_mem = memory_get_usage(true);
    $_ts  = microtime(true);

    $mtotal = $_mem - $mold;
    $ttotal = $_ts - $told;

    if (empty($comment)) {
        $comment = 'Delta';
    }

    common_debug(sprintf("%s: %d %d", $comment, $mtotal, round($ttotal * 1000000)));
}

function common_strip_html($html, $trim=true, $save_whitespace=false)
{
    if (!$save_whitespace) {
        $html = preg_replace('/\s+/', ' ', $html);
    }
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    return $trim ? trim($text) : $text;
}

function html_sprintf()
{
    $args = func_get_args();
    for ($i=1; $i<count($args); $i++) {
        $args[$i] = htmlspecialchars($args[$i]);
    }
    return call_user_func_array('sprintf', $args);
}

function _ve($var)
{
    return var_export($var, true);
}
