<?php

  error_reporting(E_ALL | E_STRICT);

  define('DEBUG', false);
  $ECHO = false;

  if (count($argv) < 2)
  {
    $COMMAND = "";
  }
  else
  {
    $COMMAND = $argv[1];
    if ($argv[1] == "echo") $ECHO = true;

    if (count($argv) > 2)
    {
      if ($argv[2] == "echo") $ECHO = true; 
    }
  }

  $LIGHTTPD_KEY = "lighttpd";
	
  $ZABBIX_SERVER = "10.7.143.20";
  $ZABBIX_HOST = "files1.uesp.net";

  $LOGFILE = "/var/log/zabbix/lighttpd_output.log";
  if (file_exists($LOGFILE)) unlink($LOGFILE);

  $lighttpd_keys = array();

  getlighttpdstats();

  if ($ECHO) echokeys();

  sendvalidkeys();

  exit(0);



function sendvalidkeys()
{
  global $lighttpd_keys;

  $count = 0;

  foreach ($lighttpd_keys as $key => $val)
  {
    zabbix_send($key, $val);
    $count++;
  }

  echo "$count";
}


function zabbix_send ($var, $val) {
	global $ZABBIX_SERVER, $ZABBIX_HOST, $LOGFILE, $LIGHTTPD_KEY;

	switch ( strtolower($val) ) {
		case "yes":
		case "on":
			$val = 1;
			break;
		case "no":
		case "":
		case "off":
			$val = 0;
			break;
	}

	if ( !is_numeric($val) ) $val = '"'.$val.'"';

	file_put_contents($LOGFILE, "$ZABBIX_SERVER $ZABBIX_HOST 10051 $LIGHTTPD_KEY.$var $val\n",FILE_APPEND);
	$cmd = "/usr/local/bin/zabbix_sender -z $ZABBIX_SERVER -p 10051 -s $ZABBIX_HOST -k $LIGHTTPD_KEY.$var -o $val";

	if ( DEBUG ) 
		echo "$cmd\n";
	else
		system("$cmd 2>&1 >> ".$LOGFILE);
}



function getlighttpdstats()
{
  global $lighttpd_keys;

  $options = array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => false
  );

  $curl = curl_init("http://localhost/server-status?auto");
  curl_setopt_array($curl, $options );
  $content = curl_exec( $curl );
  $err = curl_errno( $curl );

  if ($err != 0) exit(-2);

  $lines = explode("\n", $content);

  foreach ($lines as $key => $value)
  {
    $params = explode(":", $value);

    if (count($params) != 2) continue;

    $keyvalue = trim($params[1]);
    $keyname  = strtolower(strtr(trim($params[0]), " ", "_"));

    if ($keyname == "scoreboard") {
      parsescoreboard($keyvalue);
    }
    else {
      $lighttpd_keys[$keyname] = $keyvalue;
    }
  }

}


function parsescoreboard ($scoreboard)
{
  global $lighttpd_keys;

  $keepalivecnt = 0;
  $writecnt     = 0;
  $readcnt      = 0;
  $closecnt     = 0;
  $handlecnt    = 0;
  $requestcnt   = 0;
  $responsecnt  = 0;
  $opencnt      = 0;
  $errorcnt     = 0;

  for ($i = 0; $i < strlen($scoreboard); ++$i)
  {
    switch ($scoreboard[$i])
    {
      case "h":
        $handlecnt++;
 	break;
      case "E":
	$errorcnt++;
	break;
      case "k":
        $keepalivecnt++;
	break;
      case "W":
        $writecnt++;
	break;
      case "r":
      case "R":
        $readcnt++;
	break;
      case "C":
        $closecnt++;
	break; 
      case "q":
      case "Q":
        $requestcnt++;
	break;
      case "s":
      case "S":
	$responsecnt++;
	break;
      case ".":
	$opencnt++;
	break;
      default:
	break;
    }
  }

  $lighttpd_keys["handlecount"] = $handlecnt;
  $lighttpd_keys["writecount"] = $writecnt;
  $lighttpd_keys["readcount"] = $readcnt;
  $lighttpd_keys["closecount"] = $closecnt;
  $lighttpd_keys["requestcount"] = $requestcnt;
  $lighttpd_keys["responsecount"] = $responsecnt;
  $lighttpd_keys["opencount"] = $opencnt;
  $lighttpd_keys["errorcount"] = $errorcnt;
  $lighttpd_keys["keepalivecount"] = $keepalivecnt;

}


function echokeys()
{
  global $lighttpd_keys;

  foreach ($lighttpd_keys as $key => $value)
  {
    echo ("$key = $value\n");
  }
}

?>
