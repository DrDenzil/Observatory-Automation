<?php 
$Ld= 218.3164477 + (481267.88123421*$T) - (0.0015786*$T*$T)+(($T*$T*$T)/538841)-(($T*$T*$T*$T)/65194000); 
$D =297.8501921 + (445267.1114034*$T) - (0.0018819*$T*$T)+(($T*$T*$T)/545868)-(($T*$T*$T*$T)/113065000); 
$M = 357.5291092 + (35999.0502909*$T) - (0.0001536*$T*$T)+(($T*$T*$T)/24490000); 
$Md = 134.9633964 + (477198.8675055*$T) + (0.0087414*$T*$T)+(($T*$T*$T)/69699)-(($T*$T*$T*$T)/14712000); 
$F = 93.2720950 + (483202.0175233*$T) - (0.0036539*$T*$T)+(($T*$T*$T)/3526000)-(($T*$T*$T*$T)/863310000); 

$ii=180-$D-6.289*sin(deg2rad($Md))+2.1*sin(deg2rad($M))-1.274*sin(deg2rad(2*$D-$Md))-0.658*sin(deg2rad(2*$D))-0.214*sin(deg2rad(2*$M))-0.110*sin(deg2rad($D)); 


$A1=119.75+(131.849*$T); 
$A2=53.09+(479264.290*$T); 
$A3=313.45+(481266.484*$T); 
$E=1- (0.002516*$T)-(0.0000074*$T*$T); 


//longitude 
$mlong= 
+6288774*sin(deg2rad($Md)) 
+1274027*sin(deg2rad((2*$D)-$Md)) 
+658314*sin(deg2rad((2*$D))) 
+213618*sin(deg2rad((2*$Md))) 
-185116*$E*sin(deg2rad($M)) 
-114332*sin(deg2rad((2*$F))) 
+58793*sin(deg2rad((2*$D)-(2*$Md))) 
+57066*$E*sin(deg2rad((2*$D)-$M-$Md)) 
+53322*sin(deg2rad((2*$D)-$Md)) 
+45758*$E*sin(deg2rad((2*$D)-$M)) 
-40923*$E*sin(deg2rad($M+$Md)) 
-34720*sin(deg2rad($D)) 
-30383*$E*sin(deg2rad($M+$Md)) 
+15327*sin(deg2rad((2*$D)-(2*$F))) 
-12528*sin(deg2rad($Md+(2*$F))) 
+10980*sin(deg2rad($Md-(2*$F))) 
+10675*sin(deg2rad((4*$D)-$Md)) 
+10034*sin(deg2rad((3*$Md))) 
+8548*sin(deg2rad((4*$D)-(2*$Md))) 
-7888*$E*sin(deg2rad((2*$D)+$M-$Md)) 
-6766*$E*sin(deg2rad((2*$D)+$M)) 
-5163*sin(deg2rad($D-$Md)) 
+4987*$E*sin(deg2rad($D-$M)) 
+4036*$E*sin(deg2rad((2*$D)+$M+$Md)) 
+3994*sin(deg2rad((2*$D)+(2*$Md))) 
+3861*sin(deg2rad((4*$D))) 
+3665*sin(deg2rad((2*$D)-(3*$Md))) 
-2689*$E*sin(deg2rad($M-(2*$Md))) 
-2602*sin(deg2rad((2*$D)-$Md+(2*$F))) 
+2390*$E*sin(deg2rad((2*$D)-$M-(2*$Md))) 
-2348*sin(deg2rad($D+$Md)) 
+2236*$E*$E*sin(deg2rad((2*$D)-(2*$M))) 
-2120*$E*sin(deg2rad($M+(2*$Md))) 
-2069*$E*$E*sin(deg2rad((2*$M))) 
+2048*$E*sin(deg2rad((2*$D)-(2*$M)-$Md)) 
-1773*sin(deg2rad((2*$D)+$Md-(2*$F))) 
-1595*sin(deg2rad((2*$D)+(2*$F))) 
+1215*$E*sin(deg2rad((4*$D)-$M-$Md)) 
-1110*sin(deg2rad((2*$Md)-(2*$F))) 
-892*sin(deg2rad((3*$D)-$Md)) 
-810*$E*sin(deg2rad((2*$D)+$M+$Md)) 
+759*$E*sin(deg2rad((4*$D)-$M-(2*$Md))) 
-713*$E*$E*sin(deg2rad((2*$M)-$Md)) 
-700*$E*$E*sin(deg2rad((2*$D)+(2*$M)-$Md)) 
+691*$E*sin(deg2rad((2*$D)+$M-(2*$Md))) 
+596*$E*sin(deg2rad((2*$D)-$M-(2*$F))) 
+549*sin(deg2rad((4*$D)+$Md)) 
+537*sin(deg2rad((4*$Md))) 
+520*$E*sin(deg2rad((4*$D)-$M)) 
-487*sin(deg2rad($D-(2*$Md))) 
-399*$E*sin(deg2rad((2*$D)+$M-(2*$F))) 
-381*sin(deg2rad((2*$Md)-(2*$F))) 
+351*$E*sin(deg2rad($D+$M+$Md)) 
-340*sin(deg2rad((3*$D)-(2*$Md))) 
+330*sin(deg2rad((4*$D)-(3*$Md))) 
+327*$E*sin(deg2rad((2*$D)-$M+(2*$Md))) 
-323*$E*$E*sin(deg2rad((2*$M)+$Md)) 
+299*$E*sin(deg2rad($D+$M-$Md)) 
+294*sin(deg2rad((2*$D)+(3*$Md))) 
+3958*sin(deg2rad($A1)) 
+1962*sin(deg2rad($Ld-$F)) 
+318*sin(deg2rad($A2)); 


