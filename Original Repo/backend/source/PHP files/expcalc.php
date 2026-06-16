<?php

require_once('../mHeader.php');
if($displayPage){
?>

<script src='//observatory.herts.ac.uk/js/jquery-1.11.1.min.js'></script>
<style>

input.fix {
    color: #999999;
}
</style>

<script>

//if telescopes/cameras/filters were objects, this would be neater...

var flux0f= new Array(4);

flux0f['i']=26336.80149*16;
flux0f['r']=33768.64782*16;
flux0f['v']=40062.31374*16;
flux0f['b']=91274.92985*16;
flux0f['c']=200890.0234*16;
flux0f['ha']=2345.329852*16;
flux0f['oiii']=3363.985517*16;
flux0f['sii']=2987.506088*16;

var exptimes = new Array(16);
exptimes[0]=1;
exptimes[1]=2;
exptimes[2]=3;
exptimes[3]=4;
exptimes[4]=5;
exptimes[5]=10;
exptimes[6]=15;
exptimes[7]=20;
exptimes[8]=30;
exptimes[9]=45;
exptimes[10]=60;
exptimes[11]=90;
exptimes[12]=120;
exptimes[13]=180;
exptimes[14]=240;
exptimes[15]=300;

integral = new Array();	

integral[0] = 0.000000;
integral[1] = 0.005159;
integral[2] = 0.020478;
integral[3] = 0.043585;
integral[4] = 0.077596;
integral[5] = 0.118150;
integral[6] = 0.164268;
integral[7] = 0.217997;
integral[8] = 0.274646;
integral[9] = 0.332261;
integral[10] = 0.393318;	// 1 sigma
integral[11] = 0.454462;
integral[12] = 0.512948;
integral[13] = 0.570322;
integral[14] = 0.624838;
integral[15] = 0.675165;
integral[16] = 0.722630;
integral[17] = 0.764472;
integral[18] = 0.802221;
integral[19] = 0.835890;
integral[20] = 0.864689;	// 2 sigmas
integral[21] = 0.889805;
integral[22] = 0.911216;
integral[23] = 0.929147;
integral[24] = 0.943950;
integral[25] = 0.956116;
integral[26] = 0.965995;
integral[27] = 0.973828;
integral[28] = 0.980198;
integral[29] = 0.985077;
integral[30] = 0.988897;	// 3 sigmas
integral[31] = 0.991824;
integral[32] = 0.994022;
integral[33] = 0.995690;
integral[34] = 0.996918;
integral[35] = 0.997812;
integral[36] = 0.998470;
integral[37] = 0.998938;
integral[38] = 0.999268;
integral[39] = 0.999503;
integral[40] = 0.999665;
integral[41] = 0.999776;
integral[42] = 0.999853;
integral[43] = 0.999904;
integral[44] = 0.999938;
integral[45] = 0.999960;
integral[46] = 0.999975;
integral[47] = 0.999984;
integral[48] = 0.999990;
integral[49] = 0.999994;
integral[50] = 0.999996;
integral[51] = 0.999998;
integral[52] = 0.999999;
integral[53] = 0.999999;
integral[54] = 1.000000;
integral[55] = 1.000000;
integral[56] = 1.000000;
integral[57] = 1.000000;
integral[58] = 1.000000;
integral[59] = 1.000000;


var gains = new Array();

gains['stl6303']= new Array();
gains['q49000']= new Array();
gains['sxvrh18']= new Array();
gains['asi6200lg']= new Array();
gains['asi6200hg']= new Array();
gains['asi6200']= new Array();
gains['st7e']= new Array();

gains['q49000'][1]= 1.5;
gains['q49000'][2]= 1.7;
gains['q49000'][3]= 1.7;
gains['q49000'][4]= 1.7;

gains['stl6303'][1]= 1.4;
gains['stl6303'][2]= 2.3;
gains['stl6303'][3]= 2.3;
gains['stl6303'][4]= 2.3;

gains['sxvrh18'][1]= 0.35;
gains['sxvrh18'][2]= 0.35;
gains['sxvrh18'][3]= 0.35;
gains['sxvrh18'][4]= 0.35;

gains['st7e'][1]= 1.29;
gains['st7e'][2]= 1.29;
gains['st7e'][3]= 1.29;
gains['st7e'][4]= 1.29;

gains['asi6200lg'][1]= 0.78;
gains['asi6200lg'][2]= 0.78*4;
gains['asi6200lg'][3]= 0.78*9;
gains['asi6200lg'][4]= 0.78*16;

gains['asi6200hg'][1]= 0.024665765;
gains['asi6200hg'][2]= 0.024665765*4;
gains['asi6200hg'][3]= 0.024665765*9;
gains['asi6200hg'][4]= 0.024665765*16;

gains['asi6200'][1]= 0.24665763974189758;
gains['asi6200'][2]= 0.24665763974189758*4;
gains['asi6200'][3]= 0.24665763974189758*9;
gains['asi6200'][4]= 0.24665763974189758*16;

var sensorType = new Array();


sensorType['stl6303']= "ccd";
sensorType['q49000']= "ccd";
sensorType['sxvrh18']= "ccd";
sensorType['st7e']= "ccd";
sensorType['asi6200lg']= "ccd";
sensorType['asi6200']= "ccd";

sensorType['asi6200lg']= "aps";
sensorType['asi6200hg']= "aps";
sensorType['asi6200']= "aps";

var flengths = new Array();

flengths['ckt']=4064;
flengths['jht']=4064;
flengths['rpt']=4064;
flengths['cdk']=3974;
flengths['int']=700;

var filtereffs = new Array();

filtereffs['ckt']= new Array();
filtereffs['jht']= new Array();
filtereffs['rpt']= new Array();
filtereffs['cdk']= new Array();
filtereffs['int']= new Array();

filtereffs['ckt']['i'] = 100;
filtereffs['ckt']['r'] = 84;
filtereffs['ckt']['v'] = 88;
filtereffs['ckt']['b'] = 75;
filtereffs['ckt']['c'] = 100;
filtereffs['ckt']['ha'] = 82;
filtereffs['ckt']['oiii'] = 85;

filtereffs['jht']['i'] = 100;
filtereffs['jht']['r'] = 82;
filtereffs['jht']['v'] = 87;
filtereffs['jht']['b'] = 77;
filtereffs['jht']['c'] = 100;
filtereffs['jht']['ha'] = 100;
filtereffs['jht']['sii'] = 100;

filtereffs['cdk']['i'] = 87;
filtereffs['cdk']['r'] = 80;
filtereffs['cdk']['v'] = 100;
filtereffs['cdk']['b'] = 75;
filtereffs['cdk']['c'] = 74;
filtereffs['cdk']['ha'] = 84;
filtereffs['cdk']['oiii'] = 100;

filtereffs['rpt']['i'] = 100;
filtereffs['rpt']['r'] = 84;
filtereffs['rpt']['v'] = 88;
filtereffs['rpt']['b'] = 75;
filtereffs['rpt']['c'] = 100;
filtereffs['rpt']['ha'] = 100;
filtereffs['rpt']['oii'] = 100;

filtereffs['int']['i'] = 100;
filtereffs['int']['r'] = 84;
filtereffs['int']['v'] = 88;
filtereffs['int']['b'] = 75;
filtereffs['int']['c'] = 100;
filtereffs['int']['ha'] = 100;

var transmissions = new Array();

transmissions['ckt']=new Array();
transmissions['jht']=new Array();
transmissions['rpt']=new Array();
transmissions['cdk']=new Array();
transmissions['int']=new Array();

transmissions['ckt']['i'] = 65;
transmissions['ckt']['r'] = 84;
transmissions['ckt']['v'] = 79;
transmissions['ckt']['b'] = 63;
transmissions['ckt']['c'] = 79;
transmissions['ckt']['ha'] = 84;
transmissions['ckt']['oiii'] = 79;

transmissions['jht']['i'] = 65;
transmissions['jht']['r'] = 84;
transmissions['jht']['v'] = 79;
transmissions['jht']['b'] = 63;
transmissions['jht']['c'] = 79;
transmissions['jht']['ha'] = 84;
transmissions['jht']['sii'] = 84;

transmissions['cdk']['i'] = 70;
transmissions['cdk']['r'] = 88;
transmissions['cdk']['v'] = 91;
transmissions['cdk']['b'] = 80;
transmissions['cdk']['c'] = 85;
transmissions['cdk']['ha'] = 88;
transmissions['cdk']['oiii'] = 91;

transmissions['rpt']['i'] = 65;
transmissions['rpt']['r'] = 84;
transmissions['rpt']['v'] = 79;
transmissions['rpt']['b'] = 63;
transmissions['rpt']['c'] = 79;
transmissions['rpt']['ha'] = 84;

transmissions['int']['i'] = 65;
transmissions['int']['r'] = 84;
transmissions['int']['v'] = 79;
transmissions['int']['b'] = 63;
transmissions['int']['c'] = 79;

var qes = new Array();

qes['stl6303']= new Array();
qes['q49000']= new Array();
qes['sxvrh18']= new Array();
qes['st7e']= new Array();
qes['asi6200lg']= new Array();
qes['asi6200hg']= new Array();
qes['asi6200']= new Array();

qes['q49000']['i']= 45;
qes['q49000']['r']= 70;
qes['q49000']['v']= 60;
qes['q49000']['b']= 40;
qes['q49000']['c']= 60;

qes['stl6303']['i']= 30;
qes['stl6303']['r']= 65;
qes['stl6303']['v']= 50;
qes['stl6303']['b']= 30;
qes['stl6303']['c']= 45;

qes['sxvrh18']['i']= 30;
qes['sxvrh18']['r']= 55;
qes['sxvrh18']['v']= 55;
qes['sxvrh18']['b']= 40;
qes['sxvrh18']['c']= 40;

qes['st7e']['i']= 35;
qes['st7e']['r']= 60;
qes['st7e']['v']= 40;
qes['st7e']['b']= 20;
qes['st7e']['c']= 40;

qes['asi6200lg']['i']= 30;
qes['asi6200lg']['r']= 70;
qes['asi6200lg']['v']= 90;
qes['asi6200lg']['b']= 70;
qes['asi6200lg']['c']= 60;
qes['asi6200lg']['ha']= 60;
qes['asi6200lg']['sii']= 60;
qes['asi6200lg']['oiii']= 90;

qes['asi6200hg']['i']= 30;
qes['asi6200hg']['r']= 70;
qes['asi6200hg']['v']= 90;
qes['asi6200hg']['b']= 70;
qes['asi6200hg']['c']= 60;
qes['asi6200hg']['ha']= 60;
qes['asi6200hg']['sii']= 60;
qes['asi6200hg']['oiii']= 90;

qes['asi6200']['i']= 30;
qes['asi6200']['r']= 70;
qes['asi6200']['v']= 90;
qes['asi6200']['b']= 70;
qes['asi6200']['c']= 60;
qes['asi6200']['ha']= 60;
qes['asi6200']['sii']= 60;
qes['asi6200']['oiii']= 90;

var extcoeffs = new Array();

extcoeffs['i']=0.1;
extcoeffs['r']=0.15;
extcoeffs['v']=0.25;
extcoeffs['b']=0.4;
extcoeffs['c']=0.25;
extcoeffs['ha']=0.13;
extcoeffs['sii']=0.13;
extcoeffs['oiii']=0.25;




function changeScope(){
	
	var scope = $("#telescope option:selected").val();
	var filter  = $("#filter").val();

	console.log(scope);
	
	
	$("#flength").val(flengths[scope]);
	$("#transmission").val(transmissions[scope][filter]);
	
	if(scope=="ckt"){		
		$("#aperture").val("406.4");
		$("#secdiam").val("127");
		$("#camera").val("asi6200");		
		//$("#transmission").val("67");		
		$("#camera").val("asi6200").change();	
	}else if(scope=="jht"){		
		$("#aperture").val("406.4");
		$("#secdiam").val("127");
		$("#camera").val("asi6200");		
		//$("#transmission").val("80");		
		$("#camera").val("asi6200").change();		
	}else if(scope=="rpt"){		
		$("#aperture").val("406.4");
		$("#secdiam").val("127");
		$("#camera").val("asi6200");	
		//$("#transmission").val("90");		
		$("#camera").val("asi6200").change();		
	}else if(scope=="int"){		
		$("#aperture").val("102");
		$("#secdiam").val("0");
		$("#camera").val("q49000");	
		//$("#transmission").val("90");		
		$("#camera").val("q49000").change();		
	}else if(scope=="cdk"){		
		$("#aperture").val("610");
		$("#secdiam").val("286.7");
		$("#camera").val("asi6200");	
		//$("#transmission").val("90");		
		$("#camera").val("asi6200").change();		
	}
	
	var aperture = $("#aperture").val();
	var secdiam = $("#secdiam").val();
	var area = ((aperture/2)*(aperture/2)*Math.PI)-((secdiam/2)*(secdiam/2)*Math.PI)
	
	$("#aparea").val(Math.round(area)/100);
	
	
	changeCamera();
	changeFilter();
	updateFlux();
	
}

function updateQE(){
	
	var camera= $("#camera option:selected").val();
	console.log(camera);
	var filter = $("#filter").val();
	console.log(filter);

	$("#qe").val(qes[camera][filter]);
	
}	

function changeCamera(){
	
	var camera= $("#camera option:selected").val();
	
	
	console.log(camera);
	
	if(camera=="stl6303"){			
		$("#pxsize").val("9");
		$("#binning").val("2");
		$("#rn").val("13.5");
		$("#idark").val("1");
		$("#temp").val("-20");
		$("#ddouble").val("6.3");
		$("#tref").val("25");
	}else if(camera=="q49000"){			
		$("#pxsize").val("12");
		$("#binning").val("2");
		$("#rn").val("7");
		$("#idark").val("0.55");
		$("#temp").val("-20");
		$("#ddouble").val("7");
		$("#tref").val("25");
	}else if(camera=="sxvrh18"){			
		$("#pxsize").val("5.4");
		$("#binning").val("2");
		$("#rn").val("7");
		$("#idark").val("1");
		$("#temp").val("-20");
		$("#ddouble").val("5.8");
		$("#tref").val("10");
	}else if(camera=="asi6200lg"){			
		$("#pxsize").val("3.76");
		$("#binning").val("4");
		$("#rn").val("3.5");
		$("#idark").val("0.000222");
		$("#temp").val("0");
		$("#ddouble").val("5.8");
		$("#tref").val("0");
	}else if(camera=="asi6200hg"){			
		$("#pxsize").val("3.76");
		$("#binning").val("4");
		$("#rn").val("1.5");
		$("#idark").val("0.000222");
		$("#temp").val("0");
		$("#ddouble").val("5.8");
		$("#tref").val("0");
	}else if(camera=="asi6200"){			
		$("#pxsize").val("3.76");
		$("#binning").val("4");
		$("#rn").val("1.5");
		$("#idark").val("0.000222");
		$("#temp").val("0");
		$("#ddouble").val("5.8");
		$("#tref").val("0");
	}else if(camera=="st7e"){			
		$("#pxsize").val("9");
		$("#binning").val("1");
		$("#rn").val("15");
		$("#idark").val("0.04");
		$("#temp").val("-20");
		$("#ddouble").val("7");
		$("#tref").val("-10");
	}
	
	updateGain();
	updateQE();
	
}

function changeFilter(){
	
	var filter  = $("#filter").val();
	var scope = $("#telescope option:selected").val();
	

	$("#extcoef").val(extcoeffs[filter]);
	$("#filtereff").val(filtereffs[scope][filter]);
	$("#transmission").val(transmissions[scope][filter]);

	updateQE();
	updateFlux();
	
}

function updateGain(){
	var camera = $("#camera option:selected").val();
	var binning = $("#binning").val();
	
	var gain = gains[camera][binning];

	console.log("gain "+gain);
	
	$("#gain").val(gain.toPrecision(4));
	
}

function updateFlux(){
	var scope = $("#telescope option:selected").val();	
	var filter  = $("#filter").val();
	
	//var flux0=flux0arr[scope][filter];
	
	var transmission = $("#transmission").val()/100;
	var area = $("#aparea").val();
	var filtereff = $("#filtereff").val()/100;
	var qe	= $("#qe").val()/100;
	
	console.log("ar "+area +" tr "+transmission+" filt "+filtereff+" qe "+qe);
	console.log(flux0f[filter]*area);
	
	console.log("flux0 "+(flux0f[filter]*transmission*area*filtereff*qe));
	var flux0=flux0f[filter]*transmission*area*filtereff*qe;
		
	$("#flux0").val(flux0);
	
}


function calc(){

	var scope = $("#telescope option:selected").val();
	var flength = parseFloat($("#flength").val());
	var aperture = parseFloat($("#aperture").val());
	var secdiam = parseFloat($("#secdiam").val());

	var area = ((aperture/2)*(aperture/2)*Math.PI)-((secdiam/2)*(secdiam/2)*Math.PI)
	
	$("#aparea").val(Math.round(area)/100);
	
	updateFlux();

	var reps = parseFloat($("#reps").val());	
	var camera = $("#camera option:selected").val();
	
	//var transmis = parseFloat($("#transmis").val());

	var pxsize = parseFloat($("#pxsize").val());
	var binning = parseFloat($("#binning").val());
	var qe  =parseFloat( $("#qe").val());
	var rn = parseFloat($("#rn").val());
	var gain  = parseFloat($("#gain").val());
	var idark  =parseFloat( $("#idark").val()); //pA/cm^2
	var temp  = parseFloat($("#temp").val());
	var ddouble  = parseFloat($("#ddouble").val());
	var tref  = parseFloat($("#tref").val());		
	
	var filter  = $("#filter").val();
	var objmag  =parseFloat( $("#objmag").val());
	var seeing  =parseFloat( $("#seeing").val());
	var airmass  = parseFloat($("#airmass").val());
	var skymag  = parseFloat($("#skymag").val());
	var aprad  = parseFloat($("#aprad").val());
	var exptime  = parseFloat($("#exptime").val());
	
	var k = parseFloat($("#extcoef").val());
	
	var flux0 = parseFloat($("#flux0").val()); //e- /s
	
	var totalexp = reps*exptime;
	
	if(totalexp<300){
		$("#totalexp").val(totalexp);
		$("#expunits").html("secs");
	}else if(totalexp<3600){
		$("#totalexp").val((totalexp/60).toPrecision(2));
		$("#expunits").html("mins");
	}else{
		$("#totalexp").val((totalexp/3600).toPrecision(2));
		$("#expunits").html("hours");
	}

	var unbinned_dark_current = pxsize*pxsize * (idark/ 16.022) * Math.exp( 0.69315 * (temp -tref) / ddouble); //e- / s / unbinned pix
	
	var dark_current = unbinned_dark_current*binning*binning; //e- / s / binned pix
	
	$("#darkc").val(dark_current.toPrecision(6));
			
	//var dark_noise = dark_current*exptime;
	var dark_noise = dark_current*exptime*reps; //dark noise squared

	if(sensorType[camera]=="aps"){
		var read_noise = rn*rn*binning*binning*reps;	//read noise squared
	}else{
		//ccd
		var read_noise = rn*rn*reps;	//squared - binning independant for ccd
	}
	
	var gain_noise = (gain*gain-1)/12;		//squared
	var gain_noise2 = Math.pow(2/gain,2);		//squared		
	var gain_noise3 = Math.pow(gain/2,2);		//squared
	
	
	$("#brn").val((Math.sqrt(read_noise)/gain).toPrecision(3));
	
	console.log("rn "+ read_noise);
	console.log("gn "+ gain_noise +" "+gain_noise2+" "+gain_noise3);
	
	var base_noise = Math.sqrt(dark_noise+read_noise+gain_noise);
	//$("#basenoise").val(base_noise);
	
	var scale = pxsize*binning / (flength/ 206264.8062 ) / 1000;
	$("#imscal").val(scale.toPrecision(4));
	
	var seeingpx = seeing/ scale;
	
	$("#fwhmp").val(seeingpx.toPrecision(6));
	
	var skyflux =(flux0 * Math.exp( -0.4 * Math.log(10) * skymag ) * scale * scale) ; //e- per binned pixel /s
	$("#skyflux").val(skyflux.toPrecision(6));
	
	var bgflux=skyflux+  dark_current;
	$("#bgflux").val(bgflux.toPrecision(6));
	
	var pedestal=100;
	
	var bgcount =(bgflux*exptime)/gain+pedestal;		
	$("#bgcount").val(bgcount.toPrecision(6));
	
	var bgsnr=Math.sqrt(bgcount);
	//$("#bgsnr").val(bgsnr.toPrecision(6));
	
	var skytobg=skyflux/bgflux;
	//$("#skytobg").val(skytobg.toPrecision(6));


	
	var exobjmag = objmag + (k * airmass);		
	$("#exobjmag").val(parseFloat(exobjmag.toPrecision(6)));
	
	var aparea = Math.PI  * (aprad * scale) * (aprad*scale); //"^2
	var apareapx = Math.PI  * aprad * aprad; //"^2
	
	var objflux = Math.pow(2.5118864315,0-exobjmag)*flux0; //e- / sec
	
	$("#objflux").val(objflux.toPrecision(6));
	
	var objcount =(objflux*exptime)/gain; //ADU	
	$("#objcount").val(objcount.toPrecision(6));
	
	var aperxfwhm = 2 * aprad * scale / seeing;
	
	var instmag = -2.5*Math.log10(objcount/exptime);
	
	var zp = objmag - instmag ; 
	
	$("#zp").val(zp.toPrecision(4));

	var sigmas = aperxfwhm * 2.354820 / 2;	// convert diameter to sigma's

	var index = sigmas * 10;

	var index1 = Math.floor( index );

	var aperfrac;

	if ( index1 < 0 ){	// below 0 radius		
		aperfrac = 0;
	}else if ( index > 59 ){	// 6 sigmas or greater
		aperfrac = 1;
	}else{
		var frac = index - index1;

		aperfrac = (1-frac) * integral[ index1 ] + frac * integral[ index1+1 ];
	}

	$("#apint").val((aperfrac*100).toPrecision(6));
	
	//var snr =Math.sqrt(objcount/(1+apareapx*(bgcount+read_noise+gain_noise)/objcount));
	
	var snr2 =Math.sqrt(objcount/(1+apareapx*(bgcount+read_noise+gain_noise)/objcount));
	
	
	//var snr = (objflux*exptime)/Math.pow((objflux*exptime)+(skyflux*exptime*apareapx)+(read_noise+gain_noise2*apareapx)+(dark_noise*apareapx),0.5);
	console.log(objflux+ " "+skyflux+ " "+apareapx+ " "+read_noise+ " "+dark_noise)
	
		
	var snr = (objflux*exptime*reps*aperfrac)/Math.pow((objflux*exptime*reps*aperfrac)+(skyflux*exptime*reps*apareapx)+(read_noise*apareapx)+(dark_noise*apareapx),0.5);
	
	var sourcenoise = objflux*exptime*reps*aperfrac;
	var skynoise = skyflux*exptime*reps*apareapx;
	var readoutnoise =read_noise*apareapx;
	var darknoise = dark_noise*apareapx;
	
	
	var sn_adu = Math.sqrt(sourcenoise)/gain;
	var skn_adu = Math.sqrt(skynoise)/gain;
	var rn_adu = Math.sqrt(readoutnoise)/gain;
	var dn_adu = Math.sqrt(darknoise)/gain;
	
	console.log("aperture area "+ apareapx);
	
	console.log("source noise "+ sn_adu);
	console.log("sky noise "+ skn_adu);
	console.log("read noise "+ rn_adu);
	console.log("dark noise "+ dn_adu);
	
	var totalnoise = sourcenoise+skynoise+readoutnoise+darknoise;
	
	
	var totalnoise2 = sn_adu+skn_adu+rn_adu+dn_adu;
	
	$("#snr").val(snr.toPrecision(6));
	
	
	var magerr = 2.5*Math.log10(1+1/snr);
	
	$("#magerr").val(magerr.toPrecision(3));
	
	console.log("snr "+ snr2 +" "+snr);
	/*
	$("#sourcenoise").val((100*sourcenoise/totalnoise).toPrecision(4));
	$("#skynoise").val((100*skynoise/totalnoise).toPrecision(4));
	$("#readoutnoise").val((100*readoutnoise/totalnoise).toPrecision(4));
	$("#darknoise").val((100*darknoise/totalnoise).toPrecision(4));*/
	
			
	$("#sourcenoise").val((100*sn_adu/totalnoise2).toPrecision(4));
	$("#skynoise").val((100*skn_adu/totalnoise2).toPrecision(4));
	$("#readoutnoise").val((100*rn_adu/totalnoise2).toPrecision(4));
	$("#darknoise").val((100*dn_adu/totalnoise2).toPrecision(4));
	
	var peakval = (objcount / (2*Math.PI*Math.pow(0.51*seeingpx,2)))+bgcount;
	$("#peakval").val(peakval.toPrecision(6));
	
	
	if(peakval>50000){
		$("#peakval").css('color', 'red');
		$("#warning").html("The peak pixel value is above 50k. This will likely saturate the pixel and photometry will not be possible.");
	}else if(peakval>40000){
		$("#peakval").css('color', 'red');
		$("#warning").html("The peak pixel value is above 40k. This risks saturating the pixel and photometry will not be possible.");
	}else{
		$("#warning").html("");
		$("#peakval").css('color', 'black');
	}
	
	if(exptime<10){
		$("#warning2").html("Warning: Short exposures times may not capture enough stars to <a href=\"https://observatory.herts.ac.uk/wiki/Plate_Solving\" target=\"_blank\">plate solve</a>.");
	}else{
		$("#warning2").html("");	
	}
	

}

function findExp(){
	var exptime;
	var peakval;
	var i;
	

	
	for(i=0; i<16; i++){
		
		exptime=exptimes[i];
		$("#exptime").val(exptime);
		calc();
		
		peakval= $("#peakval").val();
		console.log(exptime +" "+peakval);
		if(peakval>30000){
			break;
		}
		
	}
	
	if(i==0){
		
	}else if(peakval>30000){
		$("#exptime").val(exptimes[i-1]);
		calc();
	}
	
	if($("#warning").html()==""){
		if(i==16){
			$("#warning2").html("Maximum recommended exposure of 300s reached. Use multiple exposures to reach desired SNR.");
			
		}else if(i<=5){
			//$("#warning2").html("Warning: Short exposures times may not capture enough stars to <a href=\"https://observatory.herts.ac.uk/wiki/Plate_Solving\" target=\"_blank\">plate solve</a>.");
			
		}else{
			$("#warning2").html("");
		}
	}else{
		$("#warning2").html("");
	}

	
}

function findSNR(){
	
	var targetSNR = parseFloat($("#snr").val());
	$("#warning").html("");
	$("#warning2").html("");
	
	console.log("target "+targetSNR);
	
	var exptime;
	var peakval;
	var i;
	
	$("#reps").val(1);
	
	for(i=0; i<16; i++){
		
		exptime=exptimes[i];
		$("#exptime").val(exptime);
		calc();
		
		SNR= parseFloat($("#snr").val());
		console.log("loop "+i+" "+exptime +" "+SNR);
		if(SNR>targetSNR){
			console.log("target reached "+SNR+">"+targetSNR);
			break;
		}
		
	}
	
	if(SNR<targetSNR){
		for(i=0; i<287; i++){
			var reps = parseInt($("#reps").val())+1;
			$("#reps").val(reps);
			calc();
			SNR= parseFloat($("#snr").val());
			if(SNR>targetSNR){
				console.log("target reached "+SNR+">"+targetSNR);
				break;
			}
		}
	}
	
	if(SNR<targetSNR){
		if($("#warning2").html()==""){
			$("#warning2").html("Could not reach target SNR with 24 hours of exposure time");
		}
	}

}

$(document).ready(function() {
	
	changeScope();	
	changeFilter();



	function roundNumber(num, dec) {
		var result = Math.round(num*Math.pow(10,dec))/Math.pow(10,dec);
		return result;
	}



});
</script>

<?php
}
require_once('../mTop.php');

