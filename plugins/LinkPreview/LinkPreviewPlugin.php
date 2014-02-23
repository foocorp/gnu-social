<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Some UI extras for now...
 *
 * @package LinkPreviewPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */
class LinkPreviewPlugin extends Plugin
{
    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'LinkPreview',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:LinkPreview',
                            'rawdescription' =>
                            // TRANS: Plugin description.
                            _m('UI extension for previewing thumbnails from links.'));

        return true;
    }

    /**
     * Load JS at runtime if we're logged in.
     *
     * @param Action $action
     * @return boolean hook result
     */
    function onEndShowScripts($action)
    {
        $user = common_current_user();
        if ($user && common_config('attachments', 'process_links')) {
            $action->script($this->path('js/linkpreview.js'));
            $data = json_encode(array(
                'api' => common_local_url('oembedproxy'),
                'width' => common_config('attachments', 'thumbwidth'),
                'height' => common_config('attachments', 'thumbheight'),
            ));
            $action->inlineScript('$(function() {SN.Init.LinkPreview && SN.Init.LinkPreview('.$data.');})');
        }
        return true;
    }

    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m URL mapper
     *
     * @return boolean hook return
     */
    function onStartInitializeRouter($m)
    {
        $m->connect('main/oembed/proxy',
                array('action' => 'oembedproxy'));

        return true;
    }
}
