<?php
//
// RabbitMQ Sender Component
// Copyright (c) 2017 Matt Capra. All rights reserved.
//  

require_once(dirname(__FILE__) . '/../componenthelper.inc.php');

$rabbitmqsender_component_name = "rabbitmqsender";

rabbitmqsender_component_init();

////////////////////////////////////////////////////////////////////////
// COMPONENT INIT FUNCTIONS
////////////////////////////////////////////////////////////////////////

function rabbitmqsender_component_init()
{
    global $rabbitmqsender_component_name;
    $desc = "";

    // Check XI version
    $versionok = rabbitmqsender_component_checkversion();
    if (!$versionok)
        $desc .= "<b>" . _("Error: This component requires Nagios XI 2009R1.2B or later.") . "</b>  ";
	
	// Warn about required Python packages
	$required = rabbitmqsender_component_checkinstallation();
	if (sizeof($required) > 0) {
		
		$desc .= "<br/><b>" . _("Installation Required!") . "</b>
        " . _("You are missing the following python dependencies:") . "<br>";
		$desc .= '<ul class="errorMessage" style="margin-top: 0;"><li>
				' . implode(', ', $required) . '
			</li></ul>';
	}

    $args = array(
        COMPONENT_NAME => $rabbitmqsender_component_name,
        COMPONENT_AUTHOR => "Matt Capra",
        COMPONENT_DESCRIPTION => _("Allows Nagios XI to send RabbitMQ messages when host and service alerts occur.") . $desc,
        COMPONENT_TITLE => "RabbitMQ Sender",
        COMPONENT_VERSION => '1.0.0',
        COMPONENT_CONFIGFUNCTION => "rabbitmqsender_component_config_func",
    );

    register_component($rabbitmqsender_component_name, $args);
}


///////////////////////////////////////////////////////////////////////////////////////////
//CONFIG FUNCTIONS
///////////////////////////////////////////////////////////////////////////////////////////

