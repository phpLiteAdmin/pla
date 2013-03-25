<?php
// Attention: this theme will not work directly in phpliteAdmin 1.9.3 (and before)
// You can already use it with the svn-version of 1.9.4
// use phpliteadmin.php?theme=dynamic.php to use it there

header("Content-type: text/css");

if(isset($_GET['image'])){
	// Accomidate uppercase & lowercase file extensions
	$image = strtolower($_GET['image']);

	// Set the mimetype and cache the image for a year
	header("Content-type: image/png");
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 60 * 60 *24 * 365) . ' GMT');

	// Deliver the correct image ...
	if($image == 'logo')		echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAA0AAAAZCAYAAADqrKTxAAAACXBIWXMAAAsSAAALEgHS3X78AAABVElEQVR4nJXUPUhWURgH8J+oiJDkC4I6OBiESuAsKrgLNbUFTUW4hDg1OQpS4OciTuLkILgL1gtSCBFI4NCi6KAgWENR4sdteYTj633VO5zhcP8/znOec86VZZmi4/aPlPAe63hyJ0IHNpHF+IH2qihW+Bzhc5zgEhO5CLVYDHCBrziOebkaeobTCH3Dx6TElRsITRHM8BOT+JegF3noFc6i/nlsJeAYrdcQGvElAgcYCXyFllBbiYbwOwJj2E7AXwxcOyfUYCYC+3iZgAyraKhED5MGjKOcgF/ov3GN8AiH0bHnScvPMI26PPQUu1jDVKAtjKI55yzV4B328BY70b3vGK5yzZSwjCO8xp9o9UJlWSl6gI0o50PsZRulW16ABsxhBZ9ihZY73pn6KGs29pRbUiWqwyDeoPHezx1d6Cv0j0AneoqiDjwuitrQXRQ1o/e+6D+5oluoWPVVVgAAAABJRU5ErkJggg==');
	elseif($image == 'del')		echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAHMSURBVHjapFO/S0JRFP4UIUJIqMWgLQzalAyKIN4TxNXJoZaGIPwHXNMt/A+C1pZabKgQQd9kQ4pS0KBUi4MNNgT+ev54nXPeVTRoqQvfu+ee7zvnnnPvfQ7LsvCf4ZLvSZi/ScIpQScYv+g1QoGQEv15zk4wHo0k2BmJYJzNskB3XuTnkoyPQxKsNLwRnJTEycZwOJRgDAbgmdYF82hfmwSzzb4fGkni4DPoHu5K9sVw2I5wu9HNZKDagXDRKNBuy6Kbywm3ePlgSAUD0zQI+tftLdDrAa0WOIB8BYYEk4851rCWY1Qb1IJpYum6bNCsf97f0xZdoNHAUiwmYJt9zLFGaTFNMOj3ZbF882yQrX9ks0CnA9RqNshmH3OsmY1xqRampz21PR6g2bRtr3dOM6ubq+B9b1Uju7AWjwNvb3YVDLLZxxxrZmPkFurbK9NH4kskgHxeyHqpJLMvGLS3DYVQT6cnt2P4HluY3ILGpy3Bd3dy2i/F4uS0dbbldohjjbod+51wBU+bC5Z1dWZZBzsCXhM05hSviUbxrJU1cdJCZcMlTzng96NSrUqJZM89ZfJLizOaVKA2TEqC8rrjTz/T1quq4D/jW4ABAF7lQOO4C9PnAAAAAElFTkSuQmCC');
	elseif($image == 'edit')	echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAA0AAAAMCAYAAAC5tzfZAAAACXBIWXMAAA7DAAAOwwHHb6hkAAABEElEQVR4nIXSsUrDUACF4Zs9W8GhUBURXFtLM9Qhj9AXEHURBV+hYB7icskSwYhDQaWBIikt3GYsnQRJd0VMlRDI5vQ7tVhsm+GM33L4BSAKZg8tS0vD0IANiEIgDUOnSpEqhRRCFyHbcRydKsVbq0W/0SCs15FCbAbT1ymj8wuGlkVQqxFUq2uRHbZL+uflmsl4Qv855On4hIdmk6FlacBeC5LBPuOeJAh6dE7PcBxn5RELMBtsk9yUSd09gsfOEviL/oHMrZDIHcJ2aQksUOwJlSddstER3/e7ZG6FmSyvBHO0FXtC50mXz/iSj7vDjWCO7Mg3vyLfJE+6vN8ebARzdBX5JpFvEnuC2BMUpfULJyi1o5NPs3wAAAAASUVORK5CYII=');
	elseif($image == 'audio')	echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAdRJREFUeNqkU89rE1EQ/maz2STbQKTV1AqRWovFBgKCQsFToEfRmxfBP0DwIl56KkUPglfP0lOhBQVpDz305EmpeNAWPAQaSBoDTUuz2SabZN97nRdN0uqmFhz49s3Oj+8x82ZIKYX/EbOrPNwYGKNvGGEcdA0rs32ncc6LnulPva5weChPOc5LMMe4f2YJmn2QFAo/c6nU2JIQyPLvZiBBsegPJIhGk3e2t0vriURyzXHkLSBS+osgHnMRMsPwpY1crnWKIBIhxzQvZnd3yx8N49IrNj0e2APbNjAxYUEpowfPI/i+0SQafaRU40FgCSclHjcwNWWhst+3xWJAq4WyEJbxT4JfdQNXxoA2t4aIA0MdvdZo4NpZz5hQUrxmbDEUQSjLFAiHBDp6uJOcDyTw2000G+6yHXOfT07K9M1p4jIIbnVP1GsHkNInnVxaXQieg/1yUR/ZdCaNvYqeNomjqqPtO9ohvn3AzruXv6PnewTUXSYrlYF9b2Hz6vXU7eHL46hVayjkfsDLf33qfVl+0y5+7y/HiQXsERDRBUreyNDdJy9gDc1wWAX5T4vy89v37NaD4TCqDJdz/CCCqJ4Z/QCM0B/NFYwmw9NknNPbqGMBBgDJpb7OvDYMdwAAAABJRU5ErkJggg==');
	elseif($image == 'image')	echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAdlJREFUeNqkkz9v00AYxp9zzvblLnac0lLiUKpmKFKDqGBgQ0KCoQsDCxJVP0A/RAcGRsTMgtQpEh3oF+haNia2qiukCzSlieP4b987d+hIk5Ne2Wf5+d3z/jlWliXmWRbmXPz9wcFrerZn1A94lmXhh+3tz7Oo9/r9XZ6lKSuoDs8/MVgMsGtkiyLJAF0dX1TBKdnuItAjr1kBvH1aQmt5kiTICbB5n8Hl9HMdWFTA2T+gbldiEHgYAY9CYLMDRCmMRmv5NEmY3oSNY6zfW0NLhebkh8vAsleBJiTo3qlgU3IWiAqgtXwaxywvChz9+ILvQmLryRu86L00lh1e2daABQmkOX2j9KRLANJorQGktDk5PUHQDPD1ch8Xl7+wsvQA7YUV3PVbqDs6D2mAygEEpRbn14A4ji1j56KDPyOO8V8H34Y/URenUFJBKQWv4Zloej6BQ6wtdfBs1YXWagDLyEG3/QqO48B1XUgp4XkKQeCbaLVI3FTwqQhS2rCERZ2INMA4MG28ufR4Z9SrKErA2ITeLYzHJc7PcwghzCGPe2UFOBsMajXbxuHHd/85PjQEmKBmO9Ba5m9s7FiNRjjLJBaj0W8aEVBdEeh7cUs9TQSGbN7rfCXAAJNovyFuktgQAAAAAElFTkSuQmCC');
	elseif($image == 'video')	echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAdxJREFUeNqMU81KI0EQ7t9EhNU3CHjw4s0nyLKEnLz5HotP4AP4AAp5B2FZ2BxCDsoKQm57yhIU3Bwig2IwEyczY3fVVo2dTUY3uzb0dHfV91V9XdUjEVF0u90zIUS92WyeineMTqezT8t5o9H4aNgAAHVaJM3To6OTndksAw5MAwFQ8B4RsFKx6vDw4CfhGVsA5gFEMHxJkhTG48dnIgUyiPneWsNEBXwIYzkAO78655BnkZTIjKUzjEZR7r1nol5WoPhDDhGcynvgBMgf2mOaZv7q6tfs7m5MATBgPDLnTwBWQAaOqhnE2cmEee7h5maUpqnzWluG4wvGy6B6oSDcS5F6YD+Z8Pb2IXNOeGMqgqeUxY3Vizq/qAHdkZySFXR7vW9VWqtRFLnBYCBft5BgGSvA0CYzVxCKcr67u5ckycxvbt5nW1ufYJlsjFbt9rHhIpe6EAJwtu+IEvIcnRAWrLWl7EotalB6B3yFYLj0XlMA4a1dW/UQ9b8U9Ki4TkqN1lb/RmaieaMgp5TB8ANR50pxxXGVggrhywomk0nRBSrsNaKKtTYr2YT5EMdxuQv9fv+iVquxIWq1Pm//72+cTqc4HA4vintzIEq+TvuN+cN6x+D2xsR9+i3AAEgKanVYjEzGAAAAAElFTkSuQmCC');
	elseif($image == 'arch')	echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAACXBIWXMAAC4jAAAuIwF4pT92AAABUUlEQVR4nGP4//8/AyWYVA0VSJhkAyo05mhM99xj/kV9tvoEUg2oUO5TqUw67fw/8ZL7f/+9lr9IMuDCwwvTQ4/Y/ou54Po/+5LHf93FWkeJNuD3n99VjjtMP+Rc9/2fftnjv+1Gww/ff36vwWUAeiBVaM3T2FB5O+R/8lWf/8H7rf9dfXx1Mq5YqDBYp7c57KjDb51l2iv///9fce3xtUnJp93/FT+I+Z97weu/cq9yN3IMYBgQesTuV/mV2P81N8L/6y7X3m+yRv967+uM/w2PYv+brdZ7hK4ZwwDtRVpryy8H/296mPW/7UHC/6Z7Mf/b3+b+jzvi/O/G05sT0DVjDQPNORrrKq+F/u9+U/y/50P5/44nSf/Vpqmtw2Y7rlioUJ+utrzhdtT/aZ9K//tst/qBHOrEJuUK7dkaWwJ32/7cfm37RnzRTFFGGhwGAAC/7+GLJgjBEQAAAABJRU5ErkJggg==');
	elseif($image == 'app')		echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAFiSURBVBgZpcEhbpRRGIXh99x7IU0asGBJWEIdCLaAqcFiCArFCkjA0KRJF0EF26kkFbVVdEj6/985zJ0wBjfp8ygJD6G3n358fP3m5NvtJscJYBObchEHx6QKJ6SKsnn6eLm7urr5/PP76cU4eXVy/ujouD074hDHd5s6By7GZknb3P7mUH+WNLZGKnx595JDvf96zTQSM92vRYA4lMEEO5RNraHWUDH3FV48f0K5mAYJk5pQQpqIgixaE1JDKtRDd2OsYfJaTKNcTA2IBIIesMAOPdDUGYJSqGYml5lGHHYkSGhAJBBIkAoWREAT3Z3JLqZhF3uS2EloQCQ8xLBxoAEWO7aZxros7EgISIIkwlZCY6s1OlAJTWFal5VppMzUgbAlQcIkiT0DXSI2U2ymYZs9AWJL4n+df3pncsI0bn5dX344W05dhctUFbapZcE2ToiLVHBMbGymS7aUhIdoPNBf7Jjw/gQ77u4AAAAASUVORK5CYII=');
	elseif($image == 'script')	echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJwSURBVDjLjZPdT1JhHMetvyO3/gfLKy+68bLV2qIAq7UyG6IrdRPL5hs2U5FR0MJIAqZlh7BVViI1kkyyiPkCyUtztQYTYbwJE8W+Pc8pjofK1dk+OxfP+X3O83srAVBCIc8eQhmh/B/sJezm4niCsvX19cTm5uZWPp/H3yDnUKvVKr6ELyinwWtra8hkMhzJZBLxeBwrKyusJBwOQ6PRcJJC8K4DJ/dXM04DOswNqNOLybsRo9N6LCy7kUgkEIlEWEE2mwX9iVar/Smhglqd8IREKwya3qhg809gPLgI/XsrOp/IcXVMhqnFSayurv6RElsT6ZCoov5u1fzUVwvcKRdefVuEKRCA3OFHv2MOxtlBdFuaMf/ZhWg0yt4kFAoVCZS3Hd1gkpOwRt9h0LOES3YvamzPcdF7A6rlPrSbpbhP0kmlUmw9YrHYtoDku2T6pEZ/2ICXEQ8kTz+g2TkNceAKKv2nIHachn6qBx1MI5t/Op1mRXzBd31AiRafBp1vZyEcceGCzQ6p24yjEzocGT6LUacS0iExcrkcK6Fsp6AXLRnmFOjyPMIZixPHmAAOGxZQec2OQyo7zpm6cNN6GZ2kK1RAofPAr8GA4oUMrdNNkIw/wPFhDwSjX3Dwlg0CQy96HreiTlcFZsaAjY0NNvh3QUXtHeHcoKMNA7NjqLd8xHmzDzXDRvRO1KHtngTyhzL4SHeooAAnKMxBtUYQbGWa0Dc+AsWzSVy3qkjeItLCFsz4XoNMaRFFAm4SyTXbmQa2YHQSGacR/pAXO+zGFif4JdlHCpShBzstEz+YfJtmt5cnKKWS/1jnAnT1S38AGTynUFUTzJcAAAAASUVORK5CYII=');
	else echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAABbSURBVCjPzdAxDoAgEERRzsFp95JbGI2ASA2SCOX3Ahtr8tuXTDIO959bCxRfpOitWS5vA+lMJg9JbKCTTmMQ1QS3ThqVQbBBlsbgpXLYE8lHCXrqLptf9km7Dzv+FwGTaznIAAAAAElFTkSuQmCC');
	
	// Exit this script when the correct image has been served
	exit();
}


