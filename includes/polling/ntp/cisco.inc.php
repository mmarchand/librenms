<?php
/*
 * LibreNMS module to capture statistics from the CISCO-NTP-MIB
 *
 * Copyright (c) 2016 Aaron Daniels <aaron@daniels.id.au>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

$module = 'ntp';

$component = new LibreNMS\Component();
$options = array();
$options['filter']['type'] = array('=',$module);
$options['filter']['disabled'] = array('=',0);
$options['filter']['ignore'] = array('=',0);
$components = $component->getComponents($device['device_id'], $options);

// We only care about our device id.
$components = $components[$device['device_id']];

// Only collect SNMP data if we have enabled components
if (count($components > 0)) {
    // Let's gather the stats..
    $cntpPeersVarEntry = snmpwalk_array_num($device, '.1.3.6.1.4.1.9.9.168.1.2.1.1', 2);

    // Loop through the components and extract the data.
    foreach ($components as $key => &$array) {
        $peer = $array['peer'];

        // Let's make sure the rrd is setup for this class.
        $rrd_name = array('ntp', $peer);
        $rrd_def = array(
            'DS:stratum:GAUGE:600:0:U',
            'DS:offset:GAUGE:600:0:U',
            'DS:delay:GAUGE:600:0:U',
            'DS:dispersion:GAUGE:600:0:U',
        );

        $array['stratum'] = $cntpPeersVarEntry['1.3.6.1.4.1.9.9.168.1.2.1.1'][9][$array['UID']];
        // Set the status, 16 = Bad
        if ($array['stratum'] == 16) {
            $array['status'] = 2;
            $array['error'] = 'NTP Stratum is Insane';
        } else {
            $array['status'] = 0;
            $array['error'] = '';
        }

        // Extract the statistics and update rrd
        $rrd['stratum'] = $array['stratum'];
        $rrd['offset'] = hexdec($cntpPeersVarEntry['1.3.6.1.4.1.9.9.168.1.2.1.1'][23][$array['UID']]);
        $rrd['delay'] = hexdec($cntpPeersVarEntry['1.3.6.1.4.1.9.9.168.1.2.1.1'][24][$array['UID']]);
        $rrd['dispersion'] = hexdec($cntpPeersVarEntry['1.3.6.1.4.1.9.9.168.1.2.1.1'][25][$array['UID']]);
        $tags = compact('ntp', 'rrd_name', 'rrd_def', 'peer');
        data_update($device, 'ntp', $tags, $rrd);

        // Let's print some debugging info.
        d_echo("\n\nComponent: ".$key."\n");
        d_echo("    Index:      ".$array['UID']."\n");
        d_echo("    Peer:       ".$array['peer'].":".$array['port']."\n");
        d_echo("    Stratum:    1.3.6.1.4.1.9.9.168.1.2.1.1.9.".$array['UID']."  = ".$rrd['stratum']."\n");
        d_echo("    Offset:     1.3.6.1.4.1.9.9.168.1.2.1.1.23.".$array['UID']." = ".$rrd['offset']."\n");
        d_echo("    Delay:      1.3.6.1.4.1.9.9.168.1.2.1.1.24.".$array['UID']." = ".$rrd['delay']."\n");
        d_echo("    Dispersion: 1.3.6.1.4.1.9.9.168.1.2.1.1.25.".$array['UID']." = ".$rrd['dispersion']."\n");

        // Clean-up after yourself!
        unset($filename, $rrd_filename, $rrd);
    } // End foreach components

    // Write the Components back to the DB.
    $component->setComponentPrefs($device['device_id'], $components);
} // end if count components

// Clean-up after yourself!
unset($type, $components, $component, $options, $module);
