<?php 

require_once('../mHeader.php');
require_once('../mTop.php');



if($displayPage){

echo"<center><br><br><br>

<form action=\"quicksubmitrtml.php\" method=\"post\" enctype=\"multipart/form-data\">

<label for=\"file\">RTML File: </label>
<input type=\"file\" name=\"file\" id=\"file\" /><br><br>  <input type=\"submit\" value=\"Submit\"  onload=\"this.disabled=false;\" onclick=\"this.disabled=true;this.value='Uploading';this.form.submit();\">
</form>
<br>
<br><!-- Please make sure you use the latest version of the RTML generator. Using old version may cause errors when uploading.<br>You can check for updates by clicking 'Check for updates' from the 'Help' menu, or get the latest version <a href=\"offlinertml.php\">here</a>. -->
<br>
<br>
<br>
<br>
";
//<input type=\"submit\" value=\"Submit\" onclick=\"this.disabled = true;this.value='Uploading'\">
}

require_once('../mFooter.php');
?>
