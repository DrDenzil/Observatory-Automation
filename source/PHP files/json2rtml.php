<?php 


require_once('../mHeader.php');
echo"<link rel=\"stylesheet\" type=\"text/css\" href=\"fshl/styles/COHEN_style.css\" media=\"screen\" />";


require_once('../mTop.php');

echo"<div ><div style=\" width:800px; margin:0px auto;\"><div id=\"topline\">Your RTML has been created and saved and is shown below. 
You can now <a href=\"editorsubmitrtml.php\"> continue to check for errors and submit >> </a></div><br>

<a id=\"download\" href=\"dlrtml.php?name=\">Download RTML file locally <img src=\"images/download.png\"></a>  (for manual editing or later upload)<br><br>"; //
$input=file_get_contents("rtml/editor/".$userid.".json");
$rtml=json_decode($input, TRUE);

if($displayPage){



function do_offset($level){
    $offset = "";             // offset for subarry 
    for ($i=1; $i<$level;$i++){
    $offset = $offset . "<td></td>";
    }
    return $offset;
}

function show_array($array, $level, $sub){
    if (is_array($array) == 1){          // check if input is an array
       foreach($array as $key_val => $value) {
           $offset = "";
           if (is_array($value) == 1){   // array is multidimensional
           echo "<tr>";
           $offset = do_offset($level);
           echo $offset . "<td>" . $key_val . "></td>";
           show_array($value, $level+1, 1);
           }
           else{                        // (sub)array is not multidim
           if ($sub != 1){          // first entry for subarray
               echo "<tr nosub>";
               $offset = do_offset($level);
           }
           $sub = 0;
           echo $offset . "<td main ".$sub." width=\"120\">" . $key_val . 
               "></td><td width=\"120\">" . $value . "</td>"; 
           echo "</tr>\n";
           }
       } //foreach $array
    }  
    else{ // argument $array is not an array
        return;
    }
}

function html_show_array($array){
  echo "<table cellspacing=\"0\" border=\"2\">\n";
  show_array($array, 1, 0);
  echo "</table>\n";
}

$out=print_r($rtml, TRUE);

//file_put_contents("out.txt", $out);

$file_path="out.rtml";


$query="SELECT * FROM `users` WHERE user=$userid LIMIT 1";
			 $result = mysqli_query($link, $query) or die(mysqli_error($link)); 
			 
$row = mysqli_fetch_array($result);
$fullname=  $row['name']." ".$row['surname'];

	
$query="SELECT * FROM obssetup WHERE num>1 ORDER BY num ASC";
$result = mysqli_query($link, $query) or die(mysqli_error($link)); 

	while($row = mysqli_fetch_array($result)){ 
		
		$bits=explode("|",$row['minbinning']);
		$minbinning[$row['num']]=$bits[0];

	}

$string = <<<XML
<?xml version="1.0" encoding="iso-8859-1"?>
<RTML version="2.3">
</RTML>
XML;

$xml = simplexml_load_string($string);

	//Contact info
	$Contact=$xml->addChild('Contact');
	$Contact->addChild('User');
	$Contact->addChild('Email');
	$Contact->addChild('Organization', 'University of Hertfordshire');
	
$numRequests = 0;

for($i=1; $i<1000; $i++){
//Request (plan) loop
	//check if first target exists
	if(isset($rtml[$i][1]['target'])){
		
		$requestimages=0;
		
		if($rtml[$i][1]['target']!=""){
		
			//Basic info

			$Request[$i]=$xml->addChild('Request');
			$Request[$i]->addChild('ID', trim($rtml[$i][1]['target']));
			$Request[$i]->addChild('UserName');
			
			if(isset($rtml['Observers'])){
				//$Request[$i]->addChild('Observers', $rtml['Observers']);
				$Request[$i]->Observers=trim($rtml['Observers']);
			}else{
				$Request[$i]->addChild('Observers', $fullname);
			}
			
			if(isset($rtml['Description'])){
				//$Request[$i]->addChild('Description', trim($rtml['Description']));
				$Request[$i]->Description=trim($rtml['Description']);
			}else{
				echo "<font color=\"#ff0000\">Your project description cannot be blank</font><br>";
			}
			
			if(isset($rtml[$i]['schedule']['monitor'])){
				$Request[$i]->addChild('Reason', 'Monitor='.$rtml[$i]['schedule']['monitor']);
			}
			
			if(!isset($rtml['Project']) || $rtml['Project']==""){				
				echo "<font color=\"#ff0000\">Your project name for Plan $i cannot be blank</font><br>";
			}else{
				$Request[$i]->Project=trim($rtml['Project']);
			}			
			
			//Schedule
			$Schedule[$i]=$Request[$i]->addChild('Schedule');
			
			if(isset($rtml[$i]['schedule']['airmass']['maximum'])){
				if(isset($rtml[$i]['schedule']['airmass']['minimum'])){
					$airmassrange[$i]=$Schedule[$i]->addChild('AirmassRange');
					$airmassrange[$i]->addChild('Minimum', $rtml[$i]['schedule']['airmass']['minimum']);
					$airmassrange[$i]->addChild('Maximum', $rtml[$i]['schedule']['airmass']['maximum']);
				}else{
					$airmassrange[$i]=$Schedule[$i]->addChild('Airmass', $rtml[$i]['schedule']['airmass']['maximum']);
				}
			}
			
			if(isset($rtml[$i]['schedule']['altitude'])){
				$Schedule[$i]->addChild('Horizon', $rtml[$i]['schedule']['altitude']);
			}
			
			if(isset($rtml[$i]['schedule']['hourangle'])){
				$hourangle[$i]=$Schedule[$i]->addChild('HourAngleRange');
				$hourangle[$i]->addChild('East', $rtml[$i]['schedule']['hourangle']['minimum']);
				$hourangle[$i]->addChild('West', $rtml[$i]['schedule']['hourangle']['maximum']);
			}
			
			if(isset($rtml[$i]['schedule']['skycondition'])){
				$Schedule[$i]->addChild('SkyCondition', $rtml[$i]['schedule']['skycondition']);
			}
			
			//moon
			if(isset($rtml[$i]['schedule']['moon'])){
				$Moon[$i]=$Schedule[$i]->addChild('Moon');
				if(isset($rtml[$i]['schedule']['moon']['width']) && isset($rtml[$i]['schedule']['moon']['distance'])){
					$Moon[$i]->addChild('Distance', $rtml[$i]['schedule']['moon']['distance']);
					$Moon[$i]->addChild('Width', $rtml[$i]['schedule']['moon']['width']);					
				}elseif(isset($rtml[$i]['schedule']['moon']['phase'])){
					$Moon[$i]->addChild('Phase', $rtml[$i]['schedule']['moon']['phase']);
				}					
			}
			
			//timerange
				//schedule>timerange>earliest
				//schedule>timerange>latest
		
			if(isset($rtml[$i]['schedule']['timerange']) && isset($rtml[$i]['schedule']['timerange']['earliest']) && isset($rtml[$i]['schedule']['timerange']['latest'])){
				$Timerange[$i]=$Schedule[$i]->addChild('TimeRange');
				$Timerange[$i]->addChild('Earliest', str_replace (' ', 'T', $rtml[$i]['schedule']['timerange']['earliest']).":00");
				$Timerange[$i]->addChild('Latest', str_replace (' ', 'T', $rtml[$i]['schedule']['timerange']['latest']).":00");			
			}
			
			//priority
			$Schedule[$i]->addChild('Priority', 1);						
			
			if(isset($rtml['Telescope'])){
				$Request[$i]->addChild('Telescope', $rtml['Telescope']);
				$binning = $minbinning[$rtml['Telescope']];
			}else{
				$Request[$i]->addChild('Telescope', 2);
				$binning = 4;
			}
			
			
			
			$numTargets=0;
			
			//target loop
			for($j=1; $j<1000; $j++){
				
				$targetimages=0;
			
				if(isset($rtml[$i][$j]['target'])){
					$Target[$i][$j]=$Request[$i]->addChild('Target');
					$Target[$i][$j]->addAttribute('count', '1');
					$Target[$i][$j]->addChild('ID', $rtml[$i][$j]['target']);
					$Target[$i][$j]->addChild('Name', trim($rtml[$i][$j]['target']));
					$Target[$i][$j]->addChild('Description', trim($rtml[$i][$j]['target']));
					
					if(isset($rtml[$i][$j]['coord']['ra']) && isset($rtml[$i][$j]['coord']['dec'])){
						$Coords[$i][$j]=$Target[$i][$j]->addChild('Coordinates');
						$Coords[$i][$j]->addChild('RightAscension', $rtml[$i][$j]['coord']['ra']);
						$Coords[$i][$j]->addChild('Declination', $rtml[$i][$j]['coord']['dec']);
					}elseif(isset($rtml[$i][$j]['mpc'])){
						//this is adding spaces
						$Target[$i][$j]->addChild('OrbitalElements', $rtml[$i][$j]['mpc']);
						
					}
				
					//observation loop
				for($k=1; $k<1000; $k++){
					if(isset($rtml[$i][$j][$k]['filter'])){
						//if($rtml[$i][$j][$k]['filter']!="" && $rtml[$i][$j][$k]['count']!="" && $rtml[$i][$j][$k]['binning']!="" && $rtml[$i][$j][$k]['binning']!="" && $rtml[$i][$j][$k]['exptime']!=""){
						if($rtml[$i][$j][$k]['filter']!="" && $rtml[$i][$j][$k]['count']!="" && $rtml[$i][$j][$k]['exptime']!=""){
							$targetimages++;
							 $requestimages++;
							$Observation[$i][$j][$k]=$Target[$i][$j]->addChild('Picture');
							$Observation[$i][$j][$k]->addAttribute('count', $rtml[$i][$j][$k]['count']);
							//$Observation[$i][$j][$k]->addChild('Name', $rtml[$i][$j][$k]['filter']."_".$rtml[$i][$j][$k]['exptime']."s_".$rtml[$i][$j][$k]['binning']."x".$rtml[$i][$j][$k]['binning']);
							$Observation[$i][$j][$k]->addChild('Name', $rtml[$i][$j][$k]['filter']."_".$rtml[$i][$j][$k]['exptime']."s_".$binning."x".$binning);
							$Observation[$i][$j][$k]->addChild('ExposureTime', $rtml[$i][$j][$k]['exptime']);
							//$Observation[$i][$j][$k]->addChild('Binning', $rtml[$i][$j][$k]['binning']);
							$Observation[$i][$j][$k]->addChild('Binning', $binning);
							$Observation[$i][$j][$k]->addChild('Filter', $rtml[$i][$j][$k]['filter']);
					
				
						}
					}else{
						//break;
					}
				}
				
				}else{
					break;
				}
				
				if($targetimages==0){
					echo "<font color=\"#ff8000\">Target $i (".$rtml[$i][1]['target'].") was not included because it contained no observations (did you select a filter?)</font><br>";
					//echo"<pre>";
					//echo "$numRequests $numTargets\n";
					//echo print_r($xml);
					//echo "</pre>";
					unset($xml->Request[$numRequests]->Target[$numTargets]);
				}else{
					$numTargets++;
				}
			}
		}
	
		
	}else{
		if($i==1){echo"Error, no targets";}
		break;		
	}
	
	if($requestimages==0){
		echo "<font color=\"#ff8000\">Plan $i (".$rtml[$i][1]['target'].") was not included because it contained no observations (did you select a filter?)</font><br>";
		unset($xml->Request[$numRequests]);
	}else{
		$numRequests++;
	}
}
	
	if($numRequests==0){
		echo "It doesn't looks your your RTML contains any valid plans<br>";
	}
	
//$xml->asXML($file_path);
	$dom = new DOMDocument('1.0', 'iso-8859-1');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($xml->asXML());
	
	//$xml->asXML("rtml/editor/".$userid."_x.rtml");


	//$dom->loadXML($contents);
	$textareatext= $dom->saveXml();
	
	//echo $dom->saveXml()."<br>";
	//$textareatext= str_replace("   " , "  ", $dom->saveXml());//disabled=\"disabled\"
	//echo $textareatext."<br>";
	//echo "<br><textarea cols='80' rows='60'  >".$textareatext."</textarea>";
	echo"<div style=\"border: solid 1px orange; background-color:#ffffee; max-height:700px;overflow-y:scroll\">";
	
	//require_once('fshl/fshl.php');
	
	
	$output_module = 'HTML';
	$start_language = 'HTML';

	//$parser = new fshlParser($output_module);
	echo '<pre class="normal">';
	//echo $parser->highlightString('HTMLonly', str_replace("  ", "   ", $textareatext));
	print(highlight_string(str_replace("  ", "   ", $textareatext)));
	if(!isset($rtml['Project']) || $rtml['Project']==""){
		$rtml['Project']="No project name";
	}
	echo"</div><script>";
	echo " document.getElementById('download').setAttribute('href', \"dlrtml.php?name=".$rtml['Project']."\");  ";
	
	if($numRequests==0){
		echo " document.getElementById('topline').innerHTML = \"Your RTML has been created and saved and is shown below\";  ";
	}
	echo  "</script>";
	
	//$dom->save("rtml/editor/".$userid.".rtml");
	
	file_put_contents("rtml/editor/".$userid.".rtml", $textareatext);//preg_replace("/ {3}/", '  ', $textareatext));
	chmod ("rtml/editor/".$userid.".rtml", 0766);
	chgrp("rtml/editor/".$userid.".rtml", 1001);



/*
echo "<br><br><br><br><b>Debugging info below (you can ignore this) -----------<br><br>JSON array:</b> (how the uncompleted plan is saved server-side)<br><br>$input";
	
//print_r($rtml);

echo"<br><br><b>Tabular:</b> (easier to visualise)<br>";
html_show_array($rtml);
*/


}

require_once('../mFooter.php');
