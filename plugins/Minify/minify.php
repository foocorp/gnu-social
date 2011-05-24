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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class MinifyAction extends Action
{
    const TYPE_CSS = 'text/css';
    const TYPE_HTML = 'text/html';
    // there is some debate over the ideal JS Content-Type, but this is the
    // Apache default and what Yahoo! uses..
    const TYPE_JS = 'application/x-javascript';

    var $file;
    var $v;

    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);
        $this->v = $args['v'];

        $f = $this->arg('f');
        if(isset($f)) {
            $this->file = INSTALLDIR.'/'.$f;
            if(file_exists($this->file)) {
                return true;
            } else {
                // TRANS: Client error displayed when not providing a valid path in parameter "f".
                $this->clientError(_m('The parameter "f" is not a valid path.'),404);
                return false;
            }
        }else{
            // TRANS: Client error displayed when not providing parameter "f".
            $this->clientError(_m('The parameter "f" is required but missing.'),500);
            return false;
        }
    }

    function etag()
    {
        if(isset($this->v)) {
            return "\"" . crc32($this->file . $this->v) . "\"";
        }else{
            $stat = stat($this->file);
            return '"' . $stat['ino'] . '-' . $stat['size'] . '-' . $stat['mtime'] . '"';
        }
    }

    function lastModified()
    {
        return filemtime($this->file);
    }

    function handle($args)
    {
        parent::handle($args);

        $c = Cache::instance();
        if (!empty($c)) {
            $cacheKey = Cache::key(MinifyPlugin::cacheKey . ':' . $this->file . '?v=' . empty($this->v)?'':$this->v);
            $out = $c->get($cacheKey);
        }
        if(empty($out)) {
            $out = $this->minify($this->file);
        }
        if (!empty($c)) {
            $c->set($cacheKey, $out);
        }

        $sec = session_cache_expire() * 60;
        header('Cache-Control: public, max-age=' . $sec);
        header('Pragma: public');
        $this->raw($out);
    }

    function minify($file)
    {
        $info = pathinfo($file);
        switch(strtolower($info['extension'])){
            case 'js':
                $out = MinifyPlugin::minifyJs(file_get_contents($file));
                header('Content-Type: ' . self::TYPE_JS);
                break;
            case 'css':
                $options = array();
                $options['currentDir'] = dirname($file);
                $options['docRoot'] = INSTALLDIR;
                $out = MinifyPlugin::minifyCss(file_get_contents($file),$options);
                header('Content-Type: ' . self::TYPE_CSS);
                break;
            default:
                // TRANS: Client error displayed when trying to minify an unsupported file type.
                $this->clientError(_m('File type not supported.'),500);
                return false;
        }
        return $out;
    }
}
