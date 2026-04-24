<?php

require_once('../mHeader.php');
require_once('../mTop.php');


if($displayPage){

echo"<div ><div style=\" width:800px; margin:0px auto;\">";


if(isset($_GET['target']) && isset($_GET['project'])){
$project=$_GET['project'];
$target=$_GET['target'];

//if(isset($_GET['user'])){$user=$_GET['user'];}else{$user=$userid;}

		$query="SELECT * FROM images WHERE `observerid`=$userid AND `project`=\"$project\" AND `target`=\"$target\"";//BINARY 
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);
		if($rows==0){
			echo"This project doesn't seem to have any images";
		}else{
			//echo"$rows images found in project $project<br>";
			$targets=array();
			
			echo"<br><b>$rows</b> images found of <b>$target</b> in project <b>$project</b><br><br><table class=\"small\" style=\"width:75%;\"><tr><th>Image ID</th><th>Filter</th><th>Exp. time</th><th>Date/time</th><th>Telescope</th>";
			
			while($row = mysqli_fetch_array($result)){ 
					$filter=$row['filter'];
					$exptime=$row['exptime'];
					$binning=$row['binning'];
					$telescope=$row['telescope'];
					$dbid=$row['dbid'];
					$imgid=$row['imgid'];
					$jd=$row['jd'];
					$solved=$row['solved'];
							echo"<tr><td>$dbid &nbsp;&nbsp;&nbsp;<a href=\"imagedetails.php?id=".$dbid."\">View image</a></td><td>$filter</td><td>".round($exptime,1)."s</td><td>".date("Y-M-d H:i:s", ($jd - 2440587.5)*86400)."<td>".$scopename[$telescope]."</td></tr>";
			}
			echo"</table>";
			//print_r($projects);
				
			
		}
		
}else{
echo"No project details supplied<br><br>";
}
echo"</div><br></div>";
}
require_once('../mFooter.php');


?>