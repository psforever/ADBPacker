<?php
setlocale(LC_ALL, 'pl_PL.UTF8','pl_PL.UTF-8','pl.UTF8','pl.UTF-8','pl_PL','pl');
date_default_timezone_set('Europe/Warsaw');
if ($argc < 3) exit ("Usage: 'php ".$argv[0]." input.adb.lst output.adb'");
$adblst = fopen($argv[1], 'r');//no point sanitizing this part, it's just for running locally anyway
$adb = fopen($argv[2], 'wb');
$string_offsets = array();
$stage = 0;
$stringoffset = 0;
$kvp = array();
$kvpoffset = 0;
$values = array();
if ($adblst) {
	//fgets strbegin, then fgets an empty line, then the loops
	$str_begin = fgets($adblst, 4096);
		if ($str_begin[0]!="#") exit("format error: #strbegin\n"); //just baaaasic error checking for now
	$str_begin = substr($str_begin, 1, -1);
	$buffer = fgets($adblst, 4096);
		if (strlen($buffer)>1) exit("format error: line break after strbegin\n");
	while (($buffer = fgets($adblst, 4096)) !== false) {
		//echo $buffer;
		switch ($stage) {
			case 0:
				if ($buffer[0]!="#" || $buffer[1]!="#") exit("format error: ##line\n");
				$kvalue=substr($buffer,2,-1);
				if (!array_key_exists($kvalue, $string_offsets)) {
					$string_offsets[$kvalue] = $stringoffset;
					$stringoffset += strlen($kvalue)+1;
				}
				$kvp[]=array($string_offsets[$kvalue], ($kvpoffset+1));
				$buffer2 = fgets($adblst, 4096);
					if (strlen($buffer2)>1) exit("format error: line break after ##line\n");
				$stage = 1;
			break;
			case 1:
				if (strlen($buffer)>1) {
					$values[]=substr_count($buffer, " ")+1;
					$kvpoffset+=1;
					$line = explode(" ", substr($buffer,0,-1));
					foreach ($line as $kline => $vline) {
						if (!array_key_exists($vline, $string_offsets)) {
							$string_offsets[$vline] = $stringoffset;
							$stringoffset += strlen($vline)+1;
						}
						$values[]=$string_offsets[$vline];
						$kvpoffset+=1;
					}
				}
				else {//end of that block
					$values[]=0;
					$kvpoffset+=1;
					$stage = 0;
				}
			break;
		}
	}
	if (!feof($adblst)) {
		exit ("Error: unexpected fgets() fail\n");
	}
	fclose($adblst);
}
fwrite($adb, "chunky");
fwrite($adb, pack("x10CxCx3", 0x01, 0x01));
fwrite($adb, "asciidatabase");
fwrite($adb, pack("x3Cx", 0x01));
fwrite($adb, pack("V", strlen($str_begin)+5+$stringoffset+4+8*count($kvp)+8+$kvpoffset*4+4 ));
fwrite($adb, $str_begin);
fwrite($adb, pack("xV", $stringoffset));
foreach ($string_offsets as $kstring => $vstring) {
	fwrite($adb, $kstring);
	fwrite($adb, pack("x"));
}
fwrite($adb, pack("V", count($kvp)));
foreach ($kvp as $kpair => $vpair) {
	fwrite($adb, pack("VV", $vpair[0], $vpair[1]));
}
fwrite($adb, pack("V", count($values)+2));
fwrite($adb, pack("x4"));
foreach ($values as $kval => $vval) {
	fwrite($adb, pack("V", $vval));
}
fwrite($adb, pack("x4"));
//var_dump($values);
//echo "\n\n";
//var_dump($kvp);
fclose($adb);
?>