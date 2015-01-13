<?php

$zabbix_sender = "/usr/local/bin/zabbix_sender";
$zabbix_server = "10.2.212.14";
$zabbix_port = 10051;
$memcache_server = "10.2.212.10";
$MEMCACHE_NAME = "files1.uesp.net";
$memcache_port = 11000;
$MEMCACHEKEY = "memcache";

$m=new Memcache;
$m->connect($memcache_server,$memcache_port);
$s=$m->getstats();

$count = 0;
$gets = 0;
$hits = 0;

foreach($s as $key=>$value)
{
	$cmd = "$zabbix_sender -z $zabbix_server -p $zabbix_port -s $MEMCACHE_NAME -k \"$MEMCACHEKEY.$key\" -o $value";
	exec ($cmd);
	#echo "$key = $value\n";
	#echo "$cmd\n";

	if ($key == "cmd_get") $gets = $value;
	if ($key == "get_hits") $hits = $value;
	$count += 1;
}

$hitrate = 0;

if ($gets > 0) $hitrate = 100*$hits/$gets;
$cmd = "$zabbix_sender -z $zabbix_server -p $zabbix_port -s $memcache_server -k $MEMCACHEKEY.hitrate -o $hitrate";
exec($cmd);
# echo "$cmd\n";

echo "$count";
?>

