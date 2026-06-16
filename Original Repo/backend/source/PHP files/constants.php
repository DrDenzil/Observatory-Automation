<?php

$SITETITLE = "Observatory";

$WEBMASTER = "David Campbell";
$WEBMASTEREMAIL = "d.a.campbell2@herts.ac.uk";

date_default_timezone_set('UTC');

ini_set("session.cookie_lifetime","3600");
session_set_cookie_params(0);
session_start();

/*
ini_set("session.cookie_lifetime","3600");
session_set_cookie_params(0);
session_start();


// Sets the default redirect location, overruled by certain pages e.g. when logging in from a page other than the homepage you are redirected back to that page

if (isset($_SESSION['referer'])) {
	$target = $_SESSION['referer'];
} else {
	$target = "index.php";
}*/


//default settings

$target = "index.php";
$authenticated = false;
//depricated
$altloggin=false;

//connect to mysql
require("/www/bayfordbury/private/db.php");
$link = mysqli_connect('localhost',$dbUser,$dbPassword, $dbDb);

//logout routine
if(isset($logout)){
	$_SESSION['obs_validated']=false;
	$_SESSION['obs_userid']="";
	$_SESSION['obs_time'] = NULL;
	unset($_SESSION['obs_time']);
	$_SESSION = array();
}

/*
if(isset($_SESSION['obs_validated'])){
	if($_SESSION['obs_validated']){
		$authenticated = True;
		$userid=$_SESSION['obs_userid'];
				
		$query="SELECT * FROM users WHERE `user`=$userid LIMIT 1";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		
		while($row = mysqli_fetch_array($result)){ 
			//$level = $row['level'];
			//$loggedinname=$row['name'];
			//$loggedinemail=$row['email'];
			
			$uhid = $row['uhid'];			
			$uhemailaddress = $row['uniemail'];			
			$surname = $row['name'];
			$givenname = $row['surname'];
			$altloggin=true;
		}
	}else{
		$authenticated = false;
	}
}else{
	$authenticated = false;
}
*/

$debug_auth = "";
$debug_id = "";
$debug_email = "";
$debug_surname = "";
$debug_givenname = "";


//if(!$authenticated){


	// SAML related bits
	require_once('/var/simplesamlphp/lib/_autoload.php');
	//$auth = new SimpleSAML_Auth_Simple('obs-sp');
	$auth =  new \SimpleSAML\Auth\Simple('obs-sp');



	if ($auth->isAuthenticated()) {
		// we are authenticated
		$authenticated = True;
		
		$debug_auth = true;

		// get some attributes from saml
		$attrs = $auth->getAttributes();
		
		
		if(isset($attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name'][0])){
			$uhid = $attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name'][0];
			$debug_id = $uhid;
		}else{
			if($uhid==""){
				$uhid="x";
				$debug_id = "not set";
			}else{
				$uhid="x";
				$debug_id = "not provided";
			}
		
		}
				
		if(isset($attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'][0])){
			$uhemailaddress = $attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'][0];
			$debug_email = $uhemailaddress;
		}else if($uhid!="x"){
			$uhemailaddress = $uhid."@herts.ac.uk";
			$debug_email.="not provided";
		}else{
			$uhemailaddress = "x";
			$debug_email.="not provided";
		}
				
		if(isset($attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname'][0])){
			$surname = $attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname'][0];
			$debug_surname = $surname;
		}else{
			$surname = "-";
			$debug_surname = "not provided";
		}		
		
		if(isset($attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname'][0])){
			$givenname = $attrs['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname'][0];
			$debug_givenname = $givenname;
		}else{
			$givenname = "-";
			$debug_givenname = "not provided";
		}

		if($uhid==""){
			$authenticated = false;
			$debug_auth = false;
		}

	} else {
		$authenticated = false;
		//$_SESSION = array();
		$debug_auth = false;
	}

//}


//check if the ip is private
function  ipIsPrivate($ip) {
    $pri_addrs = array (
                      '10.0.0.0|10.255.255.255', // single class A network
                      '172.16.0.0|172.31.255.255', // 16 contiguous class B network
                      '192.168.0.0|192.168.255.255', // 256 contiguous class C network
                      '169.254.0.0|169.254.255.255', // Link-local address also refered to as Automatic Private IP Addressing
                      '147.197.0.0|147.197.255.255', // uni
                      '127.0.0.0|127.255.255.255' // localhost
                     );

    $long_ip = ip2long ($ip);
    if ($long_ip != -1) {

        foreach ($pri_addrs AS $pri_addr) {
            list ($start, $end) = explode('|', $pri_addr);

             // IF IS PRIVATE
             if ($long_ip >= ip2long ($start) && $long_ip <= ip2long ($end)) {
                 return true;
             }
        }
    }

    return false;
}

$server_name=$_SERVER['SERVER_NAME'];
$viewerip=$_SERVER['REMOTE_ADDR'];

$unip = ipIsPrivate($viewerip);



?>
