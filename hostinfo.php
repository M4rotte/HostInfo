<?php

$chrono['global_start'] = microtime(true);

function is_valid_hostname($host) {
  if (preg_match('/^([a-zA-Z0-9](?:(?:[a-zA-Z0-9-]*|(?<!-)\.(?![-.]))*[a-zA-Z0-9]+)?)$/', $host) && strlen($host) <= 64) return true; else return false;
}

function compare_CPU($a,$b) {
  return -strnatcmp($a[3], $b[3]);
}

function compare_date($a,$b) {
  return -strnatcmp($a[2], $b[2]);
}

function sec2dhms($s) {
  $day = floor($s / 86400);
  $hours = str_pad(floor(($s / 3600) % 24),2,0, STR_PAD_LEFT);
  $minutes = str_pad(floor(($s / 60) % 60),2,0, STR_PAD_LEFT);
  $seconds = str_pad($s % 60,2,0, STR_PAD_LEFT);
  return "$day:$hours:$minutes:$seconds";
}

// If no hostname given then use localhost
if (isset ($_GET['host'])) $host = $_GET['host']; else $host = '127.0.0.1';

// If given hostname is invalid then exit
if (! is_valid_hostname($host)) { print ("« $host » is not a valid hostname or IP address…"); exit (1); }

?>

<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?php print(strtoupper($host)); ?></title>
<style>
/* Use of CSS variable, doesn't work in IE */
:root {
  --main-light-color: Gold;
  --main-color: GoldenRod;
  --bg-color: LightGoldenRodYellow
}  

body {height: 100%;
      width: 98%;
      font-family: Monospace;
      background-color: var(--bg-color);
}
div#hardware {position: relative;
        margin-top: auto;
        margin-bottom: 5px;
}
div#summary {position: relative;
               display: inline-block;
               margin-top: auto;
               margin-bottom: 5px;
}
div#fs {position: relative;
               display: inline-block;
               margin-top: auto;
               margin-bottom: 5px;
}
div#processes {position: relative;
               #display: inline-block;
               margin-top: auto;
               margin-bottom: 5px;
}
div#software  {position: relative;
               #display: inline-block;
               margin-top: auto;
               margin-bottom: 5px;
}
table {border: solid var(--main-light-color) 1px;
       display: table-cell;
      
}
th {text-align: left;
    background-color: var(--main-light-color); 
}
th.title {background-color: var(--main-color);
          text-align: center;
}
td {padding-left: 5px;
    border-bottom: solid silver 1px;
    border-right: solid silver 1px;
}

</style>
</head>
<body>
<?php

// NRPE requests

$chrono['nrpe_start'] = microtime(true);

exec('/usr/lib/nagios/plugins/check_nrpe -H '.$host,$nrpe_version);
exec('/usr/lib/nagios/plugins/check_nrpe -H '.$host.' -c check_load -a 6,4,2 8,6,4',$load_average);
exec('/usr/lib/nagios/plugins/check_nrpe -H '.$host.' -c check_swap -a 99% 5%',$swap_usage);

$chrono['nrpe_end'] = microtime(true);

$chrono['snmpwalk_start'] = microtime(true);

// SNMP walk
snmp_set_quick_print(TRUE);
$a = @snmp2_real_walk($host, "public", "", 10000000, 5);
if ($a === FALSE) { print ("Host not responding… : $host"); exit (1); }

$chrono['snmpwalk_end'] = microtime(true);

$keys = array_keys ($a);
$values = array_values ($a);
$i = 0;

foreach ($keys as $k) {
  $k1s = strpos ($k, '::')+2;
  $k1e = strpos ($k, '.');
  $k2l = strlen ($k) - $k1e;
  $k1 = substr ($k,$k1s,$k1e - $k1s);
  $k2 = substr ($k, $k1e+1, $k2l);
  $data[$k1][$k2] = $values[$i];
  $info[$k1][$k2] = $keys[$i];
  $i++;
}
if (! isset ($data['sysName'])) { print ("No SNMP data received… : $host<br />\n$nrpe_version[0]"); exit(1); }


// Display some info
// Host description
print ('<h1 title="'.$info['sysDescr'][0].'">'.$data['sysDescr'][0].'</h1>'."\n");

