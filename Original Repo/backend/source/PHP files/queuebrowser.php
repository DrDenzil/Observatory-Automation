<?php

$title="Queue browser";

require_once('../mHeader.php');

if($displayPage){
?> 

	<link rel="stylesheet" href="css/jquery-ui-1.10.4.custom.min.css" type="text/css">
	<link rel="stylesheet" href="css/fancytree/ui.fancytree.css" type="text/css">
	<link rel="stylesheet" href="css/jquery.contextMenu.css" type="text/css" />
	
	<script type="text/javascript" src="js/jquery-1.11.0.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
	<script type="text/javascript" src="js/jquery.fancytree.js"></script>	  
	<script type="text/javascript" src="js/jquery.contextMenu-1.6.5.js"></script>
	<script type="text/javascript" src="js/jquery.fancytree.contextMenu.js"></script>

	
	
	<script type="text/javascript" >
	
	var aR= {};
	var aP={};
	var aO={};
	var aI={};
	
	var sP = [];
	var sO = [];
	var sI = [];
	
	sP[0]="Pending";
	sP[1]="Deferred";
	sP[2]="Running";
	sP[3]="Completed";
	sP[4]="Failed";
	sP[5]="Disabled";
	
	sO[0]="Pending";
	sO[1]="Running";
	sO[2]="Completed";
	sO[3]="Failed";
	sO[4]="Vetoed";	
	sO[5]="Disabled";
	
	sI[0]="Pending";
	sI[1]="Running";
	sI[2]="Completed";
	sI[3]="Failed";
	sI[4]="Disabled";
	
	
	
	<?php
	
			
	
		
		$Rresult = mysqli_query($link, "SELECT * FROM `acp_projects` WHERE  `userid`='$userid' AND `deleted`=0 ORDER BY `name`") or die(mysqli_error($link));//
		$Rrows=mysqli_num_rows($Rresult);
		while($Rrow = mysqli_fetch_array($Rresult)){ 
		
			$telescope=$Rrow['telescope'];
			$projectid=$Rrow['projectid'];
			$name =addslashes ($Rrow['name']);
			$enabled =$Rrow['enabled']; 
			 
			echo"aR['$projectid']={};aR['$projectid']['n']='$name';aR['$projectid']['e']=$enabled;";
			
					
			$Presult = mysqli_query($link, "SELECT * FROM `acp_plans` WHERE `projectid`='$projectid' AND `deleted`=0") or die(mysqli_error($link)); 
			$Prows=mysqli_num_rows($Presult);
			while($Prow = mysqli_fetch_array($Presult)){ 
				
				 $planid=$Prow['planid'];
				 $name =addslashes ($Prow['name']);
				 $status =$Prow['status'];
				 
				 
				 $priority =$Prow['priority'];
				 
				 $FailureReason =$Prow['FailureReason'];
				 $DeferralExpires =$Prow['DeferralExpires'];
				 $Considered =$Prow['Considered'];
				 $MonitorInterval =$Prow['MonitorInterval'];
				 
				 echo"aP['$planid']={};aP['$planid']['n']='$name';aP['$planid']['s']=$status;aP['$planid']['p']=$priority;aP['$planid']['f']=\"$FailureReason\";aP['$planid']['d']='$DeferralExpires';aP['$planid']['c']=$Considered; aP['$planid']['m']=$MonitorInterval;";
		
				$Oresult = mysqli_query($link, "SELECT * FROM `acp_observations` WHERE `planid`='$planid' AND `deleted`=0") or die(mysqli_error($link)); 
				$Orows=mysqli_num_rows($Oresult);
				while($Orow = mysqli_fetch_array($Oresult)){ 
					
					$obsid=$Orow['obsid'];
					$name =addslashes ($Orow['name']);
					$status =$Orow['status'];
					$failurereason = $Orow['failurereason'];
					$ra = $Orow['ra'];
					$dec = $Orow['dec'];
			
					 echo"aO['$obsid']={};aO['$obsid']['n']='$name';aO['$obsid']['s']=$status;aO['$obsid']['f']='$failurereason';aO['$obsid']['r']=$ra ;aO['$obsid']['d']=$dec;aO['$obsid']['c']={};";
					 
					$Cresult = mysqli_query($link, "SELECT * FROM `acp_constraints` WHERE `obsid`='$obsid' AND `deleted`=0") or die(mysqli_error($link)); 
					$Crows=mysqli_num_rows($Cresult);
					while($Crow = mysqli_fetch_array($Cresult)){ 
						
						 $constraintid=$Crow['constraintid'];
						 $value1 =rtrim($Crow['value1'], "0");
						 $value2 =rtrim($Crow['value2'], "0");
						 $type=substr($constraintid,-1);
						if($type==0 ||$type=2||$type==5||$type==6){
							echo"aO['$obsid']['c'][$type]='{$value1},{$value2}';";
						}else{
							echo"aO['$obsid']['c'][$type]=$value1;";
						}
						
					}	
										
					$Iresult = mysqli_query($link, "SELECT * FROM `acp_images` WHERE `obsid`='$obsid' AND `deleted`=0") or die(mysqli_error($link)); 
					$Irows=mysqli_num_rows($Iresult);
					while($Irow = mysqli_fetch_array($Iresult)){ 
						
						 $imageid=$Irow['imageid'];
						 $filter =$Irow['filter'];
						 $exposure =$Irow['exposure'];
						 $binning =$Irow['binning'];
						 $status =$Irow['status'];
						 $repeatcount =$Irow['repeatcount'];
						 
						echo"aI['$imageid']={};aI['$imageid']['f']='$filter';aI['$imageid']['e']=$exposure;aI['$imageid']['b']=$binning;aI['$imageid']['r']=$repeatcount;aI['$imageid']['s']=$status;";
										
					}	
					
				}				
			}			
		}
	
	?>
	
	var allExpanded=false;
	
	
	function dbedit(node, req){
	
		
		var outgoing = {};
		outgoing['id']=node.key;
		outgoing['req']=req;
		var jsonString = JSON.stringify(outgoing);	
		type=node.key.substr(0,1);
		$.ajax({
			type: "POST",
			url: "ajax/dbedit.php",
			async: false,
			timeout: 1000,
			data: 'json=' +jsonString,
			dataType: "json",
			success: function (data) {
				if (data.success){
					//wohoo
					console.log("OK");
					console.log(data.details);
					switch (req){
						case 'dis':
							node.data.icon="dis.png"; 
							node.renderTitle();
							
							node.visit(function(innernode){
								innernode.data.icon="dis.png"; 
								innernode.renderTitle(); 								
							})
							break;
						case 'res': 
							if(type=="R"){
								node.data.icon="";  
								node.folder=true; 
							}else{
								innernode.data.icon="yel.png"; 
							}
							
							node.renderTitle();
							
							node.visit(function(innernode){
								innernode.data.icon="yel.png"; 
								innernode.renderTitle(); 								
							})						
							break;
						case 'del': node.remove(); break;
					}
				}else{
					console.log(data.reason);
					console.log(data.details);
					
					alert("Could not update ("+data.reason+") "+data.details);	
				}
			}, 
			error: function(jqXHR, textStatus, errorThrown) {
				console.log(textStatus);
				
				if(textStatus=="error"){
					alert("Could not update: connection lost to the server (try reloading the page)");
				}else if(textStatus=="parsererror"){
					
					alert("Could not update: server error (please tell observatory staff)");
				}else{
					//textStatus
					alert("Could not update ("+textStatus+")");
				}
			}
		});
	
	
	}
	
	$(function(){
		$("#tree").fancytree({
		  // Image folder used for data.icon attribute.
			
			extensions: ['contextMenu'],
			imagePath: "css/icons/",
			//clickFolderMode: 3,
			/*contextMenu: {
			menu: function(node){
				type=node.key.substr(0,1);
				if(type=='R'){               
					return {
						1: { 'name': 'Pause project', 'icon': 'disable' },
						2: { 'name': 'Resume project', 'icon': 'resubmit' },					
						3: { 'name': 'Disable all plans', 'icon': 'disable'},	
						4: { 'name': 'Resubmit all plans', 'icon': 'resubmit'},					
						5: { 'name': 'Resubmit failed plans', 'icon': 'resubmit'},
						6: { 'name': 'Delete project', 'icon': 'delete'}
					};
			   } else if(type=='P'){ 
					return {
						1: { 'name': 'Disable plan', 'icon': 'disable' },
						2: { 'name': 'Resumbit plan', 'icon': 'resubmit' },					
						3: { 'name': 'Duplicate plan', 'icon': 'resubmit'},	
						4: { 'name': 'Delete plan', 'icon': 'delete'}
					};
				}
			},
			actions: function(node, action, options) {
				type=node.key.substr(0,1);
				node.setFocus(true);
				node.setActive(true);
							
				console.log(node+" "+action)
				
				if(action==1){
				//1  = disable
				//$('#selected-action').text('Disable node ' + node.key);
	
					if(type=='R'){
					
						//dbedit(node, "dis");				
					
					
					}else if(type=='P'){
					
					
					}else if(type=='O'){					
						alert("You can only disable the entire plan");
					}else if(type=='I'){
						alert("You can only disable the entire plan");					
					}					
				
				
				}else if(action==2){
				//2  = resubmit
				//$('#selected-action').text('Resubmit node ' + node.key);
				
					if(type=='R'){
					
						//dbedit(node, "res");				
					
					
					}else if(type=='P'){
						
						//dbedit(node, "res");
					
					
					}else if(type=='O'){					
						alert("You can only resubmit the entire plan");
					}else if(type=='I'){
						alert("You can only resubmit the entire plan");					
					}
			
				
				
				}else if(action==3){
				//3  = delete
				//$('#selected-action').text('Delete node ' + node.key);
					console.log(node);	
					//node.remove();
					
					//node.data.icon="red.png";
					//node.renderTitle();
				}
			}
		  },*/
  


		  
		  activate: function(event, data) {
			var name = data.node.title;
			var key =data.node.key;
			var type=data.node.key.substr(0,1);
			
			if(type=='R'){			
				$("#projecttitle").text("Project: "+name.substring(3) +"   (ID: "+key+")");
				
				$("#project").show();
				$("#plan").hide();
				$("#obs").hide();
				$("#image").hide();
				
							
				$("#Rn").html(aR[key.substr(1)]['n']);
				if(aR[key.substr(1)]['e']){
					$("#Re").html("Yes");
				}else{
					$("#Re").html("No");
				}
				
			}else if(type=='P'){
				$("#plantitle").text("Plan: "+name +"   (ID: "+key+")");
				
				$("#project").hide();
				$("#plan").show();
				$("#obs").hide();
				$("#image").hide();
								
				$("#Pn").html(aP[key.substr(1)]['n']);
				$("#Ps").html(sP[aP[key.substr(1)]['s']]);
				
				

				$("#Pf").html(aP[key.substr(1)]['f']);
				if(aP[key.substr(1)]['p']<-5){
					$("#Pp").html("Low");
				}else if(aP[key.substr(1)]['p']>5){
					$("#Pp").html("High");
				}else{
					$("#Pp").html("Normal");
				}
				
				$("#Pd").html(aP[key.substr(1)]['d']);
				if(aP[key.substr(1)]['c']==1){
					$("#Pc").html("Yes");
				}else{
					$("#Pc").html("No");
				}
				
				if(aP[key.substr(1)]['m']==0){
					$("#Pm").html("-");
				}else{
					$("#Pm").html(aP[key.substr(1)]['m']+" days");
				}
			
			
			}else if(type=='O'){
				$("#obstitle").text("Observation: "+name +"   (ID: "+key+")");
				
				$("#project").hide();
				$("#plan").hide();
				$("#obs").show();
				$("#image").hide();
				
				
				$("#On").html(aO[key.substr(1)]['n']);
				$("#Os").html(sO[aO[key.substr(1)]['s']]);
				$("#Of").html(aO[key.substr(1)]['f']);
				$("#Or").html(aO[key.substr(1)]['r']);
				$("#Od").html(aO[key.substr(1)]['d']);
			
			
			}else if(type=='I'){
			
				$("#imagetitle").text("Image: "+name +"   (ID: "+key+")"); 
				
				
				
				$("#project").hide();
				$("#plan").hide();
				$("#obs").hide();
				$("#image").show();
				
				$("#If").html(aI[key.substr(1)]['f']);
				$("#Ie").html(aI[key.substr(1)]['e']);
				$("#Ib").html(aI[key.substr(1)]['b']);
				$("#Ir").html(aI[key.substr(1)]['r']);
				$("#Is").html(sI[aI[key.substr(1)]['s']]);
				
			
			}
			
		
			$("#echoSelection1").text("Selection id "+key);
			//data.node.data.icon="red.png";
			//data.node.load();
			//console.log(data.node);
			//console.log(node);
			//data.node.span.parentNode.hidden=true;
			
		  },
		 
		});

	});
	
	function toggle(){
	allExpanded=!allExpanded;
	
			$("#tree").fancytree("getRootNode").visit(function(node){
				//node.toggleExpanded();
				
						
				
				node.setExpanded(allExpanded);
				
				//console.log(node);
				//node.span.hidden
			});
		}
	
	function showHide(scope){
	
		$("#tree").fancytree("getRootNode").visit(function(node){
				//node.toggleExpanded();
				
				if(node.key.substr(0,1)=="R"){
					if(node.key.substr(1,1)==scope){
						node.span.parentNode.hidden=!$('#showT'+scope).is(':checked');
					}
				}
		});
		
	
	
	}
	
	
	
	
	
		
	</script>
<?php
}
require_once('../mTop.php');




