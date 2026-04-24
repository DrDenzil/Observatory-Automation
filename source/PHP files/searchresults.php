<?php

header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
header("Cache-Control: post-check=0, pre-check=0",false);
session_cache_limiter("must-revalidate");

require_once('../mHeader.php');
if($displayPage){

?>
<link rel="stylesheet" type="text/css" href="css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="js/jquery-1.11.2.min.js"></script>
<script type="text/javascript" charset="utf8" src="js/jquery.dataTables-1.10.4.min.js"></script>
<script>
	
$(document).ready( function () {


    $('#searchresults').DataTable( {
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
		"lengthMenu": [ [15, 25, 50, 100, -1], [15, 25, 50, 100, "All"] ],
		"language": {
			"info": "Showing _START_ to _END_ of _TOTAL_ results",
			"lengthMenu": "Show _MENU_ results",
			"search": "Filter results:"
		},
		"order": [[ 8, "asc" ]],
		"deferRender": true
    } );

} );

</script>

<?php
}
require_once('../mTop.php');

if($displayPage){

	
	if(isset($_POST['submit'])){

		//echo "|".$_POST['coordsearch']."|";
		$targetname=$_POST['target'];



		###constraints
		$constraints="";
		//exp time

		if($_POST['minexp']!=""){$constraints.="AND `exptime` >= ".$_POST['minexp']." ";}
		if($_POST['maxexp']!=""){$constraints.="AND `exptime` <= ".$_POST['maxexp']." ";}

		//time
		if($_POST['mintime']!=""){$constraints.="AND `jd` >= ".$_POST['mintime']." ";}
		if($_POST['maxtime']!=""){$constraints.="AND `jd` <= ".$_POST['maxtime']." ";}

		//telescope
		if($_POST['mintime']!=""){
			if($_POST['telescope']>0){
				$constraints.="AND `telescope` = ".$_POST['telescope']." ";
			}
		}	
		//filters

		$constraints.="AND (`filter`='nothing' ";
		if(isset($_POST['f_I'])){$constraints.="OR `filter`='I' ";}
		if(isset($_POST['f_R'])){$constraints.="OR `filter`='R' ";}
		if(isset($_POST['f_V'])){$constraints.="OR `filter`='V' ";}
		if(isset($_POST['f_B'])){$constraints.="OR `filter`='B' ";}
		if(isset($_POST['f_Red'])){$constraints.="OR `filter`='Red' ";}
		if(isset($_POST['f_Green'])){$constraints.="OR `filter`='Green' ";}
		if(isset($_POST['f_Blue'])){$constraints.="OR `filter`='Blue' ";}
		if(isset($_POST['f_Ha'])){$constraints.="OR `filter`='H-alpha' ";}
		if(isset($_POST['f_OIII'])){$constraints.="OR `filter`='O-III' ";}
		if(isset($_POST['f_SII'])){$constraints.="OR `filter`='S-II' ";}
		if(isset($_POST['f_465'])){$constraints.="OR `filter`='465nm' ";}
		if(isset($_POST['f_C'])){$constraints.="OR `filter`='Clear' ";}
		$constraints.=") ";
		//plate solved
		if(isset($_POST['solved'])){$constraints.="AND `solved` =1 ";}


		if($targetname!=""){
		//Target entered

			//if($_POST['coordsearch']=="yes" && $_POST['dist']!=""){

			$found=0;
			$x=0;

			
			if(preg_match("/^[Mm]\s?\d{1,3}$/", $targetname)){
				//messier object
				
				$mnum=substr(str_replace(" ","",$targetname),1);
				if($mnum<111){
					//include("database.php");

					//$link2 = mysqli_connect('localhost',$username,$password, "catalogues");
					
					$result = mysqli_query($link, "SELECT * FROM `messier` WHERE `id`=$mnum");
					
					$row = mysqli_fetch_array($result);
												
					$found=1;	

					$cra=$row['ra']*15;
					$cdec=$row['dec'];

					$dist=$_POST['dist'];
					$ra1=$cra-$dist;
					$ra2=$cra+$dist;
					$dec1=$cdec-$dist;
					$dec2=$cdec+$dist;
					
					$ra3=$ra1;
					$ra4=$ra2;
					
					if($ra1<0){$ra3=$ra1+360; $ra4=360; $ra1=0;}
					if($ra2>360){$ra3=0; $ra4=$ra2-360; $ra2=0;}
				
					
					$query="SELECT * FROM (SELECT *, DEGREES(ACOS(SIN(RADIANS(`dec`))*SIN(RADIANS($cdec))+COS(RADIANS(`dec`))*COS(RADIANS($cdec))*COS(RADIANS(`ra`-$cra)))) AS distance FROM `images`
					WHERE ((`ra` > $ra1 AND `ra` < $ra2) OR (`ra` > $ra3 AND `ra` < $ra4) ) AND`dec` > $dec1 AND `dec` < $dec2 $constraints  ORDER BY distance ASC) AS new WHERE distance<=$dist";
					
					echo"Search for centre coordinates within <b>".round($dist,2)."&deg;</b> of <b>$targetname</b> (R.A. <b>".hms_hh($cra/15)."h ".hms_dm($cra/15)."m ".hms_ds($cra/15)."s</b>  Dec. <b>".hms_dd($cdec)."&deg; ".hms_dm($cdec)."' ".hms_ds($cdec)."\"</b>)<br>";
					$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
					$rows=mysqli_num_rows($result);
					$x=1;			
					

					//mysqli_close($link2);
				}
			}	

			
				
			if(!$found){

				$url="http://simbad.u-strasbg.fr/simbad/sim-script?script=output%20console=off%20script=off%0Aformat%20object%20%22%25COO%28d;%20A%20D;FK5;J2000;%29%22%0A";
			
				$timeout=5;

				$url.=urlencode($targetname);

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_FAILONERROR, false);
				curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
					
				
				$response=curl_exec($ch);
				
				if(curl_errno($ch)){
					
					echo"Searched the Simbad database for target: \"".clean($targetname)."\"...<br>
					<b><font color=\"#FF0000\">&#x2718;</font> Could not connect to Simbad</b> (".curl_error($ch).")";
					
				}else{
				//echo $response;
					if(substr($response, 0, 7)=="::error"){				
						echo"Searched the Simbad database for target: \"".clean($targetname)."\"...<br>
						<b><font color=\"#FF0000\">&#x2718;</font> Object not found in SIMBAD database </b>";
					}elseif(substr($response, 0, 7)=="!! A pr"){
						echo"Searched the Simbad database for target: \"".clean($targetname)."\"...<br>
						<b><font color=\"#FF0000\">&#x2718;</font> SIMBAD database did not return correct format</b>";
					}elseif(substr($response, 0, 8)=="No Coord"){
						echo"Searched the Simbad database for target: \"".clean($targetname)."\"...<br>
						<b><font color=\"#FF0000\">&#x2718;</font> No coordinates returned</b>";
					}else{
						//echo $response."<br>$length";
						$parts=explode(" ", $response);
						$jsonarray['ra']=0+$parts[0];
						$jsonarray['dec']=0+$parts[1];
						
						$cra=0+$parts[0];
						$cdec=0+$parts[1];

						$dist=$_POST['dist'];
						$ra1=$cra-$dist;
						$ra2=$cra+$dist;
						$dec1=$cdec-$dist;
						$dec2=$cdec+$dist;

						$ra3=$ra1;
						$ra4=$ra2;
						
						if($ra1<0){$ra3=$ra1+360; $ra4=360; $ra1=0;}
						if($ra2>360){$ra3=0; $ra4=$ra2-360; $ra2=0;}
						

						$query="SELECT * FROM (SELECT *, DEGREES(ACOS(SIN(RADIANS(`dec`))*SIN(RADIANS($cdec))+COS(RADIANS(`dec`))*COS(RADIANS($cdec))*COS(RADIANS(`ra`-$cra)))) AS distance FROM `images`
						WHERE ((`ra` > $ra1 AND `ra` < $ra2) OR (`ra` > $ra3 AND `ra` < $ra4) ) AND`dec` > $dec1 AND `dec` < $dec2 $constraints  ORDER BY distance ASC) AS new WHERE distance<=$dist";
			
						echo"Search for centre coordinates within <b>".round($dist,2)."&deg;</b> of <b>$targetname</b> (R.A. <b>".hms_hh($cra/15)."h ".hms_dm($cra/15)."m ".hms_ds($cra/15)."s</b>  Dec. <b>".hms_dd($cdec)."&deg; ".hms_dm($cdec)."' ".hms_ds($cdec)."\"</b>)<br>";
						$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
						$rows=mysqli_num_rows($result);
						$x=1;
						
					}
				}				
				
			}
			
			
			
			//}else{
			//	$query="SELECT * FROM `images` WHERE REPLACE(target, ' ', '') = REPLACE('$targetname', ' ', '') $constraints ORDER BY `jd` DESC";
			//	$x=0;
			//	echo"Search for target name: <b>$targetname</b><br>";
			//	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			//			$rows=mysqli_num_rows($result);
			//}
		}else{
		//coords entered

		
			if($_POST['ra']==""){
				$cra=0;
			}else{
				$cra=$_POST['ra'];
			}
			if($_POST['dec']==""){
				$cdec=0;
			}else{
				$cdec=$_POST['dec'];
			}
			$dist=$_POST['dist'];
			$ra1=$cra-$dist;
			$ra2=$cra+$dist;
			$dec1=$cdec-$dist;
			$dec2=$cdec+$dist;

			$ra3=$ra1;
			$ra4=$ra2;
			
			if($ra1<0){$ra3=$ra1+360; $ra4=360; $ra1=0;}
			if($ra2>360){$ra3=0; $ra4=$ra2-360; $ra2=0;}


			//$query="SELECT *, DEGREES(ACOS(SIN(RADIANS(`dec`))*SIN(RADIANS($cdec))+COS(RADIANS(`dec`))*COS(RADIANS($cdec))*COS(RADIANS(`ra`-$cra)))) AS distance FROM `images` 
			//WHERE ((`ra` > $ra1 AND `ra` < $ra2) OR (`ra` > $ra3 AND `ra` < $ra4) ) AND`dec` > $dec1 AND `dec` < $dec2 $constraints ORDER BY distance ASC";

			$query="SELECT * FROM (SELECT *, DEGREES(ACOS(SIN(RADIANS(`dec`))*SIN(RADIANS($cdec))+COS(RADIANS(`dec`))*COS(RADIANS($cdec))*COS(RADIANS(`ra`-$cra)))) AS distance FROM `images`
			WHERE ((`ra` > $ra1 AND `ra` < $ra2) OR (`ra` > $ra3 AND `ra` < $ra4) ) AND`dec` > $dec1 AND `dec` < $dec2 $constraints  ORDER BY distance ASC) AS new WHERE distance<=$dist";
			$x=1;

			echo"Search for centre coordinates within <b>".round($dist,2)."&deg;</b> of R.A. <b>".hms_hh($cra/15)."h ".hms_dm($cra/15)."m ".hms_ds($cra/15)."s</b>  Dec. <b>".hms_dd($cdec)."&deg; ".hms_dm($cdec)."' ".hms_ds($cdec)."\"</b><br>";
			$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			$rows=mysqli_num_rows($result);
			$x=1;

		}




		$string="";

		//echo"$query <br><br>";//$rows matching images found <br><a href=\"imagesearch.php\">&lt;&lt; Search again</a> class=\"small\"
		if($x){	
			if($rows>0){

				echo"$rows matching images found <br><table  style=\"width:95%;\" id=\"searchresults\"><thead><tr><th>Image ID</th><th>Target name</th><th>R.A.</th><th>Dec.</th><th>Filter</th><th>Exp. time</th><th>Date/time</th><th>Telescope</th><th>Distance</th></tr></thead>";
				
				while($row = mysqli_fetch_array($result)){ 
					
					$uname=$row['username'];
					$project=$row['project'];
					$plan=$row['plan'];
					$targetname=$row['target'];
					$filter=$row['filter'];
					$exptime=$row['exptime'];
					$binning=$row['binning'];
					$telescope=$row['telescope'];
					$dbid=$row['dbid'];
					$imgid=$row['imgid'];
					$ra=$row['ra'];
					$dec=$row['dec'];
					$jd=$row['jd'];
					$solved=$row['solved'];
					$distance=$row['distance'];
								
					$string.="<tr ";
					//if($solved==0){echo "class=\"unsolved\"";}
					$string.="><td data-order=\"$dbid\">$dbid <br><a href=\"imagedetails.php?id=".$dbid."\">View image</a></td><td>$targetname</td>";
					
					$string.="<td data-order=\"$ra\">".hms_hh($ra/15)."h ".hms_dm($ra/15)."m ".hms_ds($ra/15)."s</td><td data-order=\"$dec\">".hms_dd($dec)."&deg; ".hms_dm($dec)."' ".hms_ds($dec)."\"</td>";
									
					$string.="<td>$filter</td>";
					$string.="<td data-order=\"$exptime\">".round($exptime,1)."s</td>";
					$string.="<td data-order=\"$jd\">".date("d M Y", ($jd - 2440587.5)*86400)."<br>".date("H:i:s", ($jd - 2440587.5)*86400)." UTC<td>".$scopename[$telescope];
					$string.="<td data-order=\"$distance\">".round($distance,2)."&deg;</td>";
					$string.="</tr>";
					
				}
				
				echo $string;
				echo"</table>";
			}else{
				echo"No results found  <a href =\"javascript:history.back()\">&lt;&lt; search again</a><br><br><br><br>";
			
			}
		}

	}else{
		echo "Nothing submitted or submission lost  <a href =\"imagesearch.php\">&lt;&lt; search again</a><br><br><br><br>";
	}	

echo"</div>";
}
require_once('../mFooter.php');

?>