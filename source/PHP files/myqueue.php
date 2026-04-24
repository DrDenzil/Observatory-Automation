<?php





require_once('../mHeader.php');
require_once('../mTop.php');



if($displayPage){


echo"<div >
Want more details? Try the <a href=\"queuebrowser.php\">queue browser</a><br>
If you want to change or remove plans already in the queue please contact observatory staff, noting the Project ID in the table below and what changes you want to make.<br>
Can't see your plan? Plans will be removed from the queue once completed or if they reach the end time constraint. Once completed you can check <a href=\"myimages.php\">my images</a>.
<br>

<center>";
$cols=array();
$cols[6]="00aa00";
$cols[7]="ffff00";
$cols[8]="ffff00";
$cols[9]="ff0000";
$cols[10]="AA0000";

$cols[6]="000000";
$cols[7]="000000";
$cols[8]="000000";
$cols[9]="000000";
$cols[10]="000000";


		/*$query="SELECT * FROM users WHERE `user`=$userid";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);
		while($row = mysqli_fetch_array($result)){ 
			$acpuser=$row['acpuser'];
		
		}*/
		//}
		for($i=3; $i<=$numscopes; $i++){
		if($i==2 || $i==3 || $i==5|| $i==6 || $i==9){
		$l=0;
			$contents=file_get_contents("control/queue/".$i.".dat");
			$lines=explode("<br>", $contents);
			$linecount=count($lines);
			//print_r($lines);
			$time=$lines[0];
			echo "<br><b>".$i.". ".$scopename[$i]."</b> (Last checked: ".date("j M G:i",$time).")<br><br>";
			
			//echo"<table class=\"small\"><tr><th>Project name</th><th>Project ID</th><th>Plans</th><th>Images</th><th>Total time</th><th><font color=\"#00aa00\">Completed</font></th><th><font color=\"#0000FF\">Pending</font></th><th><font color=\"#0000FF\">Deferred</font></th><th><font color=\"#ff0000\">Failed</font></th><th><font color=\"#ff0000\">Disabled</font></th></tr>";

			echo"<table class=\"small\"><tr><th>Project name</th><th>Project ID</th><th>Plans</th><th>Images</th><th>Total time</th><th><font color=\"#00aa00\">Completed</font></th><th><font color=\"#0000FF\">Pending</font></th><th><font color=\"#ff0000\">Failed</font></th><th><font color=\"#AA0000\">Disabled</font></th></tr>";
			
			if($linecount==2){				
				
				//echo "<tr><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr></table><br>";
				echo "<tr><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr></table><br>";
			}else{
				
				for($j=1; $j<$linecount; $j++){
				
					$parts=explode("|", $lines[$j]);
					if(substr($parts[0], 0, 3)=="PRJ"){
						/*if($acpuser==$parts[2]){
							
							if($acpuser=="Staff" || $acpuser=="Student"){
								$prjparts=explode("_", $parts[1]);
								if($prjparts[0]==$userid){
									$l++;
									echo "<tr><td><b>".$prjparts[1]."</b></td><td>".$i."_".substr($parts[0], 4)."</td><td>".$parts[3]."</td><td>".$parts[4]."</td><td>".format_seconds($parts[5])."</td>";
									
									for($g=6; $g<11; $g++){
										$bits=explode(" ", $parts[$g]);
										
										if($bits[0]=="--"){
											if($g==10){
												echo"<td><font color=\"#".$cols[$g]."\">All</font></td>";
											}else{
												echo"<td>--</td>";
											}
										}elseif(($bits[0]-0)<0.1){
											echo"<td>0</td>";
										}else{
											echo"<td><font color=\"#".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</font></td>";
										}
									}
									echo"</tr>";
								}
							}else{
								$l++;
								echo "<tr><td><b>".$parts[1]."</b></td><td>".$i."_".substr($parts[0], 4)."</td><td>".$parts[3]."</td><td>".$parts[4]."</td><td>".format_seconds($parts[5])."</td>";
									
								for($g=6; $g<11; $g++){
									$bits=explode(" ", $parts[$g]);
																			
									if($bits[0]=="--"){
										if($g==10){
											echo"<td><font color=\"#".$cols[$g]."\">All</font></td>";
										}else{
											echo"<td>--</td>";
										}
									}elseif(($bits[0]-0)<0.1){
										echo"<td>0</td>";
									}else{
										echo"<td><font color=\"#".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</font></td>";
									}
								}
								echo"</tr>";
							}*/
						if($parts[2]=="Robotic"){
							$prjparts=explode("_", $parts[1]);
							if($prjparts[0]==$userid){
								$l++;
								echo "<tr><td><b>".$prjparts[2]."</b></td><td>".$i."_".substr($parts[0], 4)."</td><td>".$parts[3]."</td><td>".$parts[4]."</td><td>".format_seconds($parts[5])."</td>";
								
								for($g=6; $g<11; $g++){

									if($g!=8){
										$bits=explode(" ", $parts[$g]);

										if($g==7){
											$bits2=explode(" ", $parts[8]);
											if($bits[0]=="--" && $bits2[0]=="--"){
												
												echo"<td>--</td>";
											
											}else if($bits[0]=="--"){

												if( ($bits2[0]-0)<0.1){
													echo"<td>0</td>";
												}else{
													echo"<td><font color=\"#".$cols[$g]."\">".$bits2[0]." (".format_seconds($bits2[1]).")</font></td>";
												}
											}else if($bits2[0]=="--"){

												if( ($bits[0]-0)<0.1){
													echo"<td>0</td>";
												}else{
													echo"<td><font color=\"#".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</font></td>";
												}
											
											}elseif(($bits[0]-0)<0.1 && ($bits2[0]-0)<0.1){
												echo"<td>0</td>";
											}else{
												$t = $bits[0]+$bits2[0];
												$t2 = $bits[1]+$bits2[1];
												echo"<td><font color=\"#".$cols[$g]."\">".$t." (".format_seconds($t2).")</font></td>";
											}
										}else if($bits[0]=="--"){
											if($g==10){
												echo"<td><font color=\"#".$cols[$g]."\">All</font></td>";
											}else{
												echo"<td>--</td>";
											}
										}elseif(($bits[0]-0)<0.1){
											echo"<td>0</td>";
										}else{
											echo"<td><font color=\"#".$cols[$g]."\">".$bits[0]." (".format_seconds($bits[1]).")</font></td>";
										}
									}
								}
								echo"</tr>";
							}
						}
					}
				}
				if($l==0){
					//echo "<tr><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr></table>";
					echo "<tr><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td></tr></table>";
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