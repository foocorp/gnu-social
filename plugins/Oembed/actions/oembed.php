<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * oEmbed data action for /main/oembed(.xml|.json) requests
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
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Oembed provider implementation
 *
 * This class handles all /main/oembed(.xml|.json)/ requests.
 *
 * @category  oEmbed
 * @package   GNUsocial
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2008 StatusNet, Inc.
 * @copyright 2014 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class OembedAction extends Action
{
    protected function handle()
    {
        parent::handle();

        $url = $this->trimmed('url');
        if (substr(strtolower($url),0,strlen(common_root_url())) !== strtolower(common_root_url())) {
            // TRANS: Error message displaying attachments. %s is the site's base URL.
            // FIXME: 404 not found?! (this will automatically become a 500 because it's not a serverError!)
            $this->serverError(sprintf(_('Only %s URLs over plain HTTP please.'), common_root_url()), 404);
        }

        $path = substr($url,strlen(common_root_url()));

        $r = Router::get();

        $proxy_args = $r->map($path);
        if (!$proxy_args) {
            // TRANS: Client error displayed in oEmbed action when path not found.
            // TRANS: %s is a path.
            $this->clientError(sprintf(_('"%s" not found.'),$path), 404);
        }

        $oembed=array();
        $oembed['version']='1.0';
        $oembed['provider_name']=common_config('site', 'name');
        $oembed['provider_url']=common_root_url();

        switch ($proxy_args['action']) {
        case 'shownotice':
            $oembed['type']='link';
            $id = $proxy_args['notice'];
            $notice = Notice::getKV($id);
            if(empty($notice)){
                // TRANS: Client error displayed in oEmbed action when notice not found.
                // TRANS: %s is a notice.
                $this->clientError(sprintf(_("Notice %s not found."),$id), 404);
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
            $oembed['url']=$notice->getUrl();
            $oembed['html']=$notice->rendered;
            break;

        case 'attachment':
            $id = $proxy_args['attachment'];
            $attachment = File::getKV($id);
            if(empty($attachment)){
                // TRANS: Client error displayed in oEmbed action when attachment not found.
                // TRANS: %d is an attachment ID.
                $this->clientError(sprintf(_('Attachment %s not found.'),$id), 404);
            }
            if (empty($attachment->filename) && $file_oembed = File_oembed::getKV('file_id', $attachment->id)) {
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
                $oembed['url']=$file_oembed->getUrl();
            } elseif (substr($attachment->mimetype,0,strlen('image/'))==='image/') {
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
                $oembed['url']=$attachment->getUrl();
                try {
                    $thumb = $attachment->getThumbnail();
                    $oembed['thumbnail_url'] = $thumb->getUrl();
                    $oembed['thumbnail_width'] = $thumb->width;
                    $oembed['thumbnail_height'] = $thumb->height;
                    unset($thumb);
                } catch (UnsupportedMediaException $e) {
                    // No thumbnail data available
                }
            } else {
                $oembed['type']='link';
                $oembed['url']=common_local_url('attachment',
                    array('attachment' => $attachment->id));
            }
            if ($attachment->title) {
                $oembed['title']=$attachment->title;
            }
            break;
        default:
            // TRANS: Server error displayed in oEmbed request when a path is not supported.
            // TRANS: %s is a path.
            $this->serverError(sprintf(_('"%s" not supported for oembed requests.'),$path), 501);
        }

        switch ($this->trimmed('format')) {
        case 'xml':
            $this->init_document('xml');
            $this->elementStart('oembed');
            foreach(array(
                        'version', 'type', 'provider_name',
                        'provider_url', 'title', 'author_name',
                        'author_url', 'url', 'html', 'width',
                        'height', 'cache_age', 'thumbnail_url',
                        'thumbnail_width', 'thumbnail_height',
                    ) as $key) {
                if (isset($oembed[$key]) && $oembed[$key]!='') {
                    $this->element($key, null, $oembed[$key]);
                }
            }
            $this->elementEnd('oembed');
            $this->end_document('xml');
            break;

        case 'json':
        case null:
            $this->init_document('json');
            $this->raw(json_encode($oembed));
            $this->end_document('json');
            break;
        default:
            // TRANS: Error message displaying attachments. %s is a raw MIME type (eg 'image/png')
            $this->serverError(sprintf(_('Content type %s not supported.'), $apidata['content-type']), 501);
        }
    }

    public function init_document($type)
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

    public function end_document($type)
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
