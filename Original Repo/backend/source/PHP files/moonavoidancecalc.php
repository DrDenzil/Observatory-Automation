<?php
require_once('../mHeader.php');
require_once('../mTop.php');
if($displayPage){

?>
<script>
function roundNumber(num, dec) {
	var result = Math.round(num*Math.pow(10,dec))/Math.pow(10,dec);
	return result;
}
function recalc()
{


		var distance = document.getElementById('distance');
		var width = document.getElementById("width");
		var d, w;

		d = distance.options[distance.selectedIndex].value
		w = width.options[width.selectedIndex].value
	
	var outtable="<table class=\"big\" ><tr><th>Age from full</th><th>Phase</th><th>Avoidance</th></tr>";
	
		
		
		
	for(i=0; i<15; i++){	
		outtable=outtable+"<tr><td>"+i+" days</td><td>"+roundNumber(((14-i)/14)*100,1)+"&#37;</td><td>"+roundNumber(d/(1+(i/w)*(i/w)),1)+"&deg;</td></tr>";
	}	
	outtable=outtable+="</table>";
	
	document.getElementById('moontable').innerHTML=outtable;	
}

</script>
<br>The Moon Avoidance Lorentzian is an algorithm used by the robotic telescope to decide if a target should be observed depending how close to and how bright the moon is.<br>The brighter the moon, the further the angular separation to the target for it to be observed.
<br>You can try different values below to see how avoidance changes with the phase of the moon.<br>Default values of 60 degrees and 6 days work well in most cases.<br><br>
<b>Distance</b>
<select id="distance" name="distance"  onChange="recalc()" >
<?php
for($i=1; $i<180; $i++){
echo"<option value=\"$i\" ";
if($i==60){echo"selected";}
echo">$i</option>";
}
?>
</select> degrees
 &nbsp; &nbsp; &nbsp;
<b>Width:</b>
<select id="width" name="width"  onChange="recalc()" >
<?php
for($i=1; $i<100; $i++){
echo"<option value=\"$i\" ";
if($i==6){echo"selected";}
echo">$i</option>";
}
?>
</select> days
<br><br>
<div id="moontable"><table class="big">
   <tr><th>Age from full</th><th>Phase</th><th>Avoidance</th></tr>
   <?php
   for($i=0; $i<15; $i++){
	echo "<tr><td>$i days</td><td>".round(((14-$i)/14)*100,1)."&#37;</td><td>".round(60/(1+($i/6)*($i/6)),1)."&deg;</td></tr>";
   }
   ?>
   </table>
	</div>

	
	
<?php
}
require_once('../mFooter.php');

?>