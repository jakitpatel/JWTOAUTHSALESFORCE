<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// You need to set these three to the values for your own application
define('CONSUMER_KEY', '');
define('CONSUMER_SECRET', '');
define('LOGIN_BASE_URL', 'https://login.salesforce.com');

//helper function
function base64url_encode($data) { 
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); 
}

//{Base64url encoded JSON header}
$jwtHeader = base64url_encode(json_encode(array(
    "alg" => "RS256",
    "typ" => "JWT"
)));

//{Base64url encoded JSON claim set}
$now = time();
$jwtClaim = base64url_encode(json_encode(array(
    "iss" => CONSUMER_KEY,
    "aud" => LOGIN_BASE_URL,
    "sub" => "1387-esben@p.am",
    "exp" => $now + 3600,
    "iat" => $now
)));

// LOAD YOUR PRIVATE KEY FROM A FILE - BE CAREFUL TO PROTECT IT USING
// FILE PERMISSIONS!
$private_key=file_get_contents('salesforce.key');

// This is where openssl_sign will put the signature
$s = "";
// SHA256 in this context is actually RSA with SHA256
//$algo = "SHA256";
$algo = "sha256WithRSAEncryption";

$jwtSig = "";

openssl_sign(
    $jwtHeader.".".$jwtClaim,
    $jwtSig,
    $private_key,
    $algo
);
$jwtSign = base64url_encode($jwtSig);

$token = $jwtHeader.".".$jwtClaim.".".$jwtSign;

$token_url = LOGIN_BASE_URL.'/services/oauth2/token';

$post_fields = array(
	'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
	'assertion' => $token
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// Make the API call, and then extract the information from the response
    $token_request_body = curl_exec($ch) 
        or die("Call to get token from code failed: '$token_url' - ".print_r($post_fields, true));

$arr_data = json_decode($token_request_body, true);
print_r($arr_data);
// Extract the user Id from saved id
$userId = null;
if (isset($arr_data['id'])) {
  $trailingSlashPos = strrpos(stripslashes($arr_data["id"]), '/');
  $userId = substr($arr_data["id"], $trailingSlashPos + 1);

  //Show Accounts Using REST
  $instanceURL = $arr_data['instance_url'];
  $token = $arr_data['access_token'];
  echo "<br/><br/> ****** Start Show All Accounts using REST API *****<br/>";
  show_accounts($instanceURL, $token);
  echo "<br/> ****** Ends Show All Accounts *****<br/><br/>";
  
  //Get SOAP API Server URL by sending get request to id URL received from oauth dance
  $ownerInfoArr = getUserResourceInfo($token,$arr_data['id']);
  print_r($ownerInfoArr);
  /*
  echo "<br/><br/> ****** Start Show Data using SOAP API *****<br/><br/>";
  getDataUsingSOAPAPI($token,$ownerInfoArr['urls']['partner']);
  echo "<br/><br/> ****** Ends Show Data using SOAP API *****<br/><br/>";
  */
}

function getUserResourceInfo($access_token,$idUrl){
    // Header options
    $headerOpts = array('Authorization: Bearer ' . $access_token);

    // Open connection
    $ch = curl_init();

    // Set the url and header options
    curl_setopt($ch, CURLOPT_URL, $idUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerOpts);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute GET
    $result = curl_exec($ch);
    $err = curl_error($ch);

    // Close connection
    curl_close($ch);
    if ($err) {
      echo "cURL Error #:" . $err;
    } 
    else {
      // Get the results
      //$typeString = gettype($result);
      $resultArray = json_decode($result, true);
      //print_r($resultArray);
    }
    return $resultArray;
}

function show_accounts($instance_url, $access_token) {
    $query = "SELECT Name, Id from Account LIMIT 10";
    $url = "$instance_url/services/data/v20.0/query?q=" . urlencode($query);
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER,
            array("Authorization: OAuth $access_token"));
    $json_response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($json_response, true);
    $total_size = $response['totalSize'];
    echo "$total_size record(s) returned<br/><br/>";
    foreach ((array) $response['records'] as $record) {
        echo $record['Id'] . ", " . $record['Name'] . "<br/>";
    }
    echo "<br/>";
}

?>