// Host summary
print ('<div id="summary">'."\n");
print ('<table>'."\n");
print ('<tr><th colspan="2" class="title">Summary</th></tr>'."\n");
print ('<tr><td>Name</td><td title="'.$info['sysName'][0].'">'.$data['sysName'][0].'</td></tr>'."\n");
print ('<tr><td>Date</td><td title="'.$info['hrSystemDate'][0].'">'.$data['hrSystemDate'][0].'</td></tr>'."\n");
print ('<tr><td>SNMP Uptime</td><td title="'.$info['hrSystemUptime'][0].'">'.$data['hrSystemUptime'][0].'</td></tr>'."\n");
print ('<tr><td>Memory</td><td title="'.$info['hrMemorySize'][0].'">'.$data['hrMemorySize'][0].'</td></tr>'."\n");
print ('<tr><td>Users</td><td title="'.$info['hrSystemNumUsers'][0].'">'.$data['hrSystemNumUsers'][0].'</td></tr>'."\n");
print ('<tr><td>Processes</td><td title="'.$info['hrSystemProcesses'][0].'">'.$data['hrSystemProcesses'][0].'</td></tr>'."\n");
print ('<tr><td>Packages</td><td>'.count($data['hrSWInstalledIndex']).'</td></tr>'."\n");
print ('<tr><td>NRPE</td><td>'.$nrpe_version[0].'</td></tr>'."\n");
print ('<tr><td>Load average</td><td>'.$load_average[0].'</td></tr>'."\n");
print ('<tr><td>Swap usage</td><td>'.$swap_usage[0].'</td></tr>'."\n");
print ('</table>'."\n");
print ('</div>'."\n");

// Hardware
print ('<div id="hardware">'."\n");
//// Devices
print ('<table>'."\n");
print ('<tr><th colspan="5" class="title">Devices</th></tr>'."\n");
print ('<tr><th>Description</th><th>Status</th><th>Capacity</th><th>Errors</th><th>CPU Load</th></tr>'."\n");
foreach ($data['hrDeviceIndex'] as $index) {
   if (! isset ($data['hrDeviceStatus'][$index])) $status = ''; else $status = $data['hrDeviceStatus'][$index];
   if (! isset ($data['hrDiskStorageCapacity'][$index])) $capacity = ''; else $capacity = $data['hrDiskStorageCapacity'][$index];
   if (! isset ($data['hrDeviceErrors'][$index])) $errors = ''; else $errors = $data['hrDeviceErrors'][$index];
   if (! isset ($data['hrProcessorLoad'][$index])) $cpuload = ''; else $cpuload = $data['hrProcessorLoad'][$index];
   print ('<tr><td>'.$data['hrDeviceDescr'][$index].'</td><td>'.$status.'</td><td>'.$capacity.'</td><td>'.$errors.'</td><td>'.$cpuload.'</td></tr>'."\n"); 
}  
print ('</table>'."\n");

//// Storage
print ('<table>'."\n");
print ('<tr><th class="title" colspan="2">Storage</th></tr>'."\n");
print ('<tr><th>Description</th><th>% used</th></tr>'."\n");
foreach ($data['hrStorageIndex'] as $index) {
$used_space = round($data['hrStorageUsed'][$index]  / max(1,$data['hrStorageSize'][$index]) * 100, 2);
   print ('<tr><td title="'.$info['hrStorageDescr'][$index].'">'.$data['hrStorageDescr'][$index].'</td><td>'.$used_space.'</td></tr>'."\n"); 
}  
print ('</table>'."\n");

//// Interfaces
print ('<table>'."\n");
print ('<tr><th colspan="9" class="title">Interfaces</th></tr>'."\n");
print ('<tr><th>Description</th><th>Admin. Status</th><th>Open. Status</th><th>IN (bytes)</th><th>Errors</th><th>Discards</th><th>OUT (bytes)</th><th>Errors</th><th>Discards</th></tr>'."\n");
foreach ($data['ifIndex'] as $index) {
   if (! isset ($data['ifAdminStatus'][$index])) $admstatus = ''; else $admstatus = $data['ifAdminStatus'][$index];
   if (! isset ($data['ifOperStatus'][$index])) $opestatus = ''; else $opestatus = $data['ifOperStatus'][$index];
   if (! isset ($data['ifInOctets'][$index])) $in = ''; else $in = $data['ifInOctets'][$index];
   if (! isset ($data['ifInErrors'][$index])) $in_err = ''; else $in_err = $data['ifInErrors'][$index];
   if (! isset ($data['ifInDiscards'][$index])) $in_disc = ''; else $in_disc = $data['ifInDiscards'][$index];
   if (! isset ($data['ifOutOctets'][$index])) $out = ''; else $out = $data['ifOutOctets'][$index];
   if (! isset ($data['ifOutErrors'][$index])) $out_err = ''; else $out_err = $data['ifOutErrors'][$index];
   if (! isset ($data['ifOutDiscards'][$index])) $out_disc = ''; else $out_disc = $data['ifOutDiscards'][$index];

   print ('<tr><td>'.$data['ifDescr'][$index].'</td><td>'.$admstatus.'</td><td>'.$opestatus.'</td><td>'.$in.'</td><td>'.$in_err.'</td><td>'.$in_disc.'</td><td>'.$out.'</td><td>'.$out_err.'</td><td>'.$out_disc.'</td></tr>'."\n"); 
}  
print ('</table>'."\n");  
print ('</div>'."\n");