function rabbitmqsender_component_config_func($mode = "", $inargs, &$outargs, &$result)
{
    global $rabbitmqsender_component_name;

    // Initialize return code and output
    $result = 0;
    $output = "";

    switch ($mode) {
        case COMPONENT_CONFIGMODE_GETSETTINGSHTML:

            // defaults
            $rabbitmq_hosts = array();
            for ($x = 0; $x <= 4; $x++) {
                $rabbitmq_hosts[$x] = array(
                    "address" => "",
                    //set port to empty
                    "port" => "",
                    "tcp" => 0,
                    "community" => "public",
                    "downtime" => 0,
                );
            }

            $settings_raw = get_option("rabbitmqsender_component_options");
            if ($settings_raw == "") {
                $settings = array(
                    "enabled" => 0
                );
            } else
                $settings = unserialize($settings_raw);


            // initial values
            $enabled = grab_array_var($settings, "enabled", "");
            $rabbitmq_hosts = grab_array_var($settings, "rabbitmq_hosts", $rabbitmq_hosts);
            
            // trim empty lines
            foreach ($rabbitmq_hosts as $x => $sa) {
                if ($sa["address"] == "")
                    unset($rabbitmq_hosts[$x]);
            }
            
            // Add an empty row at the end ...
            $rabbitmq_hosts[] = array(
                    "address" => "",
                    "port" => "",
                    "tcp" => 0,
                    "community" => "public",
                    "downtime" => 0,
            );
            
            $rabbitmq_hosts_count = count($rabbitmq_hosts);

            // fix missing values
            
            for ($x = 0; $x < $rabbitmq_hosts_count; $x++) {
                if (!array_key_exists("hoststateid", $rabbitmq_hosts[$x]))
                    $rabbitmq_hosts[$x]["hoststateid"] = "0";
                if (!array_key_exists("servicestateid", $rabbitmq_hosts[$x]))
                    $rabbitmq_hosts[$x]["servicestateid"] = "0";
                if (!array_key_exists("statetype", $rabbitmq_hosts[$x]))
                    $rabbitmq_hosts[$x]["statetype"] = "BOTH";
                if (!array_key_exists("port", $rabbitmq_hosts[$x]))
                    $rabbitmq_hosts[$x]["port"] = "";
                if (!array_key_exists("tcp", $rabbitmq_hosts[$x]))
                    $rabbitmq_hosts[$x]["tcp"] = "";
                if (!array_key_exists("downtime", $rabbitmq_hosts[$x]))
                    $rabbitmq_hosts[$x]["downtime"] = "";
            }
            
            
            $rabbitmq_hosts_count = count($rabbitmq_hosts);
            
            $component_url = get_component_url_base($rabbitmqsender_component_name);

            $output = '';

            $eventhandlersok = rabbitmqsender_component_checkeventhandlers();
            if (!$eventhandlersok)
                $output .= "<font color='red'><b>WARNING:</b> " . _("Event handlers are currently disabled.  This will prevent the RabbitMQ sender from working!") . "</font>";

            $output .= '
            
<h5 class="ul">' . _('Integration Settings') . '</h5>
    
<table class="table table-condensed table-no-border table-auto-width">
    <tr>
        <td></td>
        <td class="checkbox">
            <label>
                <input type="checkbox" class="checkbox" id="enabled" name="enabled" ' . is_checked($enabled, 1) . '>
                ' . _('Enable RabbitMQ sender integration') . '
            </label>
        </td>
    </tr>
</table>

<h5 class="ul">' . _('RabbitMQ Hosts') . '</h5>
    
<p>
    ' . _('Specify the addresses of the hosts that RabbitMQ messages should be sent to.  If you want to prevent messages from being sent during downtime check the checkbox for each host.') . '<br>
    ' . _('If your queue requires authentication, check the "Use Authentication" checkbox.') . '
</p>

<table class="table table-condensed table-bordered table-striped table-auto-width">
    <thead>
        <tr>
            <th>' . _('Host Address') . '</th>
            <th>' . _('Port') . '</th>
			<th>' . _('Queue Name') . '</th>
            <th>' . _('Use Authentication') . '</th>
            <th>' . _('Username') . '</th>
			<th>' . _('Password') . '</th>
			<th>' . _('Hosts') . '</th>
            <th>' . _('Services') . '</th>
            <th>' . _('State Type') . '</th>
            <th>' . _('Don\'t Send During Downtime') . '</th>
        </tr>
    </thead>
    <tbody>';

            for ($x = 0; $x < $rabbitmq_hosts_count; $x++) {

                $output .= '
        <tr>
            <td>
                <input type="text" size="25" name="rabbitmq_hosts[' . $x . '][address]" value="' . htmlentities($rabbitmq_hosts[$x]["address"]) . '" class="form-control">
            </td>
            <td>
                <input type="text" size="5" name="rabbitmq_hosts[' . $x . '][port]" value="' . htmlentities($rabbitmq_hosts[$x]["port"]) . '" class="form-control">
            </td>
			<td>
                <input type="text" size="15" name="rabbitmq_hosts[' . $x . '][queue]" value="' . htmlentities($rabbitmq_hosts[$x]["queue"]) . '" class="form-control">
            </td>
            <td class="center">
                <input type="checkbox" id="authentication" name="rabbitmq_hosts[' . $x . '][authentication]" value="1"' . is_checked($rabbitmq_hosts[$x]['authentication'], 1) . '>
            </td>
            <td>
                <input type="text" size="15" name="rabbitmq_hosts[' . $x . '][username]" value="' . htmlentities($rabbitmq_hosts[$x]["username"]) . '" class="form-control">
            </td>
			<td>
                <input type="text" size="15" name="rabbitmq_hosts[' . $x . '][password]" value="' . htmlentities($rabbitmq_hosts[$x]["password"]) . '" class="form-control">
            </td>
            <td>
                <select name="rabbitmq_hosts[' . $x . '][hoststateid]" class="form-control">
                    <option value="0" ' . is_selected($rabbitmq_hosts[$x]['hoststateid'], "0") . '>ALL</option>
                    <option value="1" ' . is_selected($rabbitmq_hosts[$x]['hoststateid'], "1") . '>DOWN</option>
                    <option value="-1" ' . is_selected($rabbitmq_hosts[$x]['hoststateid'], "-1") . '>NONE</option>
                </select>
            </td>
            <td>
                <select name="rabbitmq_hosts[' . $x . '][servicestateid]" class="form-control">
                    <option value="0" ' . is_selected($rabbitmq_hosts[$x]['servicestateid'], "0") . '>ALL</option>
                    <option value="2" ' . is_selected($rabbitmq_hosts[$x]['servicestateid'], "2") . '>CRITICAL</option>
                    <option value="1" ' . is_selected($rabbitmq_hosts[$x]['servicestateid'], "1") . '>WARNING</option>
                    <option value="-1" ' . is_selected($rabbitmq_hosts[$x]['servicestateid'], "-1") . '>NONE</option>
                </select>
            </td>
            <td>
                <select name="rabbitmq_hosts[' . $x . '][statetype]" class="form-control">
                    <option value="BOTH" ' . is_selected($rabbitmq_hosts[$x]['statetype'], "BOTH") . '>BOTH</option>
                    <option value="HARD" ' . is_selected($rabbitmq_hosts[$x]['statetype'], "HARD") . '>HARD</option>
                    <option value="SOFT" ' . is_selected($rabbitmq_hosts[$x]['statetype'], "SOFT") . '>SOFT</option>
                </select>
            </td>
            <td class="center">
                <input type="checkbox" id="downtime" name="rabbitmq_hosts[' . $x . '][downtime]" value="1"' . is_checked($rabbitmq_hosts[$x]['downtime'], 1) . '>
            </td>
        </tr>';
            }

            $output .= '
    </tbody>
</table>';

            break;

        case COMPONENT_CONFIGMODE_SAVESETTINGS:

            // get variables
            $enabled = checkbox_binary(grab_array_var($inargs, "enabled", ""));
            $rabbitmq_hosts = grab_array_var($inargs, "rabbitmq_hosts", "");

            // Renumber items & add a UID for each item
            $settings_new = array();
            $y = 0;
            foreach ($rabbitmq_hosts as $x => $sa) {
                if(!empty($sa["address"]))
                        $settings_new[$y++] = $sa;
            }
            $rabbitmq_hosts = $settings_new;
            
            // validate variables
            $errors = 0;
            $errmsg = array();
            if ($enabled == 1) {
            }

            // handle errors
            if ($errors > 0) {
                $outargs[COMPONENT_ERROR_MESSAGES] = $errmsg;
                $result = 1;
                return '';
            }

            // save settings
            $settings = array(
                "enabled" => $enabled,
                "rabbitmq_hosts" => $rabbitmq_hosts,
            );
            set_option("rabbitmqsender_component_options", serialize($settings));

            // info messages
            $okmsg = array();
            $okmsg[] = "Settings updated.";
            $outargs[COMPONENT_INFO_MESSAGES] = $okmsg;

            break;

        default:
            break;

    }

    return $output;
}


