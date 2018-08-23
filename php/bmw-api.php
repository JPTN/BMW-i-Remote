<?
$bmwdatafile = 'bmw.json'; // locally saved JSON data
$tokenfile = 'bearer.auth'; // locally saved authentication token

$authexpiry = 60*60*8; // authentication token expiry: 8 hours
$bmwdataexpiry = 30*60; // BMW JSON data expiry: arbitrarily set to 30 minutes

function refreshData() { // deletes the JSON data file to force a refresh
	global $bmwdatafile;
	if (file_exists($bmwdatafile)) { unlink($bmwdatafile); }
}

function authcode() { // returns the access token from BMW's API
	global $tokenfile;
	global $authexpiry;
	
	if (file_exists($tokenfile)) {
		if ((time() - filemtime($tokenfile)) < $authexpiry) { // if the locally stored token is LESS than 8 hours old (8*60*60), it should still be valid
			return(file_get_contents($tokenfile));
		}
	} else { // Get a fresh authorization token from BMW server
		$url = 'https://b2vapi.bmwgroup.us/webapi/oauth/token/';
		$credentials = 'grant_type=password&username=EMAIL%40DOMAIN.COM&password=SECRET&scope=remote_services+vehicle_data'; // fill in with your email and password

		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
			'method'  => 'POST',
			'header'  => "Authorization: Basic SECRET-API-KEY\r\n" . "Content-Type: application/x-www-form-urlencoded",
			'content' => $credentials
			)
		);

		$context  = stream_context_create($options);
		$result = @file_get_contents($url, false, $context); // @ to suppress warnings/errors
		if ($result === FALSE) { 
			return 0; // no authcode available: possibly an issue with BMW's API
		} else {
			$obj = json_decode($result);
			$authcode = $obj->access_token;
			file_put_contents($tokenfile, $authcode);
			return($authcode);
		}
	}
}

function getData($JSONdata) {
	global $bmwdatafile;
	global $tokenfile;
	global $SpeakPhrase;
	$statusurl = 'https://b2vapi.bmwgroup.us/webapi/v1/user/vehicles/VIN/status/'; // replace with your VIN

	$attempts = 0;
	do {
		$authentication = authcode();
		// use key 'http' even if you send the request to https://...
		$statusoptions = array(
			'http' => array(
			'method'  => 'GET',
			'header'  => "Authorization: Bearer ".$authentication
			)
		);
		
		$statuscontext = stream_context_create($statusoptions);
		$statusresult = @file_get_contents($statusurl, false, $statuscontext); // @ to suppress warnings/errors
		if ($statusresult === false) { // error retrieving data (likely expired token), retry
			if (file_exists($tokenfile)) { unlink($tokenfile); }
			if (file_exists($bmwdatafile)) { unlink($bmwdatafile); }
			$attempts += 1;
		} else { // successfully retrieved data from BMW, get next dataset (ChargingProfile)
			global $JSONdata;
			$chargingprofileurl = 'https://b2vapi.bmwgroup.us/webapi/v1/user/vehicles/VIN/chargingprofile/';
			$chargingoptions = array(
				'http' => array(
				'method'  => 'GET',
				'header'  => "Authorization: Bearer ".$authentication
				)
			);
		
			$chargingprofile = stream_context_create($chargingoptions);
			$chargingresult = @file_get_contents($chargingprofileurl, false, $chargingprofile); // @ to suppress warnings/errors

			$JSONdata = json_encode(array_merge(json_decode($statusresult, TRUE), json_decode($chargingresult, TRUE)), JSON_PRETTY_PRINT);
			file_put_contents($bmwdatafile, $JSONdata);
			break;
		}		
	} while ($attempts < 4); // attempt to get data 3 times before failing

	if ($statusresult === false) { $SpeakPhrase = "Data is currently unavailable. Please try again later."; } // unable to get status data after multiple attempts
	else {
		$JSONdata = json_decode($JSONdata, true);
	}
}

