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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Table Definition for file_redirection
 */

class File_redirection extends Managed_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'file_redirection';                // table name
    public $url;                             // varchar(255)  primary_key not_null
    public $file_id;                         // int(4)
    public $redirections;                    // int(4)
    public $httpcode;                        // int(4)
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    public static function schemaDef()
    {
        return array(
            'fields' => array(
                'url' => array('type' => 'varchar', 'length' => 255, 'not null' => true, 'description' => 'short URL (or any other kind of redirect) for file (id)'),
                'file_id' => array('type' => 'int', 'description' => 'short URL for what URL/file'),
                'redirections' => array('type' => 'int', 'description' => 'redirect count'),
                'httpcode' => array('type' => 'int', 'description' => 'HTTP status code (20x, 30x, etc.)'),
                'modified' => array('type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified'),
            ),
            'primary key' => array('url'),
            'foreign keys' => array(
                'file_redirection_file_id_fkey' => array('file' => array('file_id' => 'id')),
            ),
        );
    }

    static function _commonHttp($url, $redirs) {
        $request = new HTTPClient($url);
        $request->setConfig(array(
            'connect_timeout' => 10, // # seconds to wait
            'max_redirs' => $redirs, // # max number of http redirections to follow
            'follow_redirects' => true, // Follow redirects
            'store_body' => false, // We won't need body content here.
        ));
        return $request;
    }

    /**
     * Check if this URL is a redirect and return redir info.
     *
     * Most code should call File_redirection::where instead, to check if we
     * already know that redirection and avoid extra hits to the web.
     *
     * The URL is hit and any redirects are followed, up to 10 levels or until
     * a protected URL is reached.
     *
     * @param string $in_url
     * @return mixed one of:
     *         string - target URL, if this is a direct link or can't be followed
     *         array - redirect info if this is an *unknown* redirect:
     *              associative array with the following elements:
     *                code: HTTP status code
     *                redirects: count of redirects followed
     *                url: URL string of final target
     *                type (optional): MIME type from Content-Type header
     *                size (optional): byte size from Content-Length header
     *                time (optional): timestamp from Last-Modified header
     */
    public function lookupWhere($short_url, $redirs = 10, $protected = false) {
        if ($redirs < 0) return false;

        if(strpos($short_url,'://') === false){
            return $short_url;
        }
        try {
            $request = self::_commonHttp($short_url, $redirs);
            // Don't include body in output
            $request->setMethod(HTTP_Request2::METHOD_HEAD);
            $response = $request->send();

            if (405 == $response->getStatus() || 204 == $response->getStatus()) {
                // HTTP 405 Unsupported Method
                // Server doesn't support HEAD method? Can this really happen?
                // We'll try again as a GET and ignore the response data.
                //
                // HTTP 204 No Content
                // YFrog sends 204 responses back for our HEAD checks, which
                // seems like it may be a logic error in their servers. If
                // we get a 204 back, re-run it as a GET... if there's really
                // no content it'll be cheap. :)
                $request = self::_commonHttp($short_url, $redirs);
                $response = $request->send();
            }
        } catch (Exception $e) {
            // Invalid URL or failure to reach server
            common_log(LOG_ERR, "Error while following redirects for $short_url: " . $e->getMessage());
            return $short_url;
        }

        if ($response->getRedirectCount() && File::isProtected($response->getUrl())) {
            // Bump back up the redirect chain until we find a non-protected URL
            return self::lookupWhere($short_url, $response->getRedirectCount() - 1, true);
        }

        $ret = array('code' => $response->getStatus()
                , 'redirects' => $response->getRedirectCount()
                , 'url' => $response->getUrl());

        $type = $response->getHeader('Content-Type');
        if ($type) $ret['type'] = $type;
        if ($protected) $ret['protected'] = true;
        $size = $response->getHeader('Content-Length'); // @fixme bytes?
        if ($size) $ret['size'] = $size;
        $time = $response->getHeader('Last-Modified');
        if ($time) $ret['time'] = strtotime($time);
        return $ret;
    }

    /**
     * Check if this URL is a redirect and return redir info.
     * If a File record is present for this URL, it is not considered a redirect.
     * If a File_redirection record is present for this URL, the recorded target is returned.
     *
     * If no File or File_redirect record is present, the URL is hit and any
     * redirects are followed, up to 10 levels or until a protected URL is
     * reached.
     *
     * @param string $in_url
     * @param boolean $discover true to attempt dereferencing the redirect if we don't know it already
     * @return mixed one of:
     *         string - target URL, if this is a direct link or a known redirect
     *         array - redirect info if this is an *unknown* redirect:
     *              associative array with the following elements:
     *                code: HTTP status code
     *                redirects: count of redirects followed
     *                url: URL string of final target
     *                type (optional): MIME type from Content-Type header
     *                size (optional): byte size from Content-Length header
     *                time (optional): timestamp from Last-Modified header
     */
    public function where($in_url, $discover=true) {
        // let's see if we know this...
        $a = File::getKV('url', $in_url);

        if (!empty($a)) {
            // this is a direct link to $a->url
            return $a->url;
        } else {
            $b = File_redirection::getKV('url', $in_url);
            if (!empty($b)) {
                // this is a redirect to $b->file_id
                $a = File::getKV('id', $b->file_id);
                return $a->url;
            }
        }

        if ($discover) {
            $ret = File_redirection::lookupWhere($in_url);
            return $ret;
        } else {
            // No manual dereferencing; leave the unknown URL as is.
            return $in_url;
        }
    }

    /**
     * Shorten a URL with the current user's configured shortening
     * options, if applicable.
     *
     * If it cannot be shortened or the "short" URL is longer than the
     * original, the original is returned.
     *
     * If the referenced item has not been seen before, embedding data
     * may be saved.
     *
     * @param string $long_url
     * @param User $user whose shortening options to use; defaults to the current web session user
     * @return string
     */
    function makeShort($long_url, $user=null)
    {
        $canon = File_redirection::_canonUrl($long_url);

        $short_url = File_redirection::_userMakeShort($canon, $user);

        // Did we get one? Is it shorter?

        if (!empty($short_url)) {
            return $short_url;
        } else {
            return $long_url;
        }
    }

    /**
     * Shorten a URL with the current user's configured shortening
     * options, if applicable.
     *
     * If it cannot be shortened or the "short" URL is longer than the
     * original, the original is returned.
     *
     * If the referenced item has not been seen before, embedding data
     * may be saved.
     *
     * @param string $long_url
     * @return string
     */

    function forceShort($long_url, $user)
    {
        $canon = File_redirection::_canonUrl($long_url);

        $short_url = File_redirection::_userMakeShort($canon, $user, true);

        // Did we get one? Is it shorter?
        if (!empty($short_url)) {
            return $short_url;
        } else {
            return $long_url;
        }
    }

    function _userMakeShort($long_url, User $user=null, $force = false) {
        $short_url = common_shorten_url($long_url, $user, $force);
        if (!empty($short_url) && $short_url != $long_url) {
            $short_url = (string)$short_url;
            // store it
            $file = File::getKV('url', $long_url);
            if ($file instanceof File) {
                $file_id = $file->id;
            } else {
                // Check if the target URL is itself a redirect...
                $redir_data = File_redirection::where($long_url);
                if (is_array($redir_data)) {
                    // We haven't seen the target URL before.
                    // Save file and embedding data about it!
                    $file = File::saveNew($redir_data, $long_url);
                    $file_id = $file->id;
                } else if (is_string($redir_data)) {
                    // The file is a known redirect target.
                    $file = File::getKV('url', $redir_data);
                    if (empty($file)) {
                        // @fixme should we save a new one?
                        // this case was triggering sometimes for redirects
                        // with unresolvable targets; found while fixing
                        // "can't linkify" bugs with shortened links to
                        // SSL sites with cert issues.
                        return null;
                    }
                    $file_id = $file->id;
                }
            }
            $file_redir = File_redirection::getKV('url', $short_url);
            if (!$file_redir instanceof File_redirection) {
                $file_redir = new File_redirection;
                $file_redir->url = $short_url;
                $file_redir->file_id = $file_id;
                $file_redir->insert();
            }
            return $short_url;
        }
        return null;
    }

    /**
     * Basic attempt to canonicalize a URL, cleaning up some standard variants
     * such as funny syntax or a missing path. Used internally when cleaning
     * up URLs for storage and following redirect chains.
     *
     * Note that despite being on File_redirect, this function DOES NOT perform
     * any dereferencing of redirects.
     *
     * @param string $in_url input URL
     * @param string $default_scheme if given a bare link; defaults to 'http://'
     * @return string
     */
    function _canonUrl($in_url, $default_scheme = 'http://') {
        if (empty($in_url)) return false;
        $out_url = $in_url;
        $p = parse_url($out_url);
        if (empty($p['host']) || empty($p['scheme'])) {
            list($scheme) = explode(':', $in_url, 2);
            switch (strtolower($scheme)) {
            case 'fax':
            case 'tel':
                $out_url = str_replace('.-()', '', $out_url);
                break;

            case 'mailto':
            case 'aim':
            case 'jabber':
            case 'xmpp':
                // don't touch anything
                break;

            default:
                $out_url = $default_scheme . ltrim($out_url, '/');
                $p = parse_url($out_url);
                if (empty($p['scheme'])) return false;
                break;
            }
        }

        if (('ftp' == $p['scheme']) || ('ftps' == $p['scheme']) || ('http' == $p['scheme']) || ('https' == $p['scheme'])) {
            if (empty($p['host'])) return false;
            if (empty($p['path'])) {
                $out_url .= '/';
            }
        }

        return $out_url;
    }

    function saveNew($data, $file_id, $url) {
        $file_redir = new File_redirection;
        $file_redir->url = $url;
        $file_redir->file_id = $file_id;
        $file_redir->redirections = intval($data['redirects']);
        $file_redir->httpcode = intval($data['code']);
        $file_redir->insert();
    }
}
