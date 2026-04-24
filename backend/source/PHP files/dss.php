<?php

Require_once("../constants.php");

if($authenticated || $unip){

ini_set('display_errors', '1');
$width=$_GET['w'];
$ra=$_GET['ra'];
$dec=$_GET['dec'];
$x=$_GET['x'];
$y=$_GET['y'];

//echo"$width $ra $dec $x $y<Br>";

if($x>75){$x=75;}
if($y>75){$y=75;}
$url = str_replace(' ', '', "http://archive.stsci.edu/cgi-bin/dss_search?ra=".$ra."&dec=".$dec."&%20equinox=J2000&height=".$y."&generation=3&width=".$x."&format=GIF");


    $im = imagecreatefromgif($url);
	   
	   //echo"http://archive.stsci.edu/cgi-bin/dss_search?ra=".$ra."&dec=".$dec."&%20equinox=J2000&%20height=".$y."&generation=3&width=".$x."&format=GIF";
$iwidth=imagesx($im);
$iheight=imagesy($im);
$height=($width/$iwidth)*$iheight;
	//echo"xy $iwidth $iheight";

	$img = imagecreatetruecolor($width, $height);
	
imagecopyresized($img, $im, 0, 0, 0, 0, $width, $height, $iwidth, $iheight);
	
header('Content-Type: image/jpeg');

imagejpeg($img, NULL, 90);
imagedestroy($img);
?>