//latitude 
$mlat= 
+5128122*sin(deg2rad($F)) 
+280602*sin(deg2rad($Md+$F)) 
+277692*sin(deg2rad($Md-$F)) 
+173237*sin(deg2rad((2*$D)-$F)) 
+55413*sin(deg2rad((2*$D)-$Md+$F)) 
+46271*sin(deg2rad((2*$D)-$Md-$F)) 
+32573*sin(deg2rad((2*$D)+$F)) 
+17198*sin(deg2rad((2*$Md)+$F)) 
+9266*sin(deg2rad((2*$D)+$Md-$F)) 
+8822*sin(deg2rad((2*$Md)-$F)) 
+8216*$E*sin(deg2rad((2*$D)-$M-$F)) 
+4324*sin(deg2rad((2*$D)-(2*$Md)-$F)) 
+4200*sin(deg2rad((2*$D)+$Md+$F)) 
-3359*$E*sin(deg2rad((2*$D)+$M-$F)) 
+2463*$E*sin(deg2rad((2*$D)-$M-$Md+$F)) 
+2211*$E*sin(deg2rad((2*$D)-$M+$F)) 
+2065*$E*sin(deg2rad((2*$D)-$M-$Md-$F)) 
-1870*$E*sin(deg2rad($M-$Md-$F)) 
+1828*sin(deg2rad((4*$D)-$Md-$F)) 
-1794*$E*sin(deg2rad($M+$F)) 
-1749*sin(deg2rad((3*$F))) 
-1565*$E*sin(deg2rad($M-$Md+$F)) 
-1491*sin(deg2rad($D+$F)) 
-1475*$E*sin(deg2rad($M+$Md+$F)) 
-1410*$E*sin(deg2rad($M+$Md-$F)) 
-1344*$E*sin(deg2rad($M-$F)) 
-1335*sin(deg2rad($D-$F)) 
+1107*sin(deg2rad((3*$Md)+$F)) 
+1021*sin(deg2rad((4*$D)-$F)) 

