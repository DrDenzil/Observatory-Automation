<?php
//$userid=1;


	if(isset($userid)){
		
		$start = microtime(true);


		$input=file_get_contents("rtml/editor/".$userid.".json");
		$rtml=json_decode($input, TRUE);
		
		for($i=0; $i<1000; $i++){
			//plans
			
			if(isset($rtml[$i])){				
				//echo "$i<br>";	
				
				for($j=0; $j<1000; $j++){
					//targets
					
					if(isset($rtml[$i][$j])){
						//echo "$i $j<br>";		

						/*
						for($k=0; $k<1000; $k++){
							//targets
							
							if(isset($rtml[$i][$j])){
								//echo "$i $j<br>";		

							
							}				
						}	*/
						$arr = array();
						$n=1;
						foreach ($rtml[$i][$j] as $key => $value) {
							if(!is_numeric($key)){
								$arr[$key]=$value;
							}else{
								if($value['filter']!=""){
									$arr[$n]=$value;
									$n++;
								}
							}
						
						}
						
						$rtml[$i][$j]=$arr;
						
					}				
				}		

				if(isset($rtml[$i]['schedule']['moon']['width'])){
					if(!is_numeric($rtml[$i]['schedule']['moon']['width'])){
						$rtml[$i]['schedule']['moon']['width']=6;
					}else if($rtml[$i]['schedule']['moon']['width']<1 || $rtml[$i]['schedule']['moon']['width']>100){
						$rtml[$i]['schedule']['moon']['width']=6;
					}
				}		

				if(isset($rtml[$i]['schedule']['moon']['distance'])){
					if(!is_numeric($rtml[$i]['schedule']['moon']['distance'])){
						$rtml[$i]['schedule']['moon']['distance']=60;
					}else if($rtml[$i]['schedule']['moon']['distance']<1 || $rtml[$i]['schedule']['moon']['distance']>180){
						$rtml[$i]['schedule']['moon']['distance']=60;
					}
				}	
				
				if(isset($rtml[$i]['schedule']['hourangle']['maximum'])){
					if(!is_numeric($rtml[$i]['schedule']['hourangle']['maximum'])){
						$rtml[$i]['schedule']['hourangle']['maximum']=3;
					}else if($rtml[$i]['schedule']['hourangle']['maximum']<0 || $rtml[$i]['schedule']['hourangle']['maximum']>12){
						$rtml[$i]['schedule']['hourangle']['maximum']=3;
					}
				}	
				
				if(isset($rtml[$i]['schedule']['hourangle']['minimum'])){
					if(!is_numeric($rtml[$i]['schedule']['hourangle']['minimum'])){
						$rtml[$i]['schedule']['hourangle']['minimum']=-3;
					}else if($rtml[$i]['schedule']['hourangle']['minimum']>0 || $rtml[$i]['schedule']['hourangle']['minimum']<-12){
						$rtml[$i]['schedule']['hourangle']['minimum']=-3;
					}
				}	

				if(isset($rtml[$i]['schedule']['monitor'])){
					if(!is_numeric($rtml[$i]['schedule']['monitor'])){
						unset($rtml[$i]['schedule']['monitor']);
					}
				}					
			}			
		}
	
	/*
		echo"<pre>";
		print_r($rtml);
		echo"</pre>";
		*/
		if(file_put_contents("rtml/editor/".$userid.".json", json_encode($rtml))){
			//$json['success']=true;
			
		}else{
			//$json['success']=false;
			//$json['reason']="writefail";
		}
	
		$time_elapsed_secs = microtime(true) - $start;
		
		//echo "<br>time taken: $time_elapsed_secs";
	}
	
	
?>