if($displayPage){



		echo"The queue browser is a new way to see your plans in the telescope queue, and see easily which plans have been run and which are pending.<br>Future improvements will allow changes to be made to plans on already in the queue.<br><br>\n<div id=\"browser\"><div id=\"tree\">\n<ul id=\"treeData\" style=\"display: none;\">\n";
		$Rresult = mysqli_query($link, "SELECT * FROM `acp_projects` WHERE  `userid`='$userid' AND `deleted`=0 ORDER BY `name`") or die(mysqli_error($link));//
		$Rrows=mysqli_num_rows($Rresult);
		while($Rrow = mysqli_fetch_array($Rresult)){ 
			$telescope=$Rrow['telescope'];
			$projectid=$Rrow['projectid'];
			$name =$Rrow['name'];
			$enabled =$Rrow['enabled']; 
			 
			//id=\"R$projectid\" 
			if($enabled){
				echo "\t<li  id=\"R$projectid\" class=\"folder\">".$scopename[$telescope]." - $name\n";
			}else{				
				echo "\t<li  id=\"R$projectid\" data-icon=\"dis.png\">".$scopename[$telescope]." - $name\n";
			}
			
			
			echo"\t<ul>\n";
			$Presult = mysqli_query($link, "SELECT * FROM `acp_plans` WHERE `projectid`='$projectid' AND `deleted`=0") or die(mysqli_error($link)); 
			$Prows=mysqli_num_rows($Presult);
			while($Prow = mysqli_fetch_array($Presult)){ 
				
				 $planid=$Prow['planid'];
				 $name =$Prow['name'];
				 $status =$Prow['status'];
				 
				 switch($status){
					case 0:	echo "\t\t<li id=\"P$planid\" data-icon=\"yel.png\">$name\n\t\t<ul>\n";	break;//pending
					case 1:	echo "\t\t<li id=\"P$planid\" data-icon=\"ora.png\">$name\n\t\t<ul>\n";	break;//deferred
					case 2: echo "\t\t<li id=\"P$planid\" data-icon=\"cya.png\">$name\n\t\t<ul>\n";	break;//running
					case 3:	echo "\t\t<li id=\"P$planid\" data-icon=\"gre.png\">$name\n\t\t<ul>\n";	break;//completed
					case 4: echo "\t\t<li id=\"P$planid\" data-icon=\"red.png\">$name\n\t\t<ul>\n";	break;//failed
					case 5:	echo "\t\t<li id=\"P$planid\" data-icon=\"dis.png\">$name\n\t\t<ul>\n";break;//disabled
				}
				 
				
				
				
				$Oresult = mysqli_query($link, "SELECT * FROM `acp_observations` WHERE `planid`='$planid' AND `deleted`=0") or die(mysqli_error($link)); 
				$Orows=mysqli_num_rows($Oresult);
				while($Orow = mysqli_fetch_array($Oresult)){ 
					
					$obsid=$Orow['obsid'];
					$name =$Orow['name'];
					$status =$Orow['status'];
					 
					switch($status){
						case 0:	echo "\t\t<li id=\"O$obsid\" data-icon=\"yel.png\">$name\n\t\t\t<ul>\n";	break;//pending
						case 1:	echo "\t\t<li id=\"O$obsid\" data-icon=\"cya.png\">$name\n\t\t\t<ul>\n";	break;//running
						case 2: echo "\t\t<li id=\"O$obsid\" data-icon=\"gre.png\">$name\n\t\t\t<ul>\n";	break;//completed
						case 3:	echo "\t\t<li id=\"O$obsid\" data-icon=\"red.png\">$name\n\t\t\t<ul>\n";	break;//failed
						case 4: echo "\t\t<li id=\"O$obsid\" data-icon=\"ora.png\">$name\n\t\t\t<ul>\n";	break;//vetoed
						case 5:	echo "\t\t<li id=\"O$obsid\" data-icon=\"dis.png\">$name\n\t\t\t<ul>\n";	break;//disabled
					}	
					 
										
					$Iresult = mysqli_query($link, "SELECT * FROM `acp_images` WHERE `obsid`='$obsid' AND `deleted`=0") or die(mysqli_error($link)); 
					$Irows=mysqli_num_rows($Iresult);
					while($Irow = mysqli_fetch_array($Iresult)){ 
						
						 $imageid=$Irow['imageid'];
						 $filter =$Irow['filter'];
						 $exposure =$Irow['exposure'];
						 $binning =$Irow['binning'];
						 $status =$Irow['status'];
						 $repeatcount =$Irow['repeatcount'];
						 
						 switch($status){
							case 0:	echo "\t\t<li id=\"I$imageid\" data-icon=\"yel.png\">$filter {$exposure}s {$binning}x{$binning} ({$repeatcount})</li>\n";	break;//pending
							case 1:	echo "\t\t<li id=\"I$imageid\" data-icon=\"cya.png\">$filter {$exposure}s {$binning}x{$binning} ({$repeatcount})</li>\n";	break;//running
							case 2: echo "\t\t<li id=\"I$imageid\" data-icon=\"gre.png\">$filter {$exposure}s {$binning}x{$binning} ({$repeatcount})</li>\n";	break;//completed
							case 3:	echo "\t\t<li id=\"I$imageid\" data-icon=\"red.png\">$filter {$exposure}s {$binning}x{$binning} ({$repeatcount})</li>\n";	break;//failed
							case 4: echo "\t\t<li id=\"I$imageid\" data-icon=\"dis.png\">$filter {$exposure}s {$binning}x{$binning} ({$repeatcount})</li>\n";	break;//disabled
						}
						
					}
					echo"\t\t\t</ul>\n\t\t\t</li>\n";
				
				}
				echo"\t\t</ul>\n\t\t</li>\n";
			
			}
			echo"\t</ul>\n\t</li>\n";
		
		}
		echo"</ul>\n</div><div id=\"right_container\" style=\"width:66%; position: relative; left:33%; \"><div id=\"options\"> ";
		
		?>
		<div style="float: left;"><b>Filter Telescopes</b><br>
			<input type="checkbox" id="showT1" onChange="showHide(1);" checked><label for="showT1">1) DAT</label><br>
			<input type="checkbox" id="showT2" onChange="showHide(2);" checked><label for="showT2">2) INT</label><br>
			<input type="checkbox" id="showT3" onChange="showHide(3);" checked><label for="showT3">3) CKT</label><br>
			<input type="checkbox" id="showT5" onChange="showHide(5);" checked><label for="showT5">5) RPT</label><br>
			<input type="checkbox" id="showT6" onChange="showHide(6);" checked><label for="showT6">6) JHT</label><br>
		</div>
		<div style="float: left; margin-left:1%; ">
		<a href="" onClick="toggle(); return false;">Show/hide all</a>
		
		</div>
		
		
		
	
