 <?php
require_once('../mHeader.php');
require_once('../mTop.php');

if($displayPage){

echo"<center>
<table class=\"big\">
<tr><th>Name</th><th>Telescope</th><th>Camera</th><th>Filters</th><th>Declination<br>limits</th><th>Minimum binning <a href=\"https://observatory.herts.ac.uk/wiki/Binning\" target=\"_blank\" class=\"q\"></a></th><th>Status</th></tr>";
include("config.php");
//include("database.php");
$status[0]="<font color=\"#ff0000\">Not running automatically</font>";
$status[1]="<font color=\"#ff9900\">Maintenance/Testing</font>";
$status[2]="<font color=\"#00CC00\">Running automatically</font>";

//$link = mysqli_connect('localhost',$username,$password, $database);
$query="SELECT * FROM obssetup WHERE num>1 ORDER BY num ASC";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			while($row = mysqli_fetch_array($result)){ 
				$fov=explode("|", $row['fov']);
				$pixw=$row['pixw'];
			
				echo"<tr><td><b>".$row['num'].
				". ".$row['shortname'].
				"</b></td><td>".$row['telescope']."<br>Aperture: ".$row['aperture']."mm<br>Focal length: ".round($row['flength'],0)."mm".
				"</td><td>";
				if(!is_null($pixw)){
					echo $row['camera']."<br><b>FOV: </b>".round($fov[0],1)."' x ".round($fov[1],1)."'<br><b>Pixel width: </b>".$pixw."um";
				}else{
					echo"-";
				}
				echo"</td><td>";
				
				$filters=explode("|", $row['filters']);
				$numfilters=count($filters);
				for($i=0; $i<$numfilters; $i++){
				echo $filters[$i];
				if($i<3 || $i==4 || $i==5 || $i==7){if($i!=($numfilters-1)){echo ", ";}}
				#0 1 2 3
				#4 5 6
				#7 8
				if($i==3 || $i==6){
				echo"<br>";
				}
				}
				echo"</td><td>";
				
				$limits=explode("|",$row['limits']);
				echo"Lower: ".$limits[0]."&deg;<br>Upper: ".$limits[1]."&deg;</td><td>";
				$minbinning=explode("|",$row['minbinning']);
				echo $minbinning[0]."x".$minbinning[0]." </td><td>";
				echo $status[$row['status']]."<br>".$row['reason']."<br>".$row['planstogo']." plans (";
				if($row['timetogo']<3600){
				echo round($row['timetogo']/60,1)." mins";
				}else{
				echo round($row['timetogo']/3600,1)." hrs";
				}
				echo") pending</td></tr>";
				//(".$minbinning[1]."\"/pxl)
			}	
echo"</table></center><br><br>".$status[0].": Don't submit plans to this telescope without prior discussion with observatory staff<br>".$status[1].": Plans may be submitted to this telescope, but may take some time to complete, and may not always complete successfully.<br>".$status[2].": Automation working routinely and accepting plans<br>";

}

require_once('../mFooter.php');

?>