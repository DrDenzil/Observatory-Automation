<?php



require_once('../mHeader.php');
if($displayPage){

?> 

	<link rel="stylesheet" href="css/jquery-ui-1.10.4.custom.min.css" type="text/css">
	<link rel="stylesheet" href="css/fancytree/ui.fancytree.css" type="text/css">
	<link rel="stylesheet" href="css/jquery.contextMenu.css" type="text/css" />
	
	<script type="text/javascript" src="js/jquery-1.11.0.min.js"></script>
	<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
	<script type="text/javascript" src="js/jquery.fancytree.js"></script>	  
	<script type="text/javascript" src="js/jquery.fancytree.filter.js"></script>	
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
	

	
	<?php
	
			
	/*
		
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
				 
				 echo"aP['$planid']={};aP['$planid']['n']='$name';aP['$planid']['s']=$status;";
		
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
	*/
	?>
	
	var allExpanded=false;
	/*
	SAMPLE_BUTTON_DEFAULTS = {
	id: undefined,
	label: "Sample",
	newline: true,
	code: function(){ alert("not implemented"); }
};
	
	function addSampleButton(options)
{
	var opts = $.extend({}, SAMPLE_BUTTON_DEFAULTS, options),
		$buttonBar = $("#sampleButtons"),
		$container = $("<span />", {
			"class": "sampleButtonContainer"
		});

	$("<button />", {
		id: opts.id,
		title: opts.tooltip,
		text: opts.label
	}).click(function(e){
		e.preventDefault();
		opts.code();
	}).appendTo($container);

	$("<a />", {
		text: "Source code",
		href: "#",
		"class": "showCode"
	}).appendTo($container)
	.click(function(e){
		try {
			prettyPrint();
		} catch (e) {
			alert(e);
		}
		var $pre = $container.find("pre");
		if($pre.is(":visible")){
			$(this).text("Source code");
		}else{
			$(this).text("Hide source");
		}
		$pre.toggle("slow");
		return false;
	});
	var sourceCode = "" + opts.code;
	// Remove outer function(){ CODE }
//    sourceCode = sourceCode.match(/[]\{(.*)\}/);
	sourceCode = sourceCode.substring(
		sourceCode.indexOf("{") + 1,
		sourceCode.lastIndexOf("}"));
//    sourceCode = $.trim(sourceCode);
	// Reduce tabs from 8 to 2 characters
	sourceCode = sourceCode.replace(/\t/g, "  ");
	// Format code samples

	$("<pre />", {
		text: sourceCode,
		"class": "prettyprint"
	}).hide().appendTo($container);
	if(opts.newline){
		$container.append($("<br />"));
	}
	if(opts.header){
		$("<h5 />", {text: opts.header}).appendTo($("p#sampleButtons"));
	}
	if( !$("#sampleButtons").length ){
		$.error("addSampleButton() needs a container with id #sampleButtons");
	}
	$container.appendTo($buttonBar);
}
	*/
	$(function(){
		$("#tree").fancytree({
		  // Image folder used for data.icon attribute.
			extensions: ['contextMenu','filter'],
			imagePath: "css/icons/",
			//clickFolderMode: 3,
			filter: {
				mode: "hide"
			  },

			
		  
		  activate: function(event, data) {
			//Clicked on an image
		  
			
			var name = data.node.title;
			var key =data.node.key;
			
			//_=project/plan I= image
			var type=key.substr(0,1);
			
			console.log(name);
			
			if(type=="I"){			
			
				var dbid=key.substr(1);
				
				var imgurl="jpeg/"+Math.floor(dbid/1000)+"/"+dbid+".jpg";
				
				console.log(dbid);
				
				var response;
				//json['1>Schedule>Skycondition']="Poor";
				//json['1>Schedule>Altitude']=90;
				//json['2']="_unset_";
				
				var json={};
				json['dbid']=dbid;
					
				var jsonString = encodeURIComponent(JSON.stringify(json));
				//var jsonString2=jsonString;
				
				console.log(jsonString);
				
				$.ajax({ type: "POST",
					url: "ajax/imagedetails.php",
					async: false,
					timeout: 2000,
					data: 'json=' +jsonString,
					dataType: "json",
					success: function (data) {
						if (data.success == true){
							response=data.success;
							$("#summary").html(data.summary);
							$("#header").html(data.header);
							console.log(data);
						}else{
							response=data.reason;
							console.log(data);
						}
					}, 
					error: function(jqXHR, textStatus, errorThrown) {
						if(textStatus=="error"){
							response="Server lost<br>Try reloading the page</b>";
						}else if(textStatus=="parsererror"){
							response="Server error<br>Try reloading the page</b>";
						}else{
							response=textStatus;
						}
						console.log(textStatus);
					}
				});
				
				$("#echoSelection1").text("Selection id "+key);
				
				$("#imagetitle").text("Image: "+name +"   (ID: "+dbid+")"); 
					
				$("#imagecon").html('<img src="'+imgurl+'">'); 
					
				$("#image").show();				
			
				
			}
			
		  }
		 
		});
		
		 var tree = $("#tree").fancytree("getTree");

		
			$("input[name=search]").keyup(function(e){
		  var n,
			leavesOnly = $("#leavesOnly").is(":checked"),
			match = $(this).val();

		  if(e && e.which === $.ui.keyCode.ESCAPE || $.trim(match) === ""){
			$("button#btnResetSearch").click();
			return;
		  }
		  
		  if($("#regex").is(":checked")) {
			// Pass function to perform match
			n = tree.filterNodes(function(node) {
			  return new RegExp(match, "i").test(node.title);
			}, leavesOnly);
		  } else {
			// Pass a string to perform case insensitive matching
			n = tree.filterNodes(match, leavesOnly);
		  }
		  $("button#btnResetSearch").attr("disabled", false);
		  $("span#matches").text("(" + n + " matches)");
		}).focus();

		$("button#btnResetSearch").click(function(e){
		  $("input[name=search]").val("");
		  $("span#matches").text("");
		  tree.clearFilter();
		}).attr("disabled", true);

		$("input#hideMode").change(function(e){
		  tree.options.filter.mode = $(this).is(":checked") ? "hide" : "dimm";
		  tree.clearFilter();
		  $("input[name=search]").keyup();
		}).prop("checked", true);
		$("input#leavesOnly").change(function(e){
		  // tree.options.filter.leavesOnly = $(this).is(":checked");
		  tree.clearFilter();
		  $("input[name=search]").keyup();
		});
		$("input#regex").change(function(e){
		  tree.clearFilter();
		  $("input[name=search]").keyup();
		});

		/*addSampleButton({
		  label: "Filter active branch",
		  newline: false,
		  code: function(){
			if( !tree.getActiveNode() ) {
			  alert("Please activate a folder.");
			  return;
			}
			tree.filterBranches(function(node){
			  return node.isActive();
			});
		  }
		});
		
		addSampleButton({
		  label: "Reset filter",
		  newline: false,
		  code: function(){
			tree.clearFilter();
		  }
		});*/


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
	//$userid=51;


		echo"The images browser is a new way to quickly browse through your images, divided into project and targets.<br>\n<div id=\"browser\">";
		?>
		  <p>
    <label>Filter:</label>
    <input name="search" placeholder="Filter...">
    <button id="btnResetSearch">&times;</button>
    <span id="matches"></span>
  </p>
  <!--<p>
  
    <label for="hideMode">
      <input type="checkbox" id="hideMode">
      Hide unmatched nodes
    </label>
    <label for="leavesOnly">
      <input type="checkbox" id="leavesOnly">
      Leaves only
    </label>

    <label for="regex">
      <input type="checkbox" id="regex">
      Regular expression
    </label>
  </p>-->
  

		
		
		<?php
		echo"<div id=\"tree\">\n<ul id=\"treeData\" style=\"display: none;\">\n";
		
		$Rresult = mysqli_query($link, "SELECT `project`, `target` FROM images WHERE `observerid`=$userid ORDER BY `project`") or die(mysqli_error($link));//
		$Rrows=mysqli_num_rows($Rresult);
		echo $Rrows;
		
		$projects=array();
		$targets=array();
		while($Rrow = mysqli_fetch_array($Rresult)){
			$project=$Rrow['project'];
			$target=$Rrow['target'];

			if(isset($projects[$project]) || isset($projects[strtolower($project)])){
				$projects[$project]++;
			}else{
				$projects[$project]=1;
			}
						
		}
		
	
		//ksort($projects, SORT_STRING);
			
		foreach ($projects as $project => $val) {
			
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
			
			echo "<li  class=\"folder\">$prjname ($val)\n<ul>\n";// id=\"R$projectid\"
			
			
			$Presult = mysqli_query($link, "SELECT `target` FROM `images` WHERE `observerid`=$userid AND `project`=\"$project\"") or die(mysqli_error($link)); 
			$Prows=mysqli_num_rows($Presult);
			$targets=array();
			$targetsn=array();
			
			while($Prow = mysqli_fetch_array($Presult)){ 
				$target=$Prow['target'];
				if(isset($targets[strtolower($target)])){
					$targets[strtolower($target)]++;
				}else{
					$targets[strtolower($target)]=1;
					$targetsn[strtolower($target)]=$target;
				}
			}
			
			
			foreach ($targets as $target => $val) {	
				
				
				echo "\t<li >".$targetsn[$target]." ($val)\n\t<ul>\n";	//id=\"P$planid\"
				
				
				$Oresult = mysqli_query($link, "SELECT * FROM images WHERE `observerid`=$userid AND `project`=\"$project\" AND `target`=\"$target\" ORDER BY `dbid` ASC") or die(mysqli_error($link)); 
				$Orows=mysqli_num_rows($Oresult);
				while($Orow = mysqli_fetch_array($Oresult)){ 
					
					$filter=$Orow['filter'];
					$exptime=$Orow['exptime'];
					$binning=$Orow['binning'];
					$telescope=$Orow['telescope'];
					$dbid=$Orow['dbid'];
					$imgid=$Orow['imgid'];
					$jd=$Orow['jd'];
					$solved=$Orow['solved'];
					
					//echo"<tr><td>$dbid &nbsp;&nbsp;&nbsp;<a href=\"imagedetails.php?id=".$dbid."\">View image</a></td><td>$filter</td><td>".round($exptime,1)."s</td><td>".date("Y-M-d H:i:s", ($jd - 2440587.5)*86400)."<td>".$scopename[$telescope]."</td></tr>";
					if($solved){
						echo "\t<li id=\"I$dbid\" >$dbid) ".$filter."_".round($exptime,1)."s_B".$binning."_T".$telescope."_".$imgid."</li>\n";// 
					}else{
						echo "\t<li id=\"I$dbid\"  data-icon=\"red.png\">$dbid) ".$filter."_".round($exptime,1)."s_B".$binning."_T".$telescope."_".$imgid."</li>\n";// 
					}
								
				
				}
				echo"\t</ul>\n\t</li>\n";
			
			}
			echo"\t</ul>\n</li>\n";
			
		
		}
		echo"</ul>\n</div><div id=\"right_container2\"><div id=\"options\" style=\"display: none;\"> ";
		
		?>
		<div style="float: left;"><b>Filter Telescopes</b><br>
			<input type="checkbox" id="showT1" onChange="showHide(1);" checked><label for="showT1">1) DAT</label><br>
			<input type="checkbox" id="showT2" onChange="showHide(2);" checked><label for="showT2">2) INT</label><br>
			<input type="checkbox" id="showT3" onChange="showHide(3);" checked><label for="showT3">3) CKT</label><br>
			<input type="checkbox" id="showT5" onChange="showHide(5);" checked><label for="showT5">5) RPT</label><br>
			<input type="checkbox" id="showT6" onChange="showHide(6);" checked><label for="showT6">6) Paramount</label><br>
		</div>
		<div style="float: left; margin-left:1%; ">
		<a href="" onClick="toggle(); return false;">Show/hide all</a>
		
		</div>
		
		
		
	
</div><div id="details2">



<div id="image2">
	<h1 id="imagetitle">Image</h1>
	<div id="imagecon"></div>
			
</div>	

<div id="summary"></div>

<div id="header"></div>



</div></div><br>
	</div>	
<?php	

}
require_once('../mFooter.php');

?>