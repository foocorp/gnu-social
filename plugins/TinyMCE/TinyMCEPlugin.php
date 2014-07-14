<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Use TinyMCE library to allow rich text editing in the browser
 *
 * PHP version 5
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
 *
 * @category  WYSIWYG
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Use TinyMCE library to allow rich text editing in the browser
 *
 * Converts the notice form in browser to a rich-text editor.
 *
 * FIXME: this plugin DOES NOT load its static files from the configured
 * plugin server if one exists. There are cross-server permissions errors
 * if you try to do that (something about window.tinymce).
 *
 * @category  WYSIWYG
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class TinyMCEPlugin extends Plugin
{
    var $html;

    // By default, TinyMCE editor will be available to all users.
    // With restricted on, only users who have been granted the
    // "richedit" role get it.
    public $restricted = false;

    function onEndShowScripts($action)
    {
        if (common_logged_in() && $this->isAllowedRichEdit()) {
            $action->script(common_path('plugins/TinyMCE/js/jquery.tinymce.js'));
            $action->inlineScript($this->_inlineScript());
        }

        return true;
    }

    function onEndShowStyles($action)
    {
        if ($this->isAllowedRichEdit()) {
            $action->style('span#notice_data-text_container, span#notice_data-text_parent { float: left }');
        }
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'TinyMCE',
            'version' => GNUSOCIAL_VERSION,
            'author' => 'Evan Prodromou',
            'homepage' => 'http://status.net/wiki/Plugin:TinyMCE',
            'rawdescription' =>
            // TRANS: Plugin description.
            _m('Use TinyMCE library to allow rich text editing in the browser.'));
        return true;
    }

    /**
     * Sanitize HTML input and strip out potentially dangerous bits.
     *
     * @param string $raw HTML
     * @return string HTML
     */
    private function sanitizeHtml($raw)
    {
        require_once INSTALLDIR . '/extlib/htmLawed/htmLawed.php';

        $config = array('safe' => 1,
            'deny_attribute' => 'id,style,on*');

        return htmLawed($raw, $config);
    }

    /**
     * Hook for new-notice form processing to take our HTML goodies;
     * won't affect API posting etc.
     *
     * @param NewNoticeAction $action
     * @param User $user
     * @param string $content
     * @param array $options
     * @return boolean hook return
     */
    function onStartSaveNewNoticeWeb($action, $user, &$content, &$options)
    {
        if ($action->arg('richedit') && $this->isAllowedRichEdit()) {
            $html = $this->sanitizeHtml($content);
            $options['rendered'] = $html;
            $content = common_strip_html($html);
        }
        return true;
    }

    /**
     * Hook for new-notice form processing to process file upload appending...
     *
     * @param NewNoticeAction $action
     * @param MediaFile $media
     * @param string $content
     * @param array $options
     * @return boolean hook return
     */
    function onStartSaveNewNoticeAppendAttachment($action, $media, &$content, &$options)
    {
        if ($action->arg('richedit') && $this->isAllowedRichEdit()) {
            // See if we've got a placeholder inline image; if so, fill it!
            $dom = new DOMDocument();

            if ($dom->loadHTML($options['rendered'])) {
                $imgs = $dom->getElementsByTagName('img');
                foreach ($imgs as $img) {
                    if (preg_match('/(^| )placeholder( |$)/', $img->getAttribute('class'))) {
                        // Create a link to the attachment page...
                        $this->formatAttachment($img, $media);
                    }
                }
                $options['rendered'] = $this->saveHtml($dom);
            }

            // The regular code will append the short URL to the plaintext content.
            // Carry on and let it through...
        }
        return true;
    }

    /**
     * Format the attachment placeholder img with the final version.
     *
     * @param DOMElement $img
     * @param MediaFile $media
     */
    private function formatAttachment($img, $media)
    {
        $parent = $img->parentNode;
        $dom = $img->ownerDocument;

        $link = $dom->createElement('a');
        $link->setAttribute('href', $media->fileurl);
        $link->setAttribute('title', File::url($media->filename));

        if ($this->isEmbeddable($media)) {
            // Fix the the <img> attributes and wrap the link around it...
            $this->insertImage($img, $media);
            $parent->replaceChild($link, $img); //it dies in here?!
            $link->appendChild($img);
        } else {
            // Not an image? Replace it with a text link.
            $link->setAttribute('rel', 'external');
            $link->setAttribute('class', 'attachment');
            $link->setAttribute('id', 'attachment-' . $media->fileRecord->id);
            $text = $dom->createTextNode($media->shortUrl());
            $link->appendChild($text);
            $parent->replaceChild($link, $img);
        }
    }

    /**
     * Is this media file a type we can display inline?
     *
     * @param MediaFile $media
     * @return boolean
     */
    private function isEmbeddable($media)
    {
        $showable = array('image/png',
                          'image/gif',
                          'image/jpeg');
        return in_array($media->mimetype, $showable);
    }

    /**
     * Rewrite and resize a placeholder image element to match the uploaded
     * file. If the holder is smaller than the file, the file is scaled to fit
     * with correct aspect ratio (but will be loaded at full resolution).
     *
     * @param DOMElement $img
     * @param MediaFile $media
     */
    private function insertImage($img, $media)
    {
        $img->setAttribute('src', $media->fileRecord->url);

        $holderWidth = intval($img->getAttribute('width'));
        $holderHeight = intval($img->getAttribute('height'));

        $path = File::path($media->filename);
        $imgInfo = getimagesize($path);

        if ($imgInfo) {
            $origWidth = $imgInfo[0];
            $origHeight = $imgInfo[1];

            list($width, $height) = $this->sizeBox(
                    $origWidth, $origHeight,
                    $holderWidth, $holderHeight);

            $img->setAttribute('width', $width);
            $img->setAttribute('height', $height);
        }
    }

    /**
     *
     * @param int $origWidth
     * @param int $origHeight
     * @param int $holderWidth
     * @param int $holderHeight
     * @return array($width, $height)
     */
    private function sizeBox($origWidth, $origHeight, $holderWidth, $holderHeight)
    {
        $holderAspect = $holderWidth / $holderHeight;
        $origAspect = $origWidth / $origHeight;
        if ($origAspect >= 1.0) {
            // wide image
            if ($origWidth > $holderWidth) {
                return array($holderWidth, intval($holderWidth / $origAspect));
            } else {
                return array($origWidth, $origHeight);
            }
        } else {
            if ($origHeight > $holderHeight) {
                return array(intval($holderWidth * $origAspect), $holderHeight);
            } else {
                return array($origWidth, $origHeight);
            }
        }
    }

    private function saveHtml($dom)
    {
        $html = $dom->saveHTML();
        // hack to remove surrounding crap added to the dom
        // all we wanted was a fragment
        $stripped = preg_replace('/^.*<body[^>]*>(.*)<\/body.*$/is', '$1', $html);
        return $stripped;
    }

    function _inlineScript()
    {
        $path = common_path('plugins/TinyMCE/js/tiny_mce.js');
        $placeholder = common_path('plugins/TinyMCE/icons/placeholder.png');

        // Note: the normal on-submit triggering to save data from
        // the HTML editor into the textarea doesn't play well with
        // our AJAX form submission. Manually moving it to trigger
        // on our send button click.
        $scr = <<<END_OF_SCRIPT
        (function() {
        var origInit = SN.Init.NoticeFormSetup;
        SN.Init.NoticeFormSetup = function(form) {
            origInit(form);
            var noticeForm = form;
            var textarea = form.find('.notice_data-text');
            if (textarea.length == 0) return;
            textarea.tinymce({
                script_url : '{$path}',
                // General options
                theme : "advanced",
                plugins : "paste,fullscreen,autoresize,inlinepopups,tabfocus,linkautodetect",
                theme_advanced_buttons1 : "bold,italic,strikethrough,|,undo,redo,|,link,unlink,image,|,fullscreen",
                theme_advanced_buttons2 : "",
                theme_advanced_buttons3 : "",
                add_form_submit_trigger : false,
                theme_advanced_resizing : true,
                tabfocus_elements: ":prev,:next",
                setup: function(ed) {
                    noticeForm.append('<input type="hidden" name="richedit" value="1">');

                    form.find('.submit:first').click(function() {
                        tinymce.triggerSave();
                    });

                    var origCounter = SN.U.CharacterCount;
                    SN.U.CharacterCount = function(form) {
                        var text = $(ed.getDoc()).text();
                        return text.length;
                    };
                    ed.onKeyUp.add(function (ed, e) {
                        SN.U.Counter(noticeForm);
                    });

                    form.find('input[type=file]').change(function() {
                        var img = '<img src="{$placeholder}" class="placeholder" width="320" height="240">';
                        var html = tinyMCE.activeEditor.getContent();
                        ed.setContent(html + img);
                    });
                }
            });
        };
        })();
END_OF_SCRIPT;

        return $scr;
    }

    /**
     * Does the current user have permission to use the rich-text editor?
     * Always true unless the plugin's "restricted" setting is on, in which
     * case it's limited to users with the "richedit" role.
     *
     * @fixme make that more sanely configurable :)
     *
     * @return boolean
     */
    private function isAllowedRichEdit()
    {
        if ($this->restricted) {
            $user = common_current_user();
            return !empty($user) && $user->hasRole('richedit');
        } else {
            return true;
        }
    }
}
