<?php

if (!defined('GNUSOCIAL')) { exit(1); }

abstract class NoticestreamAction extends ProfileAction
{
    protected $notice = null;   // holds the stream result

    protected function prepare(array $args=array()) {
        parent::prepare($args);

        // In case we need more info than ProfileAction->doPreparation() gives us
        $this->doStreamPreparation();

        // fetch the actual stream stuff
        try {
            $stream = $this->getStream();
            $this->notice = $stream->getNotices(($this->page-1) * NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);
        } catch (PrivateStreamException $e) {
            $this->notice = new Notice();
            $this->notice->whereAdd('FALSE');
        }

        if ($this->page > 1 && $this->notice->N == 0) {
            // TRANS: Client error when page not found (404).
            $this->clientError(_('No such page.'), 404);
        }

        return true;
    }

    protected function doStreamPreparation()
    {
        // pass by default
    }

    public function extraHeaders()
    {
        parent::extraHeaders();
        foreach ($this->getFeeds() as $feed) {
            header('Link: <'.htmlspecialchars($feed->getUrl()).'>;' .
                        ' rel="'.htmlspecialchars($feed->rel()).'";' .
                        ' type="'.htmlspecialchars($feed->mimeType()).'"',
                    false    // don't overwrite previous headers of this sort
                    );
        }
    }

    // this fetches the NoticeStream
    abstract public function getStream();
}
