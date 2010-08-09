<?php

//The following class was created June 30th 2004 by Jeremy(pickle)
//This class is designed to handle a direct connection

class Dconnect
{
	var $sock;
	var $lastReceived;
	var $lastMessage;
	var $connected;
	var $cookie;
	var $type=2;
	var $connectedTo;
	
	
	function Dconnect($ip,$port)
	{
		if(!$this->connect($ip,$port))
		{
			sEcho("Connection failed constructor");
			$this->connected=false;
		}
		else
			$this->connected=true;
		
		$this->lastMessage="";
		$this->lastReceived="";
	}
	
	function readDIM()
	{
		/*
			if(!$this->stuffToRead())
			{
				sEcho("Nothing to read");
				$this->lastMessage=$this->lastReceived="";
				return false;
			}
		*/
		$head=fread($this->sock,6);
		if(strlen($head)<=0)
		{
			sEcho("The direct connection has been closed");
			return false;
		}
		$minihead=unpack("a4ver/nsize",$head);
		if($minihead['size'] <=0)
		  return;
		$headerinfo=unpack("nchan/nsix/nzero/a6cookie/Npt1/Npt2/npt3/Nlen/Npt/npt0/ntype/Nzerom/a*sn",fread($this->sock,($minihead['size']-6)));
		$allheader=array_merge($minihead,$headerinfo);
		sEcho($allheader);
		if($allheader['len']>0 && $allheader['len'] <= MAX_DIM_SIZE)
		{
			$left=$allheader['len'];
			$stuff="";
			$nonin=0;
			while(strlen($stuff) < $allheader['len'] && $nonin<3)
			{
				$stuffg=fread($this->sock,$left);
				if(strlen($stuffg)<0)
				{
					$nonin++;
					continue;
				}
				$left=$left - strlen($stuffg);
				$stuff.=$stuffg;
			}
			$data=unpack("a*decoded",$stuff);
		}
		
		else if($allheader['len'] > MAX_DIM_SIZE)
		{
			$data['decoded']="too big";
		}
		
		else
			$data['decoded']="";
		$all=array_merge($allheader,$data);
		
		$this->lastReceived=$all;
		$this->lastMessage=$all['decoded'];
		
		//$function=$this->DimInf . "(\$all);";
		//eval($function);
		
		return $all;
	}
	
	function sendMessage($message,$sn)
	{
		//Make the "mini header"
		$minihead=pack("a4n","ODC2",76);
		$header=pack("nnna6NNnNNnnNa*",1,6,0,$this->cookie,0,0,0,strlen($message),0,0,96,0,$sn);
		$bighead=$minihead . $header;
		while(strlen($bighead)<76)
			$bighead.=pack("c",0);
		
		$tosend=$bighead . pack("a*",$message);
		$w=array($this->sock);
		stream_select($r=NULL,$w,$e=NULL,NULL);
		//Now send it all
		fputs($this->sock,$tosend,strlen($tosend));
	}
	function stuffToRead()
	{
		//$info=stream_get_meta_data($this->sock);
		//sEcho($info);
		$s=array($this->sock);
		$changed=stream_select($s,$fds=NULL,$m=NULL,0,20000);
		return ($changed>0);
	}
	
	function close()
	{
		$this->connected=false;
		return fclose($this->sock);
	}
	
	function connect($ip,$port)
	{
		$this->sock=fsockopen($ip,$port,$en,$es,3);
		if(!$this->sock)
		{  sEcho("Connection failed");
			$this->sock=null;
			return false;
		}
		return true;
	}
}


class FileSendConnect
{
	var $sock;
	var $lastReceived;
	var $lastMessage;
	var $connected;
	var $cookie;
	var $tpye=3;
	
	
	function FileSendConnect($ip,$port)
	{
		if(!$this->connect($ip,$port))
		{
			sEcho("Connection failed constructor");
			$this->connected=false;
		}
		else
			$this->connected=true;
		
		$this->lastMessage="";
		$this->lastReceived="";
	}
	
	function readDIM()
	{
		
			if(!$this->stuffToRead())
			{
				sEcho("Nothing to read");
				$this->lastMessage=$this->lastReceived="";
				return;
			}
		
		$minihead=unpack("a4ver/nsize",fread($this->sock,6));
		if($minihead['size'] <=0)
		  return;
		$headerinfo=unpack("nchan/nsix/nzero/a6cookie/Npt1/Npt2/npt3/Nlen/Npt/npt0/ntype/Nzerom/a*sn",fread($this->sock,($minihead['size']-6)));
		$allheader=array_merge($minihead,$headerinfo);
		sEcho($allheader);
		if($allheader['len']>0)
			$data=unpack("a*decoded",fread($this->sock,$allheader['len']));
		else
			$data['decoded']="";
		$all=array_merge($allheader,$data);
		
		$this->lastReceived=$all;
		$this->lastMessage=$all['decoded'];
		
		//$function=$this->DimInf . "(\$all);";
		//eval($function);
		
		return $all;
	}
	
	function sendMessage($message,$sn)
	{
		//Make the "mini header"
		$minihead=pack("a4n","ODC2",76);
		$header=pack("nnna6NNnNNnnNa*",1,6,0,$this->cookie,0,0,0,strlen($message),0,0,96,0,$sn);
		$bighead=$minihead . $header;
		while(strlen($bighead)<76)
			$bighead.=pack("c",0);
		
		$tosend=$bighead . pack("a*",$message);
		
		//Now send it all
		fwrite($this->sock,$tosend,strlen($tosend));
	}
	function stuffToRead()
	{
		//$info=stream_get_meta_data($this->sock);
		//sEcho($info);
		$s=array($this->sock);
		$changed=stream_select($s,$fds=NULL,$m=NULL,1);
		return ($changed>0);
	}
	
	function close()
	{
		$this->connected=false;
		fclose($this->sock);
		unset($this->sock);
		return true;
	}
	
	function connect($ip,$port)
	{
		$this->sock=fsockopen($ip,$port,$en,$es,3);
		if(!$this->sock)
		{  sEcho("Connection failed to" . $ip . ":" . $port);
			$this->sock=null;
			return false;
		}
		return true;
	}
}
