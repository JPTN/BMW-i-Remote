<?
$EchoJArray = json_decode(file_get_contents('php://input'));
$RequestType = $EchoJArray->request->type;

require 'bmw-api.php'; // gets data from BMW's API

$JSONdataOUT = CreateJSONResponse($RequestType,$EchoJArray,$JSONdata);
$size = strlen($JSONdataOUT);
header('Content-Type: application/json');
header("Content-length: $size");
echo $JSONdataOUT;

// Generate the response to the Alexa service
function CreateJSONResponse($RequestMessageType,$EchoJArray,$JSONdata){
	global $SpeakPhrase;
	$RequestId = $EchoJArray->request->requestId;

	$ReturnValue = "";
	
	if( $RequestMessageType == "LaunchRequest" ){
		$ReturnValue= '
		{
		  "version": "1.0",
		  "response": {
			"outputSpeech": {
			  "type": "PlainText",
			  "text": "Eye three awaiting your command."
			},
			"reprompt": {
			  "outputSpeech": {
				"type": "PlainText",
				"text": "Can I help you with anything else?"
			  }
			},
			"shouldEndSession": false
		  }
		}';
	}
	
	if( $RequestMessageType == "SessionEndedRequest" )
	{
		$ReturnValue = '{
		  "type": "SessionEndedRequest",
		  "requestId": "$RequestId",
		  "timestamp": "' . date("c") . '",
		  "reason": "USER_INITIATED "
		}
		';
	}
	
	if( $RequestMessageType == "IntentRequest" ){	
		$intent = $EchoJArray->request->intent->name;
		if ($intent == "update") {
			$EndSession = "false"; // update data and await next query
		} else {
			$EndSession = "true"; // end conversation
		}
		include('text.php');
		
		$ReturnValue= '
		{
		  "version": "1.0",
		  "response": {
			"outputSpeech": {
			  "type": "PlainText",
			  "text": "'.$SpeakPhrase.'"
			},
			"card": {
			  "type": "Simple",
			  "title": "BMW i3 Remote Control",
			  "content": "'.$SpeakPhrase.'"
			},
			"shouldEndSession": ' . $EndSession . '
		  }
		}';
	}
	return $ReturnValue;
}// end function CreateJSONResponse
?>
