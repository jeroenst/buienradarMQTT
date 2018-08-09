#!/usr/bin/php
<?php


require(realpath(dirname(__FILE__))."/phpMQTT/phpMQTT.php");

$server = "192.168.2.1";     // change if necessary
$port = 1883;                     // change if necessary
$username = "";                   // set your username
$password = "";                   // set your password
$client_id = uniqid("buienradar_");; // make sure this is unique for connecting to sever - you could use uniqid()
echo ("Smartmeter MQTT publisher started...\n");
$mqttTopicPrefix = "home/buienradar";
$iniarray = parse_ini_file("buienradarMQTT.ini",true);
if (($tmp = $iniarray["buienradarMQTT"]["mqttserver"]) != "") $server = $tmp;
if (($tmp = $iniarray["buienradarMQTT"]["mqttport"]) != "") $tcpport = $tmp;
if (($tmp = $iniarray["buienradarMQTT"]["mqttusername"]) != "") $username = $tmp;
if (($tmp = $iniarray["buienradarMQTT"]["mqttpassword"]) != "") $password = $tmp;


$mqtt = new phpMQTT($server, $port, $client_id);
$mqtt->connect(true, NULL, $username, $password);

$timeouttimer = 0;

while (1)
{
        if ($timeouttimer == 0)
        {
                        // For now we use synchone fetching of buienradar because they changed from http to https :-(
                        echo ("Fetching data from  buienradar server...\n");
                        //$buienradarsocket = socketconnect('xml.buienradar.nl', 443);
                       $url = "https://xml.buienradar.nl";
                       $array = [];
                       $simpleXml = simplexml_load_file($url);
                       if ($simpleXml)
                       {
                               	simplexml_to_array($simpleXml, $array, "home/buienradar");
                        }
        }
        
        if ($timeouttimer == 600) $timeouttimer = 0;
        else $timeouttimer++;
        
        $mqtt->proc();
        sleep(1);

}        
        
 function simplexml_to_array ($xml, &$array, $origpath, $next = 0)
 {

        // Empty node : <node></node>
        //$array[$xml->getName()][] = '';

        global $mqtt;

        // Nodes with children
        foreach ($xml->children() as $child) {
        if ($next) $path = $origpath."/".$child->getName();
        else $path = $origpath;
        if ($child->getName() == "weerstation") $path.="/".$child->attributes()["id"];
                $nrofsamechilds = 0;
                foreach ($xml->children() as $searchchild)
                {
                        if ($child->getName() == $searchchild->getName()) $nrofsamechilds++;
                }
                if ($nrofsamechilds > 1)
                {
                        simplexml_to_array($child, $array[$xml->getName()][], $path,1);
                }
                else
                {
                        simplexml_to_array($child, $array[$xml->getName()], $path,1);
                }
        }
        

        // Node attributes
        foreach ($xml->attributes() as $key => $att) {
                $array[$xml->getName()]['@attributes'][$key] = (string) $att;
        }

        // Node with value
        if (trim((string) $xml) != '') {
                $array[$xml->getName()][] = (string) $xml;
                echo ($origpath."=".(string) $xml."\n");
                $mqtt->publishwhenchanged($origpath, (string) $xml, 0, 1);
        }

}
