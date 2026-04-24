<?php
require_once('../mHeader.php');
?>

<script>
function copyAPI() {
  /* Get the text field */
  var copyText = document.getElementById("apikey");

  /* Select the text field */
  copyText.select();

  /* Copy the text inside the text field */
  document.execCommand("copy");

  /* Alert the copied text */
  //alert("Copied the text: " + copyText.value);
} 

</script>

<?php
require_once('../mTop.php');


function randomString() {
    $length = 64;
    $chars = "0123456789abcdef";
    $str = "";    

    for ($i = 0; $i < $length; $i++) {
		
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }

    return $str;
}

if($displayPage){

	if(isset($_GET['makeapikey'])){
				
		$new_apikey = randomString();
			
		$query="INSERT INTO `api` (`userid`, `apikey`) VALUES ('$userid', '$new_apikey');";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 

	}


	echo "<h1> Account details</h1>
	<b>Name: $loggedinname</b><br><br><b>Notification email</b>: $loggedinemail<br><br><b>UH ID</b>: $uhid<br><br><b>Observer ID</b>: $userid";


	$query="SELECT * FROM `api` WHERE `userid`=$userid";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
	
	$rows=mysqli_num_rows($result);
	
	$api=false;
	
	if($rows>0){
		$row = mysqli_fetch_array($result);
		
		$api = $row['apikey'];
	}

	echo "<br><br><h1>API</h1>
	The API (Application programming interface) allows you to write code to interact with various systems, such as searching and downloading images.<br><br>
	API use is monitored, and excessive load on the server may result in disabling your API key.<br>
	<b>Keep your API key secret</b>. Never share it or allow others to access it.<br><br>";

	if($api){
		echo "You API key is: 
		<input type=\"text\" size=\"80\" value=\"$api\" id=\"apikey\">

		
		<button onclick=\"copyAPI()\">Copy to clipboard</button> ";
	}else{
		echo "You do not have an API key yet.<br><br>";
		
		echo "<form action=\"myaccount.php\"  method=\"get\">
		<input type=\"hidden\" name=\"makeapikey\" value=\"true\">
			<input type=\"submit\" value=\"Generate API key\" />
		</form>";
	}
	
	echo"
	<br><br><a href=\"https://observatory.herts.ac.uk/wiki/API\">API reference documentation</a>";

	

}

require_once('../mFooter.php');


?>
