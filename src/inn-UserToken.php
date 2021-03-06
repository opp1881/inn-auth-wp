<?php
require_once("inn-ApplicationToken.php");
require_once("inn-Log.php");

class inn_UserToken {

	function __construct() {
		$this->log = new inn_Log();
		$this->options = get_option("inn-auth_options");
	}

	function getUserToken($userTicket) {
		$apptoken = new inn_ApplicationToken();

		$apptokenXML = $apptoken->getAppToken();
		$this->log->info("getUserToken(), apptokenXML: " . $apptokenXML);

		$apptokenID = $apptoken->getAppTokenID($apptokenXML);
		$this->log->info("getUserToken(), apptokenID: " . $apptokenID);

		$ch = curl_init();

		curl_setopt ( $ch, CURLOPT_URL, $this->options["sts_url"] . "/tokenservice/user/" . $apptokenID . "/get_usertoken_by_userticket/");
		curl_setopt ( $ch, CURLOPT_POST, 1);
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, http_build_query(array("apptoken" => $apptokenXML, "userticket" => $userTicket)));
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/x-www-form-urlencoded'));
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true);

		$this->log->info("getUserToken(), CURLOPT_URL: " . $this->options["sts_url"] . "/tokenservice/user/" . $apptokenID . "/get_usertoken_by_userticket/");
		$this->log->info("getUserToken(), CURLOPT_POSTFIELDS: " . http_build_query(array("apptoken" => $apptokenXML, "userticket" => $userTicket)));

		$usertoken = curl_exec($ch);

		$this->log->info("getUserToken(), usertoken: \n" . $usertoken);

		if(curl_errno($ch))
		{
			$this->log->error("getUserToken() error: " . curl_error($ch));
		}

		curl_close ($ch);

		return $usertoken;
	}

	function getUserTokenById($userTokenId) {
		$apptoken = new inn_ApplicationToken();

		$apptokenXML = $apptoken->getAppToken();
		$this->log->info("getUserTokenById(), apptokenXML: " . $apptokenXML);

		$apptokenID = $apptoken->getAppTokenID($apptokenXML);
		$this->log->info("getUserTokenById(), apptokenID: " . $apptokenID);

		$ch = curl_init();

		curl_setopt ( $ch, CURLOPT_URL, $this->options["sts_url"] . "/tokenservice/user/" .$apptokenID . "/get_usertoken_by_usertokenid/");
		curl_setopt ( $ch, CURLOPT_POST, 1);
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, http_build_query(array("apptoken" => $apptokenXML, "usertokenid" => $userTokenId)));
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/x-www-form-urlencoded'));
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true);

		$this->log->info("getUserTokenById(), CURLOPT_URL: " . $this->options["sts_url"] . "/tokenservice/user/" .$apptokenID . "/get_usertoken_by_usertokenid/");
		$this->log->info("getUserTokenById(), CURLOPT_POSTFIELDS: " . http_build_query(array("apptoken" => $apptokenXML, "usertokenid" => $userTokenId)));

		$usertoken = curl_exec($ch);

		$this->log->info("getUserTokenById(), usertoken: \n" . $usertoken);

		if(curl_errno($ch))
		{
			$this->log->error("get usertokenbyid error:" . curl_error($ch));
		}

		curl_close ($ch);

		return $usertoken;
	}

	function getUserName($usertoken) {
		$ut = simplexml_load_string($usertoken);
		$username = (string)$ut->username;

		return $username;
	}

	function getFirstName($usertoken) {
		$ut = simplexml_load_string($usertoken);
		$firstname = (string)$ut->firstname;

		return $firstname;
	}

	function getLastName($usertoken) {
		$ut = simplexml_load_string($usertoken);
		$lastname = (string)$ut->lastname;

		return $lastname;
	}

	function getPhone($usertoken) {
		$ut = simplexml_load_string($usertoken);
		$phone = (string)$ut->cellphone;

		return $phone;
	}

	function getEmail($usertoken) {
		$ut = simplexml_load_string($usertoken);
		$email = (string)$ut->email;

		return $email;
	}

	function getAddress($usertoken) {
		$address = "";

		if(strlen($usertoken) > 0) {
			$ut = simplexml_load_string($usertoken) or die ("ERROR in getAddress(): Couldnt load usertoken: " . strlen($usertoken));
			foreach($ut->application as $app) {
				if ($app["ID"] == $this->options["app_id"]) {
					if($app->role["name"] == "INNDATA") {
						$address = $app->role["value"];
					}
				}
			}
		}

		return $address;
	}

	function printMyTokenFormatted(){
		$wpuserid = get_current_user_id();
		$wpusertokenid = get_user_meta($wpuserid, "inn_usertokenid", true);

		$output = "";

		if(strlen($wpusertokenid) > 0) {
			$usertoken = $this->getUserTokenById($wpusertokenid);

			echo "<div style=\"display:none;\">printMyTokenFormatted usertoken: <pre>" . $usertoken . "</pre></div>";

			$ut = simplexml_load_string($usertoken) or die("Error in  printMyTokenFormatted(): Cannot create object");

			$output = "\n<div>";
			$output .= "\n<h3>Profil</h3>";
			$output .= "\n\t<li>Brukernavn: " . $ut->username . "</li>";
			$output .= "\n\t<li>Fornavn: " . $ut->firstname . "</li>";
			$output .= "\n\t<li>Etternavn: " . $ut->lastname . "</li>";
			$output .= "\n\t<li>E-post: " . $ut->email . "</li>";

			$output .= "\n\t<h3>Leveringsadresse</h3>";
			$output .= $this->formatDeliveryaddress($this->getAddress($usertoken));

			$output .= "\n<h3>Sesjon</h3>";
			$output .= "\n\t<p>INN-sesjonen startet: " . $this->getDateTimeFromTimestamp($ut->timestamp);
			$output .= "\n\t, og utløper: " . $this->getDateTimeFromTimestamp($ut->timestamp + $ut->lifespan) . "</p>";

			$output .= "\n</div>\n";

		} else {
			$output = "<p>Oops! Ikke en INN-bruker." . $wpusertokenid . "</p>";
		}

		return $output;
	}

	function formatDeliveryaddress($deliveryAddressJSON) {
		$this->log->info("formatDeliveryaddress JSON: " . $deliveryAddressJSON);
		$deliveryAddress = json_decode($deliveryAddressJSON, true);

		$addressString = "\n<ul class=\"inn-addresscard\">";

		foreach($deliveryAddress as $address) {
			$addressString .= "\n\t<li>" . $address["name"];
			$addressString .= ", " . $address["addressLine1"];
			$addressString .= ", " . $address["postalcode"] . " " . $address["postalcity"];
			$addressString .= ", " . $address["country"] . "</li>";

			$addressString .= "\n\t<li>Kontakt: " . $address["contact"]["name"]
							. ", " . $address["contact"]["phoneNumber"] . " <span title=\"Bekreftet?\">(" . ( $phoneNumberConfirmed = $address["contact"]["phoneNumberConfirmed"] == "true" ? "Bekreftet" : "Ikke bekreftet") . ")</span>"
							. ", " . $address["contact"]["email"] . " <span title=\"Bekreftet?\">(" . ( $emailConfirmed = $address["contact"]["emailConfirmed"] == "true" ? "Bekreftet" : "Ikke bekreftet") . ")</span></li>";

			$addressString .= "\n\t<li>Leveringsinformasjon: "
							. ( $pickupPoint = strlen($address["deliveryinformation"]["pickupPoint"]) > 0 ? $address["deliveryinformation"]["pickupPoint"] : "" )
							. ( $additionalAddressInfo = strlen($address["deliveryinformation"]["additionalAddressInfo"]) > 0 ? ", " . $address["deliveryinformation"]["additionalAddressInfo"] : "" )
							. ( $deliveryTime = strlen($address["deliveryinformation"]["Deliverytime"]) > 0 ? ", " . $address["deliveryinformation"]["Deliverytime"] : "" )
							. "</li>";

			strlen($address["tags"]) > 0 ? $addressString .= "<li>Tags: " . $address["tags"] . "</li>" : $addressString = $addressString;
		}

		$addressString .= "</ul>";

		return $addressString;
	}

	function getDateTimeFromTimestamp($timestamp) {
//		echo round($timestamp/1000);
		return gmdate("Y-m-d H:i:s", round($timestamp/1000));
	}

	function getUserTokenId($usertoken) {
		$ut = simplexml_load_string($usertoken);
		$usertokenid = "";

		if(isset($ut["id"]))
			$usertokenid = (string) $ut["id"];

		return $usertokenid;
	}

}
?>