+833*sin(deg2rad((4*$D)-$Md+$F)) 
+777*sin(deg2rad($Md-(3*$F))) 
+671*sin(deg2rad((4*$D)-(2*$Md)+$F)) 
+607*sin(deg2rad((2*$D)-(3*$F))) 
+596*sin(deg2rad((2*$D)+(2*$Md)-$F)) 
+491*sin(deg2rad((2*$D)-$M+$Md-$F)) 
-451*sin(deg2rad((2*$D)-(2*$Md)+$F)) 
+439*sin(deg2rad((3*$Md)-$F)) 
+422*sin(deg2rad((2*$D)+(2*$Md)+$F)) 
+421*sin(deg2rad((2*$D)-(3*$Md)-$F)) 
-366*$E*sin(deg2rad((2*$D)+$M-$Md+$F)) 
-351*$E*sin(deg2rad((2*$D)+$M+$F)) 
+331*sin(deg2rad((4*$D)+$F)) 
+315*$E*sin(deg2rad((2*$D)-$M+$Md+$F)) 
+302*$E*$E*sin(deg2rad((2*$D)-(2*$M)-$F)) 
-283*sin(deg2rad($Md+(3*$F))) 
-229*$E*sin(deg2rad((2*$D)+$M+$Md-$F)) 
+223*$E*sin(deg2rad($D+$M-$F)) 
+223*$E*sin(deg2rad($D+$M+$F)) 
-220*$E*sin(deg2rad($M-(2*$Md)-$F)) 
-220*$E*sin(deg2rad((2*$D)+$M-$Md-$F)) 
-185*sin(deg2rad($D+$Md+$F)) 
+181*$E*sin(deg2rad((2*$D)-$M-(2*$Md)-$F)) 
-177*$E*sin(deg2rad($M+(2*$Md)+$F)) 
+176*sin(deg2rad((4*$D)-(2*$Md)+$F)) 
+166*$E*sin(deg2rad((4*$D)-$M-$Md-$F)) 
-164*sin(deg2rad($D+$Md-$F)) 
+132*sin(deg2rad((4*$D)+$Md-$F)) 
-119*sin(deg2rad($D-$Md-$F)) 
+115*$E*sin(deg2rad((4*$D)-$M-$F)) 
+107*$E*sin(deg2rad((2*$D)-(2*$M)+$F)) 
-2235*sin(deg2rad($Ld)) 
+382*sin(deg2rad($A3)) 
+175*sin(deg2rad($A1-$F)) 
+175*sin(deg2rad($A1+$F)) 
+127*sin(deg2rad($Ld-$Md)) 
-115*sin(deg2rad($Ld+$Md)); 

$lambda = $Ld + ($mlong/1000000); 
$beta= deg2rad($mlat/1000000); 

$omega = 125.04452 - (1934.136261*$T) + (0.0020708 *$T * $T) + (($T*$T*$T)/450000); 
$L = 280.4665 + (36000.7698 * $T); 
$Ldash = 218.3165 + (481267.8813 * $T); 
$psi = (((-17.20/3600)*(sin(deg2rad($omega))))-((1.32/3600)*(sin(deg2rad(2*$L))))-((0.23/3600)*(sin(deg2rad(2*$Ldash))))-((0.21/3600)*(sin(deg2rad(2*$omega))))); 

$lambda = deg2rad($lambda + $psi); 

//$lambda = deg2rad(quad($lambda + $psi)); 

// ra and dec 
$ra = atan2((((cos(deg2rad($epsilon)))*(sin($lambda)))-((tan($beta))*(sin(deg2rad($epsilon))))),(cos($lambda))); 
$dec = asin(((sin($beta))*(cos(deg2rad($epsilon))))+((cos($beta))*(sin(deg2rad($epsilon)))*(sin($lambda)))); 
$GST=280.46061837+360.98564736629*($JDE-2451545)+(0.000387933*$T*$T)-(($T*$T*$T)/38710000); 
$LST=deg2rad($GST-$longitude); 
$H=$LST-$ra; 

$moonalt=rad2deg(asin((sin($latitude)*sin($dec))+(cos($latitude)*cos($dec)*cos($H)))); 

$k= (1+(cos(deg2rad(($ii)))))/2;
$moonphase= ($k*100);
?>