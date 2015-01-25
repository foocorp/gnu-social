<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class OembedPlugin extends Plugin
{
    // settings which can be set in config.php with addPlugin('Oembed', array('param'=>'value', ...));
    // WARNING, these are _regexps_ (slashes added later). Always escape your dots and end your strings
    public $domain_whitelist = array(       // hostname => service provider
                                    '^i\d*\.ytimg\.com$' => 'YouTube',
                                    );
    public $append_whitelist = array(); // fill this array as domain_whitelist to add more trusted sources
    public $check_whitelist  = true;    // security/abuse precaution

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
     * @param array  $redir_data lookup data eg from File_redirection::where()
     * @param string $given_url
     *
     * @return boolean success
     */
    public function onEndFileSaveNew(File $file, array $redir_data, $given_url)
    {
        $fo = File_oembed::getKV('file_id', $file->id);
        if ($fo instanceof File_oembed) {
            common_log(LOG_WARNING, "Strangely, a File_oembed object exists for new file $file_id", __FILE__);
             return true;
        }

        if (isset($redir_data['oembed']['json'])
                && !empty($redir_data['oembed']['json'])) {
            File_oembed::saveNew($redir_data['oembed']['json'], $file->id);
        } elseif (isset($redir_data['type'])
                && (('text/html' === substr($redir_data['type'], 0, 9)
                || 'application/xhtml+xml' === substr($redir_data['type'], 0, 21)))) {

            try {
                $oembed_data = File_oembed::_getOembed($given_url);
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

        foreach (array('mimetype', 'url', 'title', 'modified') as $key) {
            if (!empty($oembed->{$key})) {
                $enclosure->{$key} = $oembed->{$key};
            }
        }
        return true;
    }
    
    public function onStartShowAttachmentRepresentation(HTMLOutputter $out, File $file)
    {
        $oembed = File_oembed::getKV('file_id', $file->id);
        if (empty($oembed->type)) {
            return true;
        }
        switch ($oembed->type) {
        case 'rich':
        case 'video':
        case 'link':
            if (!empty($oembed->html)
                    && (StatusNet::isAjax() || common_config('attachments', 'show_html'))) {
                require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';
                $config = array(
                    'safe'=>1,
                    'elements'=>'*+object+embed');
                $out->raw(htmLawed($oembed->html,$config));
            }
            break;

        case 'photo':
            $out->element('img', array('src' => $oembed->url, 'width' => $oembed->width, 'height' => $oembed->height, 'alt' => 'alt'));
            break;

        default:
            Event::handle('ShowUnsupportedAttachmentRepresentation', array($out, $file));
        }
    }

    public function onCreateFileImageThumbnailSource(File $file, &$imgPath, $media=null)
    {
        // If we are on a private node, we won't do any remote calls (just as a precaution until
        // we can configure this from config.php for the private nodes)
        if (common_config('site', 'private')) {
            return true;
        }

        // All our remote Oembed images lack a local filename property in the File object
        if ($file->filename !== null) {
            return true;
        }

        try {
            // If we have proper oEmbed data, there should be an entry in the File_oembed
            // and File_thumbnail tables respectively. If not, we're not going to do anything.
            $file_oembed = File_oembed::byFile($file);
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

        // We'll trust sha256 not to have collision issues any time soon :)
        $filename = hash('sha256', $imgData) . '.' . common_supported_mime_to_ext($info['mime']);
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
