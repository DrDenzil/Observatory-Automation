 <?php

if(isset($_SERVER['HTTP_REFERER'])){
	$ref=$_SERVER['HTTP_REFERER'];
	$bits=explode("/", $ref);
	$pagename=$bits[count($bits)-1];
	if($pagename=="searchresults.php"){
		$links[1]['link']="javascript: history.go(-1)"; $links[1]['name']="Back to search results";
	}
}

require_once('../mHeader.php');
require_once('../mTop.php');

if($displayPage){

echo"<center>";
if(isset($_GET['id'])){
	
	$id=preg_replace('/[^0-9]/', '', $_GET['id']);

	if($id!=""){

		$query="SELECT * FROM images WHERE dbid=$id";



		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		
		$rows=mysqli_num_rows($result);
		
		if($rows>0){
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
				$solved=$row['solved'];
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
							$dir=$uname."/".$prjparts[0]."/".$prjparts[2]."/";
						}else{
							
							//expected pathway for old images
							$row2 = mysqli_fetch_array($result2); 
							$fullname=  $row2['name']." ".$row2['surname'];
							
							//dir
							$dir=$uname."/".$fullname."/".$prjparts[2]."/";
							$ftpdir=$fullname."/".$prjparts[2]."/";
						}
						
						if($observerid==1 || $observerid==3){
							$dir=$uname."/".$project."/".$plan."/";
						}
											
					}
					
				}else if($uname=="Student" || $uname=="Staff"){
					$prjparts=explode("_", $project);
					if(is_numeric($prjparts[0])){
						$query2="SELECT * FROM `users` WHERE user=".$prjparts[0]." LIMIT 1";
						$result2 = mysqli_query($link, $query2) or die(mysqli_error($link));
						$rows=mysqli_num_rows($result2);
						if($rows<1){
							$dir=$uname."/".$prjparts[0]."/".$prjparts[1]."/";
							$ftpdir=$prjparts[0]."/".$prjparts[1]."/";
						}else{
							while($row2 = mysqli_fetch_array($result2)){ 
								$fullname=  $row2['name'];
							}
							$dir=$uname."/".$fullname."/".$prjparts[1]."/";
							$ftpdir=$fullname."/".$prjparts[1]."/";
						}
					}else{
						$dir=$uname."/".$prjparts[0]."/".$prjparts[1]."/";
						$ftpdir=$prjparts[0]."/".$prjparts[1]."/";
					}
				}else{
					$dir=$uname."/".$project."/".$plan."/";
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
	
/*
echo "user ".system('echo "$USER"')."<br>";
echo "1 ".system('ls -ald /www/bayfordbury/automation/fits')."<br>";
echo "2 ".system('ls -ald /www/bayfordbury/automation/fits/Mark\ Gallaway')."<br>";
echo "3 ".system("ls -ald /www/bayfordbury/automation/fits/_")."<br>";

$dd=$filename="/www/bayfordbury/automation";
echo "$dd ".file_exists($dd)."<br>";
$dd.="/fits";
echo "$dd ".file_exists($dd)."<br>";
$dd.="/".$uname;
echo "$dd ".file_exists($dd)."<br>";
$dd.="/".$project;
echo "$dd ".file_exists($dd)."<br>";
$dd.="/".$plan;
echo "$dd ".file_exists($dd)."<br>";
$dd.="/".$target."_".$filter."_".round($exptime,1)."s_B".$binning."_T".$telescope."_".$imgid.".fit";
echo "$dd ".file_exists($dd)."<br>";

*/
if(file_exists($filename)){

$filesize=filesize($filename)/(1024*1024);
if($in= file_get_contents($filename)){

$jpeg = "jpeg/".floor($dbid/1000)."/".$dbid.".jpg";

if(!file_exists($jpeg)){
	$jpdir="jpeg/".floor($dbid/1000);

	if(!is_dir($jpdir)){
		mkdir($jpdir, 0770, true);
	}

	//make thumbnail
	exec('/bin/convert -quality 75 -median 1 -resize 400  -normalize -brightness-contrast 60x65 -flip "'.$filename.'" -depth 16 -type Grayscale "'.$jpdir.'/'.$dbid.'.jpg"', $output);

}



if(file_exists($jpeg)){
	echo"<h1>Image preview</h1><img src=\"thumbnail.php?id=$dbid\"><br>(This is an automatically-stretched preview, and will not show all image data)";
}else{
	echo "jpeg not found $jpeg<br>";
}

$unixfile="http://homepages.herts.ac.uk/~astrodat/Images/".$dir.$target."_".$filter."_".round($exptime,1)."s_B".$binning."_T".$telescope."_".$imgid.".fit";

$filename=$target."_".$filter."_".round($exptime,1)."s_B".$binning."_T".$telescope."_".$imgid.".fit";
echo"<h1>Image Summary</h1>
<table class=\"simple\">
<tr><td class=\"left\">Image ID:</td><td>$dbid</td></tr>
<tr><td class=\"left\">Target:</td><td>$target</td></tr>
<tr><td class=\"left\">Date/time:</td><td>".date("Y-M-d H:i:s", ($jd - 2440587.5)*86400)."</td></tr>
<tr><td class=\"left\">R.A.:</td><td>".hms_hh($ra/15)."h ".hms_dm($ra/15)."m ".hms_ds($ra/15)."s";
if(!$solved){echo" (<b>not plate solved</b>)";}else{echo" (plate solved)";}
echo"</td></tr>
<tr><td class=\"left\">Dec.:</td><td>".hms_dd($dec)."&deg; ".hms_dm($dec)."' ".hms_ds($dec)."\"";
if(!$solved){echo" (<b>not plate solved</b>)";}else{echo" (plate solved)";}
echo"</td></tr>
<tr><td class=\"left\">Exposure:</td><td>".round($exptime,1)." s</td></tr>
<tr><td class=\"left\">Filter:</td><td>$filter</td></tr>
<tr><td class=\"left\">Telescope:</td><td>".$scopename[$telescope]."</td></tr>
<tr><td class=\"left\">Observer:</td><td>$observer</td></tr>
<tr><td class=\"left\">Project:</td><td>$project</td></tr>
<tr><td class=\"left\">Plan:</td><td>$plan</td></tr>
<tr><td class=\"left\">Filename:</td><td>$filename</td></tr>
<tr><td class=\"left\">Filesize:</td><td>".round($filesize,2)." MB</td></tr>
</table><a href=\"https://observatory.herts.ac.uk/allsky/imageget.php?c=1&jde=$jd&ra=$ra&dec=$dec\" target=\"_blank\">View sky condition at time of imaging</a><br><br>
";


echo"<a href=\"getfit.php?id=$dbid\">Download FITS file <img src=\"images/download.png\"></a> <br>";

if($unip){
	$ftpdir=$observerid."/".$prjname."/";
	echo"<br><a href=\"ftp://observatory-server.herts.ac.uk/images/".$ftpdir."\">Open project FTP directory</a><br>";
}
//echo"<a href=\"http://observatory-server.herts.ac.uk/getfit.php?id=$dbid\">Download from Bayfordbury server</a> (HTTP)<br>";
/*
if($uname=="Student"){
	echo"<a href=\"ftp://student@observatory-server.herts.ac.uk/".$ftpdir."\">Download from Bayfordbury server</a> (FTP)<br>";
}else if($uname=="Robotic" && $observerid>2){
	

}*/



$header=explode("END       ", $in);
$cards=str_split($header[0], 80);
$count=count($cards);
$DBID="";
echo"<br><h1>Full FITS Header</h1><table class=\"small\"><th>Key</th><th width=\"200\">Value</th><th>Comment</th>";
for($i=0; $i<$count; $i++){
	$parts=array();

	if(substr($cards[$i], 0, 7)=="HISTORY"){
		$parts[0]="HISTORY";
		$parts[1]=substr($cards[$i], 7);
	}else{
		$parts=explode("=", $cards[$i]);
	}
	//echo $cards[$i];

	
	if(count($parts)==2){
		if(isset($cards[$i+1])){
			$parts2=explode("=", $cards[$i+1]);
			if(count($parts2)==1 && substr($cards[$i+1], 0, 7)!="HISTORY"){
				$parts[1]=$parts[1].$parts2[0];
			}
		}
		
		$value = explode(" /", $parts[1]);
		
		$parts[0]=trim($parts[0]);
		
		if($parts[0]=="DBID"){
			$DBID =trim($value[0]);
		}
		
		$value[0]=trim($value[0]);
		
		if(is_numeric($value[0])){
			$value[0]=$value[0]-0;
		}else{
			$value[0]=trim($value[0], "' ");
		}
	
	
	
		if(count($value)>1){
			echo"<tr><td>".$parts[0]." </td><td>".$value[0]."</td><td>".$value[1]."</tr>";
			
		}else{
			echo"<tr><td>".$parts[0]." </td><td>".$value[0]."</td><td>&nbsp;</td></tr>";
			
		}
	}elseif(count($parts)==3){
	
		$value = explode(" /", $parts[1]);
		
		$parts[0]=trim($parts[0]);
		
	
		$value[0]=trim($value[0]);
		
		if(is_numeric($value[0])){
			$value[0]=$value[0]-0;
		}else{
			$value[0]=trim($value[0], "' ");
		}
	
	//print_r($parts);
	//print_r($value);
	
	
			echo"<tr><td>".$parts[0]." </td><td>".$value[0]."</td><td>".$value[1]."=".$parts[2]."</tr>";
			
		
	
	}
	

}
$out=$in;
echo"</table>";
}else{
	echo"Error reading: $filename ";
}
}else{
	echo"Image not found: $filename ";
}
}else{
	echo"DBID not found ";
}
}else{
	echo"DBID must be numeric";
}

}else{
	echo"No image given";
}

}
require_once('../mFooter.php');





?>
