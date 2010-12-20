<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for sitemap-generating actions
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
 * @category  Sitemap
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * superclass for sitemap actions
 *
 * @category Sitemap
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class SitemapAction extends Action
{
    /**
     * handle the action
     *
     * @param array $args unused.
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        header('Content-Type: text/xml; charset=UTF-8');
        $this->startXML();

        $this->elementStart('urlset', array('xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9'));

        while (list($url, $lm, $cf, $p) = $this->nextUrl()) {
            $this->showUrl($url, $lm, $cf, $p);
        }

        $this->elementEnd('urlset');

        $this->endXML();
    }

    function lastModified()
    {
        $y = $this->trimmed('year');

        $m = $this->trimmed('month');
        $d = $this->trimmed('day');

        $y += 0;
        $m += 0;
        $d += 0;

        $begdate = strtotime("$y-$m-$d 00:00:00");
        $enddate = $begdate + (24 * 60 * 60);

        if ($enddate < time()) {
            return $enddate;
        } else {
            return null;
        }
    }

    function showUrl($url, $lastMod=null, $changeFreq=null, $priority=null)
    {
        $this->elementStart('url');
        $this->element('loc', null, $url);
        if (!is_null($lastMod)) {
            $this->element('lastmod', null, $lastMod);
        }
        if (!is_null($changeFreq)) {
            $this->element('changefreq', null, $changeFreq);
        }
        if (!is_null($priority)) {
            $this->element('priority', null, $priority);
        }
        $this->elementEnd('url');
    }

    function nextUrl()
    {
        return null;
    }

    function isReadOnly()
    {
        return true;
    }
}
