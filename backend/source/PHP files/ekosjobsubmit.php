<?php

// File-based EKOS export path.
// Reads an approved RTML file, converts it to a neutral JSON job,
// writes it to ./jobs/outgoing/<machine_id>/, and updates rtml.status to 2.

require_once('../mHeader.php');
require_once('../mTop.php');
require_once('config.php');

function ekosjob_h($s){
	return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ekosjob_log_line($path, $line){
	$dir = dirname($path);
	if(!is_dir($dir)){
		@mkdir($dir, 0775, true);
	}
	@file_put_contents($path, date('c')." ".$line.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function ekosjob_ensure_dir($path){
	if(!is_dir($path)){
		if(!@mkdir($path, 0775, true)){
			throw new RuntimeException("Failed to create directory: $path");
		}
	}
	if(!is_writable($path)){
		throw new RuntimeException("Directory is not writable: $path");
	}
}

function ekosjob_atomic_write_json($path, $data){
	$dir = dirname($path);
	ekosjob_ensure_dir($dir);
	$tmp = $path.".tmp.".getmypid().".".bin2hex(random_bytes(4));
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if($json===false){
		throw new RuntimeException("Failed to encode JSON");
	}
	if(@file_put_contents($tmp, $json, LOCK_EX)===false){
		throw new RuntimeException("Failed to write temp file: $tmp");
	}
	if(!@rename($tmp, $path)){
		@unlink($tmp);
		throw new RuntimeException("Failed to move temp file into place: $path");
	}
}

function ekosjob_sanitize_text($value, $maxLen=500){
	$value = (string)$value;
	$value = preg_replace('/[^\P{C}\t\r\n]+/u', ' ', $value);
	$value = preg_replace('/\s+/u', ' ', trim($value));
	if($maxLen>0 && function_exists('mb_substr')){
		$value = mb_substr($value, 0, $maxLen);
	}elseif($maxLen>0){
		$value = substr($value, 0, $maxLen);
	}
	return $value;
}

function ekosjob_parse_float_or_null($value){
	if($value===null || $value==='') return null;
	if(is_numeric((string)$value)) return (float)$value;
	return null;
}

function ekosjob_parse_utc_datetime_or_null($value){
	$value = trim((string)$value);
	if($value==='') return null;
	$ts = strtotime($value . (preg_match('/Z|[+-]\d{2}:\d{2}$/', $value) ? '' : ' UTC'));
	if($ts===false) return null;
	return gmdate('c', $ts);
}

function ekosjob_iso_to_ts($iso){
	if($iso===null || $iso==='') return null;
	$ts = strtotime($iso);
	return $ts===false ? null : $ts;
}

function ekosjob_decimal_from_coord($value, $isRa=false){
	$value = trim((string)$value);
	if($value==='') return null;
	if(is_numeric($value)){
		$f = (float)$value;
		return $isRa ? $f * 15.0 : $f;
	}
	$parts = preg_split('/[:\s]+/', $value);
	if(count($parts)<2) return null;
	$sign = 1.0;
	if(!$isRa && strpos($parts[0], '-')===0) $sign = -1.0;
	$a = abs((float)$parts[0]);
	$b = isset($parts[1]) ? (float)$parts[1] : 0.0;
	$c = isset($parts[2]) ? (float)$parts[2] : 0.0;
	$deg = $a + ($b/60.0) + ($c/3600.0);
	return $isRa ? ($deg * 15.0) : ($deg * $sign);
}

function ekosjob_first_text($node, $path){
	$result = $node->xpath($path);
	if($result && isset($result[0])) return trim((string)$result[0]);
	return '';
}

function ekosjob_machine_id($telescope){
	return sprintf('scope%02d', (int)$telescope);
}

function ekosjob_default_scope_config(){
	$config = array();
	$active = isset($GLOBALS['activeScopes']) && is_array($GLOBALS['activeScopes']) ? $GLOBALS['activeScopes'] : array();
	foreach($active as $scopeNum){
		$scopeNum = (int)$scopeNum;
		$config[$scopeNum] = array(
			'name' => isset($GLOBALS['scopename'][$scopeNum]) ? $GLOBALS['scopename'][$scopeNum] : ('Scope '.$scopeNum),
			'machine_id' => ekosjob_machine_id($scopeNum),
			'status' => 2,
			'declination_min' => -90.0,
			'declination_max' => 90.0,
			'min_binning' => 1,
			'hard_min_binning' => 1,
			'filters' => array(),
			'host' => isset($GLOBALS['scopeip'][$scopeNum]) ? $GLOBALS['scopeip'][$scopeNum] : '',
		);
	}

	global $link;
	if(isset($link) && $link){
		$query = "SELECT * FROM obssetup WHERE num>1 ORDER BY num ASC";
		$result = @mysqli_query($link, $query);
		if($result){
			while($row = mysqli_fetch_array($result)){
				$scopeNum = (int)$row['num'];
				$limits = isset($row['limits']) ? explode('|', (string)$row['limits']) : array();
				$minbinning = isset($row['minbinning']) ? explode('|', (string)$row['minbinning']) : array();
				$filters = isset($row['filters']) ? array_values(array_filter(array_map('trim', explode('|', (string)$row['filters'])))) : array();
				$config[$scopeNum] = array_merge(isset($config[$scopeNum]) ? $config[$scopeNum] : array(), array(
					'name' => !empty($row['shortname']) ? $row['shortname'] : (isset($GLOBALS['scopename'][$scopeNum]) ? $GLOBALS['scopename'][$scopeNum] : ('Scope '.$scopeNum)),
					'machine_id' => ekosjob_machine_id($scopeNum),
					'status' => isset($row['status']) ? (int)$row['status'] : 2,
					'declination_min' => isset($limits[0]) && is_numeric($limits[0]) ? (float)$limits[0] : -90.0,
					'declination_max' => isset($limits[1]) && is_numeric($limits[1]) ? (float)$limits[1] : 90.0,
					'min_binning' => isset($minbinning[0]) && is_numeric($minbinning[0]) ? (int)$minbinning[0] : 1,
					'hard_min_binning' => isset($minbinning[0]) && is_numeric($minbinning[0]) ? (int)$minbinning[0] : 1,
					'filters' => $filters,
					'host' => isset($GLOBALS['scopeip'][$scopeNum]) ? $GLOBALS['scopeip'][$scopeNum] : '',
				));
			}
		}
	}
	return $config;
}

function ekosjob_find_existing_queue_files($outgoingDir, $sentDir, $rtmlId){
	$matches = array();
	foreach(array($outgoingDir, $sentDir) as $dir){
		if(!is_dir($dir)) continue;
		$pattern = rtrim($dir, '/\\').'/*/ekos_'.(int)$rtmlId.'_*.json';
		foreach((glob($pattern) ?: array()) as $file){
			$matches[] = $file;
		}
	}
	return $matches;
}

function ekosjob_load_approval_sidecar($path){
	if(!file_exists($path)) return null;
	$raw = @file_get_contents($path);
	if($raw===false) throw new RuntimeException("Failed to read approval file: $path");
	$data = json_decode($raw, true);
	if(!is_array($data)) throw new RuntimeException("Approval file is not valid JSON: $path");
	return $data;
}

function ekosjob_parse_rtml($xml, $telescopeConfig, &$warnings, &$errors){
	$contactUser  = ekosjob_sanitize_text((string)$xml->Contact->User, 100);
	$contactEmail = ekosjob_sanitize_text((string)$xml->Contact->Email, 200);
	$org          = ekosjob_sanitize_text((string)$xml->Contact->Organization, 200);
	$requests = $xml->Request;
	$planCount = count($requests);
	if($planCount<1){
		$errors[] = 'RTML contains no Request blocks';
		return null;
	}

	$job = array(
		'schema_version' => 1,
		'source_format' => 'rtml',
		'contact' => array(
			'user' => $contactUser,
			'email' => $contactEmail,
			'organisation' => $org,
		),
		'plans' => array(),
		'telescope' => null,
		'machine_id' => null,
		'telescope_name' => '',
		'telescope_host' => '',
		'project' => '',
		'description' => '',
		'observers' => '',
	);

	$selectedTelescope = null;
	$allProjects = array();
	$allDescriptions = array();
	$allObservers = array();

	$reqCounter = 0;
	foreach($requests as $req){
		$reqCounter++;
		$project     = ekosjob_sanitize_text((string)$req->Project, 200);
		$description = ekosjob_sanitize_text((string)$req->Description, 1000);
		$observers   = ekosjob_sanitize_text((string)$req->Observers, 300);
		$telescope   = (int)((string)$req->Telescope);
		$planId      = ekosjob_sanitize_text((string)$req->ID, 150);
		$userName    = ekosjob_sanitize_text((string)$req->UserName, 100);

		if($project==='') $errors[] = 'Plan '.($reqCounter).': Project name cannot be blank';
		if($description==='') $errors[] = 'Plan '.($reqCounter).': Description cannot be blank';
		if($telescope<=0) $errors[] = 'Plan '.($reqCounter).': Telescope not given';

		if($selectedTelescope===null){
			$selectedTelescope = $telescope;
		}elseif($selectedTelescope!==$telescope){
			$errors[] = 'RTML file must only use one telescope';
		}

		$scopeCfg = isset($telescopeConfig[$telescope]) ? $telescopeConfig[$telescope] : null;
		if($scopeCfg===null){
			$warnings[] = 'Plan '.($reqCounter).": Telescope $telescope is not defined in ekosjobsubmit.php config";
		} else {
			if(isset($scopeCfg['status']) && (int)$scopeCfg['status']===1) $warnings[] = 'Plan '.($reqCounter).": Telescope $telescope is marked maintenance/test";
			if(isset($scopeCfg['status']) && (int)$scopeCfg['status']===0) $warnings[] = 'Plan '.($reqCounter).": Telescope $telescope is marked not automated";
		}

		$schedule = $req->Schedule;
		$earliestIso = ekosjob_parse_utc_datetime_or_null(ekosjob_first_text($schedule, './TimeRange/Earliest'));
		$latestIso   = ekosjob_parse_utc_datetime_or_null(ekosjob_first_text($schedule, './TimeRange/Latest'));
		if($latestIso!==null && ekosjob_iso_to_ts($latestIso)<time()) $errors[] = 'Plan '.($reqCounter).': Latest start time must be in the future';
		if($earliestIso!==null && $latestIso!==null){
			$dt1=ekosjob_iso_to_ts($earliestIso);
			$dt2=ekosjob_iso_to_ts($latestIso);
			if($dt1!==null && $dt2!==null){
				$span = $dt2-$dt1;
				if($span<0) $errors[] = 'Plan '.($reqCounter).': Latest time is earlier than earliest time';
				elseif($span<86400) $warnings[] = 'Plan '.($reqCounter).': Time range covers less than 1 day';
			}
		}

		$plan = array(
			'plan_index' => $reqCounter,
			'plan_id' => $planId,
			'user_name' => $userName,
			'project' => $project,
			'description' => $description,
			'observers' => $observers,
			'telescope' => $telescope,
			'machine_id' => ekosjob_machine_id($telescope),
			'schedule' => array(
				'sky_condition' => ekosjob_sanitize_text((string)$schedule->SkyCondition, 50),
				'priority' => ekosjob_parse_float_or_null((string)$schedule->Priority),
				'time_range' => array('earliest' => $earliestIso, 'latest' => $latestIso),
				'airmass' => array(
					'minimum' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './Airmass/Minimum')),
					'maximum' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './Airmass/Maximum')),
				),
				'hour_angle' => array(
					'minimum' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './HourAngle/Minimum')),
					'maximum' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './HourAngle/Maximum')),
				),
				'altitude' => ekosjob_parse_float_or_null((string)$schedule->Altitude),
				'moon' => array(
					'down' => ekosjob_first_text($schedule, './Moon/Down'),
					'distance' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './Moon/Distance')),
					'width' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './Moon/Width')),
					'phase' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './Moon/Phase')),
				),
				'repeat' => array(
					'interval_days' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './SeriesConstraint/Interval')),
					'count' => ekosjob_parse_float_or_null(ekosjob_first_text($schedule, './SeriesConstraint/Count')),
					'until' => ekosjob_parse_utc_datetime_or_null(ekosjob_first_text($schedule, './SeriesConstraint/Until')),
				),
			),
			'targets' => array(),
			'exposure_seconds_total' => 0,
		);

		$targetCounter = 0;
		foreach($req->Target as $targetNode){
			$targetCounter++;
			$raText = trim((string)$targetNode->Coordinates->RightAscension);
			$decText = trim((string)$targetNode->Coordinates->Declination);
			if($raText==='') $raText = trim((string)$targetNode->RightAscension);
			if($decText==='') $decText = trim((string)$targetNode->Declination);
			$raDeg = ekosjob_decimal_from_coord($raText, true);
			$decDeg = ekosjob_decimal_from_coord($decText, false);

			$target = array(
				'target_index' => $targetCounter,
				'name' => ekosjob_sanitize_text((string)$targetNode->Name, 200),
				'description' => ekosjob_sanitize_text((string)$targetNode->Description, 500),
				'ra' => $raText,
				'dec' => $decText,
				'ra_deg' => $raDeg,
				'dec_deg' => $decDeg,
				'pictures' => array(),
				'exposure_seconds_total' => 0,
			);

			if($scopeCfg!==null && $decDeg!==null){
				$dMin = isset($scopeCfg['declination_min']) ? $scopeCfg['declination_min'] : -90;
				$dMax = isset($scopeCfg['declination_max']) ? $scopeCfg['declination_max'] : 90;
				if($decDeg<$dMin || $decDeg>$dMax){
					$errors[] = 'Plan '.($reqCounter).', target '.($targetCounter).': target declination is outside telescope limits';
				}
			}

			$pictureCounter = 0;
			foreach($targetNode->Picture as $pic){
				$pictureCounter++;
				$count = (int)($pic->attributes()->count ?: 1);
				$exposureTime = ekosjob_parse_float_or_null((string)$pic->ExposureTime);
				$binning = (int)((string)$pic->Binning ?: 1);
				$filter = ekosjob_sanitize_text((string)$pic->Filter, 50);
				if($count<1) $count=1;
				if($count<2) $warnings[] = 'Plan '.($reqCounter).', target '.($targetCounter).': it is advisable to take at least two images per observation';
				if($exposureTime===null || $exposureTime<=0){
					$errors[] = 'Plan '.($reqCounter).', target '.($targetCounter).': invalid exposure time';
					$exposureTime=0;
				}
				if($scopeCfg!==null){
					$filters = isset($scopeCfg['filters']) ? $scopeCfg['filters'] : array();
					if($filter!=='' && !in_array($filter, $filters, true)) $errors[] = 'Plan '.($reqCounter).', target '.($targetCounter).": filter '$filter' is not available on telescope $telescope";
					$minBin = isset($scopeCfg['min_binning']) ? (int)$scopeCfg['min_binning'] : 1;
					$hardMin = isset($scopeCfg['hard_min_binning']) ? (int)$scopeCfg['hard_min_binning'] : $minBin;
					if($binning<$hardMin) $errors[] = 'Plan '.($reqCounter).', target '.($targetCounter).": binning {$binning}x{$binning} is below the hard minimum";
					elseif($binning<$minBin) $warnings[] = 'Plan '.($reqCounter).', target '.($targetCounter).": binning {$binning}x{$binning} is below the recommended minimum";
				}
				$expTotal = $count * $exposureTime;
				$target['exposure_seconds_total'] += $expTotal;
				$plan['exposure_seconds_total'] += $expTotal;
				$target['pictures'][] = array(
					'picture_index' => $pictureCounter,
					'filter' => $filter,
					'count' => $count,
					'exposure_time' => $exposureTime,
					'binning' => $binning,
					'exposure_total' => $expTotal,
				);
			}

			if($target['exposure_seconds_total']>14400) $errors[] = 'Plan '.($reqCounter).', target '.($targetCounter).': single target exposure exceeds 4 hours';
			elseif($target['exposure_seconds_total']>7200) $warnings[] = 'Plan '.($reqCounter).', target '.($targetCounter).': target exposure exceeds 2 hours';

			$plan['targets'][] = $target;
		}

		if($plan['schedule']['repeat']['interval_days']!==null) $warnings[] = 'Plan '.$reqCounter.': repeat scheduling is present and will need explicit runner support';
		$job['plans'][] = $plan;
		$allProjects[] = $project;
		$allDescriptions[] = $description;
		$allObservers[] = $observers;
	}

	$job['telescope'] = $selectedTelescope;
	$job['machine_id'] = ekosjob_machine_id($selectedTelescope);
	$job['telescope_name'] = isset($GLOBALS['scopename'][$selectedTelescope]) ? $GLOBALS['scopename'][$selectedTelescope] : ('Scope '.$selectedTelescope);
	$job['telescope_host'] = isset($GLOBALS['scopeip'][$selectedTelescope]) ? $GLOBALS['scopeip'][$selectedTelescope] : '';
	$job['project'] = ekosjob_sanitize_text(implode(' | ', array_unique(array_filter($allProjects))), 300);
	$job['description'] = ekosjob_sanitize_text(implode(' | ', array_unique(array_filter($allDescriptions))), 1000);
	$job['observers'] = ekosjob_sanitize_text(implode(' | ', array_unique(array_filter($allObservers))), 500);
	return $job;
}

