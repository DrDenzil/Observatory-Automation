<?php

$title="";

require_once('../mHeader.php');
if($displayPage){
?>


<?php

}
require_once('../mTop.php');
if($displayPage){

			
echo"<div style=\" width:90%; margin:0px auto;\"><br>";


if($level>=1){

	if(isset($_GET['submitted'])){

		$sendername=$loggedinname;
		$senderemail=$loggedinemail;

		require_once 'swift_required.php';
		$transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, 'ssl')
	  ->setUsername('bayfordburyobs@gmail.com')
	  ->setPassword('$gpwd')
	  ;



		// Create the Mailer using your created Transport
		$mailer = Swift_Mailer::newInstance($transport);

		// Create a message
		

		if($_POST['to']==2){

		$message = Swift_Message::newInstance('New message from '.$sendername.' (Bayfordbury Observatory)')
		  ->setFrom(array('bayfordburyobs@gmail.com' => $sendername))
		  ->setReplyTo(array($senderemail => $sendername))
		  ->setTo(array('observatory@herts.ac.uk' => 'Sam Rolfe'))
		  ->setBody("ATTN: Sam Rolfe. New message sent via the Bayfordbury server from $sendername <".$senderemail.">:\r\n\r\n".$_POST['message'], 'text/plain');
		  ;
		  
		}else{
		$message = Swift_Message::newInstance('New message from '.$sendername.' (Bayfordbury Observatory)')
		  ->setFrom(array('bayfordburyobs@gmail.com' => $sendername))
		  ->setReplyTo(array($senderemail => $sendername))
		  ->setTo(array('observatory@herts.ac.uk' => 'David Campbell'))
		  ->setBody("ATTN: David Campbell. New message sent via the Bayfordbury server from $sendername <".$senderemail.">:\r\n\r\n".$_POST['message'], 'text/plain');
		  ;
		}
		// Send the message

				try{
					$mailer->send($message);
					echo"<br>Message successfully sent<br>";
				} catch (Exception $e) {
					echo"<br>Error sending message: <br><br>"; //.$e->getMessage().
				}

	}else{

		echo"Please feel free to contact us if you have any queries regarding the observatory or practicals, or feedback and suggestions about this website.<br>
				 
		<br><b>Email directly to:</b><br><br>
		Lord Dover and Sam Rolfe via <a href=\"mailto:observatory@herts.ac.uk\">observatory@herts.ac.uk</a><br>
		<br>";
		/*
		<b>Or use the form below.</b><br><br><br>
		<form action=\"contact.php?submitted=1\" method=\"post\">To: 

			 <select name=\"to\">
			 <option value=\"1\" >David Campbell</option>
			 <option value=\"2\" >Sam Rolfe</option>
			 </select><br><br>
			 Message:<br>
			 <textarea name=\"message\" cols='70' rows='15'></textarea><br><br>
			 <input type=\"submit\" value=\"Send\"  onload=\"this.disabled=false;\" onclick=\"this.disabled=true;this.value='Sending';this.form.submit();\">
			 
			 </form>
			 "; */
			 



		
		
	}
	
}else{
	echo"<br><b>Email directly to:</b><br><br>
		Lord Dover and Sam Rolfe via &lt;observatory@herts.ac.uk&gt;<br>
		<br>";
		// <br>Or log in to use the messaging form.<br>";
	
}
	
echo"</div>";
}

require_once('../mFooter.php');

?>