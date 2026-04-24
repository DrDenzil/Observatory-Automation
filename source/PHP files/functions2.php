<?php
function quad($in){
if ($in< 0){
$out = $in + ((0 - (floor($in/360)))*360);
}else{
if ($in > 360){
$out = $in - ((floor($in/360))*360);
}else{
$out = $in;
}}
return($out);
}

function quad180($in){
if ($in< -180){
$out = $in + ((0 - (floor($in/360)))*360);
}else{
if ($in > 180){
$out = $in - ((floor($in/360))*360);
}else{
$out = $in;
}}
return($out);
}

function r($planet,$t){
include("pt" .$planet. ".php");
$r = ($R0 + ($R1 * $t) + ($R2 * $t * $t) + ($R3 * $t * $t * $t) + ($R4 * $t * $t * $t * $t) +($R5 * $t * $t * $t * $t * $t));
return($r);
}
function l($planet,$t){
include("pt" .$planet. ".php");
$l = ($L0 + ($L1 * $t) + ($L2 * $t * $t) + ($L3 * $t * $t * $t) + ($L4 * $t * $t * $t * $t) + ($L5 * $t * $t * $t * $t * $t));
return($l);
}
function b($planet,$t){
include("pt" .$planet. ".php");
$b = ($B0 + ($B1 * $t) + ($B2 * $t * $t) + ($B3 * $t * $t * $t) + ($B4 * $t * $t * $t * $t) + ($B5 * $t * $t * $t * $t * $t));
return($b);
}

function delta($r, $b, $l, $R, $Slon, $Slat){
$x = ($r *(cos($b))*(cos($l)))+($R *(cos($Slon)));
$y = ($r *(cos($b))*(sin($l)))+($R *(sin($Slon)));
$z = ($r * (sin($b))) + ($R * (sin($Slat)));
$delta = sqrt(($x*$x)+($y*$y)+($z*$z));
return($delta);
}

function cosi($R,$r,$b,$L,$l,$delta){
$cosi=($r - ($R*(cos($b))*(cos($l-$L))))/$delta;
return($cosi);
}

function epsilon($T){
$omega = 125.04452 - (1934.136261*$T) + (0.0020708 *$T * $T) + (($T*$T*$T)/450000);
$L = 280.4665 + (36000.7698 * $T);
$Ldash = 218.3165 + (481267.8813 * $T);
$deltaepsilon = (((9.20/3600) * (cos(deg2rad($omega)))) + ((0.57/3600) * (cos(deg2rad(2*$L)))) + ((0.10/3600) * (cos(deg2rad(2*$Ldash)))) - ((0.09/3600) * (cos(deg2rad(2*$omega)))));
$epsilon0 = 23.4392911111 - (0.013004166667 *$T) - ((1.63889/10000000) *$T * $T) + ($T*$T*$T * (5.03611/10000000));
$epsilon = $epsilon0 + $deltaepsilon;
return($epsilon);
}


function dec($r, $b, $l, $R, $B, $L, $delta, $t, $planet){
$T = 10 * $t;
$tau = ((0.0057755183 * $delta) )/365250;
$t = $t - $tau;
$r = r($planet,$t);
$l = l($planet,$t);
$b = b($planet,$t);
$x = ($r *(cos($b))*(cos($l)))-($R *(cos($B))*(cos($L)));
$y = ($r *(cos($b))*(sin($l)))-($R *(cos($B))*(sin($L)));
$z = ($r * (sin($b))) - ($R * (sin($B)));
$lambda = atan2($y, $x);
$beta = atan2($z,(sqrt(($x*$x)+($y*$y))));
$l = l('earth',$t);
$Slon = $l + pi();
$e = deg2rad(0.016708634 - (0.000042037 * $T) - (0.0000001267*$T*$T));
$pi = deg2rad(102.937375 + (1.71946 * $T) + (0.00046 *$T*$T));
$k = deg2rad(20.49552/3600);
$deltalambda = (((0-$k)*(cos($Slon - $lambda)))+($e * $k*(cos($pi - $lambda))))/(cos($beta));
$deltabeta = - ($k*(sin($beta))*((sin($Slon-$lambda))-($e*(sin($pi-$lambda)))));
$omega = 125.04452 - (1934.136261*$T) + (0.0020708 *$T * $T) + (($T*$T*$T)/450000);
$L = 280.4665 + (36000.7698 * $T);
$Ldash = 218.3165 + (481267.8813 * $T);
$psi = deg2rad((((-17.20/3600)*(sin(deg2rad($omega))))-((1.32/3600)*(sin(deg2rad(2*$L))))-((0.23/3600)*(sin(deg2rad(2*$Ldash))))-((0.21/3600)*(sin(deg2rad(2*$omega))))));
$lambda = $lambda + $deltalambda - (deg2rad(0.09027/3600)) + $psi;
$beta = $beta + $deltabeta + (deg2rad(0.05535/3600));
$e0 = epsilon($T);
$dec = rad2deg(asin(((sin($beta))*(cos(deg2rad($e0))))+((cos($beta))*(sin(deg2rad($e0)))*(sin($lambda)))));
return($dec);
}