////////////////////////////////////////////////////////////////////////
// EVENT HANDLER AND NOTIFICATION FUNCTIONS
////////////////////////////////////////////////////////////////////////

register_callback(CALLBACK_EVENT_PROCESSED, 'rabbitmqsender_component_eventhandler');


function rabbitmqsender_component_eventhandler($cbtype, $args)
{

    echo "*** GLOBAL HANDLER (rabbitmqsender)...\n";
    print_r($args);

    switch ($args["event_type"]) {
        case EVENTTYPE_STATECHANGE:
            rabbitmqsender_component_handle_statechange_event($args);
            break;
        default:
            break;
    }
}


function rabbitmqsender_component_handle_statechange_event($args)
{
    $meta = grab_array_var($args, "event_meta", array());
    $handler_type = grab_array_var($meta, "handler-type", "");
    // load settings
    $settings_raw = get_option("rabbitmqsender_component_options");
    if ($settings_raw == "") {
        //$settings=array();
        // settings have not been configured yet...
        echo "RABBITMQ SENDER NOT CONFIGURED!\n";
        return;
    } else {
		$settings = unserialize($settings_raw);
	}
        

    // are we enabled?
    $enabled = grab_array_var($settings, "enabled", "");
    if ($enabled != 1) {
        echo "RABBITMQ SENDER NOT ENABLED! VALUE='$enabled'\n";
        return;
    }

    switch ($handler_type) {
        case "host":
            if (array_key_exists("rabbitmq_hosts", $settings)) {
                // loop through all rabbitmq hosts
                foreach ($settings["rabbitmq_hosts"] as $rh) {
                    echo "PROCESSING:\n";

                    // get address, community and port
                    $address = grab_array_var($rh, 'address');
                    $port = grab_array_var($rh, 'port');
					$queue = grab_array_var($rh, 'queue');
					$authentication = grab_array_var($rh, 'authentication');
					$username = grab_array_var($rh, 'username');
					$password = grab_array_var($rh, 'password');

                    // only send to hosts that have address and community defined
                    if ($address != "" && $port != "" && $queue != "") {
                        echo "PROCESSING:\n";
                        print_r($rh);

                        // filters
                        if (isset($rh['hoststateid']) && $rh['hoststateid'] != 0) {
                            if ($meta['hoststateid'] < $rh['hoststateid'] || $rh['hoststateid'] == -1) {
                                echo "Host matched state filter, skipping... RABBITMQ STATE SETTING=" . $rh['hoststateid'] . " EVENT STATE=" . $meta['hoststateid'] . "\n";
                                continue;
                            }
                        }
                        if (isset($rh['statetype']) && $rh['statetype'] != "BOTH") {
                            if ($rh['statetype'] != $meta['hoststatetype']) {
                                echo "Host matched type filter, skipping... RABBITMQ STATETYPE SETTING=" . $rh['statetype'] . " EVENT STATETYPE=" . $meta['hoststatetype'] . "\n";
                                continue;
                            }
                        }
                        if (rabbitmqsender_component_retrievedowntime($meta) && grab_array_var($rh, 'downtime', 0) == 1) {
                            echo "Host is in scheduled downtime... EVENT DOWNTIME=1 \n";
                            continue;
                        }
						
						$message = '[' . time() . '] HOST NOTIFICATION: ' . $meta['host'] . ';' . $meta['hoststate'] . ';' . $meta['hoststatetype'] . ';' . $meta['hostoutput'];

						if($authentication == 1) {
							$command = 'python ' . dirname(__FILE__) . '/send_rabbit.py -H ' . escapeshellarg($address) . ' -P ' . escapeshellarg($port) . ' -q ' . escapeshellarg($queue) . ' -u ' . escapeshellarg($username) . ' -p ' . escapeshellarg($password) . ' -m ' . escapeshellarg($message);
						}
						else {
							$command = 'python ' . dirname(__FILE__) . '/send_rabbit.py -H ' . escapeshellarg($address) . ' -P ' . escapeshellarg($port) . ' -q ' . escapeshellarg($queue) . ' -m ' . escapeshellarg($message);
						}
						
                        rabbitmqsender_component_sendmsg($command);
                    }
                }
            }
            break;
        case "service":
            if (array_key_exists("rabbitmq_hosts", $settings)) {

                // loop through all rabbitmq hosts
                foreach ($settings["rabbitmq_hosts"] as $rh) {

                    // get address, community and port
                    $address = grab_array_var($rh, 'address');
                    $port = grab_array_var($rh, 'port');
					$queue = grab_array_var($rh, 'queue');
					$authentication = grab_array_var($rh, 'authentication');
					$username = grab_array_var($rh, 'username');
					$password = grab_array_var($rh, 'password');

                    // only send to hosts that have address and community defined
                    if ($address != "" && $port != "" && $queue != "") {
                        echo "PROCESSING:\n";
                        print_r($rh);

                        // filters
                        if (isset($rh['servicestateid']) && $rh['servicestateid'] != 0) {
                            if ($meta['servicestateid'] < $rh['servicestateid'] || $rh['servicestateid'] == -1) {
                                echo "Service matched state filter, skipping... RABBITMQ STATE SETTING=" . $rh['servicestateid'] . " EVENT STATE=" . $meta['servicestateid'] . "\n";
                                continue;
                            }
                        }
                        if (isset($rh['statetype']) && $rh['statetype'] != "BOTH") {
                            if ($rh['statetype'] != $meta['servicestatetype']) {
                                echo "Service matched type filter, skipping... RABBITMQ STATETYPE SETTING=" . $rh['statetype'] . " EVENT STATETYPE=" . $meta['servicestatetype'] . "\n";
                                continue;
                            }
                        }
                        if (rabbitmqsender_component_retrievedowntime($meta) && grab_array_var($rh, 'downtime', 0) == 1) {
                            echo "Service is in scheduled downtime... EVENT DOWNTIME=1 \n";
                            continue;
                        }

                        $message = '[' . time() . '] SERVICE NOTIFICATION: ' . $meta['host'] . ';' . $meta['service'] . ';' . $meta['servicestate'] . ';' . $meta['servicestatetype'] . ';' . $meta['serviceoutput'];

						if($authentication == 1) {
							$command = 'python ' . dirname(__FILE__) . '/send_rabbit.py -H ' . escapeshellarg($address) . ' -P ' . escapeshellarg($port) . ' -q ' . escapeshellarg($queue) . ' -u ' . escapeshellarg($username) . ' -p ' . escapeshellarg($password) . ' -m ' . escapeshellarg($message);
						}
						else {
							$command = 'python ' . dirname(__FILE__) . '/send_rabbit.py -H ' . escapeshellarg($address) . ' -P ' . escapeshellarg($port) . ' -q ' . escapeshellarg($queue) . ' -m ' . escapeshellarg($message);
						}
						
                        rabbitmqsender_component_sendmsg($command);
                    }
                }
            }
            break;
        default;
            break;
    }
	

}


