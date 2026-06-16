<?php 
//error_reporting(E_ALL);
//ini_set('display_errors', 1);



require_once('../mHeader.php');
if($displayPage){
//require_once('top.php');
?>
<script type="text/javascript" src="js/jquery-1.9.1.min.js"></script>

<script type="text/javascript">

//time and date

function getGMT() {

	today = new Date();
	
	jd = (today.getTime()/86400000)+2440587.5;
	T = (jd-2451545)/36525;
	gst = 280.46061837+360.98564736629*(jd-2451545)+0.000387933*T*T-(T*T*T)/38710000;
	lst = (gst-0.0963)/15;
	
	while(lst>24){
		lst = lst-24;
	}
	
	while(lst<0){
		lst = lst+24;
	}
  
	lsth = Math.floor(lst);
	lstm = Math.floor((lst-lsth)*60);
	lsts = Math.floor((((lst-lsth)*60)-lstm)*60);

	exp1 = today.getUTCHours();
	exp2 = today.getUTCMinutes();
	exp3 = today.getUTCSeconds();


	exp1 = ((exp1<10) ? "0"+exp1 : exp1);
	exp2 = ((exp2<10) ? "0"+exp2 : exp2);
	exp3 = ((exp3<10) ? "0"+exp3 : exp3);

	lsth = ((lsth<10) ? "0"+lsth : lsth);
	lstm = ((lstm<10) ? "0"+lstm : lstm);
	lsts = ((lsts<10) ? "0"+lsts : lsts);


	exp = "<b>Current time:</b> "+exp1 + ":" + exp2 + ":" + exp3+" UTC<br><b>Local sidereal time:</b> "+lsth + ":" + lstm + ":" + lsts+"<br><b>Julian date: </b>"+jd.toFixed(5);

	document.getElementById('id_gmt').innerHTML = exp;

	setTimeout("getGMT()", 250);
 
}
 
function getDir(b){
   var dirs = new Array('N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'N');
   return dirs[Math.round(b/45)];
}

Date.now = Date.now || function() { return +new Date; }; 
 
//get weather
/*
function get_status(){
	var now = Date.now()/1000;

	//var response="";
	$.ajax({ 
	type: "GET",
	url: "ajax/weatherlookup.php",//?t="+Math.round(now),
	//async: true,
	cache: false,
	//timeout: 500,
	success: function(response){
			
			if(response!=""){
			
			var weather=response.split("|");
			var weather1=weather[0].split(" ");
			var weather2=weather[1].split(" ");
			var weather3=weather[2].split(" ");
			var weather4=weather[3].split(" ");
			
			var out="";
			var skyt="";
			var skyb="";
			
			if(weather1[1]=="-998."){
				weather1[1]="Sensor wet";			
			}else{
				weather1[1]=weather1[1]+"&deg;C";
			}
			
			out+='<table><tr><td><b>Temperature:</b></td><td>'+weather2[1]+'&deg;C</td></tr><tr><td><b>Humidity: </b></td><td>'+weather2[2]+'%</td></tr><tr><td><b>Dew pt:</b> </td><td>'+weather2[7]+'&deg;</td></tr><tr><td><b>Wind speed:</b> </td><td>'+weather2[3]+'kph '+getDir(weather2[4])+'</td></tr><tr><td>';
			
			skyt+='<b>Sky temperature</b><table><tr><td><b>Wide angle: </b></td><td>'+weather1[1]+'</td></tr><tr><td><b>Zenith: </b></td><td>'+(0-weather4[1]).toFixed(1)+'&deg;C</td></tr><tr><td><b>10min average: </b></td><td>'+(0-weather4[2]).toFixed(1)+'&deg;C</td></tr><tr><td><b>10min stdev: </b></td><td>'+(0-weather4[3]).toFixed(2)+'&deg;C</td></tr><tr><td><b>10min Max: </b></td><td>'+(0-weather4[4]).toFixed(1)+'&deg;C</td></tr><tr><td><b>10min Min: </b></td><td>'+(0-weather4[5]).toFixed(1)+'&deg;C</td></tr><tr><td><b>10min Diff: </b></td><td>'+(weather4[4]-weather4[5]).toFixed(1)+'&deg;C</td></tr></table';
	
			if((weather2[12]/1)>0){
				out+='<b>Rain: </td><td></b>'+weather2[12]+'mm/hr';
			}else if(weather1[2]==1){
				out+='<b>Rain: </td><td></b>in last minute';
			}else if(weather1[2]==2){
				out+='<b>Rain:</b> </td><td>Raining';
			}else if(weather1[3]==2){
				out+='<b>Rain: </b></td><td>sensor wet';
			}else if(weather1[3]==1){
				out+='<b>Rain:</b></td><td> wet in last minute';
			}else{
				out+='<b>Rain: </b></td><td>none';
			}

			out+='</td></tr><tr><td><b>Pressure: </td><td></b>'+weather2[5]+'hpa</td></tr><tr><td><b>Solar radiation:</b> </td><td>'+weather2[6]+' W/m^2';
			
				//out+= '<b>Sky brightness:</b> </td><td>'+(weather3[7]-0)+' mag\\sq\'';
			
			out+='</td></tr></table><br>';
			
			if(weather3[1]<8.1){weather3[1]=0;}
			if(weather3[2]<8.1){weather3[2]=0;}
			if(weather3[3]<8.1){weather3[3]=0;}
			if(weather3[4]<8.1){weather3[4]=0;}
			if(weather3[5]<8.1){weather3[5]=0;}
			if(weather3[6]<8.1){weather3[6]=0;}
			if(weather3[7]<6){weather3[7]=0;}
			
			var skybtime = weather1[0]-weather3[0];
			
			if((now-skybtime)<120){
			
				skyb+='<table width=150><tr><tdcolspan=2><b>Sky brightness (not calibrated)</b></td><tr><tr><td>Band</td><td>mag\\sq\"</td><td><tr><td><b>Clear</b></td><td>'+weather3[5]+'</td></tr><tr><td><b>I</b></td><td>'+weather3[1]+'</td></tr><tr><td><b>R</b></td><td>'+weather3[2]+'</td></tr><tr><td><b>V</b></td><td>'+weather3[3]+'</td></tr><tr><td><b>B</b></td><td>'+weather3[4]+'</td></tr><tr><td><b>Visual</b></td><td>'+(weather3[7]-0)+'</td></tr></table>'
			
			}
					
			document.getElementById('weather').innerHTML=out;
			document.getElementById('skytemp').innerHTML=skyt;
			document.getElementById('skybrightness').innerHTML=skyb;
			
		}else{
			//document.getElementById('weather').innerHTML="Weather server offline";
			//document.getElementById('skytemp').innerHTML="";
			//document.getElementById('skybrightness').innerHTML="";
		
		}
		setTimeout(get_status, 10000);
		},
	error: function( jqXHR, textStatus, errorThrown ){
		console.log(textStatus);
		console.log(errorThrown);
		//document.getElementById('weather').innerHTML="Weather server offline";
		//document.getElementById('skytemp').innerHTML="";
		//document.getElementById('skybrightness').innerHTML="";
		setTimeout(get_status, 10000);
		}
	});
					
	//setTimeout(get_status, 10000);		
}*/

$( document ).ready(function() {
	getGMT();
	
	//get_status();
	 	
});

</script>

<?php

}

//end of head
require_once('../mTop.php');
//start of body		


if($displayPage){		
	if($level>8){

		$query="SELECT * FROM users WHERE level=0 ORDER BY `time` DESC";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$userreqs=mysqli_num_rows($result);

		$query="SELECT * FROM `rtml` WHERE status=1";

		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$newrtml=mysqli_num_rows($result);

		if($newrtml>0){
		//if($userreqs >0 || $newrtml>0){
			//echo"<div class=\"group\" ><a href=\"accounts.php?new=1\">$userreqs Pending account requests</a><br>
			echo "<div class=\"group\" ><a href=\"allrtml.php\">$newrtml pending RTML submissions</a></div>";

		}
		
	/*
		if($debug_id =="not set"){
			echo "There was a problem retrieving your account details. Please contact observatory staff for help.";
		}else if($debug_id =="not provided"){
			echo "There was a problem retrieving your account details. Please contact observatory staff for help.";
		}*/
	}

	if($validated){
		//echo "<p style=\"border:2px; border-style:solid; border-color:#FF0000; padding: 1em;\">The CKT currently has <a href=\"obssetup.php\">significantly more observations pending</a> than other telescopes. Please request observations with other telescopes where possible, as they are likely to complete much sooner.</p><br>";
	}

	//notices for top of page

	//echo "<b>Notice: Some services may be unavailable whilst the website undergoes maintainance and upgrades 3 August - 10 August<br><br></b>";
	//echo "<b>Notice: Telescopes will not be available as the University IT systems undergo maintainance 27-28 October<br><br></b>";
	//echo "<b>Notice: The robotic telescopes will not be operating over the winter break. Operation will resume in January. <br>Plans may still be submitted in the meantime but will not be reviewed until January.<br><br></b>";
	//echo "<b>Notice: The web server will be upgraded on Friday 7th February. During this period access to this site may be interrupted. <br><br></b>";
	//echo "<b><font color=\"#ff0000\">Notice:</font> The login function is currently unavailable due to a technical issue. <br><br></b>";
	echo "<b><font color=\"#ff0000\">Notice:</font> Some services are currently unavailable due to observatory maintenance<br><br></b>";
	if(($newid || $level==0) && $authenticated){
		echo "<b>Your account is currently awaiting activation. If you previously held an account, you can <a href=\"linkaccount.php\">link the two and self-activate.</a><br>You cannot create new plans or see your list of images, but can still search the archive.</b><br><br>";
	}

	//location
	$lat = 51.7763;   
	$long = -0.0963;   
	$offset = 0;    

	//wind direction
	function getDir($b){
	$dirs = array('N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'N');
	return $dirs[round($b/45)];
	}

	$zenith1=90+50/60;
	$zenith4=90+12;

	$obshours=round(date_sunrise(time(), SUNFUNCS_RET_DOUBLE, $lat, $long, $zenith4, $offset)+24-date_sunset(time(), SUNFUNCS_RET_DOUBLE, $lat, $long, $zenith4, $offset),1);

	//-<a href=\"obsdetails.php\"> Observatory operational parameters</a> 
	//-<a href=\"obsstatus.php\"> Observatory live</a> - View what the automated telescopes are currently doing

	echo"<div style=\"padding:10px;\">

	<h4>Observatory information</h4><br>
	-<a href=\"obssetup.php\"> Telescope setup</a> - Show what cameras and filters are currently on the telescopes<br><br>

	<br><br><div id=\"id_gmt\">Current time: <br>Local sidereal time: <br>Julian date:</div><br>
	<b>Sunset:</b> ".date_sunset(time(), SUNFUNCS_RET_STRING, $lat, $long, $zenith1, $offset)."
	UTC<br><b>Automated observing starts: </b>".date_sunset(time(), SUNFUNCS_RET_STRING, $lat, $long, $zenith4, $offset)."
	UTC<br><b>Automated observing ends: </b>".date_sunrise(time(), SUNFUNCS_RET_STRING, $lat, $long, $zenith4, $offset)."
	UTC<br><b>Sunrise: </b>".date_sunrise(time(), SUNFUNCS_RET_STRING, $lat, $long, $zenith1, $offset)."
	UTC<br><br><b>Available hours of automated observing tonight:</b> $obshours hours";
	
	$day=03;
	$month=12;
	$year=2012;
	require_once('moontimes.php');
	$times=Moon::calculateMoonTimes(date("n"), date("j"), date("Y"), $lat, $long);
	$moonrise=($times->moonrise)-0;
	$moonset=($times->moonset)-0;

	$JDE=(time()/86400)+ 2440587.5;
	$T = ($JDE - 2451545.0)/36525;
	$epsilon=epsilon($T);
	$latitude = deg2rad($lat);   
	$longitude = -$long;  

	//moon 
	include('moonbits.php');

	echo "<br><b>Moon above the horizon:</b> ".date("H:i", $moonrise)." - ".date("H:i", $moonset)." UTC <br><b>Moon phase:</b> ".round($moonphase,1)."&#37;";
	
	//-<a href=\"contact.php\">Contact</a>
	echo"
	<br><br>

		-<a href=\"../wiki\"> Wiki</a> - Help, guides and information<br>
		<br>
		-<a href=\"contact.php\"> Contact</a> - Contact observatory staff<br>

	</div>
	</div>

	<div class=\"group\" >
	<h4>Current conditions</h4><br>

<!--	<div style=\"padding:10px;  \">

		<div id=\"weather\" style=\"float: left;\"></div>
		<div id=\"skytemp\" style=\"float: left; position: relative; left:30px;\"></div>
		<div id=\"skybrightness\"  style=\"float: left; position: relative; left:60px;\"></div>

	</div>-->
	<div style=\"padding:10px; clear:both;\"><br>

		-<a href=\"../allsky/index.php\"> AllSky camera website</a> - A live view of the sky from Bayfordbury<br><br>
		-<a href=\"../weather/graph.php\"> 24 hour weather graphs</a> - View weather station data for the past 24 hours<br>

	</div></div>

	<div class=\"group\" >
	<h4>Image archive</h4><br>
	<div style=\"padding:10px;\">
	";

	//image archive stats

	$exptime=0;
	$query="SELECT exptime FROM images WHERE protected=0";
	$result = mysqli_query($link, $query) or die(mysqli_error($link));

	$imagecount=mysqli_num_rows($result);

	while($row = mysqli_fetch_array($result)){ 
		$exptime+= $row['exptime'];
	}

	$exptime=round($exptime/3600,1);

	echo "Images available in the archive: <b>".number_format($imagecount)."</b><br>Total exposure time: <b>".number_format($exptime)." hours</b>
	<br><br>-<a href=\"imagesearch.php\"> Search the archive</a><br>";
	if($validated){
		echo"<br>-<a href=\"myprojects.php\"> My images</a><br>";
	}
	echo"</div>
	</div>
	";

	//only show if logged in and authenticated users
	if($validated){
		echo"<div class=\"group\" ><h4>Plan generation and submission</h4><div style=\"padding:10px;\"><br>";

		echo"
		-<a href=\"rtmleditor.php\"> Plan generator</a> - Create and submit RTML plans with a simple online form<br><br>
		-<a href=\"quickuploadrtml.php\"> Plan uploader</a> - Upload an RTML plan file and check for any errors<br><br>
		";

		echo"
		-<a href=\"myrtml.php\"> My plan uploads</a> - View the status of plans you have uploaded<br><br>
		-<a href=\"myqueue.php\"> Telescope queue</a> - View the status of any plans you have in the telescope queues<br>";

		echo"</div></div>";
	}

	echo"<div class=\"group\" ><h4>Tools</h4><div style=\"padding:10px;\"><br>
	-<a href=\"targetcheck.php\"> Target Checker</a> - Find out how long a target will be visible, and show a finder chart<br><br>
	-<a href=\"expcalc.php\"> Exposure Calculator</a> - Calculate the optimum exposure time, SNR and more<br><br>
	-<a href=\"moonavoidancecalc.php\"> Moon Avoidance Calculator</a> - View the result of different values for the Moon Avoidance Lorentzian<br>
	</div></div>";

	if($validated){

		/*
		if($level>4){echo"<div class=\"group\" >
		<h4>Supervisor tools</h4><br><div style=\"padding:10px;\">";
		if($viewerip=="127.0.0.1" || substr($viewerip, 0, 11) =="147.197.130"){
		echo"-<a href=\"obscontrol.php\"> Observatory control</a>";
		}else{
		echo"You must be using an observatory computer to access these tools.";
		}
		echo"</div></div><br><br>";
		}
		*/

		//show admin tools

		if($level>=5){
			//-<a href=\"adduser.php\"> Add new account</a><br><br>

			echo"<div class=\"group\" >
			<h4>Admin tools</h4><br><div style=\"padding:10px;\">";

			if($level>=8){
				echo"-<a href=\"accounts.php\"> View all accounts</a><br><br>
				-<a href=\"allrtml.php\"> View all RTML</a><br><br>";
			}
			
			echo"-<a href=\"queue.php\"> View everything in the queues</a><br></div>";
		}

	}else{
		echo"<br><br><div class=\"group\" >";
		
	}
} //end of display page

//footer
require_once('../mFooter.php');

?>
