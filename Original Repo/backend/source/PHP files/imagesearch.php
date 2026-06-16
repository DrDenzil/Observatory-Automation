<?php

require_once('../mHeader.php');

?>
<script type="text/javascript">

    function toggle_visibility(id) {
       var e = document.getElementById(id);
       if(e.style.display == 'block')
          e.style.display = 'none';
       else
          e.style.display = 'block';
    }

</script>
<?php
require_once('../mTop.php');
if($displayPage){
?>
<div style=" width:800px; margin:0px auto;">
<form action="searchresults.php" method="post" autocomplete="on">

<table class="bordered" width="360">
<tr>
	<td align="left" colspan="3"><b>Search radius: </b><input type="text" name="dist"  size="5" value="0.25"> degrees</td>
</tr>
<tr>
	<td>Around coordinates <br></td>
	<td align="right"><b>RA:</b></td>
	<td align="left"> <input type="text" name="ra" size="15" placeholder="e.g. 123.45"> degrees</td>
	
</tr>
<tr> 
	<td>&nbsp;</td>
	<td align="right"><b>Dec:</b></td>
	<td align="left"> <input type="text"  name="dec" size="15" placeholder="e.g. -5.13"> degrees</td>
</tr>
<tr>
	<td align="left" colspan="3">Or</td>
</tr>
<tr>
	<td align="right" colspan="2"><b>Resolve target name: </b></td>
	<td align="left"><input type="text" name="target" placeholder="e.g. M42"></td>
</tr>
<tr>
	<td colspan="3"><input type="checkbox" name="solved" checked>Must be plate solved <a href="https://observatory.herts.ac.uk/wiki/Plate_Solving" target="_blank" class="q"></a></td>
</tr>
</table>
<br>

<table class="bordered" width="360">
<tr>
<td colspan="2"><b>Telescope:</b> <select name="telescope">
<option value="-1">Any</option>

<?php
for($i=1; $i<($numscopes+1); $i++){
	echo"<option value=\"$i\">$i - ".$scopename[$i]."</option>";

}

?>

</select><td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr>
	<td width="100"><b>Exposure time:</b></td>
	<td align="left">min: <input type="text" name="minexp" size="4" value="0"> secs </td></tr>
	<tr><td>&nbsp;</td><td>max: <input type="text" name="maxexp" size="4" value="900"> secs</td>

</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr>
	<td ><b>Date range:</b></td>
	<td align="left">min: <input type="text" name="mintime" size="10" value="2455824.0"> (julian) </td></tr>
	<tr><td>&nbsp;</td><td>max: <input type="text" name="maxtime" size="10" value="<?php echo ceil(time()/86400+ 2440587.5);?>.0"> (julian)</td>

</tr>
</table>
<br>

<table class="bordered" width="360">
<tr><td>
<b>Filters:</b><br>
<input type="checkbox" name="f_I" checked>I <input type="checkbox" name="f_R" checked>R <input type="checkbox" name="f_V" checked>V <input type="checkbox" name="f_B" checked>B<br>
<input type="checkbox" name="f_Red" checked>Red <input type="checkbox" name="f_Green" checked>Green <input type="checkbox" name="f_Blue" checked>Blue<br>
<input type="checkbox" name="f_Ha" checked>H-alpha <input type="checkbox" name="f_OIII" checked>O-III <input type="checkbox" name="f_SII" checked>S-II <input type="checkbox" name="f_465" checked>465nm<br>
<input type="checkbox" name="f_C" checked>Clear
</td></tr></table>


<br><input type="submit" name="submit" value="Search" style="position: relative; left: 270px;"  >
</form>
<br>
<a href="#" onclick="toggle_visibility('dbid_search');">[+] Advanced search</a>

<table class="bordered" width="360" id="dbid_search" style="display:none";><form action="imagedetails.php" method="get" autocomplete="on">
<tr><td>


<b>Find image by DBID: </b></td><td><input type="text" name="id" ></td></tr><tr><td>&nbsp;</td><td>
 <input type="submit" value="Search by ID">
</form></table></td></tr></div>

<?php
}
require_once('../mFooter.php');

?>