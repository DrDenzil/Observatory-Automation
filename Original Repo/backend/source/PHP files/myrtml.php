<?php


require_once('../mHeader.php');
if($displayPage){


?>
<link rel="stylesheet" type="text/css" href="css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="js/jquery-1.11.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="js/jquery.dataTables.min.js"></script>
<script>
$(document).ready( function () {


    $('#searchresults').DataTable( {
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
		"lengthMenu": [ [15, 25, 50, 100, -1], [15, 25, 50, 100, "All"] ],
		"order": [[ 0, "desc" ]],
		"language": {
			"info": "Showing _START_ to _END_ of _TOTAL_ results",
			"lengthMenu": "Show _MENU_ results",
			"search": "Filter results:"
		}
    } );

} );

</script>

<?php
}
require_once('../mTop.php');

if($displayPage){

echo"<div ><center>";

$statuslist[0]="Not submitted";
$statuslist[-1]="<font color=\"#ff0000\">Cancelled</font>";
$statuslist[1]="<font color=\"#ff6600\">Pending review</font>";
$statuslist[2]="Approved <font color=\"#00cc00\">&#10004</font>";
$statuslist[-2]="<font color=\"#ff0000\">Not approved</font>";

//$userid=25;
	$query="SELECT * FROM rtml WHERE `userid`=$userid  ORDER BY `id` DESC";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
	$rows=mysqli_num_rows($result);
	if($rows==0){
		echo"You don't seem to have uploaded anything yet";
	}else{
		echo"<br><table id=\"searchresults\">
		<thead><tr><th>ID</th><th>Filename / Project</th><th>Telescope</th><th>Plans</th><th>Total<br>Exposure</th><th>Date<br>Uploaded</th><th>Comments</th><th>Status</th></tr></thead>";
		
		while($row = mysqli_fetch_array($result)){ 
			//if($row['status']!=-1 && $row['status']!=0){
				$id=$row['id'];
				if($row['origname']=="RTMLEditor.rtml" || $row['origname']=="PlanGenerator.rtml"){
					$name="Plan Generator";
				}else{
					$name=$row['origname'];
				}
				$exp=$row['totalexp'];
				$time=$row['time'];
				
				if(file_exists("rtml/json/".$id.".json")){
					$jsonrl = "<a href=\"reloadplan.php?id=$id\">- Load into plan generator</a><br>";
				}else{
					$jsonrl="";
				}
				
				echo "<tr><td data-order=\"$id\">$id<br><a href=\"rtmlread.php?id=$id\">View details</a></td><td>$name<br>". $row['project']."</td><td>". $row['telescope']."- ".$scopename[$row['telescope']]."</td><td>". $row['plans']."</td><td data-order=\"$exp\">".format_seconds($exp)."</td><td data-order=\"$time\">".date("Y-M-d", $time)."</td><td>";
				

				
				if($row['status']==1){
					echo"&nbsp;</td><td><form action=\"rtmlconfirm.php\" method=\"post\"><input type=\"hidden\" name=\"rtmlID\" value=\"$id\"><input type=\"hidden\" name=\"dowhat\" value=\"-1\">". $statuslist[$row['status']]." <input type=\"submit\" value=\"Cancel\" ></form>";
				}elseif($row['status']==2){
					echo"&nbsp;</td><td><form action=\"rtmlconfirm.php\" method=\"post\"><input type=\"hidden\" name=\"rtmlID\" value=\"$id\"><input type=\"hidden\" name=\"dowhat\" value=\"1\">". $statuslist[$row['status']]." <input type=\"submit\" value=\"Resubmit\" ></form>";
				}elseif($row['status']==-1){
					echo"&nbsp;</td><td>";
					echo $statuslist[$row['status']];
				}elseif($row['status']==0){
					echo"&nbsp;</td><td><form action=\"rtmlconfirm.php\" method=\"post\"><input type=\"hidden\" name=\"rtmlID\" value=\"$id\"><input type=\"hidden\" name=\"dowhat\" value=\"1\">". $statuslist[$row['status']]." <input type=\"submit\" value=\"Submit\" ></form>";
				}else{
					
					
					$result2 = mysqli_query($link, "SELECT `reason` FROM `rejection` WHERE `rtmlid`=$id LIMIT 1") or die(mysqli_error($link)); 
					$row2 = mysqli_fetch_array($result2);
					$reason = $row2['reason'];
					 
					echo "$reason</td><td>";
					
					echo $statuslist[$row['status']];
					
					
				}
				echo"<br><br>$jsonrl<br>
				<a href=\"dlrtml.php?rtmlid=$id&name=".$row['project']."\">- Download RTML</a>
				</td></tr>"; //<img src=\"images/download.png\">
			//}
		}
		echo"</table>";
	}
	
echo"</center></div><br>";


}
require_once('../mFooter.php');



?>