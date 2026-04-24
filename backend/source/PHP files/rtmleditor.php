<?php
//header("Cache-Control:  must-revalidate, no-store, no-cache, private, max-age=0");
//header("Cache-Control: post-check=0, pre-check=0", false);
//header("Pragma: no-cache");

require_once('../mHeader.php');

if($displayPage){
	

	$query="SELECT * FROM `users` WHERE user=$userid LIMIT 1";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			 
	$row = mysqli_fetch_array($result);
	//$acpuser = $row['acpuser'];
	$fullname = $row['name']." ".$row['surname'];	
	
	if(!file_exists("rtml/editor/".$userid.".json")){
		file_put_contents("rtml/editor/".$userid.".json","");
		chmod ("rtml/editor/".$userid.".json", 0766);
	}
	
	//here we should look at the json file to test for issues, missing filters..etc
	require_once("condition.php");
	$input=file_get_contents("rtml/editor/".$userid.".json");
	$rtml=json_decode($input, TRUE);


echo"<link rel=\"stylesheet\" type=\"text/css\" href=\"css/jquery-ui-1.10.0.custom.css\" media=\"screen\" />
<link rel=\"stylesheet\" type=\"text/css\" href=\"css/jquery.datetimepicker.min.css\" media=\"screen\" />
<link rel=\"stylesheet\" type=\"text/css\" href=\"css/editor.css\" media=\"screen\" />

<script type=\"text/javascript\" src=\"js/jquery-1.11.2.min.js\"></script>
<script type=\"text/javascript\" src=\"js/jquery.json-2.4.min.js\"></script>
<script type=\"text/javascript\" src=\"js/jquery-ui-1.10.3.custom.min.js\"></script>
<script type=\"text/javascript\" src=\"js/jquery.datetimepicker.full.min.js\"></script>";


?>

<script type="text/javascript">
     var plancount=1;
	 var obscount={};
	 obscount[1]=3;
	 var json={};
	 var response;
	 var filters={};
	 var filtercount={};
	 var scopeLimits={};
	 var save_plan=true;
	 var dates,datee;
	 	
	<?php
	if(isset($rtml['Telescope'])){
		echo "var selectedScope = ".$rtml['Telescope'].";\n";
	}else{
		echo "var selectedScope = 2;\n";
	}
	 ?>
	 
	 function dd(deg){
		var degs;
		if(deg > 0){
			if(deg<10){
				degs = "+0"+Math.floor(deg);
			}else{
				degs = "+"+Math.floor(deg);
			}
        }else{
		 //if( deg > -1){
			if(deg>-10){
				degs = "-0"+Math.abs(Math.ceil(deg));
			}else{
				degs = "-"+Math.abs(Math.ceil(deg));
			}
        }//else{
        //    degs = "-0"+Math.abs(Math.ceil(deg));
        //}
        return degs;
    }

    function hh(deg){
        var degs;
		if(deg<10){
			degs = "0"+Math.floor(deg);
		}else{
			degs = Math.floor(deg);
		}
        return degs;
    }

    function dm(deg){
		var degs;
        deg = Math.abs(deg);
        degs= Math.floor((deg - Math.floor(deg)) * 60);
		if(degs<10){
			degs="0"+degs;
		}
		return degs;
    }

    function ds(deg){
		var degs, mini;
        deg = Math.abs(deg);
        mini = ((deg - Math.floor(deg)) * 60);
		degs= Math.round((mini - Math.floor(mini)) * 60);
		if(degs<10){
			degs="0"+degs;
		}
		return degs;
		
    }
	

	 
	 <?php
	 if($validated){
		$query="SELECT * FROM obssetup ORDER BY num ASC";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
		$j=1;
			while($row = mysqli_fetch_array($result)){ 
				$filters=explode("|", $row['filters']);				
				$limits=explode("|",$row['limits']);

				if($limits[0]==""){$limits[0]=0;}
				if($limits[1]==""){$limits[1]=0;}
				
				echo"\r\nscopeLimits[$j] ={}; scopeLimits[$j][0]=".($limits[0]+0)."; scopeLimits[$j][1]=".($limits[1]+0).";";
				
				$numfilters=count($filters);
				echo"\r\nfilters[$j] ={}; filtercount[$j]=$numfilters;";
				for($i=0; $i<$numfilters; $i++){
					echo" filters[$j][$i] = \"".$filters[$i]."\";";
				}
				$j++;
			}	
		}
	 ?>
	 
$(function() {
	$( "#Project" ).autocomplete({
	source: [<?php
if($validated){

	$query="SELECT `name`, `userid` FROM `acp_projects` WHERE `userid`=$userid";
	$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
	//$rows=mysqli_num_rows($result);
	$array=array();
	while($row = mysqli_fetch_array($result)){ 
		 $projectname=$row['name'];
		 $parts=explode("_", $projectname);
		 if(count($parts)==1){
			$array[$projectname]=1;
		 }elseif(count($parts)==2){
			$array[$parts[1]]=1;
		}				
	}	
	ksort($array);
	if(count($array)>0){
	$string="";
		foreach ($array as $key => $value) {
			$string.="\"".$key."\", ";
		}
		echo substr($string, 0, -2);
	}
	}
	?>],
	change: function (event, ui) {
		Change('Project');
	}
	});
});



 $(function() {
	 $("[title]").tooltip({
	track: true,
	show: { effect: "fade", duration: 100 },
	position: { my: "left+15 top+15", at: "left bottom", collision: "flipfit" }
	});
});


	 
function post(num){

	var response;
	//json['1>Schedule>Skycondition']="Poor";
	//json['1>Schedule>Altitude']=90;
	//json['2']="_unset_";
		
	console.log("post "+num);	
		
	var jsonString = encodeURIComponent(JSON.stringify(json));
	//var jsonString2=jsonString;
	$.ajax({ type: "POST",
		url: "ajax/rtmlajax.php",
		async: true,
		timeout: 5000,
		data: 'json=' +jsonString,
		dataType: "json",
		success: function (data) {
			if (data.success == true){
				response=data.success;
			if(num==0){
				$("#planstatus_1").html("<b>Plan cleared, reloading </b><font color=\"#00FF00\">&#x2714;</font>");
				location.reload(); 
			}else{
				$("#planstatus_"+num).html("<b>Plan saved </b><font color=\"#00FF00\">&#x2714;</font>");
			}
			}else{
				if(num==0){num=1;}
				response=data.reason;
				$("#planstatus_"+num).html("<b>Plan failed to save </b><font color=\"#FF0000\">&#x2718;</font><br>"+response);
			}
			
		}, 
		error: function(jqXHR, textStatus, errorThrown) {
			console.log(textStatus+" "+errorThrown);
			if(num==0){num=1;}
			if(textStatus=="error"){
				response="Server lost<br>Try reloading the page</b>";
				$("#planstatus_"+num).html("<b>Plan failed to save </b><font color=\"#FF0000\">&#x2718;</font><br>"+response);
			}else if(textStatus=="parsererror"){
				response="Server error<br>Try reloading the page</b>";
				$("#planstatus_"+num).html("<b>Plan failed to save </b><font color=\"#FF0000\">&#x2718;</font><br>"+response);
			}else{
				response=textStatus;
				$("#planstatus_"+num).html("<b>Plan failed to save </b><font color=\"#FF0000\">&#x2718;</font><br>"+response);
			}
		}
		});
		
	return response;	
}

function mpc(num){
save_plan=false;
	$("#TargetStatus_"+num).html("Searching &nbsp;<img src=\"images/ajax-loader.gif\">");
	//json={};
	var outgoing = {};
		outgoing['target']=document.getElementById("TargetName_"+num).value;
	var jsonString = JSON.stringify(outgoing);
	//var jsonString2=jsonString;
	$.ajax({ type: "POST",
		url: "ajax/mpclookup.php",
		async: true,
		timeout: 5000,
		data: 'json=' +jsonString,
		dataType: "json",
		success: function (data) {
			if (data.success == true){
				$("#mpc_"+num).val(data.mpc)
				$("#TargetStatus_"+num).html("Orbital elements found <font color=\"#00FF00\">&#x2714;</font>");
				$("#TargetType_"+num).html("<b>Orbital elements:<b> ");
				$("#mpc_"+num).css("display","inline");
				$("#coords_"+num).css("display","none");
				save_plan=true;
				PlanSave(num);
			}else{
				if(data.reason=='3'){
					$("#TargetStatus_"+num).html("Name not found <font color=\"#FF0000\">&#x2718;</font>");
				}else{
					$("#TargetStatus_"+num).html(data.reason);
				}
			}
		}, 
		error: function(jqXHR, textStatus, errorThrown) {
			if(textStatus=="error"){
				$("#TargetStatus_"+num).html("Server lost </b><font color=\"#FF0000\">&#x2718;</font> Try reloading the page");
			}else if(textStatus=="parsererror"){
				$("#TargetStatus_"+num).html("Server error </b><font color=\"#FF0000\">&#x2718;</font> Try reloading the page");
				//$("#TargetStatus_"+num).html(textStatus);
			}else{
				$("#TargetStatus_"+num).html(textStatus);
			}
		}
	});
	save_plan=true;
}

function simbad(num){
save_plan=false;
	$("#TargetStatus_"+num).html("Searching &nbsp;<img src=\"images/ajax-loader.gif\">");
	json={};
	var outgoing = {};
		outgoing['target']=document.getElementById("TargetName_"+num).value;
		outgoing['scope']=selectedScope;
	var jsonString = encodeURIComponent(JSON.stringify(outgoing));
	//var jsonString2=jsonString;
	$.ajax({ type: "POST",
		url: "ajax/simbadlookup.php",
		async: true,
		timeout: 5000,
		data: 'json=' +jsonString,
		dataType: "json",
		success: function (data) {
			if (data.success == true){
				$("#ra_"+num).val(data.ra)
				$("#dec_"+num).val(data.dec)				
				$("#rahms_"+num).html(" ("+hh(data.ra/15)+"h "+dm(data.ra/15)+"m "+ds(data.ra/15)+"s)");
				$("#dechms_"+num).html(" ("+dd(data.dec)+"&deg; "+dm(data.dec)+"' "+ds(data.dec)+"\")");
				$("#TargetType_"+num).html("<b>Coordinates:<b> ");
				$("#mpc_"+num).css("display","none");
				$("#coords_"+num).css("display","inline");
				if(data.dec>scopeLimits[selectedScope][1]){
					$("#TargetStatus_"+num).html("<b>Coordinates found, but are above selected telescope dec limits <font color=\"#FF0000\">&#x2718;</font>");
				}else if(data.dec<scopeLimits[selectedScope][0]){
					$("#TargetStatus_"+num).html("<b>Coordinates found, but are below selected telescope dec limits <font color=\"#FF0000\">&#x2718;</font>");
				}else{
					if(data.hours=="0"){
						$("#TargetStatus_"+num).html("<b>Coordinates found <font color=\"#00FF00\">&#x2714;</font></b> Not visible with this telescope tonight)");
					}else{
						$("#TargetStatus_"+num).html("<b>Coordinates found <font color=\"#00FF00\">&#x2714;</font></b> (visible for "+data.hours+" hours with this telescope tonight)");
					}
				}
				save_plan=true;
				PlanSave(num);				
			}else{
				if(data.reason=='3'){
					$("#TargetStatus_"+num).html("Name not found \""+data.name+"\"<font color=\"#FF0000\">&#x2718;</font>");
				}else if(data.reason=='4'){
					$("#TargetStatus_"+num).html("Coordinates not found <font color=\"#FF0000\">&#x2718;</font>");
				}else if(data.reason=='2'){
					$("#TargetStatus_"+num).html("Curl error: - "+data.details +"<font color=\"#FF0000\">&#x2718;</font>");
				}else{
					$("#TargetStatus_"+num).html("Error: "+data.reason+" - "+data.details +"<font color=\"#FF0000\">&#x2718;</font>");
				}
			}
		}, 
		error: function(jqXHR, textStatus, errorThrown) {
			if(textStatus=="error"){
				$("#TargetStatus_"+num).html("Server lost </b><font color=\"#FF0000\">&#x2718;</font> Try reloading the page");
			}else if(textStatus=="parsererror"){
				$("#TargetStatus_"+num).html("Server error </b><font color=\"#FF0000\">&#x2718;</font> Try reloading the page");
				//$("#TargetStatus_"+num).html(errorThrown);
			}else{
				$("#TargetStatus_"+num).html(textStatus);
			}
		}
	});
	save_plan=true;
}

function PlanSave(num){
	if(save_plan==true){
		$("#planstatus_"+num).html("Saving plan &nbsp;<img src=\"images/ajax-loader.gif\">");
		json={};

		json[num+">1>target"]=$("#TargetName_"+num).val();
		if($("#mpc_"+num).css("display")=="none"){
			json[num+">1>mpc"]="_unset_";
			json[num+">1>coord>ra"]=$("#ra_"+num).val();
			json[num+">1>coord>dec"]=$("#dec_"+num).val();
		}else{
			json[num+">1>mpc"]=$("#mpc_"+num).val();
			json[num+">1>coord"]="_unset_";
		}
			
		//var debug=$("#mpc_"+num).css("display");
		
		for(i=1; i<=obscount[num]; i++){
			json[num+">1>obscount"]=i;
			json[num+">1>"+i+">filter"]=$("#filter_"+num+"-"+i).val();
			json[num+">1>"+i+">exptime"]=$("#exp_"+num+"-"+i).val();
			json[num+">1>"+i+">count"]=$("#count_"+num+"-"+i).val();
			//json[num+">1>"+i+">binning"]=$("#binning_"+num+"-"+i).val();
		}
			
		if ($('#skycondc_'+num).is(':checked')) {
			json[num+">schedule>skycondition"]=$('#skycond_'+num).val();
		}else{
			json[num+">schedule>skycondition"]="_unset_";
		}
		
		
		json[num+">schedule>timerange>earliest"]=$('#times_'+num).val();
		json[num+">schedule>timerange>latest"]=$('#timee_'+num).val();
		
		
		if ($('#monitorc_'+num).is(':checked')) {
			json[num+">schedule>monitor"]=$('#monitor_'+num).val();
		}else{
			json[num+">schedule>monitor"]="_unset_";
		}
		
		if ($('#maxamc_'+num).is(':checked')) {
			json[num+">schedule>airmass>maximum"]=$('#maxam_'+num).val();
			json[num+">schedule>airmass>minimum"]="_unset_";
			json[num+">schedule>hourangle"]="_unset_";
		}else if ($('#amrc_'+num).is(':checked')) {
			json[num+">schedule>airmass>maximum"]=$('#ammax_'+num).val();
			json[num+">schedule>airmass>minimum"]=$('#ammin_'+num).val();
			json[num+">schedule>hourangle"]="_unset_";
		}else if ($('#harc_'+num).is(':checked')) {
			json[num+">schedule>airmass"]="_unset_";
			json[num+">schedule>hourangle>maximum"]=$('#hamax_'+num).val();
			json[num+">schedule>hourangle>minimum"]=$('#hamin_'+num).val();
		}else if ($('#noamc_'+num).is(':checked')) {
			json[num+">schedule>airmass"]="_unset_";
			json[num+">schedule>hourangle"]="_unset_";
		}
		if ($('#minaltc_'+num).is(':checked')) {
			json[num+">schedule>altitude"]=$('#minalt_'+num).val();
		}else{
			json[num+">schedule>altitude"]="_unset_";
		}
		
		if ($('#moond_'+num).is(':checked')) {
			json[num+">schedule>moon>down"]="true";
			json[num+">schedule>moon>width"]="_unset_";
			json[num+">schedule>moon>distance"]="_unset_";
			json[num+">schedule>moon>phase"]="_unset_";
		}else if ($('#moonp_'+num).is(':checked')) {
			json[num+">schedule>moon>down"]="false";
			json[num+">schedule>moon>width"]="_unset_";
			json[num+">schedule>moon>distance"]="_unset_";
			json[num+">schedule>moon>phase"]=$('#moonpha_'+num).val()/100;
		}else if ($('#moona_'+num).is(':checked')) {
			json[num+">schedule>moon>down"]="false";
			json[num+">schedule>moon>phase"]="_unset_";
			json[num+">schedule>moon>width"]=$('#moonwid_'+num).val();
			json[num+">schedule>moon>distance"]=$('#moondis_'+num).val();
		}else if ($('#moonc_'+num).is(':checked')) {
			json[num+">schedule>moon"]="_unset_";
		}
		
		post(num);
		

	}
}

function cleanAscii(f){
	
	var field = $(f);
    field.val(field.val().replace(/[^\x00-\x7F]/g, ""));
 
}

function Change(id){
	$("#scopestatus").html("Saving "+id+" &nbsp;<img src=\"images/ajax-loader.gif\">");
	var name = document.getElementById(id).value;
	json={};
	json[id]=name;
	post(1);
	/*if(response==true){
		$("#scopestatus").html(id+" saved <font color=\"#00FF00\">&#x2714;</font>");
	}else{
		$("#scopestatus").html("<b>Saving failed </b><font color=\"#FF0000\">&#x2718;</font><br>"+response);
	}*/

}

function ChangeScope(){
	$("#scopestatus").html("Saving  &nbsp;<img src=\"images/ajax-loader.gif\">");
	var name = document.getElementById("Telescope").value-0;
	selectedScope = name;
	json={};
	json["Telescope"]=name;
	response=post(1);
	$("#scopestatus").html("Telescope saved <font color=\"#00FF00\">&#x2714;</font>");
		var option='<option value=""></option>';
		for(i=0; i<filtercount[name]; i++){
			option=option+'<option value="'+filters[name][i]+'">'+filters[name][i]+'</option>';
		}
		$("[id^=filter]").html(option);
	//todo change logic here
	/*
	if(response==true){
		
		
	}else{
		$("#scopestatus").html("<b>Saving failed </b><font color=\"#FF0000\">&#x2718;</font><br>"+response);
	}*/
}



function NewPlan(pageLoad){
	if(plancount<999){
		var planhtml=$("#plan1").html();
		plancount++;
		var newplanhtml=planhtml.replace(/_1/g,"_"+plancount);
		var planhtml=newplanhtml.replace(/\(1\)/g,"("+plancount+")");
		var newplanhtml=planhtml.replace(/Plan 1/g,"Plan "+plancount);
		//$("#debugbox").val(newplanhtml);
		

		$("#otherplans").append('<br><div class="group" id="plan'+plancount+'">'+newplanhtml+'</div>')
		obscount[plancount]=obscount[plancount-1];
			
		//$("#observations_"+plancount).html("<br><b>Observations</b><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Filter &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Count&nbsp;&nbsp;&nbsp;Exposure &nbsp;Binning<br><select id=\"filter_"+plancount+"-1\" class=\"filter\" onChange=\"PlanSave("+plancount+");\"></select> <input id=\"count_"+plancount+"-1\" size=\"1\" onChange=\"PlanSave("+plancount+");\"> x <select id=\"exp_"+plancount+"-1\" onChange=\"PlanSave("+plancount+");\"></select> <select id=\"binning_"+plancount+"-1\" onChange=\"PlanSave("+plancount+");\"></select><br><select id=\"filter_"+plancount+"-2\" class=\"filter\" onChange=\"PlanSave("+plancount+");\"></select> <input id=\"count_"+plancount+"-2\" size=\"1\" onChange=\"PlanSave("+plancount+");\"> x <select id=\"exp_"+plancount+"-2\" onChange=\"PlanSave("+plancount+");\"></select> <select id=\"binning_"+plancount+"-2\" onChange=\"PlanSave("+plancount+");\"></select><br><select id=\"filter_"+plancount+"-3\" class=\"filter\" onChange=\"PlanSave("+plancount+");\"></select> <input id=\"count_"+plancount+"-3\" size=\"1\" onChange=\"PlanSave("+plancount+");\"> x <select id=\"exp_"+plancount+"-3\" onChange=\"PlanSave("+plancount+");\"></select> <select id=\"binning_"+plancount+"-3\" onChange=\"PlanSave("+plancount+");\"></select><br>");
	}
	
	if(!pageLoad){
		$("#times_"+plancount).datetimepicker({
			format:'Y-m-d H:i',
			formatTime:'H:i',
			formatDate:'Y-m-d'
		});
		
		$("#timee_"+plancount).datetimepicker({
			format:'Y-m-d H:i',
			formatTime:'H:i',
			formatDate:'Y-m-d'
		});
	}
	$("#monitor_"+plancount).keyup(function () { 
		//console.log(this);
		if(this.value.search(/[^0-9\.]/g)!=-1){
			alert('This must be an integer');
		}
		this.value = this.value.replace(/[^0-9\.]/g,'');
	});
	
	return true;
}

function NewObs(num){
	
	console.log("adding obs "+ num);
	console.log("current obs count: "+obscount[num]);
	
	obscount[num]++;
	var name = document.getElementById("Telescope").value-0;
	var option='<div id="obs_'+num+'-'+obscount[num]+'"><select id="filter_'+num+'-'+obscount[num]+'" class="filter" onChange="PlanSave('+num+');"><option value=""></option>';
		for(i=0; i<filtercount[name]; i++){
			option=option+'<option value="'+filters[name][i]+'">'+filters[name][i]+'</option>';
		}

	option=option+'</select> <input id="count_'+num+'-'+obscount[num]+'" size=2 maxlength=3 onChange="PlanSave('+num+');" value=2> x <select id="exp_'+num+'-'+obscount[num]+'" onChange="PlanSave('+num+');"><option value="5">5s</option><option value="10">10s</option><option value="15">15s</option><option value="20">20s</option><option value="30">30s</option><option value="45">45s</option><option value="60">60s</option><option value="90">90s</option><option value="120">120s</option><option value="180">180s</option><option value="240">240s</option><option value="300">300s</option><option value="600">600s</option></select> </div>';
	
	$("#observations_"+num).append(option);
	console.log("new obs count: "+obscount[num]);
}

function RemoveLastObs(num){
	
	console.log("remove obs "+ num);
	console.log("current obs count: "+obscount[num]);

	//don't actually unset 2nd and 3rd observation, never remove first obs
	if(obscount[num]==3 || obscount[num]==2){
		json={};		
		$('#obs_'+num+'-'+obscount[num]).remove();
		json[num+">1>obscount"]=obscount[num];
		json[num+">1>"+obscount[num]+">filter"]="";
		obscount[num]--;
		post(num);
		
	}else if(obscount[num]>1){
		$('#obs_'+num+'-'+obscount[num]).remove();
				
		json={};
		json[num+">1>obscount"]=obscount[num];
		json[num+">1>"+obscount[num]]="_unset_";
		obscount[num]--;
		post(num);
	}
	console.log("new obs count: "+obscount[num]);
}

function resetAll(){

	json={};
	for(i=1; i<=1000; i++){
		json[i]="_unset_";
	}
	json['Project']="_unset_";
	json['Description']="_unset_";
	json['Telescope']="_unset_";
	json['Observers']="_unset_";
	post(0);
	
}

function resetPlan(num){
	$(':input:not(:submit, :button, :radio, :checkbox)', $("#plan"+num)).val([])
	//Add stuff to reset everything to default
	
	
	json={};
	json[num]="_unset_";
	
	post(num);
	
}

function removePlan(num){

	$('#plan'+num).remove();
	
	json={};
	json[num]="_unset_";
	
	post(num);
	
}

function load_obs(){
   
   var option='<option value=""></option>';
   var name = document.getElementById("Telescope").value-0;
   /////////
		for(i=0; i<filtercount[name]; i++){
			option=option+'<option value="'+filters[name][i]+'">'+filters[name][i]+'</option>';
		}
		$("[id^=filter_]").html(option);
	/////////
	
	option='<option value="5">5s</option><option value="10">10s</option><option value="15">15s</option><option value="20">20s</option><option value="30">30s</option><option value="45">45s</option><option value="60">60s</option><option value="90">90s</option><option value="120">120s</option><option value="180">180s</option><option value="240">240s</option><option value="300">300s</option><option value="600">600s</option>';
	$("[id^=exp_]").html(option);	
	
	//option='<option value="1">1x1</option><option value="2" selected>2x2</option><option value="3">3x3</option><option value="4">4x4</option>';
	//$("[id^=binning_]").html(option);
	
	//$("[id^=timec_]").attr("disabled",true);
	//$("[id^=times_]").attr("disabled",true);
	//$("[id^=timee_]").attr("disabled",true);
	$("[id^=ra_]").attr("disabled",true);
	$("[id^=dec_]").attr("disabled",true);
	$("[id^=mpc_]").attr("disabled",true);
}

function toggleSetup(){
	$("#obsSetup").toggle();
}

function toggleOptions(){
	$(".advconstraints").toggle();
}

$(document).ready(function() {
	
   //reload if back button used
   var perfEntries = performance.getEntriesByType("navigation");

	if (perfEntries[0].type === "back_forward") {
		location.reload(true);
	}
	
	load_obs();
	

	
	<?php

	
	//Load any pre-existing plan bits
	// could this go in php? ..probably not
	
	
	for($i=2; $i<1000; $i++){
	//plan loop
	$j=1;
		if(isset($rtml[$i][$j]['target'])){
		//echo"Old plan found $i $j<br>";
			if($i>1){
				echo"var res = NewPlan(true); console.log(plancount + ' ' +res);\n";
			}			
		}
	}
	?>
	

	
	
	<?php
	for($i=1; $i<1000; $i++){
	//plan loop
	$j=1;
		if(isset($rtml[$i][$j]['target'])){
		//echo"Old plan found $i $j<br>";

			echo"$(\"#TargetName_$i\").val(\"".$rtml[$i][$j]['target']."\");\n";
			
			if(isset($rtml[$i][$j]['coord']['ra']) && isset($rtml[$i][$j]['coord']['dec'])){
				echo"$(\"#ra_$i\").val(\"".$rtml[$i][$j]['coord']['ra']."\");\n$(\"#dec_$i\").val(\"".$rtml[$i][$j]['coord']['dec']."\");\n$(\"#mpc_$i\").css(\"display\",\"none\");\n$(\"#coords_$i\").css(\"display\",\"inline\");\n";
			}elseif(isset($rtml[$i][$j]['mpc'])){
			
				echo"$(\"#mpc_$i\").val(\"".$rtml[$i][$j]['mpc']."\");\n$(\"#mpc_$i\").css(\"display\",\"inline\");\n$(\"#coords_$i\").css(\"display\",\"none\");\n";
				
			}
			for($k=1; $k<1000; $k++){
				if(isset($rtml[$i][$j][$k]['filter'])){
					if($rtml[$i][$j][$k]['filter']!=""){
						if($k>3){echo"NewObs($i);";}
						if(isset($rtml[$i][$j][$k]['count'])){echo"$(\"#count_$i-$k\").val(\"".$rtml[$i][$j][$k]['count']."\");\n";}
						if(isset($rtml[$i][$j][$k]['exptime'])){echo"$(\"#exp_$i-$k\").val(\"".$rtml[$i][$j][$k]['exptime']."\");\n";}
						//if(isset($rtml[$i][$j][$k]['binning'])){echo"$(\"#binning_$i-$k\").val(\"".$rtml[$i][$j][$k]['binning']."\");\n";}
						if(isset($rtml[$i][$j][$k]['filter'])){echo"$(\"#filter_$i-$k\").val(\"".$rtml[$i][$j][$k]['filter']."\");\n";}
				
					}
				}	
			}
			
			//repopulate all of the things
			
			//timerange
			
			
			if(isset($rtml[$i]['schedule']['timerange']['earliest'])){
				echo "dates = \"".$rtml[$i]['schedule']['timerange']['earliest']."\";\n";
			}else{
				echo "dates = \"".date("Y-m-d H:i")."\", \"yyyy-MM-dd hh:mm\";\n";
			}
			
			if(isset($rtml[$i]['schedule']['timerange']['latest'])){
				echo "datee = \"".$rtml[$i]['schedule']['timerange']['latest']."\";\n";
				//echo"$(\"#timee_$i\").val(\"".$rtml[$i]['schedule']['timerange']['latest']."\");\n";
				
			}else{
				echo "datee = \"".date("Y-m-d H:i", strtotime('+3 months'))."\";\n";
			}				

			//echo "console.log($(\"#times_$i\").val());\n";
			//echo "console.log(dates); console.log(datee);\n";
			echo"$(\"#times_$i\").val(dates);\n";
			//echo"$(\"#times_$i\").datetimepicker('setDate', dates);\n";
			//echo "console.log($(\"#times_$i\").val());\n";
			echo"$(\"#timee_$i\").val(datee);\n";
			
			//skycondition
			
			if(isset($rtml[$i]['schedule']['skycondition'])){
				echo"$(\"#skycond_$i\").val(\"".$rtml[$i]['schedule']['skycondition']."\");\n";
				echo"$(\"#skycondc_$i\").prop('checked', true);\n";
			}else{
				echo"$(\"#skycondc_$i\").prop('checked', false);\n";
			}
			
			//monitor
			if(isset($rtml[$i]['schedule']['monitor'])){
				echo"$(\"#monitorc_$i\").prop('checked', true);\n";
				echo"$(\"#monitor_$i\").val(\"".$rtml[$i]['schedule']['monitor']."\");\n";
			}
			
			//position contraints
			
			if(isset($rtml[$i]['schedule']['airmass']['minimum'])){
				echo"$(\"#amrc_$i\").prop('checked', true);\n";
				echo"$(\"#ammin_$i\").val(\"".$rtml[$i]['schedule']['airmass']['minimum']."\");\n";
				echo"$(\"#ammax_$i\").val(\"".$rtml[$i]['schedule']['airmass']['maximum']."\");\n";
			}else if(isset($rtml[$i]['schedule']['airmass']['maximum'])){
				echo"$(\"#maxamc_$i\").prop('checked', true);\n";
				echo"$(\"#maxam_$i\").val(\"".$rtml[$i]['schedule']['airmass']['maximum']."\");\n";
			}else if(isset($rtml[$i]['schedule']['hourangle']['maximum'])){
				echo"$(\"#harc_$i\").prop('checked', true);\n";
				echo"$(\"#hamin_$i\").val(\"".$rtml[$i]['schedule']['hourangle']['minimum']."\");\n";
				echo"$(\"#hamax_$i\").val(\"".$rtml[$i]['schedule']['hourangle']['maximum']."\");\n";
			}
			
			if(isset($rtml[$i]['schedule']['altitude'])){
				echo"$(\"#minaltc_$i\").prop('checked', true);\n";
				echo"$(\"#minalt_$i\").val(\"".$rtml[$i]['schedule']['altitude']."\");\n";
			}
			
			//moon constraints
			
			/*
			if ($('#moond_'+num).is(':checked')) {
				json[num+">schedule>moon>down"]="true";
				json[num+">schedule>moon>width"]="_unset_";
				json[num+">schedule>moon>distance"]="_unset_";
				json[num+">schedule>moon>phase"]="_unset_";
			}else if ($('#moonp_'+num).is(':checked')) {
				json[num+">schedule>moon>down"]="false";
				json[num+">schedule>moon>width"]="_unset_";
				json[num+">schedule>moon>distance"]="_unset_";
				json[num+">schedule>moon>phase"]=$('#moonpha_'+num).val()/100;
			}else if ($('#moona_'+num).is(':checked')) {
				json[num+">schedule>moon>down"]="false";
				json[num+">schedule>moon>phase"]="_unset_";
				json[num+">schedule>moon>width"]=$('#moonwid_'+num).val();
				json[num+">schedule>moon>distance"]=$('#moondis_'+num).val();
			}else if ($('#moonc_'+num).is(':checked')) {
				json[num+">schedule>moon"]="_unset_";
			}
		*/
		
		
			
			if(isset($rtml[$i]['schedule']['moon']['phase'])){
				echo"$(\"#moonp_$i\").prop('checked', true);\n";
				echo"$(\"#moonpha_$i\").val(\"".($rtml[$i]['schedule']['moon']['phase']*100)."\");\n";
			}else if(isset($rtml[$i]['schedule']['moon']['width'])){
				echo"$(\"#moona_$i\").prop('checked', true);\n";
				echo"$(\"#moonwid_$i\").val(\"".$rtml[$i]['schedule']['moon']['width']."\");\n";
				echo"$(\"#moondis_$i\").val(\"".$rtml[$i]['schedule']['moon']['distance']."\");\n";
			}else if(isset($rtml[$i]['schedule']['moon']['down'])){
				if($rtml[$i]['schedule']['moon']['down']){
					echo"$(\"#moond_$i\").prop('checked', true);\n";
				}else{
					echo"$(\"#moonc_$i\").prop('checked', true);\n";
				}
			}else{
				echo"$(\"#moonc_$i\").prop('checked', true);\n";
			}
		
		}else{
			//break;
			
		}
	}


	
	?>
	
		
	//$("[id^=time]").datepicker({ dateFormat: "yy-mm-dd" });
	$.datetimepicker.setLocale('en');
	//$("[id^=time]").datetimepicker();
	$("[id^=times_]").datetimepicker({
		format:'Y-m-d H:i',
		formatTime:'H:i',
		formatDate:'Y-m-d'
	});
	$("[id^=timee_]").datetimepicker({
		format:'Y-m-d H:i',
		formatTime:'H:i',
		formatDate:'Y-m-d'
	});
	
	

	$("[id^=monitor_]").keyup(function () { 
		//console.log(this);
		if(this.value.search(/[^0-9\.]/g)!=-1){
			alert('This must be an integer');
		}
		this.value = this.value.replace(/[^0-9\.]/g,'');
	});
	
	
	
	//ChangeScope(); //Update filters on load
 });
 
 
 
 
</script>

<?php
	 }
