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
        var Bmin = parseFloat( $('#Bmin').val() );
        var Bmax = parseFloat( $('#Bmax').val());
		
        var Vmin = parseFloat( $('#Vmin').val());
        var Vmax = parseFloat( $('#Vmax').val());
		
		var Rmin = parseFloat( $('#Rmin').val());
        var Rmax = parseFloat( $('#Rmax').val());
		
		var Imin = parseFloat( $('#Imin').val());
        var Imax = parseFloat( $('#Imax').val());
		
		var Jmin = parseFloat( $('#Jmin').val());
        var Jmax = parseFloat( $('#Jmax').val());
		
		var Kmin = parseFloat( $('#Kmin').val());
        var Kmax = parseFloat( $('#Kmax').val());
		
		var ramin = parseFloat( $('#ramin').val());
        var ramax = parseFloat( $('#ramax').val());
		
		var decmin = parseFloat( $('#decmin').val());
        var decmax = parseFloat( $('#decmax').val());
		
        var ra = parseFloat( data[1] ) || 0;
        var dec = parseFloat( data[2] ) || 0;
        var B = parseFloat( data[4] ) || 0;
        var V = parseFloat( data[5] ) || 0;
        var R = parseFloat( data[6] ) || 0;
        var I = parseFloat( data[7] ) || 0;
        var J = parseFloat( data[8] ) || 0;
        var K = parseFloat( data[9] ) || 0;
 
        if ( (( isNaN( Bmin ) && isNaN( Bmax ) ) ||
             ( isNaN( Bmin ) && B <= Bmax ) ||
             ( Bmin <= B   && isNaN( Bmax ) ) ||
             ( Bmin <= B   && B <= Bmax ) )
			&&
			(( isNaN( Vmin ) && isNaN( Vmax ) ) ||
             ( isNaN( Vmin ) && V <= Vmax ) ||
             ( Vmin <= V   && isNaN( Vmax ) ) ||
             ( Vmin <= V   && V <= Vmax )
			)
			&&
			(( isNaN( Rmin ) && isNaN( Rmax ) ) ||
             ( isNaN( Rmin ) && R <= Rmax ) ||
             ( Rmin <= R   && isNaN( Rmax ) ) ||
             ( Rmin <= R   && R <= Rmax )
			)
			&&
			(( isNaN( Imin ) && isNaN( Imax ) ) ||
             ( isNaN( Imin ) && I <= Imax ) ||
             ( Imin <= I   && isNaN( Imax ) ) ||
             ( Imin <= I   && I <= Imax )
			)
			&&
			(( isNaN( Jmin ) && isNaN( Jmax ) ) ||
             ( isNaN( Jmin ) && J <= Jmax ) ||
             ( Jmin <= J   && isNaN( Jmax ) ) ||
             ( Jmin <= J   && J <= Jmax )
			)
			&&
			(( isNaN( Kmin ) && isNaN( Kmax ) ) ||
             ( isNaN( Kmin ) && K <= Kmax ) ||
             ( Kmin <= K   && isNaN( Kmax ) ) ||
             ( Kmin <= K   && K <= Kmax )
			)
			&&
			(( isNaN( ramin ) && isNaN( ramax ) ) ||
             ( isNaN( ramin ) && ra <= ramax ) ||
             ( ramin <= ra   && isNaN( ramax ) ) ||
             ( ramin <= ra   && ra <= ramax )
			)
			&&
			(( isNaN( decmin ) && isNaN( decmax ) ) ||
             ( isNaN( decmin ) && dec <= decmax ) ||
             ( decmin <= dec   && isNaN( decmax ) ) ||
             ( decmin <= dec   && dec <= decmax )
			)

			 )
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
	
	$('#Bmin, #Bmax, #Vmin, #Vmax, #Rmin, #Rmax, #Imin, #Imax, #Jmin, #Jmax, #Kmin, #Kmax, #ramin, #ramax, #decmin, #decmax').keyup( function() {
        table.draw();
    } );

} );

</script>

<?php
}
require_once('../mTop.php');


if($displayPage){



?><br><table><tr>
<td>B Mag min: </td><td><input id="Bmin" type="text" size=3 value=0></td>
<td>V Mag min: </td><td><input id="Vmin" type="text" size=3 value=0></td>
<td>R Mag min: </td><td><input id="Rmin" type="text" size=3 value=0></td>
<td>I Mag min: </td><td><input id="Imin" type="text" size=3 value=0></td>
<td>J Mag min: </td><td><input id="Jmin" type="text" size=3 value=0></td>
<td>K Mag min: </td><td><input id="Kmin" type="text" size=3 value=0></td>
<td>RA min: </td><td><input id="ramin" type="text" size=3 value=0>&deg;</td>
<td>Dec min: </td><td><input id="decmin" type="text" size=3 value=-37>&deg;</td></tr>

<tr>
<td>B Mag max: </td><td><input id="Bmax" type="text" size=3 value=20></td>
<td>V Mag max: </td><td><input id="Vmax" type="text" size=3 value=20></td>
<td>R Mag max: </td><td><input id="Rmax" type="text" size=3 value=20></td>
<td>I Mag max: </td><td><input id="Imax" type="text" size=3 value=20></td>
<td>J Mag max: </td><td><input id="Jmax" type="text" size=3 value=20></td>
<td>K Mag max: </td><td><input id="Kmax" type="text" size=3 value=20></td>
<td>RA max: </td><td><input id="ramax" type="text" size=3 value=360>&deg;</td>
<td>Dec max: </td><td><input id="decmax" type="text" size=3 value=90>&deg;</td>

</tr></table><br>
Source: <a href="https://ui.adsabs.harvard.edu/abs/2011AJ....142..138L/abstract" target="_blank">An All-sky Catalog of Bright M Dwarfs </a>
<br>
<?php
	echo"<center>";

	$query="SELECT * FROM `mdwarfs` WHERE `dec`>-35";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 

//


	echo"<table  style=\"width:80%\"  id=\"searchresults\"><thead><tr><th>Source name</th><th>Right <br>Ascension</th><th>Declination</th><th>Spectral<br>Type</th><th>B</th><th>V</th><th>R</th><th>I</th><th>J</th><th>K</th><th>Simbad<br>Identifier</th></thead>";
	while($row = mysqli_fetch_array($result)){ 
		$name=  $row['name'];
		$ra=$row['ra'];;
		$dec=$row['dec'];;
		$B=$row['B'];
		$V=$row['V'];
		$R=$row['R'];
		$I=$row['I'];
		$J=$row['J'];
		$K=$row['K'];
		$type=strtoupper($row['type']);
		$cns3=$row['cns3'];
		$simbad=$row['simbad'];
		
		$period=1;
		echo"<tr><td>$name</td><td data-order=\"$ra\">$ra &deg;<br>(".hms_hh($ra/15)."h ".hms_dm($ra/15)."m ".hms_ds($ra/15)."s)</td><td data-order=\"$dec\">$dec &deg;<br>(".hms_dd($dec)."&deg; ".hms_dm($dec)."' ".hms_ds($dec)."\")</td><td>$type</td>
		<td>$B</td><td>$V</td><td>$R</td><td>$I</td><td>$J</td><td>$K</td><td>$simbad</td></tr>";

	}
	echo"</table>";
	

	


}
require_once('../mFooter.php');

?>