if($displayPage){
	echo"<div ><div style=\" width:800px; margin:0px auto;\">";

	$RTML_DIR = __DIR__.'/rtml';
	$OUTGOING_DIR = __DIR__.'/jobs/outgoing';
	$SENT_DIR = __DIR__.'/jobs/sent';
	$APP_LOG = __DIR__.'/logs/ekosjobsubmit.log';

	$TELESCOPE_CONFIG = ekosjob_default_scope_config();
	$TELESCOPE_CONFIG[6] = array_merge(isset($TELESCOPE_CONFIG[6]) ? $TELESCOPE_CONFIG[6] : array(), array('name'=>'JHT','machine_id'=>'scope06','status'=>2,'declination_min'=>-30.0,'declination_max'=>90.0,'min_binning'=>1,'hard_min_binning'=>1,'filters'=>array('L','R','G','B','Ha','OIII','SII')));
	$TELESCOPE_CONFIG[9] = array_merge(isset($TELESCOPE_CONFIG[9]) ? $TELESCOPE_CONFIG[9] : array(), array('name'=>'CDK24','machine_id'=>'scope09','status'=>2,'declination_min'=>-20.0,'declination_max'=>85.0,'min_binning'=>4,'hard_min_binning'=>4,'filters'=>array('L','R','G','B','Ha','OIII','SII')));

	try{
		ekosjob_ensure_dir($OUTGOING_DIR);
		if(!is_dir($SENT_DIR)) @mkdir($SENT_DIR, 0775, true);

		if(!isset($_GET['id'])) throw new RuntimeException('No RTML id given');
		$rtmlid = (int)$_GET['id'];
		$priority = isset($_GET['priority']) ? (int)$_GET['priority'] : 1;
		$resubmit = isset($_GET['resubmit']);
		if($priority<-100){ $priority=-100; echo"Priority changed to -100, for lower numbers adjust manually in Browser.<br>"; }
		elseif($priority>100){ $priority=100; echo"Priority changed to 100, for higher numbers adjust manually in Browser.<br>"; }

		$file_path = $RTML_DIR.'/'.$rtmlid.'.rtml';
		$approval_path = $RTML_DIR.'/'.$rtmlid.'.approved.json';
		if(!file_exists($file_path)) throw new RuntimeException('RTML file does not exist: '.$file_path);

		$query="SELECT * FROM `rtml` WHERE `id`=$rtmlid LIMIT 1";
		$result = mysqli_query($link, $query) or die(mysqli_error($link));
		if(mysqli_num_rows($result)<1) throw new RuntimeException('RTML record not found');
		$row = mysqli_fetch_array($result);
		$owner_userid = (int)$row['userid'];
		$status = (int)$row['status'];
		if(!($level>8 || $userid==$owner_userid)) throw new RuntimeException('You do not have permission to queue this RTML plan');
		if($status==2 && !$resubmit){
			echo"Already queued/exported, <a href=\"ekosjobsubmit.php?id=$rtmlid&priority=$priority&resubmit=1\">queue again?</a><br><br>";
			echo"</div></div>";
			require_once('../mFooter.php');
			exit;
		}elseif($status<1 && !$resubmit){
			throw new RuntimeException('Plan is not approved for queueing');
		}

		$approval = ekosjob_load_approval_sidecar($approval_path);
		if($approval===null || empty($approval['approved'])) throw new RuntimeException('Approval sidecar missing or not approved');

		$existing = ekosjob_find_existing_queue_files($OUTGOING_DIR, $SENT_DIR, $rtmlid);
		if(!$resubmit && count($existing)>0){
			echo"<b>This RTML already appears to have been exported.</b><br>";
			foreach($existing as $f) echo ekosjob_h($f)."<br>";
			echo"<br><a href=\"ekosjobsubmit.php?id=$rtmlid&priority=$priority&resubmit=1\">Queue anyway as a new export?</a><br><br>";
			echo"</div></div>";
			require_once('../mFooter.php');
			exit;
		}

		libxml_use_internal_errors(true);
		$xml = simplexml_load_file($file_path);
		if($xml===false){
			$errs = libxml_get_errors();
			$msg = 'Failed to parse RTML';
			if(!empty($errs)) $msg .= ': '.trim($errs[0]->message);
			throw new RuntimeException($msg);
		}

		$warnings = array();
		$errors = array();
		$job = ekosjob_parse_rtml($xml, $TELESCOPE_CONFIG, $warnings, $errors);
		if($job===null) throw new RuntimeException('Failed to build job from RTML');

		if(count($errors)>0){
			echo"<div style=\"color:#cc0000;\"><b>Validation failed</b><br><br>";
			foreach($errors as $e) echo'- '.ekosjob_h($e).'<br>';
			echo"</div>";
			ekosjob_log_line($APP_LOG, "user=$userid rtml=$rtmlid queue_failed_validation");
			echo"</div></div>";
			require_once('../mFooter.php');
			exit;
		}

		$machineId = isset($job['machine_id']) ? $job['machine_id'] : ekosjob_machine_id($job['telescope']);
		$machineOutgoingDir = $OUTGOING_DIR.'/'.$machineId;
		$machineSentDir = $SENT_DIR.'/'.$machineId;
		ekosjob_ensure_dir($machineOutgoingDir);
		if(!is_dir($machineSentDir)) @mkdir($machineSentDir, 0775, true);

		$queueRef = 'ekos_'.$rtmlid.'_'.gmdate('Ymd\THis\Z');
		$jobFile = $machineOutgoingDir.'/'.$queueRef.'.json';
		$metaFile = $machineOutgoingDir.'/'.$queueRef.'.meta.json';

		$approval['machine_id'] = $machineId;
		$approval['telescope'] = $job['telescope'];
		$approval['telescope_name'] = $job['telescope_name'];

		$jobPayload = array(
			'schema_version' => 1,
			'queue_ref' => $queueRef,
			'job_type' => 'ekos',
			'rtml_id' => $rtmlid,
			'priority' => $priority,
			'submitted_at' => gmdate('c'),
			'submitted_by' => (int)$userid,
			'submitted_level' => (int)$level,
			'resubmit' => $resubmit ? true : false,
			'machine_id' => $machineId,
			'telescope' => array(
				'id' => $job['telescope'],
				'name' => $job['telescope_name'],
				'host' => $job['telescope_host'],
			),
			'source' => array('type'=>'rtml','path'=>$file_path,'approval_path'=>$approval_path),
			'approval' => $approval,
			'job' => $job,
			'warnings' => $warnings,
			'runner' => array(
				'state' => 'outgoing',
				'claimed_at' => null,
				'claimed_by' => null,
				'ekos_scheduler_file' => null,
				'ekos_sequence_file' => null,
			),
		);
		$metaPayload = array(
			'queue_ref' => $queueRef,
			'job_type' => 'ekos',
			'rtml_id' => $rtmlid,
			'state' => 'outgoing',
			'created_at' => gmdate('c'),
			'submitted_by' => (int)$userid,
			'priority' => $priority,
			'machine_id' => $machineId,
			'telescope' => $job['telescope'],
			'project' => $job['project'],
			'resubmit' => $resubmit ? true : false,
			'error' => null,
		);

		ekosjob_atomic_write_json($jobFile, $jobPayload);
		ekosjob_atomic_write_json($metaFile, $metaPayload);

		$query3="UPDATE `rtml` SET `status`=2 WHERE `id` = $rtmlid LIMIT 1";
		mysqli_query($link, $query3) or die(mysqli_error($link));

		ekosjob_log_line($APP_LOG, "user=$userid rtml=$rtmlid queue_ref=$queueRef machine_id=$machineId telescope=".$job['telescope']." priority=$priority queued_for_ekos");

		echo"<div style=\"color:#008800;\"><b>Plan queued for EKOS successfully.</b></div><br>";
		echo"<b>Queue reference:</b> ".ekosjob_h($queueRef)."<br>";
		echo"<b>Machine ID:</b> ".ekosjob_h($machineId)."<br>";
		echo"<b>Job file:</b> ".ekosjob_h($jobFile)."<br>";
		echo"<b>Meta file:</b> ".ekosjob_h($metaFile)."<br>";
		if(!empty($warnings)){
			echo"<br><div style=\"color:#dd8800;\"><b>Warnings</b><br>";
			foreach($warnings as $w) echo'- '.ekosjob_h($w).'<br>';
			echo"</div>";
		}
		echo"<br>The Ubuntu EKOS runner can now pull this JSON from its per-machine outgoing directory.";

	}catch(Throwable $e){
		echo"<div style=\"color:#cc0000;\"><b>ERROR:</b> ".ekosjob_h($e->getMessage())."</div>";
		ekosjob_log_line($APP_LOG, "user=".(isset($userid)?$userid:'unknown')." rtml=".(isset($rtmlid)?$rtmlid:'none')." queue_error=\"".str_replace('"', "'", $e->getMessage())."\"");
	}

	echo"</div></div>";
}

require_once('../mFooter.php');
?>
>
re_once('../mFooter.php');
?>
		echo"</div>";
		}
		echo"<br>The Ubuntu EKOS runner can now pull this JSON from its per-machine outgoing directory.";

	}catch(Throwable $e){
		echo"<div style=\"color:#cc0000;\"><b>ERROR:</b> ".ekosjob_h($e->getMessage())."</div>";
		ekosjob_log_line($APP_LOG, "user=".(isset($userid)?$userid:'unknown')." rtml=".(isset($rtmlid)?$rtmlid:'none')." queue_error=\"".str_replace('"', "'", $e->getMessage())."\"");
	}

	echo"</div></div>";
}

require_once('../mFooter.php');
?>
>
re_once('../mFooter.php');
?>
