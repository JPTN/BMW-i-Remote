<?
$content = file_get_contents('php://input');
$jsoncontent = json_decode($content);

$intent = $jsoncontent->queryResult->intent->displayName;

require 'bmw-api.php';

$JSONdataOUT = GetJsonMessageResponse($intent, $JSONdata);
$size = strlen($JSONdataOUT);
header('Content-Type: application/json');
header("Content-length: $size");
echo $JSONdataOUT;

function GetJsonMessageResponse($intent,$JSONdata) {
	global $SpeakPhrase;
	
	$ReturnValue = "";
	include('text.php');
	$DisplayValue = str_replace("eye three", "i3", $SpeakPhrase);

	$ReturnValue= '
	{
	  "payload": {
	    "google": {
	      "expectUserResponse": false,
	      "richResponse": {
	        "items": [
	          {
	            "simpleResponse": {
				"displayText": "'.$DisplayValue.'",
				"textToSpeech": "'.$SpeakPhrase.'"
	            }
	          }
	        ]
	      }
	    }
	  }
	}';
	
	return $ReturnValue;
}
?>