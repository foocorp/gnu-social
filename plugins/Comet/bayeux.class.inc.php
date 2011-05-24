<?php
/*
 * Phomet: a php comet client
 *
 * Copyright (C) 2008 Morgan 'ARR!' Allen <morganrallen@gmail.com> http://morglog.alleycatracing.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

class Bayeux
{
    private $oCurl = '';
    private $nNextId = 0;

    private $sUser = '';
    private $sPassword = '';

    public $sUrl = '';

    function __construct($sUrl, $sUser='', $sPassword='')
    {
        $this->sUrl = $sUrl;

        $this->oCurl = curl_init();

        $aHeaders = array();
        $aHeaders[] = 'Connection: Keep-Alive';

        curl_setopt($this->oCurl, CURLOPT_URL, $sUrl);
        curl_setopt($this->oCurl, CURLOPT_HTTPHEADER, $aHeaders);
        curl_setopt($this->oCurl, CURLOPT_HEADER, 0);
        curl_setopt($this->oCurl, CURLOPT_POST, 1);
        curl_setopt($this->oCurl, CURLOPT_RETURNTRANSFER,1);

        if (!is_null($sUser) && mb_strlen($sUser) > 0) {
            curl_setopt($this->oCurl, CURLOPT_USERPWD,"$sUser:$sPassword");
        }

        $this->handShake();
    }

    function __destruct()
    {
        $this->disconnect();
    }

    function handShake()
    {
        $msgHandshake = array();
        $msgHandshake['channel'] = '/meta/handshake';
        $msgHandshake['version'] = "1.0";
        $msgHandshake['minimumVersion'] = "0.9";
        $msgHandshake['supportedConnectionTypes'] = array('long-polling');
        $msgHandshake['id'] = $this->nNextId++;

        curl_setopt($this->oCurl, CURLOPT_POSTFIELDS, "message=".urlencode(str_replace('\\', '', json_encode(array($msgHandshake)))));

        $data = curl_exec($this->oCurl);

        if(curl_errno($this->oCurl))
          die("Error: " . curl_error($this->oCurl));

        $oReturn = json_decode($data);

        if (is_array($oReturn)) {
            $oReturn = $oReturn[0];
        }

        $bSuccessful = ($oReturn->successful) ? true : false;

        if($bSuccessful)
        {
            $this->clientId = $oReturn->clientId;

            $this->connect();
        }
    }

    public function connect()
    {
        $aMsg['channel'] = '/meta/connect';
        $aMsg['id'] = $this->nNextId++;
        $aMsg['clientId'] = $this->clientId;
        $aMsg['connectionType'] = 'long-polling';

        curl_setopt($this->oCurl, CURLOPT_POSTFIELDS, "message=".urlencode(str_replace('\\', '', json_encode(array($aMsg)))));

        $data = curl_exec($this->oCurl);
    }

    function disconnect()
    {
        $msgHandshake = array();
        $msgHandshake['channel'] = '/meta/disconnect';
        $msgHandshake['id'] = $this->nNextId++;
        $msgHandshake['clientId'] = $this->clientId;

        curl_setopt($this->oCurl, CURLOPT_POSTFIELDS, "message=".urlencode(str_replace('\\', '', json_encode(array($msgHandshake)))));

        curl_exec($this->oCurl);
    }

    public function publish($sChannel, $oData)
    {
        if(!$sChannel || !$oData)
          return;

        $aMsg = array();

        $aMsg['channel'] = $sChannel;
        $aMsg['id'] = $this->nNextId++;
        $aMsg['data'] = $oData;
        $aMsg['clientId'] = $this->clientId;

        curl_setopt($this->oCurl, CURLOPT_POSTFIELDS, "message=".urlencode(str_replace('\\', '', json_encode(array($aMsg)))));

        $data = curl_exec($this->oCurl);
//        var_dump($data);
    }
}
