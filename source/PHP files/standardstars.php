<?php 


require_once('../mHeader.php');
if($displayPage){
?>
<link rel="stylesheet" type="text/css" href="css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="js/jquery-1.11.0.min.js"></script>
<script type="text/javascript" charset="utf8" src="js/jquery.dataTables.min.js"></script>
<script>

$.fn.dataTable.ext.search.push(
    function( settings, data, dataIndex ) {
        var min = parseFloat( $('#min').val() );
        var max = parseFloat( $('#max').val());
        var v = parseFloat( data[4] ) || 0; // 
 
        if ( ( isNaN( min ) && isNaN( max ) ) ||
             ( isNaN( min ) && v <= max ) ||
             ( min <= v   && isNaN( max ) ) ||
             ( min <= v   && v <= max ) )
        {
            return true;
        }
        return false;
    }
);


$(document).ready( function () {
	
	
	

    var table = $('#searchresults').DataTable( {
        "dom": '<"top"if>rt<"bottom"lp><"clear">',
		"lengthMenu": [ [15, 25, 50, 100, -1], [15, 25, 50, 100, "All"] ],
		"language": {
			"info": "Showing _START_ to _END_ of _TOTAL_ results",
			"lengthMenu": "Show _MENU_ results",
			"search": "Filter results:"
		}
    } );
	
	$('#min, #max').keyup( function() {
        table.draw();
    } );

} );

</script>

<?php
}
require_once('../mTop.php');


