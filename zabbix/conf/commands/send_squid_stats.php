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


  $ZABBIX_SERVER = "10.2.212.14";
  $ZABBIX_HOST = "squid1.uesp.net";
  $SQUID_KEY = "squid";

  $SQUID_VALID_STATS = "/etc/zabbix/commands/squid-zabbix.stats";

  $LOGFILE = "/var/log/zabbix/squid_output.log";
  if (file_exists($LOGFILE)) unlink($LOGFILE);

  $squid_keys = array();
  $squid_valid_keys = array();
  $squid_zabbix_keys = array();

  $valid_localhosts = array("localhost","127.0.0.1", "%");

  getSquidStats();

  transformkeys();

  if ($ECHO)
  {
    echokeys();
  }

  loadvalidkeys();
  parsevalidkeys(); 
  sendvalidkeys();

  exit(0);


function transformkeys()
{

}


function sendvalidkeys()
{
  global $squid_zabbix_keys;

  $count = 0;

  foreach ($squid_zabbix_keys as $key => $val)
  {
    zabbix_send($key, $val);
    $count++;
  }

  echo "$count";
}


function filtervalidkeys($item)
{
	$trimitem = trim($item);

	if (count($trimitem) == 0) return false;
	if ($trimitem[0] == '#') return false;
	return true;
}


function transformvalidkeys($item)
{
	$newitem = str_replace(".", "_", $item);

	return trim($newitem);
}


function loadvalidkeys()
{
  global $squid_valid_keys, $SQUID_VALID_STATS, $COMMAND, $ECHO;

  $fileitems = file($SQUID_VALID_STATS, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);

  $tempitems = array_filter($fileitems, "filtervalidkeys");
  $squid_valid_keys = array_map("transformvalidkeys", $tempitems);

  if ($ECHO)
  {
    foreach ($squid_valid_keys as $key => $val)
    {
      echo "$key) $val\n";
    }
  }

}


function parsevalidkeys()
{
  global $squid_valid_keys, $squid_zabbix_keys, $squid_keys, $LOGFILE, $COMMAND, $ECHO;
  
  $invalidcount = 0;
  $validcount = 0;

  foreach ($squid_valid_keys as $key => $val)
  {
    $validkey = array_key_exists($val, $squid_keys);

    if ($validkey)
    {
        $validcount++;
        $squid_zabbix_keys[$val] = $squid_keys[$val];	
	file_put_contents($LOGFILE,"Adding valid key '$val'.\n",FILE_APPEND);
    }
    else
    {
        $invalidcount++;
	file_put_contents($LOGFILE,"Unknown squid key '$val'...ignoring!\n",FILE_APPEND);
    }

  }

  if ($ECHO)
  {
    $totalkeys = $invalidcount + $validcount;
    echo "Parsed $validcount valid keys out of $totalkeys\n";
  }

}


function zabbix_send ($var, $val) {
	global $ZABBIX_SERVER, $ZABBIX_HOST, $LOGFILE, $SQUID_KEY;

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

	file_put_contents($LOGFILE,"$ZABBIX_SERVER $ZABBIX_HOST 10051 $SQUID_KEY.$var $val\n",FILE_APPEND);
	$cmd = "/usr/local/bin/zabbix_sender -z $ZABBIX_SERVER -p 10051 -s $ZABBIX_HOST -k $SQUID_KEY.$var -o $val";

	if ( DEBUG ) 
		echo "$cmd\n";
	else
		system("$cmd 2>&1 >> ".$LOGFILE);
}


function echokeys()
{
  global $squid_keys;

  foreach ($squid_keys as $key => $val)
  {
    echo("$key = $val\n");
  }
}



function getSquidStats()
{
  global $squid_keys, $LOGFILE;

  exec("squidclient -p 80 mgr:5min", $outputlines);

  foreach ($outputlines as $key => $val)
  {
	//echo("$key = $val\n");
	$parts = explode("=", $val);

	switch (count($parts))
	{
  	  case 0:
		  break;
	  case 1:
		break;
	  case 2:
		$trimkey = trim($parts[0]);
		$trimvalue = trim($parts[1]);
		$newkey = str_replace(".", "_", $trimkey);
		preg_match("/[+-]?[0-9]*[.]?[0-9]*/", $trimvalue, $matches);
		$squid_keys[$newkey] = $matches[0];
		break;
	}

	
  }

  exec("squidclient -p 80 mgr:info", $outputlines);

  foreach ($outputlines as $key => $val) 
  {
  	$parts = explode(":", $val);
	if (count($parts) != 2) continue;

	$trimkey = trim($parts[0]);
	$trimvalue = trim($parts[1]);
	$newkey = str_replace(" ", "_", $trimkey);

	preg_match("/[+-]?[0-9]*[.]?[0-9]*/", $trimvalue, $matches);
	$squid_keys[$newkey] = $matches[0];

  }

  //echokeys();
  //file_put_contents($LOGFILE,"Slave Status: $key='$var'\n",FILE_APPEND);
}

