<?
		$metric = true; // use metric or imperial values
		$rex = false; // battery only (false) or range-extender (true)
		
// depending on what was requested ($intent), process the BMW JSON data and extract the relevant info, converting it into friendly English sentences for speech output
		switch ($intent) {
			case "update": // forces a refresh of stale data
				refreshData();
				getData($JSONdata);
				$SpeakPhrase = "Refreshed the data from BMW.";
				break;
			case "status": // general vehicle status: doors, lights, trunk, etc.
				$safe_state = array( // expected values for a secured vehicle
					"doorDriverFront" => "CLOSED",
					"doorPassengerFront" => "CLOSED",
					"doorPassengerRear" => "CLOSED",
					"doorDriverRear" => "CLOSED",
					"windowDriverFront" => "CLOSED",
					"windowPassengerFront" => "CLOSED",
					"hood" => "CLOSED",
					"trunk" => "CLOSED",
					"doorLockState" => "SECURED"
				);
				
				file_put_contents('safe.log',print_r($safe_state, true));
				file_put_contents('json.log',print_r($JSONdata['vehicleStatus'], true));
				file_put_contents('intersect.log',print_r(array_intersect_key($JSONdata['vehicleStatus'],$safe_state),true));
				file_put_contents('diff.log',print_r(array_diff(array_intersect_key($JSONdata['vehicleStatus'],$safe_state),$safe_state),true));
				
				foreach ($safe_state as $key) { // there's a door open/unlocked or light on, find it
					$safe_state_text = array( // equivalent vehicle elements in plain English for speech output
						"doorDriverFront" => "driver front door",
						"doorPassengerFront" => "passenger front door",
						"doorPassengerRear" => "passenger rear door",
						"doorDriverRear" => "driver rear door",
						"windowDriverFront" => "driver window",
						"windowPassengerFront" => "passenger window",
						"hood" => "frunk",
						"trunk" => "trunk",
						"doorLockState" => "doors"
					);
					if ($safe_state[key($safe_state)] != $JSONdata['vehicleStatus'][key($safe_state)]) { // add each insecure element to the list, separated by commas
						$errors .= $safe_state_text[key($safe_state)]." is ".$JSONdata['vehicleStatus'][key($safe_state)].", ";
						$errorcount += 1;
					}
					next($safe_state); // iterate through array
				}
				switch ($errorcount) { // format the plain English sentence appropriately depending on # of errors
					case 1:
						$errors = str_replace(',', '.', $errors); // only 1 error. replace , with period to end sentence
						break;
					case 2:
						$errors = preg_replace('/,/', ' and', $errors, 1); // 2 errors, remove the extra comma
						$errors = preg_replace('/,/', '.', $errors, 1); // change the last comma to a period
						break;
					default: // >2 errors: add "and" before the last error while keeping the last (Oxford) comma
						$errors = substr_replace($errors,', and',strrpos($errors, ',', strrpos($errors, ',')-strlen($errors)-1),1); 
						$errors = substr_replace($errors, '.', strrpos($errors, ','), strlen($errors)); // change the last comma to a period
						break;
				}
				$errors = str_replace('doors is', 'doors are', $errors); // fix grammar for "doors"
				$errors = ucfirst($errors); // uppercase the first letter
				$SpeakPhrase = substr($errors, 0, -1); // remove characters after the period at the end
					
				file_put_contents('diff.log',"Errors: ".$errors."\n",FILE_APPEND);

				$SpeakPhrase = $errors;
				break;
			case "charge": // battery info including range, charging status, and completion time, if applicable				
				if ($metric) { 
					$range = $JSONdata['vehicleStatus']['remainingRangeElectric']/* + $JSONdata['vehicleStatus']['remainingRangeFuel']*/; // uncomment to add in metric REx range
					$rangeText = $range." km";
				}
				else { 
					$range = $JSONdata['vehicleStatus']['remainingRangeElectricMls']/* + $JSONdata['vehicleStatus']['remainingRangeFuelMls']*/; // uncomment to add in imperial REx range
					$rangeText = $range." miles";
				}
				$SpeakPhrase = "The i3 ";
				$charge_text = array( // equivalent charging states in plain English for speech output
					"INVALID" => "is currently not charging.",
					"CHARGING" => "is actively charging",
					"FINISHED_FULLY_CHARGED" => "is ",
					"ERROR" => "has experienced an error with charging.",
					"FINISHED_NOT_FULL" => "has stopped charging.",
					"NOT_CHARGING" => "is currently not charging.",
					"WAITING_FOR_CHARGING" => "is waiting for charging to start."
				);
				switch ($JSONdata['vehicleStatus']['chargingStatus']) {
					case "FINISHED_FULLY_CHARGED":
						$SpeakPhrase = "Charging is complete, there is ".$rangeText;
						$full_text = array(
							"CONNECTED" => "still plugged in",
							"DISCONNECTED" => "unplugged"
						);
						$SpeakPhrase .= " of range, and it ".$charge_text[$JSONdata['vehicleStatus']['chargingStatus']].$full_text[$JSONdata['vehicleStatus']['connectionStatus']].".";
						break;
					case "CHARGING":
						date_default_timezone_set('America/Toronto');
						if (isset($JSONdata['vehicleStatus']['chargingTimeRemaining'])) {
							$timeLeft = time() + ($JSONdata['vehicleStatus']['chargingTimeRemaining'] * 60);
							$SpeakPhrase = "The battery is at ".$JSONdata['vehicleStatus']['chargingLevelHv']."% and ".$charge_text['CHARGING']." which is estimated to be complete at ".date("g:i a", $timeLeft).".";
							if (date('j', $timeLeft) > date('j', time())) { // adds in "tomorrow" if charging finishes after midnight
								$SpeakPhrase = substr_replace($SpeakPhrase, " tomorrow", strlen($SpeakPhrase)-1, 0);
							}
						} else {
							$SpeakPhrase .= ".";
						}
						break;
					default:
						$SpeakPhrase = "The battery is at ".$JSONdata['vehicleStatus']['chargingLevelHv']."%, there is ".$rangeText." of range, ";
						
						$SpeakPhrase .= "and it ".$charge_text[$JSONdata['vehicleStatus']['chargingStatus']];
						break;
				}
				break;
			case "windows":
				$window_state = array( // expected values for a secured vehicle
					"windowDriverFront" => "CLOSED",
					"windowPassengerFront" => "CLOSED",
				);
				$window_state_text = array(
					"windowDriverFront" => "driver's window",
					"windowPassengerFront" => "passenger's window"
				);
				
				if (array_intersect($window_state, $JSONdata['vehicleStatus']) == $window_state) { // if the data in $window_state matches the actual vehicle state, it's secure
					$SpeakPhrase = "Both windows are closed.";
				} else { 
					$errors = "The ";
					$windowcount = 0;
					if ($JSONdata['vehicleStatus']['windowDriverFront'] != $window_state['windowDriverFront']) {
						$errors .= $window_state_text[$JSONdata['vehicleStatus']['windowDriverFront']];
						$windowcount += 1;
					}
					if ($JSONdata['vehicleStatus']['windowPassengerFront'] != $window_state['windowPassengerFront']) {
						if ($windowcount > 0) {
							$errors .= " and the ";
						}
						$errors .= $window_state_text[$JSONdata['vehicleStatus']['windowPassengerFront']];
						$windowcount += 1;
					}
					if ($windowcount == 1) { $errors .= " is open."; }
					else { $errors .= " are open."; }
					$SpeakPhrase = $errors;
				}
				break;
			case "mileage":
				$SpeakPhrase = "The odometer is at ".number_format($JSONdata['vehicleStatus']['mileage']);
				if ($metric) { $SpeakPhrase .= " kms."; }
				else {$SpeakPhrase = $SpeakPhrase .= " miles."; }
				break;
			case "lock":
				lockCar();
				break;
			case "climate":
				climate();
				break;
			case "flash":
				flashLights();
				break;
			case "horn":
				honkHorn();
				break;
			case "range":
				$SpeakPhrase = "There is ";
				if ($metric) { 
					$SpeakPhrase .= $JSONdata['vehicleStatus']['remainingRangeElectric']." kms of battery";
					if ($rex) {
						$SpeakPhrase .= " and ".$JSONdata['vehicleStatus']['remainingRangeFuel']." kms of gas";
					}
				}
				else { 
					$SpeakPhrase .= $JSONdata['vehicleStatus']['remainingRangeElectricMls']." miles of battery";
					if ($rex) {
						$SpeakPhrase .= " and ".$JSONdata['vehicleStatus']['remainingRangeFuelMls']." miles of gas";
					}
				}
				$SpeakPhrase .=" range remaining.";
				break;
			default:
				$SpeakPhrase = "Unknown command. Please try again.";
				break;
		}
?>