function lockCar() {
	global $tokenfile;
	global $SpeakPhrase;
	$serviceURL = 'https://b2vapi.bmwgroup.us/webapi/v1/user/vehicles/VIN/executeService/'; // replace with your VIN

	$authentication = authcode();
	// use key 'http' even if you send the request to https://...
	$httpDATA = array(
		'http' => array(
		'method'  => 'POST',
		'header'  => "Authorization: Bearer ".$authentication,
		'content' => "serviceType=DOOR_LOCK"
		)
	);

	$statuscontext = stream_context_create($httpDATA);
	$statusresult = @file_get_contents($serviceURL, false, $statuscontext); // @ to suppress warnings/errors
	
	if ($statusresult === false) { $SpeakPhrase = "Failure in sending remote lock command to the eye three."; } // unable to get status data after multiple attempts
	else { $SpeakPhrase = "Lock command successfully sent to the eye three."; }	
}

function flashLights() {
	global $tokenfile;
	global $SpeakPhrase;
	$serviceURL = 'https://b2vapi.bmwgroup.us/webapi/v1/user/vehicles/VIN/executeService/'; // replace with your VIN

	$authentication = authcode();
	// use key 'http' even if you send the request to https://...
	$httpDATA = array(
		'http' => array(
		'method'  => 'POST',
		'header'  => "Authorization: Bearer ".$authentication,
		'content' => "serviceType=LIGHT_FLASH&count=2"
		)
	);

	$statuscontext = stream_context_create($httpDATA);
	$statusresult = @file_get_contents($serviceURL, false, $statuscontext); // @ to suppress warnings/errors
		
	if ($statusresult === false) { $SpeakPhrase = "Failure in sending light flash command to the eye three."; } // unable to get status data after multiple attempts
	else { $SpeakPhrase = "Light flash command successfully sent to the eye three."; }
}

function honkHorn() {
	global $tokenfile;
	global $SpeakPhrase;
	$serviceURL = 'https://b2vapi.bmwgroup.us/webapi/v1/user/vehicles/VIN/executeService/'; // replace with your VIN

	$authentication = authcode();
	// use key 'http' even if you send the request to https://...
	$httpDATA = array(
		'http' => array(
		'method'  => 'POST',
		'header'  => "Authorization: Bearer ".$authentication,
		'content' => "serviceType=HORN_BLOW&count=2"
		)
	);

	$statuscontext = stream_context_create($httpDATA);
	$statusresult = @file_get_contents($serviceURL, false, $statuscontext); // @ to suppress warnings/errors
		
	if ($statusresult === false) { $SpeakPhrase = "Failure in sending horn command to the eye three."; } // unable to get status data after multiple attempts
	else { $SpeakPhrase = "Honk horn command successfully sent to the eye three."; }
}

function climate() {
	global $tokenfile;
	global $SpeakPhrase;
	$serviceURL = 'https://b2vapi.bmwgroup.us/webapi/v1/user/vehicles/VIN/executeService/'; // replace with your VIN

	$authentication = authcode();
	// use key 'http' even if you send the request to https://...
	$httpDATA = array(
		'http' => array(
		'method'  => 'POST',
		'header'  => "Authorization: Bearer ".$authentication,
		'content' => "serviceType=CLIMATE_NOW"
		)
	);

	$statuscontext = stream_context_create($httpDATA);
	$statusresult = @file_get_contents($serviceURL, false, $statuscontext); // @ to suppress warnings/errors
		
	if ($statusresult === false) { $SpeakPhrase = "Remote pre-conditioning failed."; } // unable to get status data after multiple attempts
	else { $SpeakPhrase = "Climate control has been activated."; }
}

$JSONdata = null;

if (file_exists($bmwdatafile)) { // if the locally stored JSON status data exists and is <1 hour old (60*60), reuse the data
	if (((time() - filemtime($bmwdatafile)) < $bmwdataexpiry) && (filesize($bmwdatafile) > 0)) {
		$bmwdata = file_get_contents($bmwdatafile);
		$JSONdata = json_decode($bmwdata, true);		
	} else { // data is older than 1 hour or invalid/null, get new data
		getData($JSONdata);
	}
} else {
	getData($JSONdata);
}
?>