require_once('../mTop.php');

if($displayPage){

//echo"<font color=\"#ff0000\"><b>IMPORTANT NOTE:</b></font> The automated telescopes are currently non-operational due to a fault with the electrical supply to Bayfordbury. Until this is fixed no plans will run, or be submitted to the telescopes.<br><br>
echo"
<div style=\"vertical-align:middle;\"><a href=\"\" onClick=\"resetAll();return false;\"><image src=\"images/new.png\"> Clear all</a> &nbsp; &nbsp; <a href=\"json2rtml.php\"><image src=\"images/save.png\"> Save and create RTML</a></div><br>
With this form you can create simple RTML plans. Please ensure you have read through the <a href=\"https://observatory.herts.ac.uk/wiki/Guide:Plan_generator\">Plan Generator guide</a> and familiarised yourself with the principles of the <a href=\"https://observatory.herts.ac.uk/wiki/Guide:Queued_observing_system\">Queued observing system</a>.<br><br><b>Global parameters</b>";

echo"<div style=\" margin-left:10%;\">";
 

echo"<br>
<table class=\"ui-widget\">
<tr><td><label for=\"projects\"><b>Project name: </b></label></td><td><input id=\"Project\" onkeyup=\"cleanAscii(this)\" onChange=\"cleanAscii(this);Change('Project')\" maxlength=\"39\"  title=\"Enter a project name or start typing to look up previous projects. All images with the same project name will be put in a single directory. The project name will be visible by anyone viewing your images in the future.\" size=\"40\"  spellcheck=\"true\"";
if(isset($rtml['Project'])){echo" value=\"".$rtml['Project']."\"";}
echo"> <a href=\"https://observatory.herts.ac.uk/wiki/Guide:Plan_generator#Plan_parameters\" target=\"_blank\" class=\"q\"></a></td></tr>
<tr><td><label for=\"observers\"><b>Observers: </b></label></td><td><input id=\"Observers\"  title=\"The list of observers for this project.\" onChange=\"Change('Observers')\" ";
if(isset($rtml['Observers'])){echo" value=\"".$rtml['Observers']."\"";}else{echo"value=\"$fullname\"";}
echo" maxlength=\"255\" size=\"40\"  spellcheck=\"true\"></td></tr>
<tr><td><label for=\"description\"><b>Description: </b></label></td><td><textarea id=\"Description\" onkeyup=\"cleanAscii(this)\" onChange=\"cleanAscii(this);Change('Description')\" maxlength=\"255\" cols=\"40\"  title=\"Enter a description to let observatory staff know what you are trying to achieve, and assign an approriate priority to your plan. Only observatory staff will see the project description.\" rows=\"5\">";
if(isset($rtml['Description'])){echo $rtml['Description'];}
echo"</textarea> <a href=\"https://observatory.herts.ac.uk/wiki/Guide:Plan_generator#Plan_parameters\" target=\"_blank\" class=\"q\"></a></td></tr>

<tr><td><label for=\"telescope\"><b>Telescope:</b></label> </td><td>
<select name=\"telescope\" id=\"Telescope\" onChange=\"ChangeScope()\"  title=\"Check the setup to find the telescope that matches your filter/declination requirements.\">";

for($i=2; $i<($numscopes+1); $i++){
	if(isset($rtml['Telescope'])){
		if($i!=4 && $i!=1 && $i!=7 && $i!=8){
			echo"<option value=\"$i\" ";
			if($rtml['Telescope']==$i){echo "selected";}
			echo">$i - ".$scopename[$i]."</option>";
		}
	}else{
		if($i!=4 && $i!=1 && $i!=7 && $i!=8){
			echo"<option value=\"$i\" ";
			if($i==1){echo "selected";}
			echo">$i - ".$scopename[$i]."</option>";
		}
	
	}
}

	echo"</select><a href=\"\" onClick=\"toggleSetup();return false;\"> [+] Show/hide setup</a><br>Note: (Changing the telescope will reset all filter options)</td></tr></table><div style=\"display:none\" id=\"obsSetup\"><br>
	<table class=\"small\"><tr><th>Name</th><th>Filters</th><th>Declination<br>limits</th><th>Pending observations</th></tr>";
	$query="SELECT * FROM obssetup WHERE automated=1 ORDER BY num ASC";
		$result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			while($row = mysqli_fetch_array($result)){ 
				
			
				echo"<tr><td><b>";
				if($row['status']==1){
					echo"<font color=\"#ff9900\">";
				}elseif($row['status']==2){
					echo"<font color=\"#009900\">";
				}else{
					echo"<font color=\"#dd0000\">";
				}
				
				echo $row['num'].". ".$scopename[$row['num']]."</font></b></td><td>";
				$filters=explode("|", $row['filters']);
				$numfilters=count($filters);
				for($i=0; $i<$numfilters; $i++){
					echo $filters[$i];
					if($i<3 || $i==4 || $i==5 || $i==7){
						if($i!=($numfilters-1)){
							echo ", ";
						}
					}
					#0 1 2 3
					#4 5 6
					#7 8
					if($i==3 || $i==6){
						echo"<br>";
					}
				}
				echo"</td><td>";
				
				$limits=explode("|",$row['limits']);
				echo"Lower: ".$limits[0]."&deg;<br>Upper: ".$limits[1]."&deg;</td><td>";
				
				echo $row['planstogo']." plans (";
				if($row['timetogo']<3600){
				echo round($row['timetogo']/60,1)." mins";
				}else{
				echo round($row['timetogo']/3600,1)." hrs";
				}
				
				
				echo ")</td></tr>";
			
			
				
				
				
			}	
	
	echo"</table><font color=\"#009900\">Running robotically</font><br><font color=\"#ff9900\">Maintenance/testing</font><br><font color=\"#dd0000\">Not running robotically</font></div>";
?>

	</div></div>
	<br>
	<div class="group" id="plan1"><h4>Plan 1:</h4><!--<a href="" onClick="resetPlan(1);return false;">Reset plan</a> | --><a href="" onClick="removePlan(1);return false;">Remove plan</a><br>
	<div id="planstatus_1" style="float:right; position:relative; top:-30px; padding:20px;">Plan not saved</div>
	<br>
	<table class="target">
	<tr>
		<td>
			<label for="TargetName_1"><b>Target name: </b></label>
		</td>
		<td>
		<input id="TargetName_1" maxlength="50" size="40" title="A target name that is recognised by Simbad or the MPC"> <!--onChange="PlanSave(1);"-->
		<div id="TargetStatus_1" style="display:inline;"></div><br>
		<div style="padding-top:9px;">
			<input type="submit" value="Find coordinates" onClick="simbad(1);">
			<input type="submit" value="Find orbital elements" onClick="mpc(1);">
		</div>
		</td>
	</tr>
	<tr>
		<td id="TargetType_1" style="padding-top:11px;">
		<b>Coordinates:</b>
		</td>
		<td style="padding-top:8px;">
		<textarea id="mpc_1" cols="50" rows="5" style="display:none;"></textarea>
		<div id="coords_1">
			R.A: <input id="ra_1" size="10" title="Test">&deg;  <div id="rahms_1" style="display:inline-block;" ></div><br>
			Dec: <input id="dec_1" size="10">&deg; <div id="dechms_1" style="display:inline-block;"></div>
		</div>
		</td>
	</tr>
	</table><br>
	<div id="observations_1"><br>
	<b>Observations</b><br>
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Filter &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Repeats &nbsp;&nbsp;Exposure <br>
		<div id="obs_1-1"><select id="filter_1-1" class="filter" onChange="PlanSave(1);"></select> <input id="count_1-1" size="2" maxlength=3 onChange="PlanSave(1);" title="The number of times to repeat this observation" value=2> x <select id="exp_1-1" onChange="PlanSave(1);"  title="Exposure time in seconds"></select> </div>
		<div id="obs_1-2"><select id="filter_1-2" class="filter" onChange="PlanSave(1);"></select> <input id="count_1-2" size="2" maxlength=3 onChange="PlanSave(1);" title="The number of times to repeat this observation" value=2> x <select id="exp_1-2" onChange="PlanSave(1);"  title="Exposure time in seconds"></select> </div>
		<div id="obs_1-3"><select id="filter_1-3" class="filter" onChange="PlanSave(1);"></select> <input id="count_1-3" size="2" maxlength=3 onChange="PlanSave(1);" title="The number of times to repeat this observation" value=2> x <select id="exp_1-3" onChange="PlanSave(1);" title="Exposure time in seconds"></select> </div>
			
	</div><a href="" onClick="NewObs(1);return false">Add another</a> | <a href="" onClick="RemoveLastObs(1);return false">Remove last</a><br>
	<br>
	<div>
	<div class="constraint">
		<b>Plan constraints</b> <a href="https://observatory.herts.ac.uk/wiki/Guide:Plan_generator#Constraints.2Foptions" target="_blank" class="q"></a><br>
		
		Starting time range:<br>
		&nbsp;&nbsp;&nbsp;&nbsp; <input type="text" id="times_1" size="15" onChange="PlanSave(1);" value="<?php echo date("Y-m-d H:i"); ?>"> (yyyy-mm-dd hh:mm UTC)
		<br> &nbsp;&nbsp;&nbsp;&nbsp; to
		<br>&nbsp;&nbsp;&nbsp;&nbsp; <input type="text" id="timee_1" size="15" onChange="PlanSave(1);" value="<?php echo date("Y-m-d H:i", strtotime('+3 months')); ?>"> (yyyy-mm-dd hh:mm UTC)<br>
		<input type="checkbox" value="true" id="skycondc_1" checked onChange="PlanSave(1);"><label for="skycondc_1">Minimum sky condition</label>
		<select name="skycond_1" id="skycond_1" onChange="PlanSave(1);">
			<option value="Fair" selected>Fair</option>
			<option value="Good">Good</option>
			<option value="Excellent">Excellent</option>
		</select><br>
		<div class="advconstraints"><input type="checkbox"  value="true" id="monitorc_1" onChange="PlanSave(1);"><label for="monitorc_1"  title="Automatically resubmit the plan after completing (for monitoring things that change, i.e. comets, supernovae... etc)">Automatically repeat plan every</label> <input id="monitor_1" size="1" maxlength="2" value="7" onChange="PlanSave(1);" > days after completing</div>
	</div>
	<div  class="constraint">
		
		<div class="advconstraints">
		<b>Position Constraints</b> <a href="https://observatory.herts.ac.uk/wiki/Guide:Plan_generator#Position_constraints" target="_blank" class="q"></a><br>
		<input type="radio" name="airmass_1" value="none" id="noamc_1" checked onChange="PlanSave(1);"><label for="noamc_1">No air mass/hour angle constraint</label><br>
		
		<input type="radio" name="airmass_1" value="max" id="maxamc_1" onChange="PlanSave(1);">
		<label for="maxamc_1">Maximum air mass</label>
		<input id="maxam_1" size="3" maxlength="5" value="2" onChange="PlanSave(1);" onClick="$('#maxamc_1').prop('checked', true);">
		<br>
		
		<input type="radio" name="airmass_1" value="range" id="amrc_1" onChange="PlanSave(1);">
		<label for="amrc_1">Air mass range</label>
		<input id="ammin_1" size="3" maxlength="5" value="1" onChange="PlanSave(1);" onClick="$('#amrc_1').prop('checked', true);"> to 
		<input id="ammax_1" size="3" maxlength="5" value="3" onChange="PlanSave(1);" onClick="$('#amrc_1').prop('checked', true);">
		<br>
		
		<input type="radio" name="airmass_1" value="ha" id="harc_1" onChange="PlanSave(1);">
		<label for="harc_1">Hour angle range</label>
		<input id="hamin_1" size="3" maxlength="5" value="-3" onChange="PlanSave(1);" onClick="$('#harc_1').prop('checked', true);"> to 
		<input id="hamax_1" size="3" maxlength="5" value="3" onChange="PlanSave(1);" onClick="$('#harc_1').prop('checked', true);">
		
		<br>
		
		<input type="checkbox"  value="true" id="minaltc_1" onChange="PlanSave(1);"><label for="minaltc_1">Minimum altitude</label> <input id="minalt_1" size="3" maxlength="5" value="0" onChange="PlanSave(1);">&deg;<br>
		</div>
	</div>
	<div class="constraint">
		<div class="advconstraints">
		<b>Moon constraints</b> <a href="https://observatory.herts.ac.uk/wiki/Guide:Plan_generator#Moon_constraints" target="_blank" class="q"></a><br>
		<input type="radio" name="moon_1" value="none" id="moonc_1" onChange="PlanSave(1);">
		<label for="moonc_1"  title="Use with caution">The moon is fine</label>
		<br>
		
		<input type="radio" name="moon_1" value="down" id="moond_1" onChange="PlanSave(1);">
		<label for="moond_1">The moon must be down</label>
		<br>
		
		<input type="radio" name="moon_1" value="phase" id="moonp_1" onChange="PlanSave(1);">
		<label for="moonp_1">Maximum moon phase</label> 
		<input id="moonpha_1" size="2" maxlength="4" value="80" onChange="PlanSave(1);"  onClick="$('#moonp_1').prop('checked', true);"> &#37;
		
		<br>
		
		<input type="radio" name="moon_1" value="avoid" id="moona_1" checked onChange="PlanSave(1);">
		<label for="moona_1"  title="Target has to be further from the moon the brighter it is.">		Moon avoidance Lorentzian<br>&nbsp;&nbsp;&nbsp; Distance:</label>
		<input id="moondis_1" size="1" maxlength="3" value="60" checked onChange="PlanSave(1);"  onClick="$('#moona_1').prop('checked', true);"> Width:
		<input id="moonwid_1" size="1" maxlength="2" value="6" onChange="PlanSave(1);"  onClick="$('#moona_1').prop('checked', true);">
		</div>
	</div>
	
	</div><br>
	<a href="" onClick="toggleOptions();return false;"> [+] Show/hide Advanced Options</a>
	<br><br>
	<a href="" onClick="NewPlan(false);return false">+ add new plan</a>
	</div>
	
	<div id="otherplans">
	
	</div>
	
	<div id="debug">
	
	</div>
<?php
//<textarea id="debugbox"></textarea>
//<a href="#" onClick="NewPlan();return false"> do stuff</a>
}

require_once('../mFooter.php');
?>
