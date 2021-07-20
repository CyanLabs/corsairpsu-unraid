<?php
if (file_exists("/boot/config/plugins/corsairpsu/corsairpsu.cfg")) {
	$settings = parse_ini_file( "/boot/config/plugins/corsairpsu/corsairpsu.cfg" );
} else {
	$settings["TYPE"] = "corsairmi";
}

if ($settings["TYPE"] == "corsairmi") {
	$stdout = shell_exec('/usr/local/bin/corsairmi 2>&1');
} elseif ($settings["TYPE"] == "cpsumoncli") {
	$stdout = shell_exec('/usr/local/bin/cpsumon/cpsumoncli ' . $settings["TTY"] . ' 2>&1');
	//$stdout = file_get_contents("https://raw.githubusercontent.com/CyanLabs/corsairpsu-unraid/master/axoutput-example.txt"); //- Debug Testing	
} else {
	die("There is an error with your configuration!");
}

$re     = '/(?<key>[^:]+):\s+\'*(?<value>[^\n\']+)\'*\s*/';
preg_match_all($re, $stdout, $matches, PREG_SET_ORDER, 0);
foreach ($matches as $match)
    $data[$match['key']] = $match['value'];
if (!isset($data))
    exit(json_encode(array($stdout)));

if ($settings["TYPE"] == "corsairmi") {
	$capacity = filter_var($data['product'], FILTER_SANITIZE_NUMBER_INT);
	$load     = round($data['total watts'] / $capacity * 100);

	$output_power = round($data['total watts']);
	$input_voltage = round($data['supply volts']);

	# Table of Corsair power estimation of each model
	switch ($data['product']) {
		case "RM650i":
			$fpowin115 = 0.00017323493381072683 * $output_power * $output_power + 1.0047044721686030 * $output_power + 12.376592422281606;
			$fpowin230 = 0.00012413136310310370 * $output_power * $output_power + 1.0284317478987164 * $output_power + 9.465259079360674;
			break;
		case "RM750i":
			$fpowin115 = 0.00015013694263596336 * $output_power * $output_power + 1.0047044721686027 * $output_power + 14.280683564171110;
			$fpowin230 = 0.00010460621468919797 * $output_power * $output_power + 1.0173089573727216 * $output_power + 11.495900706372142;
			break;
		case "RM850i":
			$fpowin115 = 0.00012280002467981107 * $output_power * $output_power + 1.0159421430340847 * $output_power + 13.555472968718759;
			$fpowin230 = 0.00008816054254801031 * $output_power * $output_power + 1.0234738318592156 * $output_power + 10.832902491655597;
			break;
		case "RM1000i":
			$fpowin115 = 0.00010018433053123574 * $output_power * $output_power + 1.0272313660072225 * $output_power + 14.092187353321624;
			$fpowin230 = 0.00008600634771656125 * $output_power * $output_power + 1.0289245073649413 * $output_power + 13.701515390258626;
			break;
		case "HX750i":
			$fpowin115 = 0.00013153276902318052 * $output_power * $output_power + 1.0118732314945875 * $output_power + 9.783796618886313;
			$fpowin230 = 0.00009268856467314546 * $output_power * $output_power + 1.0183515407387007 * $output_power + 8.279822175342481;
			break;
		case "HX850i":
			$fpowin115 = 0.00011552923724840388 * $output_power * $output_power + 1.0111311876704099 * $output_power + 12.015296651918918;
			$fpowin230 = 0.00008126644224872423 * $output_power * $output_power + 1.0176256272095185 * $output_power + 10.290640442373850;
			break;
		case "HX1000i":
			$fpowin115 = 0.00009486097544171090 * $output_power * $output_power + 1.0170509865269720 * $output_power + 11.619826520447452;
			$fpowin230 = 0.00009649987544008507 * $output_power * $output_power + 1.0018241767296636 * $output_power + 12.759957859756842;
			break;
		case "HX1200i":
			$fpowin115 = 0.00006244705156199815 * $output_power * $output_power + 1.0234738310580973 * $output_power + 15.293509559389241;
			$fpowin230 = 0.00005941317979435096 * $output_power * $output_power + 1.0023670927127724 * $output_power + 15.886126793547152;
			break;
		default:
			$fpowin115 = 0;
			$fpowin230 = 0;
	}

	# If the model is not listed above show 0
	if ($fpowin230 == 0) {
		$est_input_power = 0;
		$efficiency = 0;
	} else {
		$est_input_power = $fpowin115 + ($fpowin230 - $fpowin115) / 115 * ($input_voltage - 115);
		$efficiency = round($output_power * 100 / $est_input_power);
	}

	$output0_load     = round($data['output0 watts'] / $capacity * 100);
	$output1_load     = round($data['output1 watts'] / $capacity * 100);
	$output2_load     = round($data['output2 watts'] / $capacity * 100);
	
	// Sets keys for the 3 outputs to 12v, 5v and 3v
	$output0 = substr($data['output0 volts'], 0, strrpos($data['output0 volts'], '.'));
	$output1 = substr($data['output1 volts'], 0, strrpos($data['output1 volts'], '.'));
	$output2 = substr($data['output2 volts'], 0, strrpos($data['output2 volts'], '.'));

	// Removes raw timestamp on uptime / total hours leaving just the Xd, Xh text
	preg_match('/\((.*?)\)/', $data['uptime'], $uptime);
	preg_match('/\((.*?)\)/', $data['powered'], $poweredon);

	// Adds the keys and values to an array, named appropiately
	$json = array(
		'temp1' => $data['temp1'],
		'temp2' => $data['temp2'],
		'fan_rpm' => $data['fan rpm'],
		'capacity' => $capacity,
		"{$output0}v_watts" => $data['output0 watts'],
		"{$output1}v_watts" => $data['output1 watts'],
		"{$output2}v_watts" => $data['output2 watts'],
		'watts' => $data['total watts'],
		'load' => $load,
		"{$output0}v_load" => $output0_load,
		"{$output1}v_load" => $output1_load,
		"{$output2}v_load" => $output2_load,
		'vendor' => $data['vendor'],
		'product' => $data['product'],
		'uptime' => $uptime[1],
		'uptime_raw' => substr($data['uptime'], 0, strpos($data['uptime'], ' ')),
		'poweredon' => $poweredon[1],
		'poweredon_raw' => substr($data['powered'], 0, strpos($data['powered'], ' ')),
		'input_voltage' => round($input_voltage),
		'input_power' => round($est_input_power),
		'efficiency' => floatval($efficiency)
	);	
} else {
	$capacity = filter_var($data["PSU type"], FILTER_SANITIZE_NUMBER_INT);
	$input = floatval($data['Input power']);
	$load = round($input / $capacity * 100);

	$rail_5v = explode(",", $data['5V Rail']);
	$rail_3v = explode(",", $data['3.3V Rail']);

	$rail_5v_watts     = round(floatval($rail_5v[2]),2);
	$rail_3v_watts     = round(floatval($rail_3v[2]),2);
	$rail_12v = $input - $rail_5v_watts - $rail_3v_watts;
	
	$rail_12v_watts     = round(floatval($rail_12v),2);

	$rail_5v_load     = round(floatval($rail_5v[2]) / $capacity * 100);
	$rail_3v_load     = round(floatval($rail_3v[2]) / $capacity * 100);
	$rail_12v_load     = round(floatval($rail_12v) / $capacity * 100);

	$json = array(
		'temp1' => floatval($data["Temperature"]),
		'temp2' => floatval($data["Temperature"]),
		'fan_rpm' => floatval($data["Fan speed"]),
		'capacity' => $capacity,
		"12v_watts" => $rail_12v_watts,
		"5v_watts" => $rail_5v_watts,
		"3v_watts" => $rail_3v_watts,
		'watts' => $input,
		'load' => $load,
		"12v_load" => $rail_12v_load,
		"5v_load" => $rail_5v_load,
		"3v_load" => $rail_3v_load,
		'vendor' => "CORSAIR",
		'product' => str_replace("\r","",$data["PSU type"]),
		'uptime' => "Not Supported",
		'uptime_raw' => "Not Supported",
		'poweredon' => "Not Supported",
		'poweredon_raw' => "Not Supported",
		'efficiency' => floatval($data['Efficiency'])
	);
}
header('Content-Type: application/json');
echo json_encode($json);
?>