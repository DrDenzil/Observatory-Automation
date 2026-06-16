<?php
Require_once("/www/bayfordbury/constants.php");

if($authenticated || $unip){

if(file_exists($rtml_file)){
		
	$errorlevel=0;
	$dwarning=0;
	$desblank=0;
	$projblank=0;
	$binningwarning=0;	
	$binningwarningASI=0;	
	$timeWarning=0;
	$timeDiffWarning=0;
	$statusWarning=0;
	$countWarning=0;
	$errorlist="";
	$filtererror=0;
	$tscope=0;
	$planlengtherror=0;	
	$repeatwarning=0;
				
	//rtml_priority($file,10);
	$xml = simplexml_load_file($rtml_file);
	$User=$xml->Contact->User;
	//$Email=$xml->Contact->Email;
	$Organisation=$xml->Contact->Organization;

	$Request = $xml->Request;
	$totalexp=0;
	$plans=count($Request);
	echo"<div id=\"warning\"></div><br>";
	echo "<div id=\"summary\"></div>";
	
	echo"<table class=\"simple2\"><tr><th>Plan</th><th>Schedule</th><th>Targets</th></tr>";
	for ($i = 0; $i < $plans; $i++) {
		
		$Target = $Request[$i]->Target;
		$Project = $Request[$i]->Project;
		$ID= $Request[$i]->ID;
		$UserName= $Request[$i]->UserName;
		$Observers= $Request[$i]->Observers;
		$Description= $Request[$i]->Description;
		
		if(!isset($Telescope)){$Telescope="";}
		$Telescope= $Request[$i]->Telescope;
		
		if($Telescope!=""){
			
			if($tscope==0){
				$tscope=$Telescope;
			}elseif(($Telescope-$tscope)!=0){
				$errorlist.="<font color=\"#ff0000\">- Error: The RTML file must only use one telescope</font><br>";
				$errorlevel++;
			}
			
			$query="SELECT * FROM obssetup WHERE `num`=$Telescope";
			//echo"QUERY $query<br>";
			$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			while($row = mysqli_fetch_array($result)){ 
				$ScopeNum=$row['num'];
				$ScopeStatus=$row['status'];
				
				$declimits=explode("|",$row['limits']);
				$dlower=$declimits[0];
				$dupper=$declimits[1];	
				$minbinning=explode("|",$row['minbinning']);
				$MinBinning=$minbinning[0];
				$filterlist=explode("|", $row['filters']);
			}
		}else{
			$errorlevel++;
			$errorlist.="<font color=\"#ff0000\">- Error: Telescope not given</font><br>";
		}
		
		//print_r($minbinning);
		
		if($Project==""){$projblank=1;$errorlevel++;}
		if($Description==""){$desblank=1;$errorlevel++;}
		$targets=count($Target);
		$planexp=0;
		
		$SkyCondition = $Request[$i]->Schedule->SkyCondition;
		$Moon = $Request[$i]->Schedule->Moon;
		$MoonWidth = $Request[$i]->Schedule->Moon->Width;
		$MoonDistance = $Request[$i]->Schedule->Moon->Distance;
		$Priority = $Request[$i]->Schedule->Priority;
		$Reason = $Request[$i]->Reason;
		$TimeRange = $Request[$i]->Schedule->TimeRange;
		$Earliest = $Request[$i]->Schedule->TimeRange->Earliest;
		$Latest = $Request[$i]->Schedule->TimeRange->Latest;
		
		//
		
		
		//Plan details
		echo"<tr><td><b>".($i+1).".</b> $ID ($targets target";if($targets>1){echo"s";}echo")<br><b>User:</b> $UserName<br><b>Observers:</b> $Observers<Br><b>Project:</b> $Project<br><b>Description:</b> $Description<br><b>Telescope: </b>";
		if($Telescope==""){
			echo"<font color=\"#ff0000\">No telescope given</font></td>";
		}else{
			echo"$Telescope) ".$scopename[$ScopeNum]."</td>";
		}
		//Schedule
		echo"<td>";
		if($SkyCondition!=""){
			echo"<b>Sky Condition:</b> $SkyCondition<br>";
		}
			
		if($Moon!=""){echo"<b>Moon</b><br>";
			if($MoonWidth!="" && $MoonDistance!=""){
				echo"Width: $MoonWidth Distance: $MoonDistance";
			}
			echo"<br>";
		}
		
		if($TimeRange!=""){echo"<b>TimeRange</b><br>";
			if($Earliest!="" && $Latest!=""){
				
				$Latestunix = strtotime ($Latest);
				$Earliestunix = strtotime ($Earliest);
				
				$timediff = $Latestunix-$Earliestunix;
				
				//echo "$Latestunix $Earliestunix $timediff<br>";
				
				
				if($Latestunix<time()){
					$timeWarning++;$errorlevel++;
					echo"<b>Earliest</b>: $Earliest<br><font color=\"#ff0000\"><b>Latest</b>: $Latest</font>";
				}else if($timediff<86400){
					$timeDiffWarning++;
					echo"<font color=\"#dd8800\"><b>Earliest</b>: $Earliest<br><b>Latest</b>: $Latest</font>";
				}else{
					echo"<b>Earliest</b>: $Earliest<br><b>Latest</b>: $Latest";
				}
			}
			echo"<br>";
		}

		if($Reason!=""){
			echo"<b>Plan repeats:</b> Every <b>".substr($Reason,8)."</b> days<br>";
			$repeatwarning++;
			$repeattime=substr($Reason,8);
			$repeatend = date("d F Y", $Latestunix);
		}else{
			$repeatwarning=0;
		}
		
		echo"</td>";
		echo"<td>";
		//Targets
		for ($j = 0; $j < $targets; $j++) {
			
			$Name = $Target[$j]->Name;			
			
			if(isset($Target[$j]->OrbitalElements)){
				$elements=$Target[$j]->OrbitalElements;
				$mpc=1;
			}else{
				$mpc=0;
				$RightAscension = $Target[$j]->Coordinates->RightAscension;
				$Declination = $Target[$j]->Coordinates->Declination;
				$RAh=((float) $RightAscension)/15;
			}
			
			
			$Picture = $Target[$j]->Picture;
			$pictures=count($Picture);
			$p=0;
			
			for($k=0; $k<$pictures; $k++){
				$p+=$Picture[$k]->attributes()->count;
			}
			
			$targetexp=0;
			
			if($Telescope==""){
				$dlower=-90;
				$dupper=90;
				$MinBinning=0;
			}
			
			if($j>0){echo"<br><br>";}
			
			
			if($mpc==1){
				echo"<b>Name: </b>$Name<br>MP/Comet elements:  (<b>".$elements."</b>)<br>Image sets: $pictures Total exposures: $p<br>";
			}elseif($Declination<$dlower || $Declination>$dupper){
				$dwarning++;
				$errorlevel++;
				echo"<b>Name: $Name <font color=\"#ff0000\">(RA: $RightAscension Dec: $Declination)</font></b><br>Image sets: $pictures Total exposures: $p<br>";
			}else{
				echo"<b>Name: $Name (RA: $RightAscension Dec: $Declination)</b><br>Image sets: $pictures Total exposures: $p<br>";
			}
			echo"<table border=\"0\" class=\"\"><tr><th>Filter</th><th>Exposure</th><th>Binning</th></tr>";
			for($k=0; $k<$pictures; $k++){
				
				$count=$Picture[$k]->attributes()->count;
				$ExposureTime=$Picture[$k]->ExposureTime;
				$Binning=$Picture[$k]->Binning;
				$Filter=$Picture[$k]->Filter;
				
				
				echo"<tr><td border=\"0\">";
				$filternow=0;
				for($g=0; $g<count($filterlist); $g++){
				
					if($Filter==$filterlist[$g]){
						$filternow=1;
					}
															
				}
															
				if($filternow==0){
					echo"<font color=\"#ff0000\"><b>$Filter </b>(not available)</font>";
					$filtererror++;
					$errorlevel++;
				}else{
					echo $Filter;
				}
				
				
				echo"</td><td>";
				
				if($count>1){
					echo "$count x ";
				}else{
					$countWarning++;
					echo "<font color=\"#dd8800\"><b>$count x </font>";
				}
				
				echo $ExposureTime."s</td><td>";
				if($Telescope==9 && $Binning<4){
					$binningwarningASI++;
					$errorlevel++;
					echo"<font color=\"#ff0000\"><b>".$Binning."x$Binning</b></font>";
				}else if($Binning<$MinBinning){
					$binningwarning++;
					
					echo"<font color=\"#dd8800\"><b>".$Binning."x$Binning</b></font>";
				}else{
					echo $Binning."x$Binning";
				}
				
				echo"</td></tr>";
				$totalexp+=($ExposureTime*$count);
				$planexp+=($ExposureTime*$count);
				$targetexp+=($ExposureTime*$count);
			}
			echo"</table>";
			if($planexp>14400){
				$errorlevel++;
				$planlengtherror=2;
				echo"Exposure on target: <font color=\"#ff0000\">".format_seconds($targetexp)."</font>";
			}elseif($planexp>7200){
				if($planlengtherror==0){$planlengtherror=1;}
				echo"Exposure on target: <font color=\"#dd8800\">".format_seconds($targetexp)."</font>";
			}else{
				echo"Exposure on target: ".format_seconds($targetexp);
			}
		
		}
		echo"</td>";
		
		
		echo"</tr>";
	}

	echo"</table>";

	echo"<script language=\"Javascript\">document.getElementById(\"summary\").innerHTML = '<b>Number of plans: </b>".$plans." <b>Total exposure:</b> ".format_seconds($totalexp)."<br>';</script>";
	echo"<script language=\"Javascript\">document.getElementById(\"warning\").innerHTML = '";
		
	if($ScopeStatus==1){
		echo"<font color=\"#dd8800\">- Warning:</font> The selected telescope is currently undergoing maintenance or testing and may take some time to complete your observations.<br></font>";
	}
	if($ScopeStatus==0){
		echo"<font color=\"#ff0000\">- Warning:</font> The selected telescope is not currently running automated. If you submit a plan to it without prior consultation with observatory staff it will likely be rejected.<br></font>";
	}
	
	if($binningwarning>0){
		echo"<font color=\"#dd8800\">- Warning: </font>$binningwarning image set";
		if($binningwarning>1){echo"s";}echo" use";if($binningwarning==1){echo"s";}
		echo" a binning level below the recommended minimum. (You may need to take your own calibration frames if required)<br></font>";
	}
	
	if($binningwarningASI>0){
		echo"<font color=\"#ff0000\">- Error: </font>The ASI6200 camera may only be used with 4x4 binning<br></font>";
	}
	
	if($countWarning){
		echo"<font color=\"#dd8800\">- Warning: </font>It is advisable to take at least two images for each observation<br></font>";
	}
	if($planlengtherror==1){
		echo"<font color=\"#dd8800\">- Warning:</font> It is advised you keep plans below 2 hours in length, split up long plans so they have more chance of completing uninterrupted<br></font>";
	}else if($planlengtherror==2){
		echo"<font color=\"#ff0000\">- Error: </font>Single plans may not exceed 4 hours in length<br></font>";
	}
	if($dwarning>0){
		echo"<font color=\"#ff0000\">- Error: </font>$dwarning target";
		if($dwarning>1){echo"s are";}else{echo" is";} echo" outside the declination limits of the selected telescope<br></font>";
	}
	
	if($repeatwarning>0){
		echo"<font color=\"#dd8800\"><b>Warning:</b> </font> One or more of your plans is set to repeat <b>every $repeattime days</b> until $repeatend.</font> <b>Please ensure this is necessary and is justified by your plan description.</b><br>";
	}
	
	if($timeDiffWarning>0){
		echo"<font color=\"#dd8800\"><b>Warning:</b> </font> One or more of your plans has a starting time range that covers less than 1 day. <a href=\"https://observatory.herts.ac.uk/wiki/Guide:Queued_observing_system#Constraints\" target=\"blank\">Do not try to schedule observations yourself</a> using this constraint, unless your observation is time-critical.<br>";
	}
		
	if($projblank>0){
		echo"<font color=\"#ff0000\">- Error: </font>Project name cannot be blank<br>";
	}
		
	if($desblank>0){
		echo"<font color=\"#ff0000\">- Error: </font>Project description cannot be blank<br></font>";
	}
	
	if($filtererror>0){
		echo"<font color=\"#ff0000\">- Error: </font>You have requested filters $filtererror times which are not available on this telescope<br></font>";
	}
	
	if($timeWarning>0){
		echo"<font color=\"#ff0000\">- Error: </font>Latest start time must be in the future<br></font>";
	}
	


	if($errorlevel>0){
		echo"$errorlist";
	}

	

}else{
	echo"File not found";

}

}
?>
