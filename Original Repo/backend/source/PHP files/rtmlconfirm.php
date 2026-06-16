<?php 

require_once('../mHeader.php');
require_once('../mTop.php');

function rtmlconfirm_log_line($path, $line){
	$dir = dirname($path);
	if(!is_dir($dir)){
		@mkdir($dir, 0775, true);
	}
	@file_put_contents($path, date('c')." ".$line.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function rtmlconfirm_atomic_json_write($path, $data){
	$dir = dirname($path);
	if(!is_dir($dir)){
		@mkdir($dir, 0775, true);
	}
	$tmp = $path.".tmp.".getmypid().".".bin2hex(random_bytes(4));
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if($json===false){
		return false;
	}
	if(@file_put_contents($tmp, $json, LOCK_EX)===false){
		return false;
	}
	if(!@rename($tmp, $path)){
		@unlink($tmp);
		return false;
	}
	return true;
}

if($displayPage){

echo"<br><center><br>";
$error=0;
$error_msg="";
$rtmlID=0;
$dowhat=0;

if(isset($_POST["rtmlID"])){
	if(isset($_POST["dowhat"])){
		$rtmlID=(int)$_POST["rtmlID"];
		$dowhat=(int)$_POST["dowhat"];
		
		if($dowhat==-1){
			
		}elseif($dowhat==1){
			
		}else{
			$error=1;
			$error_msg="$dowhat is not a valid modifier";
		}
	}else{
		$error=3;
		$error_msg="Missing modifier";
	}
}else{
	$error=2;
	$error_msg="Missing RTML ID";
}

if($error==0){
	$query="SELECT * FROM `rtml` WHERE `id`=$rtmlID LIMIT 1";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
	$rows=mysqli_num_rows($result);
	
	if($rows<1){
		$error=4;
		$error_msg="RTML record not found";
	}else{
		$row = mysqli_fetch_array($result);
		$current_status = (int)$row['status'];
		$owner_userid = isset($row['userid']) ? (int)$row['userid'] : 0;
		
		if(!($level>8 || $userid==$owner_userid)){
			$error=5;
			$error_msg="You do not have permission to modify this RTML plan";
		}
	}
}

if($error==0){
	$approval_file="rtml/".$rtmlID.".approved.json";
	$status_changed=false;
	$sidecar_changed=false;
	$sidecar_error="";
	
	if($dowhat==-1 && $current_status==2){
		if($level>8 ){
			echo"RTML couldn't be cancelled, already queued for execution.<br><br><br>";
			rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID cancelledButAlreadyQueuedAdmin");
		}else{
			echo"RTML couldn't be cancelled, already queued for execution.<br><br><br>";
			rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID cancelledButAlreadyQueued");
		}
	}else{
		$query3="UPDATE `rtml` SET `status`=$dowhat WHERE `id` = $rtmlID LIMIT 1";
		$result3 = mysqli_query($link, $query3) or die(mysqli_error($link));
		$status_changed = (bool)$result3;
		
		if($status_changed){
			if($dowhat==1){
				$approval_data = array(
					"rtml_id" => $rtmlID,
					"approved" => true,
					"approved_at" => gmdate('c'),
					"approved_by" => (int)$userid,
					"submitted_by" => $owner_userid,
					"current_status" => 1
				);
				$sidecar_changed = rtmlconfirm_atomic_json_write($approval_file, $approval_data);
				if(!$sidecar_changed){
					$sidecar_error = "Failed to write approval file";
				}
			}else{
				if(file_exists($approval_file)){
					$sidecar_changed = @unlink($approval_file);
					if(!$sidecar_changed){
						$sidecar_error = "Failed to remove approval file";
					}
				}else{
					$sidecar_changed = true;
				}
			}
		}
		
		if($level>8 ){
			if($dowhat==1){
				rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID approvedAdmin");
				echo"Plan approved.";
				if($sidecar_error!=""){
					echo"<br><span style=\"color:#cc0000;\">Warning: $sidecar_error</span>";
					rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID approvalSidecarErrorAdmin");
				}
				echo"<br>You can queue it for EKOS now:<br><br><form action=\"ekosjobsubmit.php\" method=\"GET\"><input type=\"hidden\" name=\"id\" value=\"$rtmlID\">Priority (-100 to 100): <input type=\"text\" name=\"priority\" size=\"1\" value=\"1\"><input type=\"submit\" value=\"Queue for EKOS\"></form><br><br><br><br>";
			}else{
				echo"RTML cancelled.<br><br><br>";
				if($sidecar_error!=""){
					echo"<span style=\"color:#cc0000;\">Warning: $sidecar_error</span><br><br>";
					rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID removeApprovalSidecarErrorAdmin");
				}
				rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID cancelledAdmin");
			}
		}else{
			if($dowhat==1){
				rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID submitted");
				echo"Successfully submitted RTML. <br>Your plan will be checked by observatory staff and, if approved, queued for execution.<br><br><a href=\"quickuploadrtml.php\">  Upload another RTML plan</a><br><br><br>";
				if($sidecar_error!=""){
					echo"<span style=\"color:#cc0000;\">Warning: $sidecar_error</span><br><br>";
					rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID approvalSidecarError");
				}

				$rtml_file="rtml/".$rtmlID.".rtml";

				$xml = @simplexml_load_file($rtml_file);

				if($xml!==false){
					$Request = $xml->Request;
					$plans=count($Request);

					$Project = $Request[0]->Project;
					$Observers= $Request[0]->Observers;
					$Description= $Request[0]->Description;

					$body = "New RTML submission, ID ".$rtmlID."\nObservers: ".$Observers."\nProject: ".$Project."\nDescription: ".$Description;

					try{
						$title = "New RTML submission";

						$ch = curl_init();

						curl_setopt($ch, CURLOPT_URL, "https://observatory.herts.ac.uk/api/notification.php?p=9okEap1xDT2mVR3k&title=".urlencode($title)."&message=".urlencode($body));

						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2000);
						curl_setopt($ch, CURLOPT_TIMEOUT, 2000);

						$result = curl_exec($ch);
						$err=curl_error($ch);
						curl_close($ch);
					} catch (Exception $e) {
						echo"<br>Error sending notification email: <br>".$e->getMessage()."<br>";
					}
				}
			}else{
				echo"RTML cancelled.<br><br><br>";
				if($sidecar_error!=""){
					echo"<span style=\"color:#cc0000;\">Warning: $sidecar_error</span><br><br>";
					rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID removeApprovalSidecarError");
				}
				rtmlconfirm_log_line("logs/rtmlconfirm.log", "$userid $rtmlID cancelled");
			}
		}
	}
}else{
	echo"Error $error <br>";
	if($error_msg!=""){
		echo htmlspecialchars($error_msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."<br><br>";
	}else{
		echo"<br>";
	}
}
}

require_once('../mFooter.php');
?>
