<?php

require_once("constants.php");

header('Content-type: text/html; charset=utf-8'); 




error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once("database.php");
$link = mysqli_connect('localhost',$username,$password, $database);
require_once('config.php');



$timeout=0;
$level=0;
$validated=0;	

if($authenticated){
	$query="SELECT `level`, `name`, `surname`, `user` FROM users WHERE `user`=$userid LIMIT 1";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 

	$numrows=mysqli_num_rows($result);
	
	if($numrows<1){
		//not registered
		$validated=0;	
	}else{
		
		$row = mysqli_fetch_array($result);
			
		$level = $row['level'];
		$loggedinname=$row['name']." ".$row['surname'];
		
		$userid=$row['user'];
		
		if($level>0){
			$validated=1;	
			$_SESSION['userid']=$userid;
		}else{
			//account not confirmed
			$validated=0;	
			
		}
	}
		
	
}




if(isset($_SESSION['validated'])){
	if($_SESSION['validated']){
		$validated=1;
		$userid=;
				
		$query="SELECT `level`, `name` FROM users WHERE `user`=$userid LIMIT 1";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		
		while($row = mysqli_fetch_array($result)){ 
			$level = $row['level'];
			$loggedinname=$row['name'];
		}
	}else{
		$validated=0;
	}
}else{
	$validated=0;
}

echo"<!DOCTYPE html> <html><head>
<link rel=\"stylesheet\" type=\"text/css\" href=\"css/layout.css\" media=\"screen\" />
<link rel=\"stylesheet\" type=\"text/css\" href=\"css/default.css\" media=\"screen\" />
<title>$title - Bayfordbury Observatory</title>";
?>
