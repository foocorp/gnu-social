<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notice attachments
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
 * @category  UI
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * widget for displaying a single notice
 *
 * This widget has the core smarts for showing a single notice: what to display,
 * where, and under which circumstances. Its key method is show(); this is a recipe
 * that calls all the other show*() methods to build up a single notice. The
 * ProfileNoticeListItem subclass, for example, overrides showAuthor() to skip
 * author info (since that's implicit by the data in the page).
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      NoticeList
 * @see      ProfileNoticeListItem
 */
class AttachmentListItem extends Widget
{
    /** The attachment this item will show. */

    var $attachment = null;

    /**
     * @param File $attachment the attachment we will display
     */
    function __construct(File $attachment, $out=null)
    {
        parent::__construct($out);
        $this->attachment  = $attachment;
    }

    function title() {
        return $this->attachment->getTitle();
    }

    function linkTitle() {
        return $this->title();
    }

    /**
     * recipe function for displaying a single notice.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */
    function show()
    {
        $this->showStart();
        $this->showNoticeAttachment();
        $this->showEnd();
    }

    function linkAttr() {
        return array('class' => 'attachment',
                     'href' => $this->attachment->getUrl(),
                     'id' => 'attachment-' . $this->attachment->id,
                     'title' => $this->linkTitle());
    }

    function showLink() {
        $this->out->elementStart('a', $this->linkAttr());
        $this->out->element('span', null, $this->linkTitle());
        $this->showRepresentation();
        $this->out->elementEnd('a');
    }

    function showNoticeAttachment()
    {
        $this->showLink();
    }

    function showRepresentation() {
        if (Event::handle('StartShowAttachmentRepresentation', array($this->out, $this->attachment))) {
            if (!empty($this->attachment->mimetype)) {
                switch ($this->attachment->mimetype) {
                case 'image/gif':
                case 'image/png':
                case 'image/jpg':
                case 'image/jpeg':
                    try {
                        $thumb = $this->attachment->getThumbnail();
                        $this->out->element('img', array('class'=>'u-photo', 'src' => $thumb->getUrl(), 'alt' => ''));
                    } catch (UseFileAsThumbnailException $e) {
                        $this->out->element('img', array('class'=>'u-photo', 'src' => $e->file->getUrl(), 'alt' => $e->file->title));
                    } catch (UnsupportedMediaException $e) {
                        // FIXME: Show a good representation of unsupported/unshowable images
                    }
                    break;

                case 'application/ogg':
                    $arr  = array('type' => $this->attachment->mimetype,
                        'data' => $this->attachment->url,
                        'width' => 320,
                        'height' => 240
                    );
                    $this->out->elementStart('object', $arr);
                    $this->out->element('param', array('name' => 'src', 'value' => $this->attachment->url));
                    $this->out->element('param', array('name' => 'autoStart', 'value' => 1));
                    $this->out->elementEnd('object');
                    break;

                case 'audio/ogg':
                case 'audio/x-speex':
                case 'video/mpeg':
                case 'audio/mpeg':
                case 'video/mp4':
                case 'video/ogg':
                case 'video/quicktime':
                case 'video/webm':
                    $mediatype = common_get_mime_media($this->attachment->mimetype);
                    try {
                        $thumb = $this->attachment->getThumbnail();
                        $poster = $thumb->getUrl();
                        unset ($thumb);
                    } catch (Exception $e) {
                        $poster = null;
                    }
                    $this->out->elementStart($mediatype,
                                        array('class'=>"attachment_player u-{$mediatype}",
                                            'poster'=>$poster,
                                            'controls'=>'controls'));
                    $this->out->element('source',
                                        array('src'=>$this->attachment->url,
                                            'type'=>$this->attachment->mimetype));
                    $this->out->elementEnd($mediatype);
                    break;

                case 'text/html':
                    if (!empty($this->attachment->filename)
                            && (StatusNet::isAjax() || common_config('attachments', 'show_html'))) {
                        // Locally-uploaded HTML. Scrub and display inline.
                        $this->showHtmlFile($this->attachment);
                        break;
                    }
                    // Fall through to default.

                default:
                    Event::handle('ShowUnsupportedAttachmentRepresentation', array($this->out, $this->attachment));
                }
            } else {
                Event::handle('ShowUnsupportedAttachmentRepresentation', array($this->out, $this->attachment));
            }
        }
        Event::handle('EndShowAttachmentRepresentation', array($this->out, $this->attachment));
    }

    protected function showHtmlFile(File $attachment)
    {
        $body = $this->scrubHtmlFile($attachment);
        if ($body) {
            $this->out->raw($body);
        }
    }

    /**
     * @return mixed false on failure, HTML fragment string on success
     */
    protected function scrubHtmlFile(File $attachment)
    {
        $path = File::path($attachment->filename);
        if (!file_exists($path) || !is_readable($path)) {
            common_log(LOG_ERR, "Missing local HTML attachment $path");
            return false;
        }
        $raw = file_get_contents($path);

        // Normalize...
        $dom = new DOMDocument();
        if(!$dom->loadHTML($raw)) {
            common_log(LOG_ERR, "Bad HTML in local HTML attachment $path");
            return false;
        }

        // Remove <script>s or htmlawed will dump their contents into output!
        // Note: removing child nodes while iterating seems to mess things up,
        // hence the double loop.
        $scripts = array();
        foreach ($dom->getElementsByTagName('script') as $script) {
            $scripts[] = $script;
        }
        foreach ($scripts as $script) {
            common_log(LOG_DEBUG, $script->textContent);
            $script->parentNode->removeChild($script);
        }

        // Trim out everything outside the body...
        $body = $dom->saveHTML();
        $body = preg_replace('/^.*<body[^>]*>/is', '', $body);
        $body = preg_replace('/<\/body[^>]*>.*$/is', '', $body);

        require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';
        $config = array('safe' => 1,
                        'deny_attribute' => 'id,style,on*',
                        'comment' => 1); // remove comments
        $scrubbed = htmLawed($body, $config);

        return $scrubbed;
    }

    /**
     * start a single notice.
     *
     * @return void
     */
    function showStart()
    {
        // XXX: RDFa
        // TODO: add notice_type class e.g., notice_video, notice_image
        $this->out->elementStart('li');
    }

    /**
     * finish the notice
     *
     * Close the last elements in the notice list item
     *
     * @return void
     */
    function showEnd()
    {
        $this->out->elementEnd('li');
    }
}
