<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class OembedPlugin extends Plugin
{
    // settings which can be set in config.php with addPlugin('Oembed', array('param'=>'value', ...));
    // WARNING, these are _regexps_ (slashes added later). Always escape your dots and end your strings
    public $domain_whitelist = array(       // hostname => service provider
                                    '^i\d*\.ytimg\.com$' => 'YouTube',
                                    '^i\d*\.vimeocdn\.com$' => 'Vimeo',
                                    );
    public $append_whitelist = array(); // fill this array as domain_whitelist to add more trusted sources
    public $check_whitelist  = false;    // security/abuse precaution

    protected $imgData = array();

    // these should be declared protected everywhere
    public function initialize()
    {
        parent::initialize();

        $this->domain_whitelist = array_merge($this->domain_whitelist, $this->append_whitelist);
    }

    public function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('file_oembed', File_oembed::schemaDef());
        return true;
    }

    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('main/oembed', array('action' => 'oembed'));
    }

    public function onGetRemoteUrlMetadataFromDom($url, DOMDocument $dom, stdClass &$metadata)
    {
        try {
            common_log(LOG_INFO, 'Trying to discover an oEmbed endpoint using link headers.');
            $api = oEmbedHelper::oEmbedEndpointFromHTML($dom);
            common_log(LOG_INFO, 'Found oEmbed API endpoint ' . $api . ' for URL ' . $url);
            $params = array(
                'maxwidth' => common_config('thumbnail', 'width'),
                'maxheight' => common_config('thumbnail', 'height'),
            );
            $metadata = oEmbedHelper::getOembedFrom($api, $url, $params);
        } catch (Exception $e) {
            common_log(LOG_INFO, 'Could not find an oEmbed endpoint using link headers, trying OpenGraph from HTML.');
            // Just ignore it!
            $metadata = OpenGraphHelper::ogFromHtml($dom);
        }
    }

    public function onEndShowHeadElements(Action $action)
    {
        switch ($action->getActionName()) {
        case 'attachment':
            $action->element('link',array('rel'=>'alternate',
                'type'=>'application/json+oembed',
                'href'=>common_local_url(
                    'oembed',
                    array(),
                    array('format'=>'json', 'url'=>
                        common_local_url('attachment',
                            array('attachment' => $action->attachment->id)))),
                'title'=>'oEmbed'),null);
            $action->element('link',array('rel'=>'alternate',
                'type'=>'text/xml+oembed',
                'href'=>common_local_url(
                    'oembed',
                    array(),
                    array('format'=>'xml','url'=>
                        common_local_url('attachment',
                            array('attachment' => $action->attachment->id)))),
                'title'=>'oEmbed'),null);
            break;
        case 'shownotice':
            if (!$action->notice->isLocal()) {
                break;
            }
            try {
                $action->element('link',array('rel'=>'alternate',
                    'type'=>'application/json+oembed',
                    'href'=>common_local_url(
                        'oembed',
                        array(),
                        array('format'=>'json','url'=>$action->notice->getUrl())),
                    'title'=>'oEmbed'),null);
                $action->element('link',array('rel'=>'alternate',
                    'type'=>'text/xml+oembed',
                    'href'=>common_local_url(
                        'oembed',
                        array(),
                        array('format'=>'xml','url'=>$action->notice->getUrl())),
                    'title'=>'oEmbed'),null);
            } catch (InvalidUrlException $e) {
                // The notice is probably a share or similar, which don't
                // have a representational URL of their own.
            }
            break;
        }

        return true;
    }

    /**
     * Save embedding information for a File, if applicable.
     *
     * Normally this event is called through File::saveNew()
     *
     * @param File   $file       The newly inserted File object.
     *
     * @return boolean success
     */
    public function onEndFileSaveNew(File $file)
    {
        $fo = File_oembed::getKV('file_id', $file->id);
        if ($fo instanceof File_oembed) {
            common_log(LOG_WARNING, "Strangely, a File_oembed object exists for new file {$file->id}", __FILE__);
            return true;
        }

        if (isset($file->mimetype)
            && (('text/html' === substr($file->mimetype, 0, 9)
            || 'application/xhtml+xml' === substr($file->mimetype, 0, 21)))) {

            try {
                $oembed_data = File_oembed::_getOembed($file->url);
                if ($oembed_data === false) {
                    throw new Exception('Did not get oEmbed data from URL');
                }
            } catch (Exception $e) {
                return true;
            }

            File_oembed::saveNew($oembed_data, $file->id);
        }
        return true;
    }

    public function onEndShowAttachmentLink(HTMLOutputter $out, File $file)
    {
        $oembed = File_oembed::getKV('file_id', $file->id);
        if (empty($oembed->author_name) && empty($oembed->provider)) {
            return true;
        }
        $out->elementStart('div', array('id'=>'oembed_info', 'class'=>'e-content'));
        if (!empty($oembed->author_name)) {
            $out->elementStart('div', 'fn vcard author');
            if (empty($oembed->author_url)) {
                $out->text($oembed->author_name);
            } else {
                $out->element('a', array('href' => $oembed->author_url,
                                         'class' => 'url'),
                                $oembed->author_name);
            }
        }
        if (!empty($oembed->provider)) {
            $out->elementStart('div', 'fn vcard');
            if (empty($oembed->provider_url)) {
                $out->text($oembed->provider);
            } else {
                $out->element('a', array('href' => $oembed->provider_url,
                                         'class' => 'url'),
                                $oembed->provider);
            }
        }
        $out->elementEnd('div');
    }

    public function onFileEnclosureMetadata(File $file, &$enclosure)
    {
        // Never treat generic HTML links as an enclosure type!
        // But if we have oEmbed info, we'll consider it golden.
        $oembed = File_oembed::getKV('file_id', $file->id);
        if (!$oembed instanceof File_oembed || !in_array($oembed->type, array('photo', 'video'))) {
            return true;
        }

        foreach (array('mimetype', 'url', 'title', 'modified', 'width', 'height') as $key) {
            if (isset($oembed->{$key}) && !empty($oembed->{$key})) {
                $enclosure->{$key} = $oembed->{$key};
            }
        }
        return true;
    }
    
    public function onShowUnsupportedAttachmentRepresentation(HTMLOutputter $out, File $file)
    {
        try {
            $oembed = File_oembed::getByFile($file);
        } catch (NoResultException $e) {
            return true;
        }

        // the 'photo' type is shown through ordinary means, using StartShowAttachmentRepresentation!
        switch ($oembed->type) {
        case 'rich':
        case 'video':
        case 'link':
            if (!empty($oembed->html)
                    && (GNUsocial::isAjax() || common_config('attachments', 'show_html'))) {
                require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';
                $config = array(
                    'safe'=>1,
                    'elements'=>'*+object+embed');
                $out->raw(htmLawed($oembed->html,$config));
            }
            return false;
            break;
        }

        return true;
    }

    public function onCreateFileImageThumbnailSource(File $file, &$imgPath, $media=null)
    {
        // If we are on a private node, we won't do any remote calls (just as a precaution until
        // we can configure this from config.php for the private nodes)
        if (common_config('site', 'private')) {
            return true;
        }

        // All our remote Oembed images lack a local filename property in the File object
        if (!is_null($file->filename)) {
            return true;
        }

        try {
            // If we have proper oEmbed data, there should be an entry in the File_oembed
            // and File_thumbnail tables respectively. If not, we're not going to do anything.
            $file_oembed = File_oembed::getByFile($file);
            $thumbnail   = File_thumbnail::byFile($file);
        } catch (Exception $e) {
            // Not Oembed data, or at least nothing we either can or want to use.
            return true;
        }

        try {
            $this->storeRemoteFileThumbnail($thumbnail);
        } catch (AlreadyFulfilledException $e) {
            // aw yiss!
        }

        $imgPath = $thumbnail->getPath();

        return false;
    }

    /**
     * @return boolean          false on no check made, provider name on success
     * @throws ServerException  if check is made but fails
     */
    protected function checkWhitelist($url)
    {
        if (!$this->check_whitelist) {
            return false;   // indicates "no check made"
        }

        $host = parse_url($url, PHP_URL_HOST);
        foreach ($this->domain_whitelist as $regex => $provider) {
            if (preg_match("/$regex/", $host)) {
                return $provider;    // we trust this source, return provider name
            }
        }

        throw new ServerException(sprintf(_('Domain not in remote thumbnail source whitelist: %s'), $host));
    }

    protected function storeRemoteFileThumbnail(File_thumbnail $thumbnail)
    {
        if (!empty($thumbnail->filename) && file_exists($thumbnail->getPath())) {
            throw new AlreadyFulfilledException(sprintf('A thumbnail seems to already exist for remote file with id==%u', $thumbnail->file_id));
        }

        $url = $thumbnail->getUrl();
        $this->checkWhitelist($url);

        // First we download the file to memory and test whether it's actually an image file
        // FIXME: To support remote video/whatever files, this needs reworking.
        common_debug(sprintf('Downloading remote thumbnail for file id==%u with thumbnail URL: %s', $thumbnail->file_id, $url));
        $imgData = HTTPClient::quickGet($url);
        $info = @getimagesizefromstring($imgData);
        if ($info === false) {
            throw new UnsupportedMediaException(_('Remote file format was not identified as an image.'), $url);
        } elseif (!$info[0] || !$info[1]) {
            throw new UnsupportedMediaException(_('Image file had impossible geometry (0 width or height)'));
        }

        // We'll trust sha256 (File::FILEHASH_ALG) not to have collision issues any time soon :)
        $filename = hash(File::FILEHASH_ALG, $imgData) . '.' . common_supported_mime_to_ext($info['mime']);
        $fullpath = File_thumbnail::path($filename);
        // Write the file to disk. Throw Exception on failure
        if (!file_exists($fullpath) && file_put_contents($fullpath, $imgData) === false) {
            throw new ServerException(_('Could not write downloaded file to disk.'));
        }
        // Get rid of the file from memory
        unset($imgData);

        // Updated our database for the file record
        $orig = clone($thumbnail);
        $thumbnail->filename = $filename;
        $thumbnail->width = $info[0];    // array indexes documented on php.net:
        $thumbnail->height = $info[1];   // https://php.net/manual/en/function.getimagesize.php
        // Throws exception on failure.
        $thumbnail->updateWithKeys($orig);
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'Oembed',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://gnu.io/',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Plugin for using and representing Oembed data.'));
        return true;
    }
}
