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

  $APACHE_KEY = "apache";
	
  $ZABBIX_SERVER = "10.7.143.20";
  $ZABBIX_HOST = "content3.uesp.net";

  $LOGFILE = "/var/log/zabbix/apache_output.log";
  if (file_exists($LOGFILE)) unlink($LOGFILE);

  $apache_keys = array();

  getapachestats();
  getapacherequesttimestats();

  if ($ECHO) echokeys();

  sendvalidkeys();

  exit(0);



function sendvalidkeys()
{
  global $apache_keys;

  $count = 0;

  foreach ($apache_keys as $key => $val)
  {
    zabbix_send($key, $val);
    $count++;
  }

  echo "$count";
}


function zabbix_send ($var, $val) {
	global $ZABBIX_SERVER, $ZABBIX_HOST, $LOGFILE, $APACHE_KEY;

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

	file_put_contents($LOGFILE, "$ZABBIX_SERVER $ZABBIX_HOST 10051 $APACHE_KEY.$var $val\n",FILE_APPEND);
	$cmd = "/usr/local/bin/zabbix_sender -z $ZABBIX_SERVER -p 10051 -s $ZABBIX_HOST -k $APACHE_KEY.$var -o $val";

	if ( DEBUG ) 
		echo "$cmd\n";
	else
		system("$cmd 2>&1 >> ".$LOGFILE);
}



function getapachestats()
{
  global $apache_keys;

  $options = array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => false,
  	CURLOPT_SSL_VERIFYHOST => false,
  );

  $curl = curl_init("https://localhost/server-status?auto");
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
      $apache_keys[$keyname] = $keyvalue;
    }
  }

}


function getapacherequesttimestats()
{
  global $apache_keys;

  $options = array(
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => false,
  	CURLOPT_SSL_VERIFYHOST => false,
  );

  $curl = curl_init("https://localhost/server-status");
  curl_setopt_array($curl, $options );
  $content = curl_exec( $curl );
  $err = curl_errno( $curl );

  $result = preg_match_all('#<tr>(.*?)</tr>#s', $content, $matches);
  if ($result == false) return false;
  
  foreach ($matches as $key => $match)
  {
  }

}


function parsescoreboard ($scoreboard)
{
  global $apache_keys;

  $keepalivecnt = 0;
  $writecnt     = 0;
  $readcnt      = 0;
  $closecnt     = 0;
  $waitcnt      = 0;
  $dnscnt       = 0;
  $startcnt     = 0;
  $logcnt       = 0;
  $gracecnt     = 0;
  $idlecnt      = 0;
  $opencnt      = 0;

  for ($i = 0; $i < strlen($scoreboard); ++$i)
  {
    switch ($scoreboard[$i])
    {
      case "_":
        $waitcnt++;
 	break;
      case "K":
        $keepalivecnt++;
	break;
      case "W":
        $writecnt++;
	break;
      case "R":
        $readcnt++;
	break;
      case "C":
        $closecnt++;
	break; 
      case "D":
        $dnscnt++;
	break;
      case "I":
	$idlecnt++;
	break;
      case ".":
	$opencnt++;
	break;
      case "L":
	$logcnt++;
	break;
      case "S":
	$startcnt++;
	break;
      case "G":
	$gracecnt++;
	break;
      default:
	break;
    }
  }

  $apache_keys["waitcount"] = $waitcnt;
  $apache_keys["writecount"] = $writecnt;
  $apache_keys["readcount"] = $readcnt;
  $apache_keys["closecount"] = $closecnt;
  $apache_keys["dnscount"] = $dnscnt;
  $apache_keys["startcount"] = $startcnt;
  $apache_keys["logcount"] = $logcnt;
  $apache_keys["gracecount"] = $gracecnt;
  $apache_keys["idlecount"] = $idlecnt;
  $apache_keys["opencount"] = $opencnt;
  $apache_keys["keepalivecount"] = $keepalivecnt;

}


function echokeys()
{
  global $apache_keys;

  foreach ($apache_keys as $key => $value)
  {
    echo ("$key = $value\n");
  }
}

?>
