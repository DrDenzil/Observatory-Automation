<?php
require_once('../mHeader.php');
require_once('../mTop.php');


if($displayPage){

echo"<div ><center>";

		$nameparts=explode(" ", $loggedinsurname);
		$namesmush=substr($loggedinfirstname,0,1);
		$namesmush.=substr($nameparts[count($nameparts)-1],0,5);
		$namesmush=substr($namesmush,0,6);
		$prefix=$userid."_".$namesmush."_";

if(isset($_GET['project'])){

//if(isset($_GET['user'])){$user=$_GET['user'];}else{$user=$userid;}

$project=$_GET['project'];

		$query="SELECT * FROM images WHERE `observerid`=$userid AND `project`=\"$project\"";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);
		if($rows==0){
			echo"This project doesn't seem to have any images yet";
		}else{
		//echo"$rows images found in project $project<br>";
		$targets=array();
		$targetsn=array();
		
		
		while($row = mysqli_fetch_array($result)){ 
			$target=$row['target'];
			$uname=$row['username'];
			$project=$row['project'];
			/*
			$x=0;
			for($i=0; $i<count($targets); $i++){
				if($target==$targets[$i]){
					$x=1;
				}
			}
			if($x==0){$targets[count($targets)]=$target;}
		
			*/
			
			if(isset($targets[strtolower($target)])){
				$targets[strtolower($target)]++;
			}else{
				$targets[strtolower($target)]=1;
				$targetsn[strtolower($target)]=$target;
			}
		}
		
				$query2="SELECT * FROM `users` WHERE user=$userid LIMIT 1";
				$result2 = mysqli_query($link, $query2) or die(mysqli_error($link));
				
				$row2 = mysqli_fetch_array($result2);
				$fullname=  $row2['name']. " ".$row2['surname'];
				
				
				if($unip){
					$ftpdir="<a href=\"ftp://observatory-server.herts.ac.uk/".$userid."/".str_replace($prefix,"", $project)."\">FTP folder link to this project folder</a><br>";
				}else{
					$ftpdir="";
				}
		
				
		
		
		//print_r($projects);
		$targetcount=count($targets);
		echo"<br><b>$rows</b> images across <b>$targetcount</b> targets found in project <b>'".str_replace($prefix,"", $project)."':<br>$ftpdir<br><table  class=\"small\" ><tr><th>Target name</th><th>Number of images</th></tr>";
		
		ksort($targets, SORT_STRING);
		
		foreach ($targets as $key => $val) {
			echo "<tr><td><a href=\"viewtarget.php?target=".urlencode($targetsn[$key])."&project=".urlencode($project)."\">".$targetsn[$key]."</a></td><td>$val</td></tr>";
		}
		echo"</table><br><br>";
		
			
		}	
}else{
echo"No project details supplied<br><br>";
}

echo"</div><br>";
}

require_once('../mFooter.php');


?>
