<?php

require_once('../mHeader.php');
require_once('../mTop.php');


if($displayPage){

echo"<div style=\" width:100%; margin:0px auto;\"><br>";
if(isset($_GET['id'])){

	$rtmlid = $_GET['id'];
	$id = $rtmlid;

	$rtml_file="rtml/".$rtmlid.".rtml";

	$query="SELECT * FROM `rtml` WHERE `id`=$rtmlid";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 

	$row = mysqli_fetch_array($result);
	$status = $row['status'];
	

	include("rtmldetails.php");
	
	if($errorlevel==0){
		echo "No errors found <br>";



	}
	
	echo"'</script>";

	if($errorlevel==0 && $status==1 && $level>8){
		echo"<br><form action=\"ekosjobsubmit.php\" method=\"GET\">
		<input type=\"hidden\" name=\"id\" value=\"$id\">Priority: 
		<input type=\"text\" name=\"priority\" size=\"1\" value=\"1\"><br>
		<input type=\"submit\" value=\"Queue for EKOS\" onload=\"this.disabled=false;\" onclick=\"this.disabled = true;this.value='Sending...';this.form.submit();\"\">
		</form>
		
		<form action=\"rtmlreject1.php\" method=\"POST\"><input type=\"hidden\" name=\"id\" value=\"$id\">
		<input type=\"submit\" value=\"Reject\">
		</form>";
	}

}else{
	echo"No id given";
}

}

require_once('../mFooter.php');

?>
