<?php
//Checks for errors from RTML submitting with the RTML editor

require_once('../mHeader.php');
require_once('../mTop.php');

error_reporting(E_ALL);
ini_set('display_errors', '1');

if($displayPage){

echo"<div style=\" width:100%; margin:0px auto;\"><br>";

$tmp_name = "rtml/editor/".$userid.".rtml";

$file=$tmp_name;
	
if(file_exists($file)){
	$filesize=filesize($tmp_name);

	if($filesize<10485760){
		$handle = fopen($tmp_name, "r");
		$contents = fread($handle, $filesize);
		fclose($handle);
	
		$firstline=substr($contents, 0, 43);
		//echo"<pre>$firstline</pre><br>";

		if($firstline=="<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>"){
			$origname="PlanGenerator.rtml";
			echo"Your RTML file (".round($filesize/1024,1)."KB) has been created but <b>not yet submitted</b><br>Checking for errors...<br>";
			
					$rtml_file=$file;

					include("rtmldetails.php");
					
					if($errorlevel>0){
						echo "<font color=\"#ff0000\"><b>You must fix the above errors before continuing.</b><br></font><a href=\"rtmleditor.php\">Return to Plan Generator</a><br>";
					}else{		
				
						$nowtime=time();
						$query = "INSERT INTO rtml(`origname`, `status`, `userid`, `project`, `prjdes`, `telescope`, `plans`, `totalexp`, `time`) VALUES ('".mysqli_real_escape_string($link, $origname)."', 0, $userid, '".mysqli_real_escape_string($link, $Project)."', '".mysqli_real_escape_string($link, $Description)."', $tscope, $plans, $totalexp, $nowtime)";
						//echo"<br>$query<br>";
						$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
						$rtmlID=mysqli_insert_id($link);
					
						$name = "rtml/".$rtmlID.".rtml";
						
						file_put_contents("logs/editorsubmitrtml.log", date('c')." $userid $rtmlID created".PHP_EOL, FILE_APPEND);
													
						if(!rename($tmp_name, $name)){
							echo"<font color=\"#ff0000\">Error saving file</font> :(<br>";
							file_put_contents("logs/error.log", date('c')." $userid $rtmlID editorsubmitrtml errorSavingFile".PHP_EOL, FILE_APPEND);
						}else{
							
							//$grp = chgrp ($name, "web");
							
							copy("rtml/editor/".$userid.".json", "rtml/json/".$rtmlID.".json");
							
							chmod ($name, 0766);
							chmod ("rtml/json/".$rtmlID.".json", 0766);
							
							echo "No errors found <font color=\"#00FF00\">&#x2714;</font><br><br>Check the details of your plans carefully below, and click submit to send your plans to be reviewed.<br><table><tr><td>								  <form action=\"rtmlconfirm.php\" method=\"post\"><input type=\"hidden\" name=\"rtmlID\" value=\"$rtmlID\">								  <input type=\"hidden\" name=\"dowhat\" value=\"-1\">								  <input type=\"submit\" value=\"Cancel\" onload=\"this.disabled=false;\" onclick=\"this.disabled=true;this.form.submit();\">								  </form>								  </td><td>								  								  <form action=\"rtmlconfirm.php\" method=\"post\"><input type=\"hidden\" name=\"rtmlID\" value=\"$rtmlID\"><input type=\"hidden\" name=\"dowhat\" value=\"1\"><input type=\"submit\" value=\"Submit\" onload=\"this.disabled=false;\" onclick=\"this.disabled=true;this.form.submit();\"></form> </td></tr></table>";
							
						}
						
					}		

					echo"';</script>";					
		}else{
			echo"invalid rtml file<br>";
		}
	}else{
		echo"file too big<br>";
	}
}else{
	echo"You must create the RTML file before submitting it";
}	

echo"</div>";
  

echo"<br><br><br>";
}

require_once('../mFooter.php');
?>