function ra($r, $b, $l, $R, $B, $L, $delta, $t, $planet){
$T = 10 * $t;
$tau = ((0.0057755183 * $delta) )/365250;
$t = $t - $tau;
$r = r($planet,$t);
$l = l($planet,$t);
$b = b($planet,$t);
$x = ($r *(cos($b))*(cos($l)))-($R *(cos($B))*(cos($L)));
$y = ($r *(cos($b))*(sin($l)))-($R *(cos($B))*(sin($L)));
$z = ($r * (sin($b))) - ($R * (sin($B)));
$lambda = atan2($y, $x);
$beta = atan2($z,(sqrt(($x*$x)+($y*$y))));
$l = l('earth',$t);
$Slon = $l + pi();
$e = deg2rad(0.016708634 - (0.000042037 * $T) - (0.0000001267*$T*$T));
$pi = deg2rad(102.937375 + (1.71946 * $T) + (0.00046 *$T*$T));
$k = deg2rad(20.49552/3600);
$deltalambda = (((0-$k)*(cos($Slon - $lambda)))+($e * $k*(cos($pi - $lambda))))/(cos($beta));
$deltabeta = - ($k*(sin($beta))*((sin($Slon-$lambda))-($e*(sin($pi-$lambda)))));
$omega = 125.04452 - (1934.136261*$T) + (0.0020708 *$T * $T) + (($T*$T*$T)/450000);
$L = 280.4665 + (36000.7698 * $T);
$Ldash = 218.3165 + (481267.8813 * $T);
$psi = deg2rad((((-17.20/3600)*(sin(deg2rad($omega))))-((1.32/3600)*(sin(deg2rad(2*$L))))-((0.23/3600)*(sin(deg2rad(2*$Ldash))))-((0.21/3600)*(sin(deg2rad(2*$omega))))));
$lambda = $lambda + $deltalambda - (deg2rad(0.09027/3600)) + $psi;
$beta = $beta + $deltabeta + (deg2rad(0.05535/3600));
$e0 = epsilon($T);
$ra = rad2deg(atan2((  ((cos(deg2rad($e0)))*(sin($lambda)))-((tan($beta))*(sin(deg2rad($e0))))),(cos($lambda))));
$ra = quad($ra);
return($ra/15);
}



function d($decimal){
if ($decimal > 0){
$degs= floor($decimal);
}else{
$degs= ceil($decimal);
}
return($degs);
}
function m($decimal){
if ($decimal > 0){
$decimal = $decimal;
}else{
$decimal = - $decimal;
}
$degs= floor($decimal);
$mini = (($decimal - $degs) * 60);
$mins = floor($mini);
return($mins);
}
function s($decimal){
if ($decimal > 0){
$decimal = $decimal;
}else{
$decimal = - $decimal;
}
$degs= floor($decimal);
$mini = (($decimal - $degs) * 60);
$mins = floor($mini);
$seci = (($mini - $mins) * 60);
$secs = round($seci,2);
return($secs);
}

?>