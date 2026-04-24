<?php 


require_once('../mHeader.php');
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
require_once('../mTop.php');



if($displayPage){


echo"<center><br>";


if(isset($_GET['new'])){
	echo"<table width=\"500\"><tr><td align=\"center\"><a href=\"accounts.php\">Active accounts</a></td><td align=\"center\"><b>Inactive accounts</b></td></tr></table><br>";


	$query="SELECT * FROM users WHERE level=0 ORDER BY `time` DESC";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
	if(mysqli_num_rows($result) < 1){
		echo"No users in request list<br>";
	}else{

		while($row = mysqli_fetch_array($result)){ 
			$id=  $row['user'];
			$name=  $row['name'];
			$surname=  $row['surname'];
			$email=$row['email'];

			$time=$row['time'];

			$type=$row['type'];


			echo"<table style=\"border-width:thin; border-color:#666666; border-style:solid\" width=600>
			<tr><td><b>Name:</b> $name $surname
			<br><b>Email:</b> $email
			<br><b>Date registered:</b> ".date("F j Y", $time)."
			
			</td><td  width=200 align=\"right\" valign=\"top\"><a href=\"activateuser.php?id=$id\">Approve</a></td></table><br>";

		}
	}

	echo"</center>";



}else{

	echo"<table width=\"500\"><tr><td align=\"center\"><b>Active accounts</b></td><td align=\"center\"><a href=\"accounts.php?new=1\">Inactive accounts</a></td></tr></table><br>";


	$query="SELECT * FROM users WHERE `user`>0 AND `type` <>'Left' ORDER BY `time` DESC";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 

	if(mysqli_num_rows($result) < 1){
		echo"ERROR: no valid users<br>";
	}else{
		echo"<table  id=\"searchresults\"><thead><tr><th>User id</th><th>Name</th><th>Email</th><th>Date registered</th><th>Account level</th><th>User type</th><th>Options</th></tr></thead>";
		while($row = mysqli_fetch_array($result)){ 
			$name=  $row['name'];
			$surname = $row['surname'];
			$email=$row['email'];
			$type=$row['type'];
			$time=$row['time'];
			$id=  $row['user'];

			if($row['level']==0){
				$level1="Inactive user";
			}elseif($row['level']==1){
				$level1="Student";
			}elseif($row['level']==2){
				$level1="Staff";
			}elseif($row['level']==5){
				$level1="Supervisor";
			}elseif($row['level']>8){
				$level1="Administrator";
			}

			echo"
			<tr><td>$id</td><td>$name $surname</td><td>$email</td><td data-order=\"$time\">".date("F j Y", $time)."</td><td>$level1</td><td>$type
			</td>
			<td></td>			
			</tr>";

		}
		echo"</table>";
	}

	

}
}
require_once('../mFooter.php');

?>