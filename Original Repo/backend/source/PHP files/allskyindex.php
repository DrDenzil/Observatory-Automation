<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$links[0]['link']="index.php"; $links[0]['name']="Home";
$links[1]['link']="weather.php"; $links[1]['name']="Weather";


include('cname.php'); 


$title = "UH AllSky Cameras - Latest Image (".$cname[$camera].")";

require_once('../bayfordbury/mHeader.php');



$lat      = 51.7763;
$long     = -0.0963;

$JDE=time()/86400+2440587.5;


$T            = ($JDE - 2451545.0) / 36525;
$L0pre        = 280.46646 + ($T * (36000.76983 + (0.0003032 * $T))); //The geometric mean longitude of the Sun
$L0           = quad($L0pre); //Get into the range 0-360 degrees
$Mpre         = 357.52911 + ($T * (35999.05029 + (0.0001537 * $T))); //The mean anomoly of the Sun
$M            = quad($Mpre); //Get into the range 0-360 degrees
$e            = 0.016708634 - ($T * (0.000042037 + (0.0000001267 * $T))); //The eccentricity of the Earth's orbit
$C            = +((1.914602 - ($T * (0.004817 + (0.000014 * $T)))) * (sin(deg2rad($M)))) //The Sun's equation of centre
	+ ((0.019993 - ($T * 0.000101)) * (sin(deg2rad(2 * $M)))) + 0.000289 * (sin(deg2rad(3 * $M)));
$Slon         = $L0 + $C; //The Sun's true longitude
$v            = $M + $C; //The Sun's true anomoly
$R            = (1.000001018 * (1 - ($e * $e))) / (1 + ($e * (cos(deg2rad($v))))); //Distance between the Sun and Earth
$omega        = 125.04452 - (1934.136261 * $T) + (0.0020708 * $T * $T) + (($T * $T * $T) / 450000);
$L            = 280.4665 + (36000.7698 * $T);
$Ldash        = 218.3165 + (481267.8813 * $T);
$deltaepsilon = (((9.20 / 3600) * (cos(deg2rad($omega)))) + ((0.57 / 3600) * (cos(deg2rad(2 * $L)))) + ((0.10 / 3600) * (cos(deg2rad(2 * $Ldash)))) - ((0.09 / 3600) * (cos(deg2rad(2 * $omega))))); //The mean obliquity to the ecliptic
$epsilon0     = 23.4392911111 - (0.013004166667 * $T) - ((1.63889 / 10000000) * $T * $T) + ($T * $T * $T * (5.03611 / 10000000));
$epsilon      = $epsilon0 + $deltaepsilon; //The true obliquity to the ecliptic
$ra           = rad2deg(atan2(((cos(deg2rad($epsilon))) * (sin(deg2rad($Slon)))), (cos(deg2rad($Slon))))); //The right ascension of the Sun
$dec          = asin((sin(deg2rad($epsilon))) * (sin(deg2rad($Slon)))); //The declination of the Sun
$theta        = 280.46061837 + (360.98564736629 * ($JDE - 2451545)) + (0.000387933 * $T * $T) - (($T * $T * $T) / 38710000); //The sidereal time at Greenwich
$H            = deg2rad(quad($theta + $long - $ra));
$latrad       = deg2rad($lat);
$sunaltnow       = number_format(rad2deg(asin(((sin($latrad)) * (sin($dec))) + ((cos($latrad)) * (cos($dec)) * (cos($H))))), 1);

if (isset($_GET['c'])) {
	$camera = numonly($_GET['c']);
} elseif($sunaltnow<0){
	$camera = 1;
}else{
	$camera = 7;
}


?>


<?php
require_once('../bayfordbury/mTop.php');

mysqli_select_db ($link , "allsky");

echo "<center>";

//<option value="sqmzoo2|cloud">Calculated clouds</option>

