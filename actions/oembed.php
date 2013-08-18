<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * StatusNet-only extensions to the Twitter-like API
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
 * @category  Twitter
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Oembed provider implementation
 *
 * This class handles all /main/oembed(.xml|.json)/ requests.
 *
 * @category  oEmbed
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class OembedAction extends Action
{

    function handle($args)
    {
        common_debug("in oembed api action");

        $url = $args['url'];
        if( substr(strtolower($url),0,strlen(common_root_url())) == strtolower(common_root_url()) ){
            $path = substr($url,strlen(common_root_url()));

            $r = Router::get();

            $proxy_args = $r->map($path);

            if (!$proxy_args) {
                // TRANS: Server error displayed in oEmbed action when path not found.
                // TRANS: %s is a path.
                $this->serverError(sprintf(_('"%s" not found.'),$path), 404);
            }
            $oembed=array();
            $oembed['version']='1.0';
            $oembed['provider_name']=common_config('site', 'name');
            $oembed['provider_url']=common_root_url();
            switch($proxy_args['action']){
                case 'shownotice':
                    $oembed['type']='link';
                    $id = $proxy_args['notice'];
                    $notice = Notice::getKV($id);
                    if(empty($notice)){
                        // TRANS: Server error displayed in oEmbed action when notice not found.
                        // TRANS: %s is a notice.
                        $this->serverError(sprintf(_("Notice %s not found."),$id), 404);
                    }
                    $profile = $notice->getProfile();
                    if (empty($profile)) {
                        // TRANS: Server error displayed in oEmbed action when notice has not profile.
                        $this->serverError(_('Notice has no profile.'), 500);
                    }
                    $authorname = $profile->getFancyName();
                    // TRANS: oEmbed title. %1$s is the author name, %2$s is the creation date.
                    $oembed['title'] = sprintf(_('%1$s\'s status on %2$s'),
                        $authorname,
                        common_exact_date($notice->created));
                    $oembed['author_name']=$authorname;
                    $oembed['author_url']=$profile->profileurl;
                    $oembed['url']=($notice->url?$notice->url:$notice->uri);
                    $oembed['html']=$notice->rendered;
                    break;
                case 'attachment':
                    $id = $proxy_args['attachment'];
                    $attachment = File::getKV($id);
                    if(empty($attachment)){
                        // TRANS: Server error displayed in oEmbed action when attachment not found.
                        // TRANS: %d is an attachment ID.
                        $this->serverError(sprintf(_('Attachment %s not found.'),$id), 404);
                    }
                    if(empty($attachment->filename) && $file_oembed = File_oembed::getKV('file_id', $attachment->id)){
                        // Proxy the existing oembed information
                        $oembed['type']=$file_oembed->type;
                        $oembed['provider']=$file_oembed->provider;
                        $oembed['provider_url']=$file_oembed->provider_url;
                        $oembed['width']=$file_oembed->width;
                        $oembed['height']=$file_oembed->height;
                        $oembed['html']=$file_oembed->html;
                        $oembed['title']=$file_oembed->title;
                        $oembed['author_name']=$file_oembed->author_name;
                        $oembed['author_url']=$file_oembed->author_url;
                        $oembed['url']=$file_oembed->url;
                    }else if(substr($attachment->mimetype,0,strlen('image/'))=='image/'){
                        $oembed['type']='photo';
                        if ($attachment->filename) {
                            $filepath = File::path($attachment->filename);
                            $gis = @getimagesize($filepath);
                            if ($gis) {
                                $oembed['width'] = $gis[0];
                                $oembed['height'] = $gis[1];
                            } else {
                                // TODO Either throw an error or find a fallback?
                            }
                        }
                        $oembed['url']=$attachment->url;
                        $thumb = $attachment->getThumbnail();
                        if ($thumb) {
                            $oembed['thumbnail_url'] = $thumb->url;
                            $oembed['thumbnail_width'] = $thumb->width;
                            $oembed['thumbnail_height'] = $thumb->height;
                        }
                    }else{
                        $oembed['type']='link';
                        $oembed['url']=common_local_url('attachment',
                            array('attachment' => $attachment->id));
                    }
                    if($attachment->title) $oembed['title']=$attachment->title;
                    break;
                default:
                    // TRANS: Server error displayed in oEmbed request when a path is not supported.
                    // TRANS: %s is a path.
                    $this->serverError(sprintf(_('"%s" not supported for oembed requests.'),$path), 501);
            }
            switch($args['format']){
                case 'xml':
                    $this->init_document('xml');
                    $this->elementStart('oembed');
                    $this->element('version',null,$oembed['version']);
                    $this->element('type',null,$oembed['type']);
                    if($oembed['provider_name']) $this->element('provider_name',null,$oembed['provider_name']);
                    if($oembed['provider_url']) $this->element('provider_url',null,$oembed['provider_url']);
                    if($oembed['title']) $this->element('title',null,$oembed['title']);
                    if($oembed['author_name']) $this->element('author_name',null,$oembed['author_name']);
                    if($oembed['author_url']) $this->element('author_url',null,$oembed['author_url']);
                    if($oembed['url']) $this->element('url',null,$oembed['url']);
                    if($oembed['html']) $this->element('html',null,$oembed['html']);
                    if($oembed['width']) $this->element('width',null,$oembed['width']);
                    if($oembed['height']) $this->element('height',null,$oembed['height']);
                    if($oembed['cache_age']) $this->element('cache_age',null,$oembed['cache_age']);
                    if($oembed['thumbnail_url']) $this->element('thumbnail_url',null,$oembed['thumbnail_url']);
                    if($oembed['thumbnail_width']) $this->element('thumbnail_width',null,$oembed['thumbnail_width']);
                    if($oembed['thumbnail_height']) $this->element('thumbnail_height',null,$oembed['thumbnail_height']);

                    $this->elementEnd('oembed');
                    $this->end_document('xml');
                    break;
                case 'json': case '':
                    $this->init_document('json');
                    print(json_encode($oembed));
                    $this->end_document('json');
                    break;
                default:
                    // TRANS: Error message displaying attachments. %s is a raw MIME type (eg 'image/png')
                    $this->serverError(sprintf(_('Content type %s not supported.'), $apidata['content-type']), 501);
            }
        }else{
            // TRANS: Error message displaying attachments. %s is the site's base URL.
            $this->serverError(sprintf(_('Only %s URLs over plain HTTP please.'), common_root_url()), 404);
        }
    }

    function init_document($type)
    {
        switch ($type) {
        case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            $this->startXML();
            break;
        case 'json':
            header('Content-Type: application/json; charset=utf-8');

            // Check for JSONP callback
            $callback = $this->arg('callback');
            if ($callback) {
                print $callback . '(';
            }
            break;
        default:
            // TRANS: Server error displayed in oEmbed action when request specifies an unsupported data format.
            $this->serverError(_('Not a supported data format.'), 501);
            break;
        }
    }

    function end_document($type='xml')
    {
        switch ($type) {
        case 'xml':
            $this->endXML();
            break;
        case 'json':
            // Check for JSONP callback
            $callback = $this->arg('callback');
            if ($callback) {
                print ')';
            }
            break;
        default:
            // TRANS: Server error displayed in oEmbed action when request specifies an unsupported data format.
            $this->serverError(_('Not a supported data format.'), 501);
            break;
        }
        return;
    }

    /**
     * Is this action read-only?
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        return true;
    }
}
