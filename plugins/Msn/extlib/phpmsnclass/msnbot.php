#!/usr/bin/php
<?php
global $msn;
function ChildSignalFunction($signal)
{
	global $msn;	
	switch($signal)
	{
		case SIGTRAP:
		case SIGTERM:
		case SIGHUP:			
			if(is_object($msn))	$msn->End();
			return;
	}
}

// network:
//      1: WLM/MSN
//      2: LCS
//      4: Mobile Phones
//     32: Yahoo!
function getNetworkName($network)
{
	switch ($network)
	{
		case 1:
			return 'WLM/MSN';
		case 2:
			return 'LCS';
		case 4:
			return 'Mobile Phones';
		case 32:
			return 'Yahoo!';
	}
	return "Unknown ($network)";
}


require_once('config.php');
include_once('msn.class.php');

$msn = new MSN(array(
                'user' => 'xxx@hotmail.com',
                'password' => 'mypassword',
                'alias' => 'myalias',
                'psm' => 'psm',
//                'PhotoSticker' => 'msntitle.jpg',
                'debug'=> true,
/*                'Emotions' => array(
                   'aaa' =>  'emotion.gif'
                 ),*/
));

$fp=fopen(MSN_CLASS_LOG_DIR.DIRECTORY_SEPARATOR.'msnbot.pid', 'wt');
if($fp)
{
	fputs($fp,posix_getpid());
	fclose($fp);
}
declare(ticks = 1);
$msn->Run();
$msn->log_message("done!");
@unlink(dirname($_SERVER['argv'][0]).DIRECTORY_SEPARATOR.'log'.DIRECTORY_SEPARATOR.'msnbot.pid');