if($camera==7){
	#######DAYTIME CAMERA
	
	
	
	if (gmdate('G') > 20) {
		$yday   = date("d");
		$ymonth = date("m");
		$yyear  = date("Y");
	} else {
		$yday   = date("d", strtotime("-1 day"));
		$ymonth = date("m", strtotime("-1 day"));
		$yyear  = date("Y", strtotime("-1 day"));
	}
	//$vidname = "camera" . $camera . "/videos/$yyear-$ymonth-$yday.flv";
	$vidname2 = "camera" . $camera . "/videos/$yyear-$ymonth-$yday.mp4";
	
	if ($sunaltnow<0 && file_exists($vidname2)){
		##nighttime, showing yesterday's video
		
		if ($ymonth > 2) {
			$M2 = $ymonth;
			$Y  = $yyear;
		} else {
			$M2 = ($ymonth + 12);
			$Y  = $yyear - 1;
		}
		$A      = floor($Y / 100);
		$B      = 2 - $A + floor($A / 4);
		$vidjde = round((floor(365.25 * ($Y + 4716))) + (floor(30.6001 * ($M2 + 1))) + $yday + $B - 1524.5 - 2455000, 0);
		//echo "vidjde: $vidjde<br>";
		$query  = "
		SELECT time, number, ABS( time - $vidjde ) AS distance FROM (
			(
				SELECT time, number
				FROM `images" . $camera . "`
				WHERE time >=$vidjde
				ORDER BY time
				LIMIT 1
			) UNION ALL (
				SELECT time, number
				FROM `images" . $camera . "`
				WHERE time < $vidjde
				ORDER BY time DESC
				LIMIT 1
			)
		) AS n
		ORDER BY distance
		LIMIT 1
		";
		
		$result = mysqli_query($link, $query);
		while ($row = mysqli_fetch_array($result)){ 
			$distance = $row['distance'] * 24;
			$num      = $row['number'];
			$time     = $row['time'];
		}
		
		
		
		
		
			
		
			echo"<video width=\"640\" height=\"480\" controls ";
			  if($distance <9){
				 // echo "d$distance";
					
					
					$fileno = sprintf("%09d", $num);
			
					$file = "camera" . $camera . "/" . JDEtoDir($time + 2455000.5) . "/AllSkyImage" . $fileno . ".JPG";	
		
		
				  echo "poster=\"$file\"";
				  
			  }
				echo ">
			  <source src=\"$vidname2\" type=\"video/mp4\" >Your browser does not support the video tag.
			</video>";

		
		
		
		
	}else{
		//day, show live
		
		
		
		$query = "SELECT * FROM images" . $camera . " ORDER BY time DESC LIMIT 1";
		$result = mysqli_query($link, $query);// or die(mysql_error());
		while ($row = mysqli_fetch_array($result)) {
			$time1  = $row['time'];
			$number = $row['number'];
			
			$exp = $row['exp'];
		}
		
		$lastexp = 1000;
	
		
		
		$longno          = sprintf("%09d", $number);
		$JDE4            = $time1 + 2455000+($exp/86400);
		
		
		$latestimagepath = "camera" . $camera . "/" . JDEtoDir($JDE4 + 0.5) . "/AllSkyImage" . $longno . ".JPG";
		
		
		echo "<img id=\"live\" src=\"$latestimagepath\">";
		
	}
	
}else{
	
	
	if (gmdate('G') > 20) {
		$yday   = date("d");
		$ymonth = date("m");
		$yyear  = date("Y");
	} else {
		$yday   = date("d", strtotime("-1 day"));
		$ymonth = date("m", strtotime("-1 day"));
		$yyear  = date("Y", strtotime("-1 day"));
	}
	//$vidname = "camera" . $camera . "/videos/$yyear-$ymonth-$yday.flv";
	$vidname2 = "camera" . $camera . "/videos/$yyear-$ymonth-$yday.mp4";
	
	if ($sunaltnow>0 && file_exists($vidname2)){
		##nighttime, showing yesterday's video
		
		if ($ymonth > 2) {
			$M2 = $ymonth;
			$Y  = $yyear;
		} else {
			$M2 = ($ymonth + 12);
			$Y  = $yyear - 1;
		}
		$A      = floor($Y / 100);
		$B      = 2 - $A + floor($A / 4);
		$vidjde = round((floor(365.25 * ($Y + 4716))) + (floor(30.6001 * ($M2 + 1))) + $yday + $B - 1524.5 - 2455000, 0) + 0.5;
		//echo "vidjde: $vidjde<br>";
		$query  = "
		SELECT time, number, ABS( time - $vidjde ) AS distance FROM (
			(
				SELECT time, number
				FROM `images" . $camera . "`
				WHERE time >=$vidjde
				ORDER BY time
				LIMIT 1
			) UNION ALL (
				SELECT time, number
				FROM `images" . $camera . "`
				WHERE time < $vidjde
				ORDER BY time DESC
				LIMIT 1
			)
		) AS n
		ORDER BY distance
		LIMIT 1
		";
		
		$result = mysqli_query($link, $query);
		while ($row = mysqli_fetch_array($result)){ 
			$distance = $row['distance'] * 24;
			$num      = $row['number'];
			$time     = $row['time'];
		}
		
		
		
		
		
			
		
			echo"<video width=\"640\" height=\"480\" controls ";
			  if($distance <9){
				 // echo "d$distance";
					
					
					$fileno = sprintf("%09d", $num);
			
					$file   = "camera" . $camera . "/" . JDEtoDir($time + 2455000) . "/AllSkyImage" . $fileno . ".JPG";
		
		
				  echo "poster=\"$file\"";
				  
			  }
				echo ">
			  <source src=\"$vidname2\" type=\"video/mp4\" >Your browser does not support the video tag.
			</video>";

		
		
		
		
	}else{
		//day, show live
		
		
		
		$query = "SELECT * FROM images" . $camera . " ORDER BY time DESC LIMIT 1";
		$result = mysqli_query($link, $query);// or die(mysql_error());
		while ($row = mysqli_fetch_array($result)) {
			$time1  = $row['time'];
			$number = $row['number'];
			
			$exp = $row['exp'];
		}
		
		$lastexp = 1000;
	
		
		
		$longno          = sprintf("%09d", $number);
		$JDE4            = $time1 + 2455000+($exp/86400);
		
		
		$latestimagepath = "camera" . $camera . "/" . JDEtoDir($JDE4) . "/AllSkyImage" . $longno . ".JPG";
		
		
		echo "<img id=\"live\" src=\"$latestimagepath\">";
		
	}
	
	
	
	
}





?>
</center>

<?php

require_once('../bayfordbury/mFooter.php');