</div><div id="details">

<div id="project">
	<h1 id="projecttitle">Project</h1>
	
	
	<table>
	<tr><td>Name: </td><td><div id="Rn" ></div></td></tr>
	<tr><td>Enabled: </td><td><div id="Re" ></div></td></tr>
		<tr><td>&nbsp;</td><td><div type="submit" value="Save" onClick=""  disabled>	</td></tr></table>		
</div>


<div id="plan">
	<h1 id="plantitle">Plan</h1>
	
	<table>
	<tr><td>Name: </td><td><div id="Pn" ></div></td></tr>
	<tr><td>Status: </td><td><div id="Ps" ></div></td></tr>
	<tr><td>Failure reason: </td><td><div id="Pf" ></div></td></tr>
	<tr><td>Priority: </td><td><div id="Pp" ></div></td></tr>
	<tr><td>Deferral expires: </td><td><div id="Pd" ></div></td></tr>
	<tr><td>Considered: </td><td><div id="Pc" ></div></td></tr>
	<tr><td>Repeat interval: </td><td><div id="Pm" ></div></td></tr>
		<tr><td>&nbsp;</td><td><input type="submit" value="Save" onClick=""  disabled>	</td></tr></table>			
			
</div>


<div id="obs">
	<h1 id="obstitle">Observation</h1>
	<table>
	<tr><td>Name: </td><td><div id="On" ></div></td></tr>
	<tr><td>RA: </td><td><div id="Or" ></div></td></tr>
	<tr><td>Dec: </td><td><div id="Od" ></div></td></tr>
	<tr><td>Status: </td><td><div id="Os" ></div></td></tr>
	<tr><td>Failure reason: </td><td><div id="Of"></div></td></tr>
		<tr><td>&nbsp;</td><td><input type="submit" value="Save" onClick=""  disabled>	</td></tr></table>						
</div>	


<div id="image">
	<h1 id="imagetitle">Image</h1>
	<table>
	<tr><td>Filter: </td><td><div id="If" ></div></td></tr>
	<tr><td>Exposure: </td><td><div id="Ie" ></div></td></tr>
	<tr><td>Binning: </td><td><div id="Ib" ></div></td></tr>
	<tr><td>Repeats: </td><td><div id="Ir" ></div></td></tr>
	<tr><td>Status: </td><td><div id="Is" ></div></td></tr>
	<tr><td>&nbsp;</td><td><input type="submit" value="Save" onClick=""  disabled>	</td></tr></table>			
</div>	
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>



</div></div><br>
		
<?php	

}
require_once('../mFooter.php');

?>