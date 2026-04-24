<?php

//This page is the single pathway to submitting RTML files to the telescopes
//It will adjust priority, remove invalid characters

require_once('../mHeader.php');
require_once('../mTop.php');

if($displayPage){

echo"<div ><div style=\" width:800px; margin:0px auto;\">";


if(isset($_GET['id'])){

if(isset($_GET['priority'])){
	$priority=$_GET['priority'];
}else{
	$priority=1;
}

if($priority<-100){
	$priority=-100;
	echo"Priority changed to -100, for lower numbers adjust manually in Browser.<br>";
}elseif($priority>100){
	$priority=100;
	echo"Priority changed to 100, for higher numbers adjust manually in Browser.<br>";
}

$rtmlid=$_GET['id'];

    $file_path = "rtml/".$rtmlid.".rtml";
	if(file_exists($file_path)){
	
		$query="SELECT * FROM `rtml` WHERE `id`=$rtmlid";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);

		if($rows>0){
		
		
			while($row = mysqli_fetch_array($result)){ 
				 
				 $userid = $row['userid'];
				 $status = $row['status'];
				 $origname = $row['origname'];
				 
				 //unset($row2);
				 $query2="SELECT * FROM `users` WHERE user=$userid LIMIT 1";
				 $result2 = mysqli_query($link, $query2) or die(mysqli_error($link)); 
				 
				 $row2 = mysqli_fetch_array($result2);
				 
				$acpuser = "Robotic";
				$useremail = $row2['email'];
				$fname=$row2['name'];
				$sname=$row2['surname'];
				$fullname = $fname." ".$sname;
			 
								 
				 //$origname = $row['origname'];
				 $projectname = $row['project'];
				 //$prjdes  = $row['prjdes'];
				 $telescope = $row['telescope'];
				// $telescope = 6; //##############################remove telescope
				 				
			}	
			
			if($status==2 && !isset($_GET['resubmit'])){
				echo"Already submitted, <a href=\"rtmlsubmit.php?id=$rtmlid&priority=$priority&resubmit\">resubmit?</a><br><br>";
			}elseif($status<1 && !isset($_GET['resubmit'])){
				echo"Already submitted, <a href=\"rtmlsubmit.php?id=$rtmlid&priority=$priority&resubmit\">resubmit?</a><br><br>";
			}elseif($status==1 || isset($_GET['resubmit'])){
				
				$time_start = microtime(true);
				
				$bad = array_merge(array_map('chr', range(0,31)),array('<', '>', ':', '"', '/', '\\', '|', '?', '*'));
				$bad2 = array("<", ">", '"', '|', "*");
				
				//Fix priority and RTML project name
				echo"Changing priority to $priority<br>";
				$xml = simplexml_load_file($file_path);
				$Request = $xml->Request;
				$xml->Contact->User=$acpuser;
				
				$plans=count($Request);
				for ($i = 0; $i < $plans; $i++) {
					//$Priority = $Request[$i]->Schedule->Priority;
					$Request[$i]->ID = str_replace($bad, " ", $Request[$i]->ID);
					$Request[$i]->Schedule->Priority=$priority;
					$Request[$i]->Project = str_replace($bad, " ", $Request[$i]->Project);
					$Request[$i]->Description = str_replace($bad2, " ", $Request[$i]->Description);
					$Request[$i]->Observers = str_replace($bad2, " ", $Request[$i]->Observers);
					$Request[$i]->UserName=$acpuser;
					
					$Target = $Request[$i]->Target;
					$targets=count($Target);
					for ($j = 0; $j < $targets; $j++) {
						$Target[$j]->Name=str_replace($bad, " ", $Target[$j]->Name);
						$Target[$j]->Description=str_replace($bad, " ", $Target[$j]->Description);
					}
					/*
					//old way
					if($userid!=3 && $userid!=1){
						$Project =$Request[$i]->Project;
						$prjparts=explode("_", $Project);
						
						//Create the project name for ACP
						if(count($prjparts)==1){
							$Request[$i]->Project=$userid."_".str_replace("_", "-", str_replace("|", " ", $Project));
						}else{
							if($prjparts[0]!=$userid){
								$Request[$i]->Project=$userid."_".str_replace("_", "-", str_replace("|", " ", $Project));
							}
						}
					}
					*/
					//new way
					
					//echo $Request[$i]->Project."<br>";			
					
					$nameparts=explode(" ", $sname);
					//print_r($nameparts);
					$namesmush=substr($fname,0,1);
					
					//echo "namesmush: ".$namesmush."<br>";
					
					//for($m=1; $m<count($nameparts); $m++){
						$namesmush.=substr($nameparts[count($nameparts)-1],0,5);					
					//}
					$namesmush=substr($namesmush,0,6);
					
					//echo "namesmush: ".$namesmush."<br>";

					$Project =$Request[$i]->Project;
					$prjparts=explode("_", $Project);
					
					//echo "project ".$Project."<br>";
					
					//Create the project name for ACP
					if(count($prjparts)==1){
						$Request[$i]->Project=$userid."_".$namesmush."_".str_replace("_", "-", str_replace("|", " ", $Project));
					}else{
						if($prjparts[0]!=$userid){
							$Request[$i]->Project=$userid."_".$namesmush."_".str_replace("_", "-", str_replace("|", " ", $Project));
						}
					}
					
					$Request[$i]->Project=substr($Request[$i]->Project,0,50);
					//echo $Request[$i]->Project."<br>";
				}
				$xml->asXML($file_path);
				
				include("config.php");
				$acpuser="Robotic";
				
				$url = "http://".$scopeip[$telescope]."/server/uploadrtml.asp";
				
				echo"Submitting RTML to ".$scopename[$telescope]."<Br>";
				
				$timeout=(ceil(filesize($file_path)/25000))*2;
				
				if($timeout<30){$timeout=30;}
				
				$start=microtime(true);
				//$data['Filedata'] = "@".$file_path;
				$data['Filedata'] = new \CURLFile($file_path);
				$data['autoSubmit'] = "true";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_FAILONERROR, false);
				//curl_setopt($ch, CURLOPT_MUTE, false);
				curl_setopt($ch, CURLOPT_COOKIESESSION, true);
				curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 
				curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				curl_setopt($ch, CURLOPT_USERPWD, $acpuser . ":" . $acppassword[$acpuser]);
				curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
				//curl_setopt($ch, CURLOPT_HEADER, true); //debug
					
				//curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/xml', $additionalHeaders));
				//curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/xml', $additionalHeaders));
				$response=curl_exec($ch);
				if(curl_errno($ch)){
					echo "Curl error no".curl_errno($ch).": " . curl_error($ch);
					file_put_contents("logs/rtmlsubmit.log", date('c')." $userid $rtmlid curlError $telescope".PHP_EOL, FILE_APPEND);
				}else{

					file_put_contents("logs/rtmlsubmit.log", date('c')." $userid $rtmlid submitted $telescope".PHP_EOL, FILE_APPEND);
				
				
					//if($telescope==5){
						$output=str_replace("  ", " ", str_replace("&nbsp;", "", substr($response, 118)));
					//}else{
					//	$output=str_replace("  ", " ", str_replace("&nbsp;", "", str_replace("style=\"color: yellow;\"", "", substr($response, 420, -221))));
					//}
					
					
					echo"<Br>Response from ACP:<br><div style=\"padding-left: 10px; border:1px solid; width:400; height:350px; overflow:auto;  background-color: #E4E4D1; color: #000000; border: 1px solid #ff7f00; padding: 2px 2px 2px 2px;\">$output</div>";
					
					
					
					if (strpos($output, 'RTML uploaded successfully for Robotic') !== false) {
						
						$query3="UPDATE `rtml` SET `status`=2 WHERE `id` = $rtmlid"; 
						$result3 = mysqli_query($link, $query3) or die(mysqli_error($link));
						
						$outputarray=explode("<br />", $output);
						
						//echo"<Br>Full from ACP:<br><div style=\"padding-left: 10px; border:1px solid; width:400; height:350px; overflow:auto;  background-color: #E4E4D1; color: #000000; border: 1px solid #ff7f00; padding: 2px 2px 2px 2px;\">";
						//echo $url."|".$response."|".curl_errno($ch)."|";
						//print_r($outputarray);
						//echo "</div>";
						
						
						$plancount=count($outputarray);
						//print_r($outputarray);
						//echo"<br>";
						$projectparts=explode("|", $outputarray[1]);
						$projectid=trim($projectparts[1]);
						$projectname=trim($projectparts[2]);
						//echo"Project id: |".$projectid."| name: |".$projectname."|<br>";
						$query3="INSERT INTO `projects` (`id`, `userid`, `name`) VALUES('".$telescope."_".$projectid."', $userid, '".mysqli_real_escape_string($link, $projectname)."') ON DUPLICATE KEY UPDATE `userid`=$userid, `name`='".mysqli_real_escape_string($link, $projectname)."';";
						//echo "$query3<br>";

						$result3 = mysqli_query($link, $query3) or die(mysqli_error($link));
						
						for($i=2; $i<($plancount-2); $i++){
							$planparts=explode("|", $outputarray[$i]);
							$planid=trim($planparts[0]);
							$planname=trim($planparts[1]);
							//echo"$i plan id: |".$planid."| name: |".$planname."|<br>";
							
							$query3="INSERT INTO `plans` (`id`, `userid`, `name`, `project`) VALUES('".$telescope."_".$planid."', $userid, '".mysqli_real_escape_string($link, $planname)."', '".$telescope."_".$projectid."') ON DUPLICATE KEY UPDATE `userid`=$userid, `name`='".mysqli_real_escape_string($link, $planname)."', project='".$telescope."_".$projectid."';";
							$result3 = mysqli_query($link, $query3) or die(mysqli_error($link));
						}
						
						echo 'Upload completed without any errors';
						

						$emailsOK=false;
						
						if($emailsOk && !($level>8 || $userid==38|| $userid==155)){
							//$useremail="d.a.campbell2@herts.ac.uk"; //testing
							require_once 'swift_required.php';
							$transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, 'ssl')
							->setUsername('bayfordburyobs@gmail.com')
							->setPassword($gpwd)
							;

								// Create the Mailer using your created Transport
							$mailer = Swift_Mailer::newInstance($transport);
								// Create a message
								
							$body=$fullname.",\r\n\r\nYour RTML upload '".$origname."' (for project '".$Project."') has been approved and sent to the telescope.\r\n\r\nDetails\r\nRTML id: ".$rtmlid."\r\nTelescope: (".$telescope.") ".$scopename[$telescope]."\r\nProject name in telescope queue: ".$projectname."\r\nNumber of plans: ".($plancount-4)."\r\n";	
							if($priority<1){
								$body.="Priority: Low";
							}elseif($priority>1){
								$body.="Priority: High";
							}else{
								$body.="Priority: Normal";
							}
							
								
							$message = Swift_Message::newInstance('RTML plan approved')
							  ->setFrom(array('bayfordburyobs@gmail.com' => 'Bayfordbury Observatory'))
							  ->setTo(array($useremail => $fullname))
							  ->setBody($body, 'text/plain');
							  
							  // Send the message
							  
							  
							try{
							$mailer->send($message);
								echo"<br>Notification email successfully sent<br>";
							} catch (Exception $e) {
								echo"<br>Error sending notification email: <br>".$e->getMessage()."<br>";
							}
						}else{
							echo "<br> Email notification disabled<br>";
						}		
						
					
					}else if (strpos($output, 'No permission to upload') !== false) {
						echo "<font color=\"#ff0000\"><b>ERROR</font> - looks like ACP has crashed.</b><br><br>";
						file_put_contents("logs/rtmlsubmit.log", date('c')." $userid $rtmlid ACP error $telescope".PHP_EOL, FILE_APPEND);
					}else if (strpos($output, 'Bad RTML message') !== false) {
						echo "<font color=\"#ff0000\"><b>ERROR</font> - Problem with the RTML syntax.</b> - Please fix and resubmit.<br><br>";
						file_put_contents("logs/rtmlsubmit.log", date('c')." $userid $rtmlid ACP error $telescope".PHP_EOL, FILE_APPEND);	
											
					}else{
						echo "<font color=\"#ff0000\"><b>ERROR</font> with response from ACP. Please fix and resubmit.</b><br><br>";
						file_put_contents("logs/rtmlsubmit.log", date('c')." $userid $rtmlid ACP error $telescope".PHP_EOL, FILE_APPEND);
					}

					$time_end = microtime(true);
					$time = $time_end - $time_start;	

					echo "Time taken $time seconds\n";					
				
					
					//echo"<Br>Response from ACP: ".$response;
				}
				curl_close($ch);
				//echo"<br>Time taken to upload: ".round((microtime(true)-$start),2)."s";				
			}
		}else{
			echo"Rtml details not found";
		}
	}else{
		echo"File doesn't exist";
	}
}else{
	echo"No id";
}

echo"<br></div>";
}


require_once('../mFooter.php');

?>
