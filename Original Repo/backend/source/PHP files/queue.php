<?php

require_once('../mHeader.php');
require_once('../mTop.php');

//include('control/queue/queue.php');

if($displayPage){


echo"<div ><center>";
$cols=array();
$cols[6]="00ff00";
$cols[7]="FFFF00";
$cols[8]="FFFF00";
$cols[9]="ff0000";
$cols[10]="ff0000";

$colss[6]="00ff00";
$colss[7]="0000ff";
$colss[8]="0000ff";
$colss[9]="ff0000";
$colss[10]="ff0000";


	
		//}
		for($i=1; $i<=$numscopes; $i++){
		if($i==2 || $i==3 || $i==5|| $i==6 || $i==9){
		$l=0;
			$contents=file_get_contents("control/queue/".$i.".dat");
			$lines=explode("<br>", $contents);
			$linecount=count($lines);
			//print_r($lines);
			$time=$lines[0];
			echo "<br><b>".$i.". ".$scopename[$i]."</b> (Last checked: ".date("j M G:i",$time).")<br><br>";
			

			
			echo"<table class=\"small\"><tr><th>Name</th><th>Project name</th><th>Project ID</th><th>Plans</th><th>Images</th><th>Total time</th><th><font color=\"#".$colss[6]."\">Completed</font></th><th><font color=\"#".$colss[7]."\">Pending</font></th><th><font color=\"#".$colss[8]."\">Deferred</font></th><th><font color=\"#".$colss[9]."\">Failed</font></th><th><font color=\"#".$colss[10]."\">Disabled</font></th></tr>";
			
			
			if($linecount==2){
				
				
				echo "<tr><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr></table><br>";
			}else{
				
				for($j=1; $j<$linecount; $j++){
				
					$parts=explode("|", $lines[$j]);
					if(substr($parts[0], 0, 3)=="PRJ"){
						$acpuser= $parts[2];
							
							if($acpuser=="Staff" || $acpuser=="Student"){
								$prjparts=explode("_", $parts[1]);
								
								$query="SELECT * FROM users WHERE `user`=".$prjparts[0];
								$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
								$rows=mysqli_num_rows($result);
								while($row = mysqli_fetch_array($result)){ 
								$fullname=$row['name']." ".$row['surname'];
								
								}								
									$l++;
									echo "<tr><td>$fullname</td><td><b>".$prjparts[1]."</b></td><td>".$i."_".substr($parts[0], 4)."</td><td>".$parts[3]."</td><td>".$parts[4]."</td><td>".format_seconds($parts[5])."</td>";
									
									for($g=6; $g<11; $g++){
										$bits=explode(" ", $parts[$g]);
										
										if($bits[0]=="--"){
											if($g==10){
												
												//echo"<td><font color=\"#".$cols[$g]."\">All</font></td>";
												echo"<td style=\"text-shadow: 0px 0px 5px #".$cols[$g]."\">All</td>";
											}else{
												echo"<td>--</td>";
											}
										}elseif(($bits[0]-0)<0.1){
											echo"<td>0</td>";
										}else{
											echo"<td style=\"text-shadow: 0px 0px 5px #".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</td>";
											
											//echo"<td><font color=\"#".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</font></td>";
										}
									}
									echo"</tr>";
									
							}else if($acpuser=="Robotic"){
								
								$prjparts=explode("_", $parts[1]);
								
								$query="SELECT * FROM users WHERE `user`=".$prjparts[0];
								$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
								$rows=mysqli_num_rows($result);
								while($row = mysqli_fetch_array($result)){ 
								$fullname=$row['name']." ".$row['surname'];
								
								}								
									$l++;
									echo "<tr><td>$fullname</td><td><b>".$prjparts[2]."</b></td><td>".$i."_".substr($parts[0], 4)."</td><td>".$parts[3]."</td><td>".$parts[4]."</td><td>".format_seconds($parts[5])."</td>";
									
									for($g=6; $g<11; $g++){
										$bits=explode(" ", $parts[$g]);
										
										if($bits[0]=="--"){
											if($g==10){
												
												//echo"<td><font color=\"#".$cols[$g]."\">All</font></td>";
												echo"<td style=\"text-shadow: 0px 0px 5px #".$cols[$g]."\">All</td>";
											}else{
												echo"<td>--</td>";
											}
										}elseif(($bits[0]-0)<0.1){
											echo"<td>0</td>";
										}else{
											echo"<td style=\"text-shadow: 0px 0px 5px #".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</td>";
											
											//echo"<td><font color=\"#".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</font></td>";
										}
									}
									echo"</tr>";
								
							}else{
								$l++;
								echo "<tr><td>$acpuser</td><td><b>".$parts[1]."</b></td><td>".$i."_".substr($parts[0], 4)."</td><td>".$parts[3]."</td><td>".$parts[4]."</td><td>".format_seconds($parts[5])."</td>";
									
								for($g=6; $g<11; $g++){
									$bits=explode(" ", $parts[$g]);
									
									if($bits[0]=="--"){
											if($g==10){
												
												//echo"<td><font color=\"#".$cols[$g]."\">All</font></td>";
												echo"<td style=\"text-shadow: 0px 0px 5px #".$cols[$g]."\">All</td>";
											}else{
												echo"<td>--</td>";
											}
										}elseif(($bits[0]-0)<0.1){
											echo"<td>0</td>";
										}else{
											echo"<td style=\"text-shadow: 0px 0px 5px #".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</td>";
											
											//echo"<td><font color=\"#".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</font></td>";
										}
								}
								echo"</tr>";
							}
						
					}
				}
				if($l==0){
					echo "<tr><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr></table>";
				}else{
				echo"</table><br>";
				}
			}
			}
		}

echo"</center></div>";
}
require_once('../mFooter.php');


?>