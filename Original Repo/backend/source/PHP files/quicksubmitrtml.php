<?php
//Checks for errors from RTML uploaded

require_once('../mHeader.php');
require_once('../mTop.php');

if($displayPage){

echo"<div style=\" width:800px; margin:0px auto;\"><br>";

if (!empty($_FILES)){

	foreach ($_FILES as $file) {
		if (!strtolower(substr($file['tmp_name'],-4))=="rtml"){
			echo"invalid file type<br>";
		}else{
			//echo"file type ok<br>";
	  		
			if ($file["error"] == UPLOAD_ERR_OK) {
				$tmp_name = $file["tmp_name"];
		
				
				//echo"tmp name: $tmp_name<br>";
				$filesize=filesize($tmp_name);
				
				if($filesize<10485760){
					$handle = fopen($tmp_name, "r");
					$contents = fread($handle, $filesize);
					fclose($handle);
				
					$firstline=substr($contents, 0, 43);
					//echo"<pre>$firstline</pre><br>";
			
					if($firstline=="<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>"){
						$origname=$file["name"];
						echo"Your file \"".$file["name"]."\" (".round($filesize/1024,1)."KB) has been received but <b>not yet submitted</b><br>Checking for errors...<br>";
						
							
							
							
							include("config.php");
							include("/www/bayfordbury/private/db.php");
							$file=$tmp_name;

							if(file_exists($file)){
							
							
								$rtml_file=$file;

								include("rtmldetails.php");

								
								if($errorlevel>0){
									echo"$errorlist<font color=\"#ff0000\"><b>You must resubmit the RTML with the above errors fixed before continuing.</b><br></font>";
								}else{
									
									$nowtime=time();
									$query = "INSERT INTO rtml(`origname`, `status`, `userid`, `project`, `prjdes`, `telescope`, `plans`, `totalexp`, `time`) VALUES ('".mysqli_real_escape_string($link, $origname)."', 0, $userid, '".mysqli_real_escape_string($link, $Project)."', '".mysqli_real_escape_string($link, $Description)."', $tscope, $plans, $totalexp, $nowtime)";
									//echo"<br>$query<br>";
									$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
									$rtmlID=mysqli_insert_id($link);
									$name = "rtml/".$rtmlID.".rtml";
																	
									if(!move_uploaded_file($tmp_name, $name)){
										echo"<font color=\"#ff0000\">Error saving file</font><br>";
									}else{
										chmod ($name, 0766);
										echo"<font color=\"#00ff00\">No errors found</font><br><br>Check the details of your plans carefully below, and click submit to send your plans to the queue.<br><table><tr><td><form action=\"rtmlconfirm.php\" method=\"post\"><input type=\"hidden\" name=\"rtmlID\" value=\"$rtmlID\"><input type=\"hidden\" name=\"dowhat\" value=\"1\"><input type=\"submit\" value=\"Submit\" onload=\"this.disabled=false;\" onclick=\"this.disabled=true;this.form.submit();\"></form></td><td><form action=\"rtmlconfirm.php\" method=\"post\"><input type=\"hidden\" name=\"rtmlID\" value=\"$rtmlID\"><input type=\"hidden\" name=\"dowhat\" value=\"-1\"><input type=\"submit\" value=\"Cancel\" onload=\"this.disabled=false;\" onclick=\"this.disabled=true;this.form.submit();\"></form></td></tr></table>";
									}
								}
								echo"';</script>";
							}else{
								echo"File not found";
							}
							
							
																	
					}else{
					echo"invalid rtml file<br>";
					}
				}else{
				echo"file too big<br>";
				}
			}else{
			echo"error uploading file<br>";
			}
		}
	}
}else{
echo"No file submitted<br>";
}
echo"</div>";
  

echo"<br><br><br>";
}

require_once('../mFooter.php');
?>
