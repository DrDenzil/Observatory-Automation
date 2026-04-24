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
		
		

		$query="SELECT `project`, `target` FROM images";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);
	
			
			$projects=array();
			$projectTitles=array();
			$targets=array();
			while($row = mysqli_fetch_array($result)){ 
				$project=$row['project'];
				$target=$row['target'];
				/*
				$x=0;
				for($i=0; $i<count($projects); $i++){
					if($project==$projects[$i]){
						$x=1;
					}
				}
				if($x==0){
					$projects[count($projects)]=$project;
				}
				*/
				
				
				
				
				if(isset($projects[$project]) || isset($projects[strtolower($project)])){
					$projects[$project]++;
				}else{
					$projects[$project]=1;
				}
				
				if(isset($targets[$project][$target])){
					$targets[$project][$target]++;
				}else{
					$targets[$project][$target]=1;
				}
				
				
			}
			
				$query2="SELECT * FROM `users` WHERE user=$userid LIMIT 1";
				$result2 = mysqli_query($link, $query2) or die(mysqli_error($link));
					
				while($row2 = mysqli_fetch_array($result2)){ 
					$fullname=  $row2['name']." ".$row2['surname'];
					$uname=  $row2['acpuser'];
				}
			
				if($unip){
					$ftpdir="<a href=\"ftp://observatory-server.herts.ac.uk/".$userid."\">FTP folder link to your images</a><br>";
				}else{
					$ftpdir="";
				}
			
			
			$projectcount=count($projects);
			echo"<b>$rows</b> of your images found across <b>$projectcount</b> project";if($projectcount>1){echo"s";}echo":<br>$ftpdir<br><table  class=\"small\" ><tr><th>Project name</th><th>Number of targets</th><th>Number of images</th></tr>";
			//print_r($projects);
			
			
			ksort($projects, SORT_STRING);
			
			foreach ($projects as $key => $val) {
				$targetcount=count($targets[$key]);
				echo "<tr><td><a href=\"viewproject.php?project=".urlencode($key)."\">".str_replace($prefix,"", $key)."</a></td><td>$targetcount</td><td>$val</td></tr>";
			}
			echo"</table><br><br>";
		

echo"</div>";
}

require_once('../mFooter.php');


?>