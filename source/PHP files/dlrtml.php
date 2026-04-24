<?php 
session_start();
ini_set('display_errors', 1);


	 
	// *Lots* of security in here...
	

require_once("../constants.php");


require_once("/www/bayfordbury/private/db.php");
$link = mysqli_connect('localhost',$dbUser,$dbPassword, $dbDb);

if($authenticated){
	$query="SELECT `level`,  `user` FROM `users` WHERE `uhid`='$uhid' LIMIT 1";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 

	$idnumrows=mysqli_num_rows($result);
	
	
	
	if($idnumrows>0){
		
		$row = mysqli_fetch_array($result);
		
		$level=$row['level'];
		
		$userid=$row['user'];
		if($level>0){
			$validated=true;
		}else{
			$validated=false;
		}	
		
	}else{
		$validated=false;
	}

	
}
	
if(isset($_GET['rtmlid'])){
	if(is_numeric($_GET['rtmlid'])){
		$rtmlid = $_GET['rtmlid']+0;
		$filename="rtml/".$rtmlid.".rtml";
	}else{
		$filename="rtml/editor/".$userid.".rtml";
	}
}else{
	$filename="rtml/editor/".$userid.".rtml";
}

$basename=$_GET['name'].".rtml";

if($validated){

	if(file_exists($filename)){
	 
	 $file=$filename;
	 

		
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
		header('Content-Length: ' . filesize($file));
		header('Connection: close');
		ob_clean();
		flush();
		readfile($file);
		exit; 
		
	}else{
		echo"File not found";
	}
	
}else{
	echo "not logged in";
}
	   

?>