// File systems
print ('<div id="fs">'."\n");
print ('<table>'."\n");
print ('<tr><th colspan="3" class="title">File systems</th></tr>'."\n");
print ('<tr><th>Mount point</th><th>Remote mount point</th><th>Type</th></tr>'."\n");

foreach ($data['hrFSIndex'] as $index) {
   $mp = str_replace ('"','',$data['hrFSMountPoint'][$index]);
   $rmp = str_replace ('"','',$data['hrFSRemoteMountPoint'][$index]);
   $type = split('::',$data['hrFSType'][$index],2);
   print ('<tr><td title="'.$info['hrFSMountPoint'][$index].'">'.$mp.'</td><td title="'.$info['hrFSRemoteMountPoint'][$index].'">'.$rmp.'</td><td title="'.$info['hrFSType'][$index].'">'.$type[1].'</td></tr>'."\n");
}

print ('</table>'."\n");  
print ('</div>'."\n");


// Processes
print ('<div id="processes">'."\n");
print ('<table>'."\n");
print ('<tr><th colspan="5" class="title">Processes</th></tr>'."\n");
print ('<tr><th>Path</th><th>Name</th><th>Parameters</th><th>CPU Time</th><th>Memory (kBytes)</th></tr>'."\n");

foreach ($data['hrSWRunIndex'] as $index) {
   $path = str_replace ('"','',$data['hrSWRunPath'][$index]);
   $name = str_replace ('"','',$data['hrSWRunName'][$index]);
   $params = str_replace ('"','',$data['hrSWRunParameters'][$index]);
   $cpu = $data['hrSWRunPerfCPU'][$index];
   $memory = split(' ',$data['hrSWRunPerfMem'][$index],2);
   $processes[$index] = array ($path, $name, $params, $cpu, $memory[0]);
}  

uasort($processes, 'compare_CPU');

foreach ($processes as $p) {
  print ('<tr><td>'.$p[0].'</td><td>'.$p[1].'</td><td>'.$p[2].'</td><td>'.sec2dhms($p[3]).'</td><td>'.$p[4].'</td></tr>'."\n");
}
print ('</table>'."\n");  
print ('</div>'."\n");


// Software
print ('<div id="software">'."\n");
print ('<table>'."\n");
print ('<tr><th colspan="3" class="title">Software</th></tr>'."\n");
print ('<tr><th>Name</th><th>Type</th><th>Date</th></tr>'."\n");

foreach ($data['hrSWInstalledIndex'] as $index) {
   $name = str_replace ('"','',$data['hrSWInstalledName'][$index]);
   $type = str_replace ('"','',$data['hrSWInstalledType'][$index]);
   $date = $data['hrSWInstalledDate'][$index];
   $software[$index] = array ($name, $type, $date);
}  

uasort($software, 'compare_date');

foreach ($software as $s) {
  print ('<tr><td>'.$s[0].'</td><td>'.$s[1].'</td><td>'.$s[2].'</td></tr>'."\n");
}
print ('</table>'."\n");  
print ('</div>'."\n");

$chrono['global_end'] = microtime(true);


$chrono['global_time'] = $chrono['global_end'] - $chrono['global_start'];
$chrono['snmpwalk_time'] = $chrono['snmpwalk_end'] - $chrono['snmpwalk_start'];
$chrono['nrpe_time'] = $chrono['nrpe_end'] - $chrono['nrpe_start'];
print ('<hr />Page generated '.date('r',time()).' in '.round($chrono['global_time'],3).' seconds, including '.round($chrono['snmpwalk_time'],3).' seconds for the snmpwalk request and '.round($chrono['nrpe_time'],3).' seconds for the NRPE requests.'."<br />\n\n");

?>

</body>
</html>