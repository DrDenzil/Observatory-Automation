<?php


require_once('../mHeader.php');
require_once('../mTop.php');
if($displayPage){

echo"<div ><div style=\" width:800px; margin:0px auto;\">";





if(isset($_POST['id'])){


$rtmlid=$_POST['id'];


$reason=mysqli_real_escape_string($link, $_POST['reason']);


		
				$query="SELECT * FROM `rtml` WHERE `id`=$rtmlid";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);

		if($rows>0){
		
		
			while($row = mysqli_fetch_array($result)){ 
				 
				 $userid = $row['userid'];
				 $status = $row['status'];
				 $totalexp = $row['totalexp'];
				 $projectname = $row['project'];
				 $prjdes  = $row['prjdes'];
				 $telescope = $row['telescope'];
				 $plans = $row['plans'];
				 $time = $row['time'];		
				 $origname = $row['origname'];
				 $time = $row['time'];
				 
				 //unset($row2);
				 $query2="SELECT * FROM `users` WHERE user=$userid LIMIT 1";
				 $result2 = mysqli_query($link, $query2) or die(mysqli_error($link)); 
				 
				 while($row2 = mysqli_fetch_array($result2)){ 
					$useremail = $row2['email'];
					$fullname = $row2['name']." ".$row2['surname'];
					
				 }
										 
			
			}	
			echo"
				<br>RTML Rejected<br><br><a href=\"allrtml.php\">&lt;&lt; Back to all RTML</a><br><br>";
				
						$query="UPDATE `rtml` SET `status` = '-2' WHERE `rtml`.`id` =$rtmlid;";
			$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			
			$query = "INSERT INTO rejection(`rtmlid`,`reason`) VALUES ($rtmlid, '".mysqli_real_escape_string ($link,$reason)."')";
			$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			
			$frommail="do-not-reply@herts.ac.uk";

			ini_set("sendmail_from","Bayfordbury Observatory<$frommail>"); 

			$headers =  'MIME-Version: 1.0' . "\r\n"; 
			$headers .= 'From: Bayfordbury Observatory<'.$frommail.'>' . "\r\n";
			$headers .= 'Reply-To: Bayfordbury Observatory<d.a.campbell2@herts.ac.uk>' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n"; 			
				
			/*
			require_once 'swift_required.php';
			$transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, 'ssl')
			->setUsername('bayfordburyobs@gmail.com')
			->setPassword('$gpwd')
			;
			
				// Create the Mailer using your created Transport
			$mailer = Swift_Mailer::newInstance($transport);
				// Create a message
				*/
			//$body=$fullname.",<br><br>Your RTML upload '".$origname."' has been rejected for the following reason:<br><br>".str_replace( '\r', ' ',str_replace( '\n', '<br />', $reason )) ."<br><br>Details<br>RTML id: ".$rtmlid."<br>Telescope: (".$telescope.") ".$scopename[$telescope]."<br>Project: ".$projectname."<br>Number of plans: ".($plans)."<br>Total exposure time: ".format_seconds($totalexp)."<br><br>Contact observatory staff if you have any queries - contact details are available here: https://observatory.herts.ac.uk/telescopes/contact.php<br>Do not reply directly to this email.";	
			$body=$fullname.",<br><br>Your observatory plan upload '".$origname."' has not been approved for the following reason:<br><br>".stripcslashes($reason )."<br><br>Details<br>RTML id: ".$rtmlid."<br>Telescope: (".$telescope.") ".$scopename[$telescope]."<br>Project: ".$projectname."<br>Number of plans: ".($plans)."<br>Total exposure time: ".format_seconds($totalexp)."<br><br>Contact observatory staff if you have any queries - contact details are available here: https://observatory.herts.ac.uk/telescopes/contact.php<br>Do not reply directly to this email.";	
			
			
				/*t
			$message = Swift_Message::newInstance('RTML plan rejected')
			  ->setFrom(array('bayfordburyobs@gmail.com' => 'Bayfordbury Observatory'))
			  ->setTo(array($useremail => $fullname))
			  ->setBody($body, 'text/plain');
			  
			  // Send the message
			  
			ry{
			$mailer->send($message);
				echo"Notification email successfully sent<br><br>";
			} catch (Exception $e) {
				echo"Error sending notification email: <br>".$e->getMessage()."<br><br>";
			}*/
			
			try{
				mail("$fullname<$useremail>","Observatory plan not approved",$body,$headers);
				echo"Notification email successfully sent<br><br>";
			} catch (Exception $e) {
				echo"Error sending notification email: <br>".$e->getMessage()."<br><br>";
			}
			
			
		}else{
			echo"Rtml details not found";
		}		
			
		
		
	
}else{
echo"No id";
}



echo "<br></div>";
}
require_once('../mFooter.php');
?>