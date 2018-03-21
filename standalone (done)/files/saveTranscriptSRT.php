<?php
/*
 * The MIT License (MIT)
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @author Tan Yan Ling (c) 2017
 */

// get location of XML and the new XML text


/***
if(!empty($_POST['xmlLoc']) && !empty($_POST['processedXML'])) {
	$xml_loc = '.' . $_POST['xmlLoc'];

	// check that file location is writeable
	if(is_writable($xml_loc)) {
		if($_POST['download'] == "false") {
			// remove file first
			if(file_exists($xml_loc))
				unlink($xml_loc);
		$tempo = tmpfile(file_get_contents($xmlLoc));
		}
	}
}
**/



$xmlLoc = '.' . $_POST['xmlLoc'];
$tempo = file_get_contents($xmlLoc);


function getSRTtime($timeval) {
	$timeval_split = explode(".", $timeval);
	$time_sec = (int)$timeval_split[0];
	$time_mili = (int)$timeval_split[1];
	$minute = floor($time_sec / 60);
	$second = floor($time_sec % 60);
	$hour = floor($minute / 60);
	$minute = floor($minute % 60);
	$timev = sprintf('%02d',$hour).":".sprintf('%02d',$minute).":".sprintf('%02d',$second).",".sprintf('%03d',($time_mili*10));
	return $timev;
}	

function writeSRT($srtString) {
	$xmlLoc = '.' . $_POST['xmlLoc'];
	$path_parts = pathinfo($xmlLoc)['filename'];
	$my_file = $path_parts.'.srt';
	//$my_file = 'output.srt';
	//check if file exist, delete before creating a new one to ensure fresh start
	if (file_exists($my_file)){
		unlink($my_file);
	}
	$handle = fopen($my_file, 'w') or die('Cannot open file:  '.$my_file); //implicitly creates file
	fwrite($handle, $srtString);
	fclose($handle);
	echo "<br> [LOG|INFO] Successfully write into SRT File";
} 

function parseXML($xmlLoc){
	echo '[LOG|INFO] Written SRT file at ../files/[filename]';
	$tempo = file_get_contents($xmlLoc);
	
	//#extract <document> tag
	$xml = simplexml_load_string($tempo);
	$srtSentence = "";
	$base = $xml[0]->content->segment;

	for ($i=0; $i<count($base); $i++){
		$sentences =json_decode(json_encode($base[$i]), true);
		
		if (!empty($sentences['sentence'])){
			foreach ($sentences['sentence'] as $words_array){
				if (count($words_array)>1){
					
					$text = implode(" ",$words_array);
				}
				elseif (count($words_array)==1){
					$text = $words_array;
				}
				else{
					$text = '';
				}
			}
		}
		
		$tr_dict = array();

		$tr_dict['st'] = getSRTtime($base[$i]['startTime']);
		$tr_dict['et'] = getSRTtime($base[$i]['endTime']);

		$srtSentence .= "\r\n".($i+1)."\r\n".$tr_dict['st'] ." -----> ".  $tr_dict['et']."\r\n".$text;
	}
	return $srtSentence;

}

$srtString = parseXML($xmlLoc);
writeSRT($srtString);



////////////////create file output//////////////

	#find all <sentence> tag
	//preg_match_all($xml,('*/segment/sentence'),$match,PREG_SET_ORDER);
	//print_r($match);
	/**foreach ($match as $sid=>$row) {
		
		$tr_dict = array();
		$senttext = '';
		
		#get meta for <sentence> tag
		$tr_dict['st'] = getSRTtime($asent.get('startTime'));
		$tr_dict['et'] = getSRTtime($asent.get('endTime'));
		$tr_dict['id'] = $asent.get('id');
		$tr_dict['spk'] = $asent.get('spkName');
		
		#get text for <word> tag
		preg_match_all($asent, ('word'), $word, PREG_SET_ORDER);
		foreach ($word as $aword) {
			$senttext += $aword.text.' ';
		}
		$tr_dict['txt'] = $senttext.trim();
		$tr_list->append($tr_dict);
	
	echo '[LOG|INFO] Written SRT file at: {}'.$xmlLoc;**/



	//}
/********aryani' close
if (!debug_backtrace()) {
	$xmlFile = '.' . $_POST['xmlLoc'];
	$srtFile = '.' . $_POST[$outFile];
	
	$trList = parseXML($xmlFile);
	writeSRT($trList, $srtFile);
	//print_r $srtFile;
}
//$location = substr($outFile, 0, -4);
//$srtFile = $location.'_V.srt';
*****/

/*if(is_writable($xmlLoc)) {
	$counter = 1;
	$location = substr($outFile, 0, -4);
	while(file_exists($location."_V".$counter.".xml"))
		$counter++;
	$output = fopen("php://output",'w') or die("Can't open php://output");
	header("Content-Type:application/xml; charset=utf-8");
	header("Content-Disposition:attachment; filename=\"".$srtFile."_V".$counter.".srt\"");
	fclose($output) or die("Can't close php://output");
	} else
		echo "XML Location (" . $srtFile . ") is not writeable!";
}*/
	


			/*$tempo = tmpfile();
			fwrite($tempo, "writing to tempfile");
			fseek($tempo, 0);
			echo fread($tempo, 1024);
			// put the new XML text into existing file
			file_put_contents($tempo, $_POST['processedXML']);
			echo "success";*/


/**			
		} else {
			$counter = 1;
			$file_name = substr(explode("/", $xml_loc)[3], 0, -4);
			$location = substr($xml_loc, 0, -4);
			while(file_exists($location."_V".$counter.".srt"))
				$counter++;
			$tempo = tmpfile($file_name);

			print_r($file_name);
			
		}
	}
	
	
	
	
	
 echo " HELLO";
/*function getSRTtime($timeval)
{
	$time_sec = int($timeval.split(.)[0]);
	$time_mili = int($timeval.split('.')[1]);
	$minute = $time_sec / 60;
	$second = $time_sec % 60;
	$hour = $minute / 60;
	$minute = $minute % 60;
	$timev = "%02d-%02d-%02d,%d" % ($hour, $minute, $second, $time_mili * 10);
	return $timev;
}

function writeSRT(tr_list, outFile) {
	echo "[LOG|INFO] Writing SRT file at: {}".format(outFile);
	
	// get location of XML and the new XML text
	if(!empty($_POST['xmlLoc']) && !empty($_POST['processedXMLSRT'])) {
	$xml_loc = '.' . $_POST['xmlLoc'];

	// check that file location is writeable
	if(is_writable($xmltoSRT)) {
		if($_POST['download'] == "false") {
			// remove file first
			if(file_exists($xmltoSRT))
				unlink($xmltoSRT);

			// put the new XML text into existing file
			file_put_contents($xmltoSRT, $_POST['processedXMLSRT'], FILE_APPEND | LOCK_EX);
			echo "success";
		} else {
			$counter = 1;
			$file_name = substr(explode("/", $xml_loc)[3], 0, -4);
			$location = substr($xml_loc, 0, -4);
			while(file_exists($location."_V".$counter.".xml"))
				$counter++;
			$output = fopen("php://output",'w') or die("Can't open php://output");
			header("Content-Type:application/xml; charset=utf-8");
			header("Content-Disposition:attachment; filename=\"".$file_name."_V".$counter.".xml\"");
			echo $_POST['processedXMLSRT'];
			fclose($output) or die("Can't close php://output");
		}
	} else
		echo "XML Location (" . $xml_loc . ") is not writeable!";
}
else
	echo "Missing XML Location or Missing Processed XML Data!";*/

?>