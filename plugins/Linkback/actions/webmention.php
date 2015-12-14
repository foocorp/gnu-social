<?php
/*
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

if (!defined('STATUSNET')) {
    exit(1);
}

class WebmentionAction extends Action
{
    protected function handle()
    {
        GNUsocial::setApi(true); // Minimize error messages to aid in debugging
        parent::handle();
        if ($this->isPost()) {
            return $this->handlePost();
        }

        return false;
    }

    function handlePost()
    {
        $source = $this->arg('source');
        $target = $this->arg('target');

        header('Content-Type: text/plain; charset=utf-8');

        if(!$source) {
            echo _m('"source" is missing')."\n";
            throw new ServerException(_m('"source" is missing'), 400);
        }

        if(!$target) {
            echo _m('"target" is missing')."\n";
            throw new ServerException(_m('"target" is missing'), 400);
        }

        $response = linkback_get_source($source, $target);
        if(!$response) {
            echo _m('Source does not link to target.')."\n";
            throw new ServerException(_m('Source does not link to target.'), 400);
        }

        $notice = linkback_get_target($target);
        if(!$notice) {
            echo _m('Target not found')."\n";
            throw new ServerException(_m('Target not found'), 404);
        }

        $url = linkback_save($source, $target, $response, $notice);
        if(!$url) {
            echo _m('An error occured while saving.')."\n";
            throw new ServerException(_m('An error occured while saving.'), 500);
        }

        echo $url."\n";

        return true;
    }
}
