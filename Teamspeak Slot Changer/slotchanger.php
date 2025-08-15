<?php
require_once("libraries/TeamSpeak3/TeamSpeak3.php");

TeamSpeak3::init();

// CONFIG

$extra_slots = 0; // Current client + extra_slots = Max slots
$count_query_users = true; // true / false
$safemode = false; // Disallows $extra_slots to be bellow 0

// Teamspeak Server Connection
$ts3['username'] = "serveradmin";
$ts3['password'] = "PASSWORD";
$ts3['ip'] = "127.0.0.1";
$ts3['qport'] = "10011";
$ts3['vport'] = "9987";
$ts3['nickname'] = "Slot Changer";

// CONFIG END


/*
  PLEASE READ:

  DO NOT SET $extra_slots to 0

  When disabling $count_query_users make sure that your extra_slots is
  higher than the amount of query clients you want to have on your server
  otherwise query clients cannot connect.

  Example:
  If there are no clients on your serer and $extra_slots set to 0 the 
  maxiumum slots will be set to 0 for both clients and query clients.
  Meaning, neither clients nor query clients can connect to the server. 

*/

$ts3['nickname'] = urlencode($ts3['nickname']);

if ($extra_slots == 0) {
  echo "\nWARNING:\n";
  echo "\$extra_slots is set to 0, if the server becomes empty, max slots will be set to 0 and clients will not be able to connect.\n\n";
}

if ($safemode && $extra_slots == 0) {
  $extra_slots = 3;
}

if ($safemode) {
  $sm = "True";
} else {
  $sm = "False";
}

if ($count_query_users) {
  $cqu = "True";
} else {
  $cqu = "False";
}

echo "Bot Started\n\n";
echo "Safe mode: " . $sm . "\n";
echo "Count Query Clients: " . $cqu . "\n";
echo "Extra Slots: " . $extra_slots . "\n\n";



try
{
  TeamSpeak3_Helper_Signal::getInstance()->subscribe("notifyServerselected", "onSelect");

  $tsHandle = TeamSpeak3::factory("serverquery://{$ts3['username']}:{$ts3['password']}@{$ts3['ip']}:{$ts3['qport']}/?server_port={$ts3['vport']}&timeout=5&blocking=0&nickname={$ts3['nickname']}");

  while(1) $tsHandle->getAdapter()->wait();
  
}
catch(Exception $e)
{
  die("[ERROR]  " . $e->getMessage() . "\n");
}


function onTimeout($seconds, TeamSpeak3_Adapter_ServerQuery $adapter)
{
  if($adapter->getQueryLastTimestamp() < time()-250)
  {
    $adapter->request("clientupdate");
  }
}


function onSelect(TeamSpeak3_Node_Host $host)
{
  TeamSpeak3_Helper_Signal::getInstance()->subscribe("serverqueryWaitTimeout", "onTimeout");
  TeamSpeak3_Helper_Signal::getInstance()->subscribe("notifyCliententerview", "onClientEnter");
  TeamSpeak3_Helper_Signal::getInstance()->subscribe("notifyClientleftview", "onClientEnter");
  $host->serverGetSelected()->notifyRegister("server");
  echo "Connected to: " . $host->serverGetSelected()->getProperty("virtualserver_name") . "\n\n";

}

function onClientEnter(TeamSpeak3_Adapter_ServerQuery_Event $event, TeamSpeak3_Node_Host $host)
{
    try {
        global $extra_slots, $count_query_users, $safemode;
        if ($count_query_users) {
          $count = $host->serverGetSelected()->getProperty("virtualserver_clientsonline");
          $max_slots = $host->serverGetSelected()->getProperty("virtualserver_maxclients");
        } else {
          $count = $host->serverGetSelected()->getProperty("virtualserver_clientsonline") - $host->serverGetSelected()->getProperty("virtualserver_queryclientsonline");
          $max_slots = $host->serverGetSelected()->getProperty("virtualserver_maxclients");
        }

        $desired_maxslots = $count + $extra_slots;

        if ($desired_maxslots == 0 && $safemode) {
          $desired_maxslots = 1;
        }

        if ($desired_maxslots != $max_slots) {
          $properties = ['virtualserver_maxclients' => $desired_maxslots];
          $host->serverGetSelected()->execute("serveredit", $properties);
        }

    } catch(TeamSpeak3_Exception $e) {
        echo "Error ".$e->getCode().": ".$e->getMessage();
    }
}
?>