<?php

$title="";

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
	

if(isset($_GET['type'])){
	$type=$_GET['type'];
}else{
	$type=1;
}

echo"<div style=\" width:90%; margin:0px auto;\"><br>";



		if($type==1){
			echo"<table width=\"500\"><tr><td align=\"center\"><b>Pending approval</b></td><td align=\"center\"><a href=\"allrtml.php?type=2\">Approved</a></td><td align=\"center\"><a href=\"allrtml.php?type=-2\">Rejected</a></td><td align=\"center\"><a href=\"allrtml.php?type=-1\">Cancelled</a></td></tr></table><br>";
		}elseif($type==2){
			echo"<table width=\"500\"><tr><td align=\"center\"><a href=\"allrtml.php?type=1\">Pending approval</a></td><td align=\"center\"><b>Approved</b></td><td align=\"center\"><a href=\"allrtml.php?type=-2\">Rejected</a></td></tr></table><br>";
		}elseif($type==0){
			echo"<table width=\"500\"><tr><td align=\"center\"><a href=\"allrtml.php?type=1\">Pending approval</a></td><td align=\"center\"><a href=\"allrtml.php?type=2\">Approved</a></td><td align=\"center\"><a href=\"allrtml.php?type=-2\">Rejected</a></td></tr></table><br>";
		}elseif($type==-1){
			echo"<table width=\"500\"><tr><td align=\"center\"><a href=\"allrtml.php?type=1\">Pending approval</a></td><td align=\"center\"><a href=\"allrtml.php?type=2\">Approved</a></td><td align=\"center\"><b>Rejected</b></td></tr></table><br>";
		}elseif($type==-2){
			echo"<table width=\"500\"><tr><td align=\"center\"><a href=\"allrtml.php?type=1\">Pending approval</a></td><td align=\"center\"><a href=\"allrtml.php?type=2\">Approved</a></td><td align=\"center\"><b>Rejected</a></td></tr></table><br>";
		}


$query="SELECT * FROM `rtml` WHERE status=$type ORDER BY `time`";
if($type==1){$query.=" ASC";}else{$query.=" DESC";}

		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);
			if($type==1){
				echo"$rows RTML uploads waiting approval";
			}elseif($type==2){
				echo"$rows approved plans found";
			}elseif($type==0){
				echo"$rows non-submitted plans found";
			}elseif($type==-1){
				echo"$rows cancelled plans found";
			}elseif($type==-2){
				echo"$rows rejected plans found";
			}

		
		if($rows>0){
			echo"</div><table  style=\"width:100%;\" id=\"searchresults\"><thead><tr><th>ID</th><th>User Name</th><th>Project</th><th>Description</th><th>Telescope</th><th>Plans</th><th>Total exposure</th><th>Upload time</th>";
			if($type==-2){echo"<th>Rejection reason</th>";}
			if($type==1){echo"<th>Submit to queue</th>";}
			echo"</tr></thead>";
			
			
			
		
			while($row = mysqli_fetch_array($result)){ 
				 $id = $row['id'];
				 $userid = $row['userid'];
				 $time = $row['time'];
				 unset($row2);
				
				if($type==-2){
					$result2 = mysqli_query($link, "SELECT `reason` FROM `rejection` WHERE `rtmlid`=$id LIMIT 1") or die(mysqli_error($link)); 
					 while($row2 = mysqli_fetch_array($result2)){ 
						$reason = $row2['reason'];
					 }
				}
				
				 $result2 = mysqli_query($link, "SELECT * FROM `users` WHERE user=$userid LIMIT 1") or die(mysqli_error($link)); 
				 while($row2 = mysqli_fetch_array($result2)){ 
					$name = $row2['name']." ".$row2['surname'];
				 }
				
				 //print_r($result2);
				 
				 $status = $row['status'];
				 
				 $origname = $row['origname'];
				 $project = $row['project'];
				 $prjdes  = $row['prjdes'];
				 $telescope = $row['telescope'];
				 $plans = $row['plans'];
				 $totalexp = $row['totalexp'];
				 echo"<tr><td><a href=\"rtmlread.php?id=$id\">$id</a></td><td>$name</td><td>$project</td><td>$prjdes</td><td>".$scopename[$telescope]."</td><td>$plans</td><td data-order=\"$totalexp\">".format_seconds($totalexp)."</td><td data-order=\"$time\">".date("Y-M-d", $time)."</td>";
				 if($type==-2){echo"<td>$reason</td>";}
				 if($type==1){echo"<td><form action=\"ekosjobsubmit.php\" method=\"GET\"><input type=\"hidden\" name=\"id\" value=\"$id\">Priority: <input type=\"text\" name=\"priority\" size=\"1\" value=\"1\"><br><input type=\"submit\" value=\"Queue for EKOS\" onload=\"this.disabled=false;\" onclick=\"this.disabled = true;this.value='Sending...';this.form.submit();\"\"></form><form action=\"rtmlreject1.php\" method=\"POST\"><input type=\"hidden\" name=\"id\" value=\"$id\"><input type=\"submit\" value=\"Reject\"></form>
				 
				 
				 </td>";}
				 echo"</tr>";
				 
				
			}	
		}else{
			

		}

			echo"</table>";

}



require_once('../mFooter.php');

?>