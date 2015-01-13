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

  $MYSQL_KEY = "mysql";
	
  $MYSQL_SERVER = "localhost";
  $MYSQL_USER   = "uespinfo";
  $MYSQL_PASSWD = "hQ39ab8Xrt";

  $ZABBIX_SERVER = "10.2.212.14";
  $ZABBIX_HOST = "content3.uesp.net";

  $MYSQL_VALID_STATS = "/etc/zabbix/commands/mysql-zabbix.stats";

  $LOGFILE = "/var/log/zabbix/mysql_output.log";
  if (file_exists($LOGFILE)) unlink($LOGFILE);

  $mysql_keys = array();
  $mysql_valid_keys = array();
  $mysql_zabbix_keys = array();

  $valid_localhosts = array("localhost","127.0.0.1", "%");

  $mysql_privs = array(
			"Insert_priv"=>"Insert_priv_count",
			"Update_priv"=>"Update_priv_count",
			"Delete_priv"=>"Delete_priv_count",
			"Drop_priv"=>"Drop_priv_count",
			"Shutdown_priv"=>"Shut_down_priv_count",
			"Process_priv"=>"Process_priv_count",
			"File_priv"=>"File_priv_count",
			"Grant_priv"=>"Grant_priv_count",
			"Alter_priv"=>"Alter_priv_count",
			"Super_priv"=>"Super_priv_count",
			"Lock_tables_priv"=>"Lock_tables_priv_count",
	);

  $db_connection = mysql_connect($MYSQL_SERVER, $MYSQL_USER, $MYSQL_PASSWD);
  mysql_select_db("mysql");

  if ($COMMAND == "priv")
  {
    getprivstatus();
  }
  else
  {
    getclientversion();
    getglobalvariables();
    getglobalstatus();
    getmasterstatus();
    getslavestatus();
  }

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
  global $mysql_keys;

  if (array_key_exists("Slave_IO_Running", $mysql_keys))
  {
    $val = $mysql_keys["Slave_IO_Running"];

    if (strtolower($val) == "yes")
      $mysql_keys["Slave_IO_Running"] = 1;
    else
      $mysql_keys["Slave_IO_Running"] = 0;
  }

  if (array_key_exists("Slave_SQL_Running", $mysql_keys))
  {
    $val = $mysql_keys["Slave_SQL_Running"];

    if (strtolower($val) == "yes")
      $mysql_keys["Slave_SQL_Running"] = 1;
    else
      $mysql_keys["Slave_SQL_Running"] = 0;
  }
}


function sendvalidkeys()
{
  global $mysql_zabbix_keys;

  $count = 0;

  foreach ($mysql_zabbix_keys as $key => $val)
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
	return trim($item);
}


function loadvalidkeys()
{
  global $mysql_valid_keys, $MYSQL_VALID_STATS, $COMMAND, $ECHO;

  $fileitems = file($MYSQL_VALID_STATS, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);

  $tempitems = array_filter($fileitems, "filtervalidkeys");
  $mysql_valid_keys = array_map("transformvalidkeys", $tempitems);

  if ($ECHO)
  {
    foreach ($mysql_valid_keys as $key => $val)
    {
      echo "$key) $val\n";
    }
  }

}


function parsevalidkeys()
{
  global $mysql_valid_keys, $mysql_zabbix_keys, $mysql_keys, $LOGFILE, $COMMAND, $ECHO;
  
  $invalidcount = 0;
  $validcount = 0;

  foreach ($mysql_valid_keys as $key => $val)
  {
    $validkey = array_key_exists($val, $mysql_keys);

    if ($validkey)
    {
        $validcount++;
        $mysql_zabbix_keys[$val] = $mysql_keys[$val];	
	file_put_contents($LOGFILE,"Adding valid key '$val'.\n",FILE_APPEND);
    }
    else
    {
        $invalidcount++;
	file_put_contents($LOGFILE,"Unknown mysql key '$val'...ignoring!\n",FILE_APPEND);
    }

  }

  if ($ECHO)
  {
    $totalkeys = $invalidcount + $validcount;
    echo "Parsed $validcount valid keys out of $totalkeys\n";
  }

}


