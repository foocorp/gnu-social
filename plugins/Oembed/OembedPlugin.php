<?php

class OembedPlugin extends Plugin
{
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
        $out->elementStart('div', array('id'=>'oembed_info', 'class'=>'entry-content'));
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
            if (!empty($oembed->html) && common_config('attachments', 'show_html')) {
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
