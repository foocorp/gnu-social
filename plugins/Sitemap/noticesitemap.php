<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show list of user pages
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
 * sitemap for users
 *
 * @category Sitemap
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class NoticesitemapAction extends SitemapAction
{
    var $notices = null;
    var $j = 0;

    function prepare($args)
    {
        parent::prepare($args);

        $y = $this->trimmed('year');

        $m = $this->trimmed('month');
        $d = $this->trimmed('day');

        $i = $this->trimmed('index');

        $y += 0;
        $m += 0;
        $d += 0;
        $i += 0;

        $this->notices = $this->getNotices($y, $m, $d, $i);
        $this->j       = 0;

        return true;
    }

    function nextUrl()
    {
        if ($this->j < count($this->notices)) {
            $n = $this->notices[$this->j];
            $this->j++;
            return array(common_local_url('shownotice', array('notice' => $n[0])),
                         common_date_w3dtf($n[1]),
                         'never',
                         null);
        } else {
            return null;
        }
    }

    function getNotices($y, $m, $d, $i)
    {
        $n = Notice::cacheGet("sitemap:notice:$y:$m:$d:$i");

        if ($n === false) {

            $notice = new Notice();

            $begindt = sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d);

            // XXX: estimates 1d == 24h, which screws up days
            // with leap seconds (1d == 24h + 1s). Thankfully they're
            // few and far between.

            $theend = strtotime($begindt) + (24 * 60 * 60);
            $enddt  = common_sql_date($theend);

            $notice->selectAdd();
            $notice->selectAdd('id, created');

            $notice->whereAdd("created >= '$begindt'");
            $notice->whereAdd("created <  '$enddt'");

            $notice->whereAdd('is_local = ' . Notice::LOCAL_PUBLIC);

            $notice->orderBy('created');

            $offset = ($i-1) * SitemapPlugin::NOTICES_PER_MAP;
            $limit  = SitemapPlugin::NOTICES_PER_MAP;

            $notice->limit($offset, $limit);

            $notice->find();

            $n = array();

            while ($notice->fetch()) {
                $n[] = array($notice->id, $notice->created);
            }

            $c = Cache::instance();

            if (!empty($c)) {
                $c->set(Cache::key("sitemap:notice:$y:$m:$d:$i"),
                        $n,
                        Cache::COMPRESSED,
                        ((time() > $theend) ? (time() + 90 * 24 * 60 * 60) : (time() + 5 * 60)));
            }
        }

        return $n;
    }
}