if($displayPage){


?><div>
<table style="width:100%"><tr><td>
<form action="standardstars.php" method="post" autocomplete="on">

<table class="bordered" width="400">
<tr>
	<td align="left" colspan="3"><b>Search radius: </b><input type="text" name="dist"  size="5"  <?php 
	if(isset($_POST['dist'])){
		if( $_POST['dist']!=""){
			echo "value = \"". $_POST['dist']."\"";
		}else{
			echo "value=\"15\"";
		}
	}else{
		echo "value=\"15\"";
	}?>> degrees</td>
</tr>
<tr>
	<td>Around coordinates <br></td>
	<td align="right"><b>RA:</b></td>
	<td align="left"> <input type="text" name="ra" size="15" <?php 
	if(isset($_POST['ra'])){
		if( $_POST['ra']!=""){
			echo "value = \"". $_POST['ra']."\"";
		}
	}?>> decimal degs</td>
	
</tr>
<tr> 
	<td>&nbsp;</td>
	<td align="right"><b>Dec:</b></td>
	<td align="left"> <input type="text"  name="dec" size="15" <?php 
	if(isset($_POST['dec'])){
		if( $_POST['dec']!=""){
			echo "value = \"". $_POST['dec']."\"";
		}
	}?>> decimal degs</td>
</tr>
<tr>
	<td align="left" colspan="3">Or</td>
</tr>
<tr>
	<td align="right" colspan="2"><b>Target name: </b></td>
	<td align="left"><input type="text" name="target" <?php 
	if(isset($_POST['target'])){
		if( $_POST['target']!=""){
			echo "value = \"". $_POST['target']."\"";
		}
	}?>
		></td>
</tr>
<tr><td><input type="submit" value="Submit"></td><td> &nbsp;</td></tr>
</table></form>

</td><td>
Sources:<br>
<a href="https://ui.adsabs.harvard.edu/abs/2016AJ....152...91C" target="_blank">Clem 2016</a><br>
<a href="https://ui.adsabs.harvard.edu/abs/2000A%26AS..146..169G" target="_blank">Galadi 2000</a><br>
<a href="https://ui.adsabs.harvard.edu/abs/2009AJ....137.4186L" target="_blank">Landolt 2009</a><br>
<a href="https://ui.adsabs.harvard.edu/abs/2013AJ....146..131L" target="_blank">Landolt 2013</a><br>
<Br>
Brightest V magnitude: <input id="min" name="min" type="text" size=3 value=7><br>Dimmest V magnitude: <input id="max" name="max" type="text" size=3 value=19>


</td></tr></table>
<?php
	echo"<center>";
	
	$constrained=false;
		
	if(isset($_POST['target'])){
		if( $_POST['target']!=""){
			$targetname=$_POST['target'];
			
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

					$query="SELECT *, DEGREES(ACOS(SIN(RADIANS(`dec`))*SIN(RADIANS($cdec))+COS(RADIANS(`dec`))*COS(RADIANS($cdec))*COS(RADIANS(`ra`-$cra)))) AS distance FROM catalogues.standards WHERE ((`ra` > $ra1 AND `ra` < $ra2) OR (`ra` > $ra3 AND `ra` < $ra4) ) AND`dec` > $dec1 AND `dec` < $dec2	ORDER BY distance ASC";

					echo"Search for centre coordinates within <b>".round($dist,2)."&deg;</b> of <b>$targetname</b> (R.A. <b>".hms_hh($cra/15)."h ".hms_dm($cra/15)."m ".hms_ds($cra/15)."s</b>  Dec. <b>".hms_dd($cdec)."&deg; ".hms_dm($cdec)."' ".hms_ds($cdec)."\"</b>)<br>";
					$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
					$rows=mysqli_num_rows($result);
					$x=1;			
					$constrained=true;

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

						$query="SELECT *, DEGREES(ACOS(SIN(RADIANS(`dec`))*SIN(RADIANS($cdec))+COS(RADIANS(`dec`))*COS(RADIANS($cdec))*COS(RADIANS(`ra`-$cra)))) AS distance FROM catalogues.standards WHERE ((`ra` > $ra1 AND `ra` < $ra2) OR (`ra` > $ra3 AND `ra` < $ra4) ) AND`dec` > $dec1 AND `dec` < $dec2 ORDER BY distance ASC";

						echo"Search for centre coordinates within <b>".round($dist,2)."&deg;</b> of <b>$targetname</b> (R.A. <b>".hms_hh($cra/15)."h ".hms_dm($cra/15)."m ".hms_ds($cra/15)."s</b>  Dec. <b>".hms_dd($cdec)."&deg; ".hms_dm($cdec)."' ".hms_ds($cdec)."\"</b>)<br>";
						$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
						$rows=mysqli_num_rows($result);
						$x=1;
						$constrained=true;
						
					}
				}
				
				
			}
			
			
			
			/*}else{
				$query="SELECT * FROM `images` WHERE REPLACE(target, ' ', '') = REPLACE('$targetname', ' ', '') $constraints ORDER BY `jd` DESC";
				$x=0;
				echo"Search for target name: <b>$targetname</b><br>";
				$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
						$rows=mysqli_num_rows($result);
			}*/
		}
	}
	
	if(isset($_POST['ra']) && isset($_POST['dec']) && !$constrained){
		if($_POST['ra']!="" && $_POST['dec']!="" && $_POST['dist']!=""){
			
		//coords entered
			
			$cra=$_POST['ra'];
		
			$cdec=$_POST['dec'];
		
			$dist=$_POST['dist'];
			
			$ra1=$cra-$dist;
			$ra2=$cra+$dist;
			$dec1=$cdec-$dist;
			$dec2=$cdec+$dist;

			
			$ra3=$ra1;
			$ra4=$ra2;
			
			if($ra1<0){$ra3=$ra1+360; $ra4=360; $ra1=0;}
			if($ra2>360){$ra3=0; $ra4=$ra2-360; $ra2=0;}

			$query="SELECT * FROM (SELECT *, DEGREES(ACOS(SIN(RADIANS(`dec`))*SIN(RADIANS($cdec))+COS(RADIANS(`dec`))*COS(RADIANS($cdec))*COS(RADIANS(`ra`-$cra)))) AS distance FROM catalogues.standards WHERE ((`ra` > $ra1 AND `ra` < $ra2) OR (`ra` > $ra3 AND `ra` < $ra4) ) AND`dec` > $dec1 AND `dec` < $dec2  ORDER BY distance ASC) AS new WHERE distance<=$dist";
			$x=1;

			echo"Search for centre coordinates within <b>".round($dist,2)."&deg;</b> of R.A. <b>".hms_hh($cra/15)."h ".hms_dm($cra/15)."m ".hms_ds($cra/15)."s</b>  Dec. <b>".hms_dd($cdec)."&deg; ".hms_dm($cdec)."' ".hms_ds($cdec)."\"</b><br>";
			$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			$rows=mysqli_num_rows($result);
			$x=1;
			$constrained=true;
		}
	}
	//echo "c> $constrained row> $rows";
	if(!$constrained){

		$constrained=false;

		 $query="SELECT * FROM catalogues.standards";
		 $result = mysqli_query($link, $query) or die(mysqli_error($link)); 
	}

	echo"<table style=\"width:90%\" id=\"searchresults\"><thead><tr><th>Right Ascension
	 </th><th>Declination</th><th>Name</th><th>B</th><th>V</th><th>R</th><th>I</th><th>Alt. name</th><th>Source</th>";
	 if($constrained){echo "<th>Declination distance &deg;</th><th>Total Distance  &deg;</th>";}
	 echo"</tr></thead>";
	while($row = mysqli_fetch_array($result)){ 
		$ra=  $row['ra'];
		$dec=$row['dec'];
		$name=$row['name'];
		$u=$row['U'];
		$b=$row['B'];
		if($b=="0.000"){
			$b="";
		}
		$v=$row['V'];
		$r=$row['R'];
		$i=$row['I'];
		$name2=$row['simbad'];
		$source=$row['source'];

		echo"
		<tr><td data-order=\"$ra\">$ra &deg;<br>(".hms_hh($ra/15)."h ".hms_dm($ra/15)."m ".hms_ds($ra/15)."s)</td><td data-order=\"$dec\">$dec &deg;<br>(".hms_dd($dec)."&deg; ".hms_dm($dec)."' ".hms_ds($dec)."\")</td><td>$name</td>
		<td>$b</td><td>$v</td><td>$r</td><td>$i</td>
		
		<td>$name2</td><td>$source</td>";
		if($constrained){
			
			$distance=round($row['distance'],2);
			echo "<td>".round(abs($cdec-$dec),2)."</td>";
			echo "<td>$distance</td>";
		
		}
		echo"</tr>";

	}
	echo"</table>";
	

	

}

require_once('../mFooter.php');

?>