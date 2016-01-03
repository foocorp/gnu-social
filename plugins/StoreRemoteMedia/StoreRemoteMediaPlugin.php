<?php

if (!defined('GNUSOCIAL')) { exit(1); }

// FIXME: To support remote video/whatever files, this plugin needs reworking.

class StoreRemoteMediaPlugin extends Plugin
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

    /**
     * Save embedding information for a File, if applicable.
     *
     * Normally this event is called through File::saveNew()
     *
     * @param File   $file       The abount-to-be-inserted File object.
     *
     * @return boolean success
     */
    public function onStartFileSaveNew(File &$file)
    {
        // save given URL as title if it's a media file this plugin understands
        // which will make it shown in the AttachmentList widgets

        if (isset($file->title) && strlen($file->title)>0) {
            // Title is already set
            return true;
        }
        if (!isset($file->mimetype)) {
            // Unknown mimetype, it's not our job to figure out what it is.
            return true;
        }
        switch (common_get_mime_media($file->mimetype)) {
        case 'image':
            // Just to set something for now at least...
            $file->title = $file->mimetype;
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

        if ($media !== 'image') {
            return true;
        }

        // If there is a local filename, it is either a local file already or has already been downloaded.
        if (!empty($file->filename)) {
            return true;
        }

        $this->checkWhitelist($file->getUrl());

        // First we download the file to memory and test whether it's actually an image file
        $imgData = HTTPClient::quickGet($file->getUrl());
        common_debug(sprintf('Downloading remote file id==%u with URL: %s', $file->id, $file->getUrl()));
        $info = @getimagesizefromstring($imgData);
        if ($info === false) {
            throw new UnsupportedMediaException(_('Remote file format was not identified as an image.'), $file->getUrl());
        } elseif (!$info[0] || !$info[1]) {
            throw new UnsupportedMediaException(_('Image file had impossible geometry (0 width or height)'));
        }

        $filehash = hash(File::FILEHASH_ALG, $imgData);
        try {
            // Exception will be thrown before $file is set to anything, so old $file value will be kept
            $file = File::getByHash($filehash);

            //FIXME: Add some code so we don't have to store duplicate File rows for same hash files.
        } catch (NoResultException $e) {
            $filename = $filehash . '.' . common_supported_mime_to_ext($info['mime']);
            $fullpath = File::path($filename);

            // Write the file to disk if it doesn't exist yet. Throw Exception on failure.
            if (!file_exists($fullpath) && file_put_contents($fullpath, $imgData) === false) {
                throw new ServerException(_('Could not write downloaded file to disk.'));
            }

            // Updated our database for the file record
            $orig = clone($file);
            $file->filehash = $filehash;
            $file->filename = $filename;
            $file->width = $info[0];    // array indexes documented on php.net:
            $file->height = $info[1];   // https://php.net/manual/en/function.getimagesize.php
            // Throws exception on failure.
            $file->updateWithKeys($orig, 'id');
        }
        // Get rid of the file from memory
        unset($imgData);

        $imgPath = $file->getPath();

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

        throw new ServerException(sprintf(_('Domain not in remote source whitelist: %s'), $host));
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'StoreRemoteMedia',
                            'version' => GNUSOCIAL_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'https://gnu.io/',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Plugin for downloading remotely attached files to local server.'));
        return true;
    }
}