function zabbix_send ($var, $val) {
	global $ZABBIX_SERVER, $ZABBIX_HOST, $LOGFILE, $MYSQL_KEY;

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

	file_put_contents($LOGFILE,"$ZABBIX_SERVER $ZABBIX_HOST 10051 $MYSQL_KEY.$var $val\n",FILE_APPEND);
	$cmd = "/usr/local/bin/zabbix_sender -z $ZABBIX_SERVER -p 10051 -s $ZABBIX_HOST -k $MYSQL_KEY.$var -o $val";

	if ( DEBUG ) 
		echo "$cmd\n";
	else
		system("$cmd 2>&1 >> ".$LOGFILE);
}


function echokeys()
{
  global $mysql_keys;

  foreach ($mysql_keys as $key => $val)
  {
    echo("$key = $val\n");
  }
}


function getprivstatus()
{
  global $mysql_keys, $mysql_privs, $valid_localhosts;

  foreach ($mysql_privs as $key => $var)
  {
    $mysql_keys[$var] = 0;
  }

  $result = mysql_query("select * from user;");
  if (!$result) return;

  $root_count = 0;
  $nopass_count = 0;
  $broadhost_count = 0;
  $anon_count = 0;

  $mysql_keys["Root_Remote_Login"] = 0;
  $mysql_keys["Root_No_Password"]  = 0;

  while ($row = mysql_fetch_assoc($result))
  {
    if ($row['Host'] == "" || $row['Host'] == '%') $broadhost_count++;

    if ($row["User"] == "root")
    {
      $root_count++;
      $invalid = false;

      if ($row["Host"] == "" || $row["Host"] == "%" || !in_array($row["Host"], $valid_localhosts) ) $mysql_keys["Root_Remote_Login"] = 1;
      if ($row["Password"] == "") $mysql_keys["Root_No_Password"] = 1;
    }

    if ($row["Password"] == "") $nopass_count++;
    if ($row["User"] == "") $anon_count++;

    foreach ($mysql_privs as $key => $var)
    {
      if ($row[$key] == "Y")
      {
        $mysql_keys[$var]++;
      }
    }
  }

  $mysql_keys["Anonymous_Accounts_Count"]  = $anon_count;
  $mysql_keys["Root_Accounts_Count"]       = $root_count;
  $mysql_keys["Accounts_Without_Password"] = $nopass_count;
  $mysql_keys["Accounts_With_BroadHost"]   = $broadhost_count;
  
}


function getslavestatus()
{
  global $mysql_keys, $LOGFILE;

  $result = mysql_query("show slave status;");
  if (!$result) return;

  while ($row = mysql_fetch_assoc($result))
  {
    foreach ($row as $key => $var)
    {
      $mysql_keys[$key] = $var;

      file_put_contents($LOGFILE,"Slave Status: $key='$var'\n",FILE_APPEND);
    }
  }

}


function getmasterstatus()
{
  global $mysql_keysi, $LOGFILE;

  $result = mysql_query("show master status;");
  if (!$result) return;

  while ($row = mysql_fetch_assoc($result))
  {
    foreach ($row as $key => $var)
    {
      $key = "Master_Status_$key";
      $mysql_keys[$key] = $var;	

      file_put_contents($LOGFILE,"Master Status: $key='$var'\n",FILE_APPEND);
    }
  }

}


function getglobalstatus()
{
  $result = mysql_query("show global status;");
  if (!$result) return;

  storequeryresult($result);
}


function getglobalvariables()
{
  $result = mysql_query("show global variables;");
  if (!$result) return;

  storequeryresult($result);
}


function storequeryresult ($result)
{
  global $mysql_keys, $LOGFILE;

  while ($row = mysql_fetch_assoc($result))
  {
    $var = $row["Variable_name"];
    $val = $row["Value"];

    $mysql_keys[$var] = $val;

    file_put_contents($LOGFILE,"Status: $var='$val'\n",FILE_APPEND);
  }

}


function getclientversion()
{
  global $mysql_keys;

  $buffer = explode(" ", `mysql --version`);
  $mysql_keys["clientversion"] = substr($buffer[5], 0, strlen($buffer[4])-1);
}

?>
