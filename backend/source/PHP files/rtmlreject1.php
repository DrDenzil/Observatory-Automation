<?php



require_once('../mHeader.php');
require_once('../mTop.php');


if($displayPage){

echo"<div ><div style=\" width:800px; margin:0px auto;\">";
if(isset($_POST['id'])){


$rtmlid=$_POST['id'];

    $file_path = "rtml/".$rtmlid.".rtml";
	if(file_exists($file_path)){

		$query="SELECT * FROM `rtml` WHERE `id`=$rtmlid";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);

		if($rows>0){
		
		
			while($row = mysqli_fetch_array($result)){ 
				 
				 $userid = $row['userid'];
				 $status = $row['status'];
				 
				 //unset($row2);
				 $query2="SELECT * FROM `users` WHERE user=$userid LIMIT 1";
				 $result2 = mysqli_query($link, $query2) or die(mysqli_error($link)); 
				 
				 $row2 = mysqli_fetch_array($result2);
				 
					$useremail = $row2['email'];
					$fullname = $row2['name']." ".$row2['surname'];
				 
								 
				 $totalexp = $row['totalexp'];
				 $projectname = $row['project'];
				 $prjdes  = $row['prjdes'];
				 $telescope = $row['telescope'];
				 $plans = $row['plans'];
				 $time = $row['time'];
			}	
			
			echo"
			<br>			
			<b>Name:</b> $fullname<br>
			<b>Contact email:</b> $useremail<br>
			<br>
			<a href=\"rtmlread.php?id=$rtmlid\">View RTML</a><br>
			
			<br><b>Project:</b> $projectname<br>
			<b>Description:</b> $prjdes<br>
			<b>Number of plans:</b> $plans<br>
			<b>Total exposure time:</b> ".format_seconds($totalexp)."<br>
			<b>Telescope:</b> ".$scopename[$telescope]."<br>
			<b>Upload date:</b> ".date("j F Y", $time)."<br>
			
			
			<form action=\"rtmlreject2.php\" method=\"post\"><input type=\"hidden\" name=\"id\" value=\"$rtmlid\"><br>Reason for rejection:<br><textarea rows=\"4\" cols=\"50\" name=\"reason\"></textarea><br><br><input type=\"submit\" value=\"Reject RTML\"></form>
			";
			
			
			
			
			
		}else{
		echo"Rtml details not found";
		}
	}else{
	echo"File doesn't exist";
	}
}else{
echo"No id";
}
}


require_once('../mFooter.php');
?>