function rabbitmqsender_component_sendmsg($command)
{
    echo 'RUNNING COMMAND: ' . $command . PHP_EOL;

    exec($command);
}


///////////////////////////////////////////////////////////////////////////////////////////
// MISC FUNCTIONS
///////////////////////////////////////////////////////////////////////////////////////////

function rabbitmqsender_component_checkversion()
{

    if (!function_exists('get_product_release'))
        return false;
    //requires greater than 2009R1.2
    if (get_product_release() < 114)
        return false;

    return true;
}

function rabbitmqsender_component_checkinstallation()
{
    $packages = array();
	$required = array('pika');
	
	$depends = exec('pip freeze | grep \'' . implode('\\|', $required) . '\'', $packages);
	
	foreach($packages as $package) {
		$required = array_diff($required, array(substr($package, 0, strpos($package, '='))));
	}
	
	return $required;
}

function rabbitmqsender_component_checkeventhandlers()
{
    $args = array(
        "cmd" => "getprogramstatus",
    );
    $xml = get_backend_xml_data($args);
    if ($xml) {
        $v = intval($xml->programstatus->event_handlers_enabled);
        if ($v == 1)
            return true;
    }

    return false;
}

function rabbitmqsender_component_retrievedowntime($meta)
{

    $handler_type = grab_array_var($meta, "handler-type", "");

    if (!empty($handler_type)) {
        if ($handler_type == 'host') {
            $req = array("host_name" => $meta['host']);
            $obj = simplexml_load_string(get_host_status_xml_output($req));
            $dt = intval($obj ->hoststatus ->scheduled_downtime_depth);

            if ($dt > 0) {
                return true;
            }
            return false;
        } else if ($handler_type == 'service') {
            $req = array("name" => $meta['service'], "host_name" => $meta['host']);
            $obj = simplexml_load_string(get_service_status_xml_output($req));
            $dt = intval($obj ->servicestatus ->scheduled_downtime_depth);

            if ($dt > 0) {
                return true;
            }
            return false;
        }
    }
}