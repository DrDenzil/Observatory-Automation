<?php 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

Require_once("/www/bayfordbury/constants.php");

if($authenticated || $unip){

	 
	// *Lots* of security in here...
	
	//include("/www/bayfordbury/private/db.php");
	if(isset($_GET['id'])){
	$id=preg_replace("[^0-9]","",$_GET['id']);

	//$link = mysqli_connect('localhost',$username,$password, $database);
	$query="SELECT * FROM images WHERE dbid=$id";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$rows=mysqli_num_rows($result);
		if($rows==1){
			while($row = mysqli_fetch_array($result)){ 
				
				$uname=$row['username'];
				$project=$row['project'];
				$plan=$row['plan'];
				$target=$row['target'];
				$filter=$row['filter'];
				$exptime=$row['exptime'];
				$binning=$row['binning'];
				$telescope=$row['telescope'];
				$dbid=$row['dbid'];
				$imgid=$row['imgid'];
				$ra=$row['ra'];
				$dec=$row['dec'];
				$jd=$row['jd'];
				$observer=$row['observername'];
				$observerid=$row['observerid'];
				$uploaded=$row['uploaded'];
		}
		
		/*

		if($uname=="Robotic"){
			//This should be the main pathway now
			
			//format is userid_name_projectname, ie 5_DCampb_Messier 1
			$prjparts=explode("_", $project);
			
			if(is_numeric($prjparts[0])){
			
				$observerid=$prjparts[0];
				$prjname=$prjparts[2];
				
				//get username from uid
				$query2="SELECT * FROM `users` WHERE user=".$prjparts[0]." LIMIT 1";
				$result2 = mysqli_query($link, $query2) or die(mysqli_error($link));
				$rows=mysqli_num_rows($result2);
				
				if($rows<1){
					$dir="fits/".$uname."/".$prjparts[0]."/".$prjparts[2]."/";
				}else{
					
					//expected pathway for old images
					$row2 = mysqli_fetch_array($result2); 
					$fullname=  $row2['name']." ".$row2['surname'];
					
					//dir
					$dir="fits/".$uname."/".$fullname."/".$prjparts[2]."/";
				}
				
				if($observerid==1 || $observerid==3){
					$dir="fits/".$uname."/".$project."/".$plan."/";
				}
									
			}
			
		}else if($uname=="Student" || $uname=="Staff"){
			$prjparts=explode("_", $project);
			if(is_numeric($prjparts[0])){
				$query2="SELECT * FROM `users` WHERE user=".$prjparts[0]." LIMIT 1";
				$result2 = mysqli_query($link, $query2) or die(mysqli_error($link));
				$rows=mysqli_num_rows($result2);
				echo"$query2";
				if($rows<1){
					$dir="fits/".$uname."/".$prjparts[0]."/".$prjparts[1]."/";
					$ftpdir=$prjparts[0]."/".$prjparts[1]."/";
				}else{
					$row2 = mysqli_fetch_array($result2);
					$fullname=  $row2['name']." ".$row2['surname'];
					
					$dir="fits/".$uname."/".$fullname."/".$prjparts[1]."/";
					$ftpdir=$fullname."/".$prjparts[1]."/";
				}
			}else{
				$dir="fits/".$uname."/".$prjparts[0]."/".$prjparts[1]."/";
				$ftpdir=$prjparts[0]."/".$prjparts[1]."/";
			}
		}else{
			$dir="fits/".$uname."/".$project."/".$plan."/";
		}
	*/
	
	$prjparts=explode("_", $project);
	
	if(is_numeric($prjparts[0])){			
		if(count($prjparts)==1){
			$prjname=$project;
		}else if(count($prjparts)==2){
			$prjname=$prjparts[1];	
		}else if(count($prjparts)==3){
			$prjname=$prjparts[2];	
		}else{
			$prjname=$prjparts[2]; //fix
		}					
	}else{
		$prjname=$project;
	}
	$dir = "/www/bayfordbury/automation/fits/".$observerid."/".$prjname."/";
	$basename=$target."_".$filter."_".round($exptime,1)."s_B".$binning."_T".$telescope."_".$dbid.".fit";
	$filename=$dir.$basename;
	
	mysqli_close($link);


	if(file_exists($filename)){
		 
		 //$file=$filename;
	 
	
		
		//$mime = 'application/force-download';
		header('Content-Description: File Transfer');
		header('Pragma: public');   
		header('Expires: 0');       
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Cache-Control: private',false);
		//header('Content-Type: '.$mime);
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.$basename.'";');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . filesize($filename));
		header('Connection: close');
		ob_clean();
		flush();
		readfile($filename);
		exit; 
		
		}else{
			echo"File not found";
		}
		
		}else{
			echo "ID not found";
		}
		
}else{
echo "ID not found";
}
	   
}
?>