if($displayPage){
?>

For a quick exposure time calculation of a point source select the <b>telescope</b> and <b>filter</b>, enter the <b>object magnitude</b> and click 'Find maximum exposure time'</b>'. <br>
To calculate the exposure time required to achieve a certain SNR, select the <b>telescope</b> and <b>filter</b>, enter the <b>object magnitude</b> and minimum <b>SNR</b> and click 'Solve to target SNR'.<br>
<br>For full details see the <a href="https://observatory.herts.ac.uk/wiki/Guide:Exposure_calculator">guide on the wiki</a>.<br>

<table cellspacing=30><tr><td valign="top">
<div>
<table class="bordered"><tr><td>
<b>Telescope: </b></td><td>
<select id="telescope" onChange="changeScope()" >
    
    <option value="cdk" selected>CDK24</option>
    <option value="ckt">CKT</option>
    <option value="rpt">RPT</option>
    <option value="jht">JHT</option>
    <!--<option value="int">INT</option>-->
    
</select></td></tr>

<tr><td>Focal length:</td><td><input type="text" id="flength"  size=7 class="fix"> mm</td></tr>

<tr><td>Primary diameter:</td><td><input type="text" id="aperture"  size=7 class="fix"> mm</td></tr>

<tr><td>Secondary diameter:</td><td><input type="text" id="secdiam"  size=7 class="fix"> mm</td></tr>

<tr><td>Aperture area:</td><td><input type="text" id="aparea"  size=7 class="fix"> cm^2</td></tr>

<tr><td>Efficiency:</td><td><input type="text" id="transmission"  size=7 class="fix"> %</td></tr>


</table>
</div>
</td><td valign="top">

<div>
<table class="bordered"><tr><td>
<b>Camera: </b></td><td>
<select id="camera" onChange="changeCamera()" >
	<option value="asi6200" selected>ASI6200</option>
    <option value="stl6303">SBIG STL-6303</option>
    <option value="q49000">MI Q4-9000</option>
    <option value="sxvrh18">SXVR-H18</option>
    <option value="st7e">ST-7E</option>
	<option value="asi6200lg">ASI6200 (low gain)</option>
	<option value="asi6200hg">ASI6200 (high gain)</option>
</select></td></tr>

<tr><td>Pixel size: </td><td><input type="text" id="pxsize"  size=7 class="fix"> um</td></tr>

<tr><td>Binning:</td><td> <select id="binning" onChange="updateGain()" >
    <option value="1">1</option>
    <option value="2" selected>2</option>
    <option value="3">3</option>
    <option value="4">4</option>
</select> x</td></tr>

<tr><td>Read noise: </td><td><input type="text" id="rn"  size=7 class="fix"> e-</td></tr>

<tr><td>Gain:</td><td> <input type="text" id="gain"  size=7 class="fix"> e-/adu</td></tr>

<tr><td>Dark current: </td><td><input type="text" id="idark"  size=7 class="fix"> pA/cm^2</td></tr>

<tr><td>Sensor temperature:</td><td> <input type="text" id="temp"  size=7> C</td></tr>

<tr><td>Dark current doubling:</td><td> <input type="text" id="ddouble"  size=7 class="fix"> C</td></tr> 

<tr><td>Reference temperature: </td><td><input type="text" id="tref"  size=7 class="fix"> C</td></tr>

<tr><td>Quantum efficiency: </td><td><input type="text" id="qe"  size=7 class="fix"> %</td></tr>


</table>
</div>
</td><td valign="top">

<div><b>Target and conditions:</b>
<table class="bordered"><tr><td>
<b> Filter: </b></td><td>
<select id="filter" onChange="changeFilter()" >
    <option value="i">I</option> 
    <option value="r">R</option>    
    <option value="v" selected >V</option>
    <option value="b">B</option>
    <option value="c">Clear</option>
    <option value="ha">H-alpha</option>
    <option value="oiii">O-III</option>
    <option value="sii">S-II</option>
</select></td></tr>

<tr><td>Filter efficiency:</td><td> <input type="text" id="filtereff"  size=7 class="fix"> %</td></tr> 

<tr><td>Zero magnitude flux:</td><td> <input type="text" id="flux0"  size=10 class="fix"> e- /s</td></tr>

<tr><td><b>Object magnitude</b>:</td><td> <input type="text" id="objmag"  size=7 value=12> mag</td></tr>

<tr><td>Seeing: </td><td><input type="text" id="seeing" value="2.5" size=7> " fwhm</td></tr>

<tr><td>Airmass: </td><td><input type="text" id="airmass"  size=7 value = 1> </td></tr>

<tr><td>Extinction coefficient: </td><td><input type="text" id="extcoef"  size=7 value ="0.2"></td></tr>

<tr><td>Sky magnitude:</td><td> <input type="text" id="skymag"  size=7 value=18> mag/"^2</td></tr>

<tr><td>Aperture radius: </td><td><input type="text" id="aprad" value=10 size=7> pixels</td></tr>

<tr><td>Repeated exposures: </td><td><input type="text" id="reps" size=7 value=1> (stacked)</td></tr>

<tr><td>Exposure time: </td><td><input type="text" id="exptime"  size=7 value=60> seconds</td></tr>


</table>
</div>

</td></tr></table>

<input type="button" value="Calculate" onclick="calc();" />   <input type="button" value="Find maximum exposure time" onclick="findExp();" />  <input type="button" value="Solve to target SNR" onclick="findSNR();" /> <div id="warning" style="color: #ff0000; display: inline-block"></div><div id="warning2" style="display: inline-block"></div>
<br><br>
<div><b>Results</b>
<table cellspacing=30><tr><td valign="top">
<table class="bordered">


<tr><td>Total exposure time: </td><td> <input type="text" id="totalexp"  size=7> <div id="expunits" style="display:inline">mins</div></td></tr>
<tr><td>Image scale: </td><td> <input type="text" id="imscal"  size=7> "/pixel</td></tr>
<tr><td>FWHM: </td><td> <input type="text" id="fwhmp"  size=7> pix</td></tr>
<tr><td>Dark current: </td><td> <input type="text" id="darkc"  size=7> e- / s / pix</td></tr>
<tr><td>Sky flux: </td><td> <input type="text" id="skyflux"  size=7> e- / s / pix</td></tr>
<tr><td>Total background flux: </td><td> <input type="text" id="bgflux"  size=7> e- / s / pix</td></tr>
<tr><td>Total background count: </td><td> <input type="text" id="bgcount"  size=7> ADU / pix</td></tr>
<tr><td>Binned read noise: </td><td> <input type="text" id="brn"  size=7> ADU / pix</td></tr>

</table>
</td><td valign="top">
<table class="bordered">
<tr><td>System zero point (1s): </td><td> <input type="text" id="zp"  size=7> mag</td></tr>
<tr><td>Extincted object magnitude: </td><td> <input type="text" id="exobjmag"  size=7> mag</td></tr>
<tr><td>Object flux: </td><td> <input type="text" id="objflux"  size=7> e- / s</td></tr>
<tr><td>Object count: </td><td> <input type="text" id="objcount"  size=7> ADU</td></tr>
<tr><td><b>SNR</b>: </td><td> <input type="text" id="snr"  size=7> </td></tr>
<tr><td>Magnitude uncertainty: </td><td> <input type="text" id="magerr"  size=7> +/- mag</td></tr>
<tr><td>Aperture coverage: </td><td> <input type="text" id="apint"  size=7> %</td></tr>
<tr><td>Peak pixel value: </td><td> <input type="text" id="peakval"  size=7> ADU </td></tr>
</table>
</td><td valign="top">
Noise contribution
<table class="bordered">
<tr><td>Source noise: </td><td> <input type="text" id="sourcenoise"  size=7> %</td></tr>
<tr><td>Sky noise: </td><td> <input type="text" id="skynoise"  size=7> %</td></tr>
<tr><td>Dark noise: </td><td> <input type="text" id="darknoise"  size=7> %</td></tr>
<tr><td>Readout noise: </td><td> <input type="text" id="readoutnoise"  size=7> %</td></tr>

</table>
</td></tr>

<table>
</div>


<?php
}
require_once('../mFooter.php');

?>