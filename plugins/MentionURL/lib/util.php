<?php

function mention_url_representative_hcard($url, $fn=null, $mf2=null) {
   if(!$mf2) {
       $request = HTTPClient::start();

        try {
            $response = $request->get($url);
        } catch(Exception $ex) {
            return null;
        }

        $url = $response->getEffectiveUrl();
        $mf2 = new Mf2\Parser($response->getBody(), $url);
        $mf2 = $mf2->parse();
    }

    $hcard = null;

    if(!empty($mf2['items'])) {
        $hcards = array();
        foreach($mf2['items'] as $item) {
            if(!in_array('h-card', $item['type'])) {
                continue;
            }

            // We found a match, return it immediately
            if(isset($item['properties']['url']) && in_array($url, $item['properties']['url'])) {
                $hcard = $item['properties'];
                break;
            }

            // Let's keep all the hcards for later, to return one of them at least
            $hcards[] = $item['properties'];
        }

        // No match immediately for the url we expected, but there were h-cards found
        if (count($hcards) > 0) {
            $hcard = $hcards[0];
        }
    }

    if(!$hcard && $fn) {
        $hcard = array('name' => array($fn));
    }

    if(!$hcard && $response) {
        preg_match('/<title>([^<]+)/', $response->getBody(), $match);
        $hcard = array('name' => array($match[1]));
    }

    if($hcard && !$hcard['url']) {
        $hcard['url'] = array($url);
    }

    return $hcard;
}