$bg = '#879845';
$bgContent = '#DEE4BE';
$tdhead = '#474F28';
$border ='#879845';
$td1 = '#E9E9E2';
$td2 = '#fff';
$nav = '#E2E2D8';
$radius = 5;

define('FILENAME',basename(__FILE__));

if(isset($_GET['blabla'])){
?>
<style>
<?php
}
?>
/*
phpLiteAdmin dynamic Theme
Created by Ayman Teryaki on 06.Nov.2012

Posted here: http://code.google.com/p/phpliteadmin/issues/detail?id=133
*/

/* overall styles for entire page */
body{ margin: 0px; padding: 0px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color:#000; background: <?php echo $bg; ?>; }
/* general styles for hyperlink */
a{ color: #474F28; text-decoration: none; cursor :pointer; }
a:hover{ color: #FF9900; }

.warning, .empty, .drop, .delete, .delete_db{ color:#ff0000; }

.edit *{ display:none; }
.edit{ background:url(<?php echo FILENAME; ?>?image=edit) no-repeat; padding:0 3px 0 16px; }
.delete *{ display:none; }
.delete{ padding:0 3px 0 16px; background:url(<?php echo FILENAME; ?>?image=del) no-repeat;  }

.sidebar_table *{ display:none; }
.sidebar_table{ font-size:12px; background:url(<?php echo FILENAME; ?>?image=arch) no-repeat;padding:0 3px 0 16px;}

.active_table, .active_db{ font-weight:bold; color:#FF9900; }

.null{ color:#8A945F; }

/* horizontal rule */
hr { height: 1px; border: 0; color: #3C3C3C; background-color: <?php echo $bg; ?>; width: 100%; }
/* logo text containing name of project */
h1 {
	margin: 0px; padding: 5px; font-size: 24px;
	background: url(<?php echo FILENAME; ?>?image=logo) no-repeat 7px 9px ;
	text-align: center; margin-bottom: 5px;color:#3C3C3C; }
/* version text within the logo */
h1 #version { color:#666; font-size: 16px; }
/* logo text within logo */
h1 #logo { padding-left:9px; }
/* general header for various views */
h2 { margin:0px; padding:0px; font-size:14px; margin-bottom:20px; }
/* input buttons and areas for entering text */
input, select, textarea {
	font-family:Arial, Helvetica, sans-serif;
	background-color:<?php echo $nav; ?>; color:<?php echo $tdhead; ?>;
	border-color:<?php echo $border; ?>; border-style:solid;
	border-width:1px; margin:5px;
	border-radius:<?php echo $radius; ?>px; -moz-border-radius:<?php echo $radius; ?>px; padding:1px 3px;
}
select{	border-radius:1px; -moz-border-radius:1px; }
input:focus, textarea:focus, select:focus{ background:<?php echo $bgContent; ?>; }
/* just input buttons */
input.btn { cursor:pointer;
	background: -moz-linear-gradient( top, <?php echo $bgContent; ?> 0%, #ebebeb 50%, #dbdbdb 50%, #b5b5b5);
	background: -webkit-gradient( linear, left top, left bottom, from(<?php echo bgContent; ?>), color-stop(0.50, #ebebeb), color-stop(0.50, #dbdbdb), to(#b5b5b5));
	border: 1px solid #949494;
	-moz-box-shadow: 0px 1px 3px rgba(000,000,000,0.5), inset 0px 0px 3px rgba(255,255,255,1);
	-webkit-box-shadow: 0px 1px 3px rgba(000,000,000,0.5), inset 0px 0px 3px rgba(255,255,255,1);
	box-shadow: 0px 1px 3px rgba(000,000,000,0.5), inset 0px 0px 3px rgba(255,255,255,1);
	text-shadow: 0px -1px 0px rgba(000,000,000,0.2), 0px 1px 0px rgba(255,255,255,1);
}
input.btn:hover { 
	-moz-box-shadow: 0px 0px 0px rgba(000,000,000,0.5), inset 0px 0px 0px rgba(255,255,255,1);
	-webkit-box-shadow: 0px 0px 0px rgba(000,000,000,0.5), inset 0px 0px 0px rgba(255,255,255,1);
	box-shadow: 0px 0px 1px rgba(000,000,000,0.5), inset 0px 0px 0px rgba(255,255,255,1);
	text-shadow: 0px -1px 0px rgba(000,000,000,0.2), 0px 1px 0px rgba(255,255,255,1);
}
/* general styles for hyperlink */
#headerlinks{ background:<?php echo $td1; ?>; text-align:center;   }
fieldset{ padding:15px; border:#A7A7A7 1px solid; border-radius:<?php echo $radius; ?>px; -moz-border-radius:<?php echo $radius; ?>px; background-color:#f9f9f9; }
/* outer div that holds everything */
#container { padding:10px; }
/* div of left box with log, list of databases, etc. */
#leftNav{
	float:left; width:250px; padding:0px;
	border:<?php echo $border; ?> 1px solid; background-color:<?php echo $bgContent; ?>; padding-bottom:15px;
	border-radius:<?php echo $radius; ?>px; -moz-border-radius:<?php echo $radius; ?>px;
	-webkit-box-shadow: 2px 2px 2px 2px rgba(33, 33, 33, 0.5);
	box-shadow: 2px 2px 2px 2px rgba(33, 33, 33, 0.5);

}
/* div holding the content to the right of the leftNav */
#content { overflow:hidden; padding-left:10px; }
/* div holding the login fields */
#loginBox {
	width:500px; margin-left:auto; margin-right:auto;
	margin-top:50px; border:<?php echo $border; ?> 1px solid;
	background-color:<?php echo $bgContent; ?>; border-radius:<?php echo $radius; ?>px; -moz-border-radius:<?php echo $radius; ?>px;
}
/* div under tabs with tab-specific content */
#main {
	border:<?php echo $border; ?> 1px solid; padding:15px; overflow:auto; background-color:<?php echo $bgContent; ?>;
	border-bottom-left-radius:<?php echo $radius; ?>px;
	border-bottom-right-radius:<?php echo $radius; ?>px;
	border-top-right-radius:<?php echo $radius; ?>px;
	-moz-border-radius-bottomleft:<?php echo $radius; ?>px;
	-moz-border-radius-bottomright:<?php echo $radius; ?>px;
	-moz-border-radius-topright:<?php echo $radius; ?>px; 
	-webkit-box-shadow: 2px 2px 2px 2px rgba(33, 33, 33, 0.5);
	box-shadow: 2px 2px 2px 2px rgba(33, 33, 33, 0.5);

}
td{ padding:2px 6px 2px 6px; }
/* odd-numbered table rows */
.td1 { background-color:<?php echo $td2; ?>; text-align:right; font-size:12px; }
/* even-numbered table rows */
.td2 { background-color:<?php echo $td1; ?>; text-align:right; font-size:12px;  }
/* table column headers */
.tdheader { 
	border:<?php echo $border;?> 1px solid; font-weight:bold; font-size:12px; 
	background-color:<?php echo $tdhead; ?>; color:<?php echo $bgContent; ?>;
}
.tdheader a:link, .tdheader a:visited{ color:<?php echo $bgContent; ?>; }
.tdheader a:hover{ color:#FF9900; }
/* div holding the confirmation text of certain actions */
.confirm { border:<?php echo $border; ?> 1px dashed; padding:15px; background-color:<?php echo $td2; ?>; }
/* tab navigation for each table */
.tab{
	display:block;
	padding:5px 8px;
	border:<?php echo $border; ?> 1px solid;
	margin-right:5px;
	float:left;
	border-bottom-style:none;
	position:relative;
	top:1px;
	padding-bottom:4px;
	background-color:<?php echo $nav; ?>;
	border-top-left-radius:<?php echo $radius; ?>px;
	border-top-right-radius:<?php echo $radius; ?>px;
	-moz-border-radius-topleft:<?php echo $radius; ?>px;
	-moz-border-radius-topright:<?php echo $radius; ?>px;
}
/* pressed state of tab */
.tab_pressed{
	display:block;
	padding:5px;
	padding-right:8px;
	padding-left:8px;
	border:<?php echo $border; ?> 1px solid;
	margin-right:5px;
	float:left;
	border-bottom-style:none;
	position:relative;
	top:1px;
	background-color:<?php echo $bgContent; ?>;
	cursor:default;
	border-top-left-radius:<?php echo $radius; ?>px;
	border-top-right-radius:<?php echo $radius; ?>px;
	-moz-border-radius-topleft:<?php echo $radius; ?>px;
	-moz-border-radius-topright:<?php echo $radius; ?>px;
	-webkit-box-shadow: 1px -3px 1px 0px rgba(33, 33, 33, 0.2);
	box-shadow: 1px -3px 1px 0px rgba(33, 33, 33, 0.2);
}
/* tooltip styles */
#tt{ position:absolute; display:block; }
#tttop { display:block; height:5px; margin-left:5px; overflow:hidden }
#ttcont { display:block; padding:2px 12px 3px 7px; margin-left:5px; background:#f3cece; color:#333 }
#ttbot { display:block; height:5px; margin-left:5px; overflow:hidden }