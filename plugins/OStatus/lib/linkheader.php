<?php
/**
 * @todo Add file header and documentation.
 */

class LinkHeader
{
    var $href;
    var $rel;
    var $type;

    function __construct($str)
    {
        preg_match('/^<[^>]+>/', $str, $uri_reference);
        //if (empty($uri_reference)) return;

        $this->href = trim($uri_reference[0], '<>');
        $this->rel = array();
        $this->type = null;

        // remove uri-reference from header
        $str = substr($str, strlen($uri_reference[0]));

        // parse link-params
        $params = explode(';', $str);

        foreach ($params as $param) {
            if (empty($param)) continue;
            list($param_name, $param_value) = explode('=', $param, 2);
            $param_name = trim($param_name);
            $param_value = preg_replace('(^"|"$)', '', trim($param_value));

            // for now we only care about 'rel' and 'type' link params
            // TODO do something with the other links-params
            switch ($param_name) {
            case 'rel':
                $this->rel = trim($param_value);
                break;

            case 'type':
                $this->type = trim($param_value);
            }
        }
    }

    static function getLink($response, $rel=null, $type=null)
    {
        $headers = $response->getHeader('Link');
        if ($headers) {
            // Can get an array or string, so try to simplify the path
            if (!is_array($headers)) {
                $headers = array($headers);
            }

            foreach ($headers as $header) {
                $lh = new LinkHeader($header);

                if ((is_null($rel) || $lh->rel == $rel) &&
                    (is_null($type) || $lh->type == $type)) {
                    return $lh->href;
                }
            }
        }
        return null;
    }
}
