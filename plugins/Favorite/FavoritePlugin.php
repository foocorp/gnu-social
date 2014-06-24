<?php
/*
 * GNU Social - a federating social network
 * Copyright (C) 2014, Free Software Foundation, Inc.
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

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * @package     UI
 * @maintainer  Mikael Nordfeldth <mmn@hethane.se>
 */
class FavoritePlugin extends Plugin
{
    public function onRouterInitialized(URLMapper $m)
    {
        // Web UI actions
        $m->connect('main/favor', array('action' => 'favor'));
        $m->connect('main/disfavor', array('action' => 'disfavor'));

        if (common_config('singleuser', 'enabled')) {
            $nickname = User::singleUserNickname();

            $m->connect('favorites',
                        array('action' => 'showfavorites',
                              'nickname' => $nickname));
            $m->connect('favoritesrss',
                        array('action' => 'favoritesrss',
                              'nickname' => $nickname));
        } else {
            $m->connect('favoritedrss', array('action' => 'favoritedrss'));
            $m->connect('favorited/', array('action' => 'favorited'));
            $m->connect('favorited', array('action' => 'favorited'));

            $m->connect(':nickname/favorites',
                        array('action' => 'showfavorites'),
                        array('nickname' => Nickname::DISPLAY_FMT));
            $m->connect(':nickname/favorites/rss',
                        array('action' => 'favoritesrss'),
                        array('nickname' => Nickname::DISPLAY_FMT));
        }

        // Favorites for API
        $m->connect('api/favorites/create.:format',
                    array('action' => 'ApiFavoriteCreate',
                          'format' => '(xml|json)'));
        $m->connect('api/favorites/destroy.:format',
                    array('action' => 'ApiFavoriteDestroy',
                          'format' => '(xml|json)'));
        $m->connect('api/favorites/list.:format',
                    array('action' => 'ApiTimelineFavorites',
                          'format' => '(xml|json|rss|atom|as)'));
        $m->connect('api/favorites/:id.:format',
                    array('action' => 'ApiTimelineFavorites',
                          'id' => Nickname::INPUT_FMT,
                          'format' => '(xml|json|rss|atom|as)'));
        $m->connect('api/favorites.:format',
                    array('action' => 'ApiTimelineFavorites',
                          'format' => '(xml|json|rss|atom|as)'));
        $m->connect('api/favorites/create/:id.:format',
                    array('action' => 'ApiFavoriteCreate',
                          'id' => '[0-9]+',
                          'format' => '(xml|json)'));
        $m->connect('api/favorites/destroy/:id.:format',
                    array('action' => 'ApiFavoriteDestroy',
                          'id' => '[0-9]+',
                          'format' => '(xml|json)'));

        // AtomPub API
        $m->connect('api/statusnet/app/favorites/:profile/:notice.atom',
                    array('action' => 'AtomPubShowFavorite'),
                    array('profile' => '[0-9]+',
                          'notice' => '[0-9]+'));

        $m->connect('api/statusnet/app/favorites/:profile.atom',
                    array('action' => 'AtomPubFavoriteFeed'),
                    array('profile' => '[0-9]+'));

        // Required for qvitter API
        $m->connect('api/statuses/favs/:id.:format',
                    array('action' => 'ApiStatusesFavs',
                          'id' => '[0-9]+',
                          'format' => '(xml|json)'));
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Favorite',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://gnu.io/',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('Favorites (likes) using ActivityStreams.'));

        return true;
    }
}
