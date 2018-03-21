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
 * @author Bejamin Bigot (c) 2013
 * @author Ong Jia Hui (c) 2015
 * @author Kyaw Zin Tun (c) 2015
 * @author Hew Jun Wei Zach (c) 2016
 * @author Tan Yan Ling (c) 2017
 */

$ready = false;
$error = false;
$SHOWDEBUG='N';
// set the document from GET param if exist, else null
$document = (isset($_GET['document']) ? $_GET['document'] : null);

// method to routinely delete the uploaded audios & videos to save space on pc/server
function cleanUploads() {
	// traverse through every file in uploads folder
	foreach (glob("././uploads/*.*") as $filename) {
		// exclude .htaccess and XML transcripts from deletion
		if (is_file($filename) && $filename != ".htaccess" && substr($filename, -3) != "xml")
			unlink($filename);
	}
}

// if document is set at GET param, we will load that document specified
if(!empty($document)) {
	// point xmlLoc to the document's XML transcript
	$xmlLoc = "./uploads/$document.xml";

	// if XML transcript specified exist in uploads folder
	if (file_exists($xmlLoc)){

		/* --------------------
		READ IN TRANSCRIPT XML
		//include_once("./files/loadXML.php");
		---------------------*/
		function loadXML ($docDir){
			// very simple function that uses simplexml to create an instance of the xml file
			// input: well formatted XML text file
			// ouput : instance of the document
			// TODO add more error message
			if (file_exists($docDir)) {
				$docXML = simplexml_load_file($docDir) or die("Error: Cannot create $docDir object");
				return $docXML;
			}
			else
				return 0;
		}
		// load XML by calling the function with xmlLoc param
		$xml = loadXML($xmlLoc);

		/* --------------------
		GET META DATA (NAME, DESCRIPTION ETC.) FROM TRANSCRIPT XML
		//include_once("./files/getMetadataFromXmlDocument.php");
		---------------------*/
		//~ metada and transcription should come together.
		function getMetadataFromXmlDocument($xml, $path) {
			//~ Improvement could be to load directly the attributes
			// input: instance of the xml document
			// output: table with metada
			$docInfo = array();
			$docName = (string) $xml->attributes()->name;

			foreach($xml->metadata as $t) {
				$docInfo['xmlLoc'] = "./$path/" . $docName . '.xml';
				$docInfo['description'] = (string) $t->description;
				$docInfo['media'] = "./$path/" . (string) $t->media->attributes()->name;
				$docInfo['type'] = (string) pathinfo($t->media->attributes()->name, PATHINFO_EXTENSION);
				$docInfo['speaker'] = array();

				foreach($t->speakers->speaker as $spk) {
					$currSpk = (string) $spk->attributes()->name;
					if(preg_match("/unknown_/", $currSpk)) {
						$currSpk = (string) "unknown";
					}
					$currId = (int) $spk->attributes()->id;
					$docInfo['speaker'][(int) $currId] = $currSpk;
				}
				$docInfo['speakerEdit'] = join(', ', $docInfo['speaker']);
				$docInfo['duration'] = (string) $t->media->attributes()->duration;
			}
			$docInfo['date'] = (string) $xml->attributes()->date;
			//print_r($docInfo);	// debug
			return $docInfo;
		}

		// specify the uploads folder location
		$path_to_doc = "./uploads";
		// get the metadata from XML transcript
		$docInfo = getMetadataFromXmlDocument($xml, $path_to_doc);

		/* --------------------
		PROCESS THE XML TRANSCRIPT
		//include_once("./files/loadTranscript.php");
		---------------------*/
		
		/*
		load Transcript where it goes thru all the attributes and include them into the $transcript.
		*/
		function loadTranscript($xml) {
			// load transcription from an $xml object in an array
			// currently like this but not correct
			// $transcript ["idSegment"] => segment['segInfo']// ["idSentence"] => sentence["sentenceInfo"] ["idWord"] => word["wordInfo"]
			// the following is more accessible

			// $transcript is the final array
			$transcript = array();
			$content = $xml->content;
			$sentCounter = 0;
			$segCounter = 0;
			$SenStartTime=0;
			$SenEndTime=0;
			
			// ============================= //
			// For each segment in the content of the current XML
			foreach($content->segment as $seg) {
				//$segId = (int) $seg->attributes()->id;
				// dynamic segment id to break up long sentences
				$segId = $segCounter;
				$transcript[$segId] = array();
				$transcript[$segId]['content'] = array();
				$transcript[$segId]['spkName'] = (string) $seg->attributes()->spkName;
				//the below two to be overwritten --> see end of for loop
				//$transcript[$segId]['startTime'] = (string) $seg->attributes()->startTime;
				//$transcript[$segId]['endTime'] = (string) $seg->attributes()->endTime;

				// ============================= //
				// Set Sentences for each sentence of the current segment
				$sentIdCounter = 0;
				foreach($seg->sentence as $sen) {
					//$sentId = (int) $sen->attributes()->id;
					// dynamic sent id to break up long sentences
					$sentId = $sentIdCounter;
					$transcript[$segId]['content'][$sentId]['content'] = array();
					$transcript[$segId]['content'][$sentId]['spkName'] = (string) $sen->attributes()->spkName;
					//the below two to be overwritten--> see end of for loop
					//$transcript[$segId]['content'][$sentId]['startTime'] = (string) $sen->attributes()->startTime;
					//$transcript[$segId]['content'][$sentId]['endTime'] = (string) $sen->attributes()->endTime;
					$transcript[$segId]['content'][$sentId]['confidence'] = (string) $sen->attributes()->confidence;
					$transcript[$segId]['content'][$sentId]['wordTimings'] = (string) $sen->attributes()->wordTimings;
					$transcript[$segId]['content'][$sentId]['id'] = $sentCounter++;

					// ============================= //
					// Set Words, foreach word contained in the current sentence, in the current segment

					/*
					foreach($sen->word as $wd) {
						$wordId = (int) $wd->attributes()->id;
						$startTimeid = (string) $wd->attributes()->startTime;
						$endTimeid = (string) $wd->attributes()->endTime;
						//count the number of words in a sentence
						$a = count($wordId);
						$wordcount = null;
					
						for ($i = 0; $i <= $a; $i++) {
							$wordcount[$i] = $i;
							//$startTimeid = $endTimeid;
							
							

							$b = 0;
							
							if ($i > 0 && ($wordcount[$i]+1) % $wordtobreak == 0) {
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['spkName'] = (string) $wd->attributes()->spkName;
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['startTime'] = (string) $wd->attributes()->startTime;
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['endTime'] = (string) $wd->attributes()->endTime;
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['confidence'] = (string) $wd->attributes()->confidence;
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['word'] = strip_tags($wd->asXml());
								//$addattribute->addAttribute('segment');
								$transcript = $xml->content->addChild('segment');
								$seg->attributes()->endTime = $wordId[$i]->endTime;
								$endTimeid = $seg->attributes()->endTime;
								$segment->addAttribute('endTime', $endTimeid);
								$segment->addAttribute('id', $b);	
								$transcript = $contentt->addChild('sentence');
								//endTime of the segment is the endTime of the last word.

								print($wordcount[$i]."<p/>");
								
							}
							
							else if ($i > 0 && ($wordcount[$i]+1) % $wordtobreak != 0) {
								//startTime of the segment is the startTime of the first word.
								$transcript = $xml->content->addChild('segment');
								$seg->attributes()->startTime = $wordId[$i]->startTime;
								$startTimeid = $seg->attributes()->startTime;
								$segment->addAttribute('startTime', $startTimeid);
								$segment->addAttribute('id', $b);
								$transcript = $content->addChild('sentence');
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['spkName'] = (string) $wd->attributes()->spkName;
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['startTime'] = (string) $wd->attributes()->startTime;
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['endTime'] = (string) $wd->attributes()->endTime;
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['confidence'] = (string) $wd->attributes()->confidence;
								$transcript[$segId+$b]['content'][$sentId]['content'][$wordId]['word'] = strip_tags($wd->asXml());

							}
							$b++;
							
						}
			
						print_r($transcript);
						
					} 
					*/
					$WORD_TO_BREAK = 30;
					
					foreach($sen->word as $wd) {
					
						//print_r($transcript);
						$wordId = (int) $wd->attributes()->id;
						//$new_wordId = (int) $wd->attributes()->id;
						
						if ($wordId > 0 && $wordId % $WORD_TO_BREAK == 0) { 
							//$segId = $segCounter;
							$segId=++$segCounter;
							$transcript[$segId] = array();
							$transcript[$segId]['content'] = array();
							$transcript[$segId]['spkName'] = (string) $seg->attributes()->spkName;						
							//the below two to be overwritten --> see end of for loop
							//$transcript[$segId]['startTime'] = (string) $seg->attributes()->startTime;
							//$transcript[$segId]['endTime'] = (string) $seg->attributes()->endTime;							
							
							
							//$sentId=++$sentIdCounter;
							
							$sentId = $sentIdCounter;
							$transcript[$segId]['content'][$sentId]['content'] = array();
							$transcript[$segId]['content'][$sentId]['spkName'] = (string) $sen->attributes()->spkName;
							//the below two to be overwritten--> see end of for loop
							//$transcript[$segId]['content'][$sentId]['startTime'] = (string) $sen->attributes()->startTime;
							//$transcript[$segId]['content'][$sentId]['endTime'] = (string) $sen->attributes()->endTime;
							$transcript[$segId]['content'][$sentId]['confidence'] = (string) $sen->attributes()->confidence;
							$transcript[$segId]['content'][$sentId]['wordTimings'] = (string) $sen->attributes()->wordTimings;
							$transcript[$segId]['content'][$sentId]['id'] = $sentCounter++;							

							$new_wordId=0;							
							if($GLOBALS['SHOWDEBUG']=='Y')echo "&nbsp;&nbsp;&nbsp;&nbsp;segId-->$segId---- sentId-->$sentId break sentence <br/>\r\n";
						} else {
							if($wordId==0) 
								$new_wordId = 0;
							else
								$new_wordId++;
						}		
						
						//~ $transcript[$segId]['content'][$sentId]['content'] = array();
						//~ $transcript[$segId]['content'][$sentId]['content'][$wordId] = array();
						$transcript[$segId]['content'][$sentId]['content'][$new_wordId]['spkName'] = (string) $wd->attributes()->spkName;
						$transcript[$segId]['content'][$sentId]['content'][$new_wordId]['startTime'] = (string) $wd->attributes()->startTime;
						$transcript[$segId]['content'][$sentId]['content'][$new_wordId]['endTime'] = (string) $wd->attributes()->endTime;
						$transcript[$segId]['content'][$sentId]['content'][$new_wordId]['confidence'] = (string) $wd->attributes()->confidence;
						$transcript[$segId]['content'][$sentId]['content'][$new_wordId]['word'] = strip_tags($wd->asXml());
						
						if($new_wordId==0) $SenStartTime= (string) $wd->attributes()->startTime;
						if($new_wordId!=0) {
							$SenEndTime=(string) $wd->attributes()->endTime;	
							$transcript[$segId]['content'][$sentId]['startTime'] = $SenStartTime;
							$transcript[$segId]['content'][$sentId]['endTime'] = $SenEndTime;
							$transcript[$segId]['startTime'] = $SenStartTime;
							$transcript[$segId]['endTime'] = $SenEndTime;							
						}							
						
						/* 
						// backup
						$transcript[$segId]['content'][$sentId]['content'][$wordId]['spkName'] = (string) $wd->attributes()->spkName;
						$transcript[$segId]['content'][$sentId]['content'][$wordId]['startTime'] = (string) $wd->attributes()->startTime;
						$transcript[$segId]['content'][$sentId]['content'][$wordId]['endTime'] = (string) $wd->attributes()->endTime;
						$transcript[$segId]['content'][$sentId]['content'][$wordId]['confidence'] = (string) $wd->attributes()->confidence;
						$transcript[$segId]['content'][$sentId]['content'][$wordId]['word'] = strip_tags($wd->asXml());
						*/

					}
					// increment sentence counter
					if($GLOBALS['SHOWDEBUG']=='Y')echo "&nbsp;&nbsp;&nbsp;&nbsp;segId-->$segId----sentIdCounter-->$sentIdCounter-->start time->$SenStartTime, end time->$SenEndTime <br/>\r\n";
					$transcript[$segId]['content'][$sentId]['startTime'] = $SenStartTime;
					$transcript[$segId]['content'][$sentId]['endTime'] = $SenEndTime;					
					//contributes to $sentId
					$sentIdCounter++;
				}
				if($GLOBALS['SHOWDEBUG']=='Y')echo "segCounter>$segCounter>start time->$SenStartTime, end time->$SenEndTime <br/>\r\n";						
				$transcript[$segId]['startTime'] = $SenStartTime;
				$transcript[$segId]['endTime'] = $SenEndTime;

				// increment segment counter
				//contributes to $segId
				$segCounter++;
			}
			if($GLOBALS['SHOWDEBUG']=='Y')print_r($transcript);
			return $transcript;
		}
		$transcript = loadTranscript($xml);

		//include_once("./files/prepareSegTranscript.php");
		function prepSegTranscript($transcriptArray, $docInfo) {
			// input = array of arrays with segment, sentence, and word informations
			// $transcriptString = '<p align="justify" style="text-align:justify">' ;
			//print_r($transcriptArray);

			// shorten unknow speaker name
			foreach($docInfo['speaker'] as &$spk) {
				if(preg_match("/unknown_/", $spk)) {
					$spk = (string) "unknown";
				}
			}
			// ===========================================================
			$counter = 0;
			$currentSpeaker = 'none';
			$speakerChange = array();
			$previousSegmentEnd = (isset($transcriptArray[0]['content'][0]['startTime']) ? (int) ($transcriptArray[0]['content'][0]['startTime'] * 100) : 0);
			//$previousSegmentEnd = (int) ($transcriptArray[0]['content'][0]['startTime'] * 100);

			// ============================
			// Building table of sentence for display
			// Capturing speaker sections
			// ============================
			ksort($transcriptArray);
			$tmpArrRet =array_keys($transcriptArray);
			$last = array_pop($tmpArrRet);
			//$last = array_pop(array_keys($transcriptArray));

			foreach($transcriptArray as $iSeg => $value) {
				// get speaker changes
				if(isset($transcriptArray[$iSeg]['spkName']) && $currentSpeaker != $transcriptArray[$iSeg]['spkName']) {
				//if($currentSpeaker != $transcriptArray[$iSeg]['spkName']) {
					$speakerChange[] = $counter;
				}
				if($iSeg == $last) {
					$speakerChange[] = $counter + 1;
				}
				$currentSpeaker = (isset($transcriptArray[$iSeg]['spkName']) ? $transcriptArray[$iSeg]['spkName'] : null);
				//$currentSpeaker = $transcriptArray[$iSeg]['spkName'];
				/*
				echo '<pre>';
				print_r($speakerChange);
				print_r($value);
				echo '</pre>';
				*/
				// ============================
				ksort($transcriptArray[$iSeg]['content']);
				foreach($transcriptArray[$iSeg]['content'] as $iSent => $value) {
					$sentenceTag = $iSeg . ',' . $iSent;
					/*
					$sentStartTime = (int) ($transcriptArray[$iSeg]['content'][$iSent]['startTime'] * 1000);
					$sentEndTime = (int) ($transcriptArray[$iSeg]['content'][$iSent]['endTime'] * 1000);
					// confidence score
					$sentConfidence = (float) ($transcriptArray[$iSeg]['content'][$iSent]['confidence']);
					*/
					$sentStartTime = (isset($transcriptArray[$iSeg]['content'][$iSent]['startTime']) ? (int) ($transcriptArray[$iSeg]['content'][$iSent]['startTime'] * 1000) : 0);
					$sentEndTime = (isset($transcriptArray[$iSeg]['content'][$iSent]['endTime']) ? (int) ($transcriptArray[$iSeg]['content'][$iSent]['endTime'] * 1000) : 0);
					// confidence score
					$sentConfidence =  (isset($transcriptArray[$iSeg]['content'][$iSent]['confidence']) ? (float) ($transcriptArray[$iSeg]['content'][$iSent]['confidence']) : 0);
					$durationTime = (int) $sentEndTime - (int) $sentStartTime;
					$gapSent = $sentStartTime - $previousSegmentEnd;
					if($gapSent < 0) {
						$gapSent = 0;
					}
					$previousSegmentEnd = $sentEndTime;

					// ============================
					$currentLine = array();
					$wordTimings = "";
					$wordTimingsEnd = "";
					ksort($transcriptArray[$iSeg]['content'][$iSent]['content']);
					foreach($transcriptArray[$iSeg]['content'][$iSent]['content'] as $iWord => $value) {
						$word = (string) $transcriptArray[$iSeg]['content'][$iSent]['content'][$iWord]['word'];
						$wordStart = (float) $transcriptArray[$iSeg]['content'][$iSent]['content'][$iWord]['startTime'];
						$wordEnd = (float) $transcriptArray[$iSeg]['content'][$iSent]['content'][$iWord]['endTime'];
						$currentLine[] = $word;
						$wordTimings .= $wordStart . ",";
						$wordTimingsEnd .= $wordEnd . ",";
					}
					$wordTimings = rtrim($wordTimings, ",");
					$wordTimingsEnd = rtrim($wordTimingsEnd, ",");

					$text = join(' ', $currentLine);
					$text .= '.';
					$text = ucfirst($text);
					$setOfSentences[$counter] = array();
					$setOfSentences[$counter]['text'] = $text;
					$setOfSentences[$counter]['eq'] = $sentenceTag;
					$setOfSentences[$counter]['start'] = $sentStartTime;
					$setOfSentences[$counter]['duration'] = $durationTime;
					//$setOfSentences[$counter]['speaker'] = $docInfo['speaker'][$currentSpeaker];
					$setOfSentences[$counter]['speaker'] = (isset($docInfo['speaker'][$currentSpeaker]) ? $docInfo['speaker'][$currentSpeaker] : null);
					$setOfSentences[$counter]['speakerId'] = $currentSpeaker;
					$setOfSentences[$counter]['gap'] = $gapSent;
					
					
					
					
					// confidence score
					$setOfSentences[$counter]['confidence'] = $sentConfidence;
					$setOfSentences[$counter]['wordTimings'] = $wordTimings;
					$setOfSentences[$counter]['wordTimingsEnd'] = $wordTimingsEnd;

					$counter++;
				}
			}
			//print_r($setOfSentences);
			//print_r($speakerChange);

			// ===========================================================
			// Preparing text for display and search
			// ===========================================================
			$transcriptString = array();

			for($idSpkChange = 0; $idSpkChange < (count($speakerChange) - 1); $idSpkChange++) {

				// Splice from  $setOfSentences[$speakerChange[$idSpkChange]] to $setOfSentences[$speakerChange[$idSpkChange +1]]
				$offset = $speakerChange[$idSpkChange];
				$length = $speakerChange[$idSpkChange + 1] - $speakerChange[$idSpkChange];
				$currentSlice = array_slice($setOfSentences, $offset, $length);

				//// ================================================ //
				$transcriptString[$idSpkChange]['text'] = '';

				for($idText = 0; $idText < count($currentSlice); $idText++) {
					if(count($currentSlice) == 1) {
						$currentSlice[$idText]['prepText'] = '<p>' . "\n" . '<span id="' . ($offset + $idText) . '">' . $currentSlice[$idText]['text'] . '</span>' . "\n" . '</p>' . "\n";
					} else if($idText == 0) {
						$currentSlice[$idText]['prepText'] = '<p>' . "\n" . '<span id="' . ($offset + $idText) . '">' . $currentSlice[$idText]['text'] . '</span>' . "\n";
					} else if($currentSlice[$idText]['gap'] > 500) {
						$currentSlice[$idText]['prepText'] = '</p>' . "\n" . '<p>' . "\n" . '<span id="' . ($offset + $idText) . '">' . $currentSlice[$idText]['text'] . '</span>' . "\n";
					} else if($idText == count($currentSlice) - 1) {
						$currentSlice[$idText]['prepText'] = '<span id="' . ($offset + $idText) . '">' . $currentSlice[$idText]['text'] . '</span>' . "\n" . '</p>' . "\n";
					} else {
						$currentSlice[$idText]['prepText'] = '<span id="' . ($offset + $idText) . '">' . $currentSlice[$idText]['text'] . '</span>' . "\n";
					}

					$currentSlice[$idText]['prepSpeaker'] = '<b class="speaker">' . $currentSlice[$idText]['speaker'] . ': </b>';

					// === populating the final table == //
					$transcriptString[$idSpkChange]['text'] .= $currentSlice[$idText]['prepText'];
					$transcriptString[$idSpkChange]['speaker'] = $currentSlice[$idText]['prepSpeaker'];
					$transcriptString[$idSpkChange]['table'][] = $offset + $idText . ',' . $currentSlice[$idText]['start'] . ', ' . $currentSlice[$idText]['duration'] . ', "' . $currentSlice[$idText]['speaker'] . '", ' . $currentSlice[$idText]['speakerId'] . ', "MS", "' . $currentSlice[$idText]['confidence'] . '", "' . $currentSlice[$idText]['wordTimings'] . '", "' . $currentSlice[$idText]['wordTimingsEnd'] . '", "' . $currentSlice[$idText]['text'] . '"';
					$transcriptString[$idSpkChange]['eq'][$currentSlice[$idText]['eq']] = $idText + $offset;
					
				}
			}
			return $transcriptString;
		}
		$printScript = prepSegTranscript($transcript, $docInfo);
		//print_r($printScript);

		// need to preprare also the text for the description. (no loop in the view)
		if ($transcript === 0) {
			$error = true;
			$errorMsg = "Something is wrong with your transcript file.";
		} else
			$ready = true;

		// check if media file exists
		if(!file_exists($docInfo['media'])) {
			$error = true;
			$ready = false;
			$errorMsg = "Video not found based on XML media location (".$docInfo['media'].")";
		}
	}
	// if XML transcript is missing in uploads folder, we throw an error
	else {
		$error = true;
		$ready = false;
		$errorMsg = "Cannot load $document. Problem with document entry.";
	}
}

function return_bytes($val) {
	$val = trim($val);
	$last = strtolower($val[strlen($val)-1]);
	switch($last)
	{
		case 'g':
		$val *= 1024;
		case 'm':
		$val *= 1024;
		case 'k':
		$val *= 1024;
	}
	return $val;
}

function max_file_upload() {
	//select maximum upload sizes
	$max_upload = return_bytes(ini_get('upload_max_filesize'));
	$max_post = return_bytes(ini_get('post_max_size'));
	$memory_limit = return_bytes(ini_get('memory_limit'));
	// return the smallest of them, this defines the real limit
	$actual_limit = min($max_upload, $max_post, $memory_limit);

	$units = array('B', 'KB', 'MB', 'GB', 'TB');
	$bytes = max($actual_limit, 0);
	$pow = floor(($actual_limit ? log($actual_limit) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);

	return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<!--
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
 * @author Bejamin Bigot (c) 2013
 * @author Ong Jia Hui (c) 2015
 * @author Kyaw Zin Tun (c) 2015
 * @author Hew Jun Wei Zach (c) 2016
 * @author Tan Yan Ling (c) 2017
-->
<html>
	<head>
		<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
		<title>Web Based Transcription Editor</title>
		<link href="./files/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
		<link href="./files/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css" />
		<link href="./files/css/ionicons.min.css" rel="stylesheet" type="text/css" />
		<link href="./files/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
		<link href="./files/css/jquery-ui.css" rel="stylesheet" />
		<link href="./files/css/jquery.highlighttextarea.min.css" rel="stylesheet" />
		<link href="./files/css/style.min.css" rel="stylesheet" type="text/css" />
		<script src="./files/js/jquery-1.12.4.min.js" type='text/javascript'></script>
		<script src="./files/js/videojs.wavesurfer.js" type='text/javascript'></script>
		<style type="text/css">
		/* Sticky footer styles
		-------------------------------------------------- */
		html {
			position: relative;
			min-height: 100%;
		}
		body {
			/* Margin bottom by footer height */
			margin-bottom: 60px;
		}
		.footer {
			position: absolute;
			bottom: 0;
			width: 100%;
			/* Set the fixed height of the footer here */
			height: 60px;
			background-color: #f5f5f5;
			text-align:center;
			padding-top:20px;
		}
		</style>
	</head>
	<body>
		<div class="jumbotron">
			<div class="container">
				<div class="row">
					<div class="col-lg-10 col-lg-offset-1">
						<p id="lslabel"><a href="./"><i class="ionicons ion-android-hangout" style="font-size:35px;line-height:10px;margin-right:5px"></i>&nbsp; Offline Web Subtitle Editor</a></p>
					</div>
				</div>
			</div>
		</div>
		<div style="margin-top:20px">&nbsp;</div>

		<?php if(!$ready) { ?>
		<div class="col-lg-10 col-lg-offset-1">
			<?php if($error) { ?>
			<div class="alert alert-danger">
				<strong>Error :</strong> <?php echo $errorMsg; ?>
			</div>
			<?php
				}
				// perform folder cleanup when error encountered or when uploading new document
				cleanUploads();
			?>
			<div class="row">
				<div class="col-sm-6">
					<div id="step1" class="box">
						<h3>Select Media File</h3>
						<div class="row" style="padding:20px 0">
							<div class="col-sm-12">
								<div id="progressOuter" class="progress progress-striped active" style="display:none;">
									<div id="progressBar" class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%">
									</div>
								</div>
								<div id="dragbox1" style="color:#b2b2b2;background:#fafafa;border:2px dashed #e5e5e5;margin:0 auto;font-size:22px;margin-bottom:20px;padding:40px 0"><i class="fa fa-download"></i>&nbsp; Drop Media Here</div>
								<button id="uploadBtn" class="btn btn-large btn-primary">Choose File</button>
							</div>
						</div>
						<div class="row">
							<div class="col-lg-12">
								<div id="msgBox">
									Max File Size: <?php echo max_file_upload(); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-sm-6">
					<div id="step2" class="box">
						<h3>Select XML File</h3>
						<div class="row" style="padding:20px 0">
							<div class="col-sm-12">
								<div id="progressOuter2" class="progress progress-striped active" style="display:none;">
									<div id="progressBar2" class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%">
									</div>
								</div>
								<div id="dragbox2" style="color:#b2b2b2;background:#fafafa;border:2px dashed #e5e5e5;margin:0 auto;font-size:22px;margin-bottom:20px;padding:40px 0"><i class="fa fa-download"></i>&nbsp; Drop XML Here</div>
								<button id="uploadBtn2" class="btn btn-large btn-primary">Choose File</button>
							</div>
						</div>
						<div class="row">
							<div class="col-xs-12">
								<div id="msgBox2">
									Max File Size: <?php echo max_file_upload(); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php } else {
			$audioFormat = array('wav','mp3','aac');
			$videoFormat = array('mp4');
			/*if (in_array($docInfo['type'],$audioFormat)) {
				$elementStartTag = "audio id='media'";
				$elementType = "audio/".$docInfo['type'];
				$elementEndTag = "/audio";
			} elseif (in_array($docInfo['type'],$videoFormat)) {
				$elementStartTag = "video id='media'";
				$elementType = "video/".$docInfo['type'];
				$elementEndTag = "/video";
				$elementName = "video";
			} */
			if (in_array($docInfo['type'],$videoFormat)) {
				$elementStartTag = "video id='media'";
				$elementType = "video/".$docInfo['type'];
				$elementEndTag = "/video";
				$elementName = "video";
			} elseif (in_array($docInfo['type'],$audioFormat)) {
				$elementStartTag = "audio id='media'";
				$elementType = "audio/".$docInfo['type'];
				$elementEndTag = "/audio";
			} 
			else {
				$elementName = "video";
			}
		?>
		<div id="wrap">
				<div class="col-lg-10 col-lg-offset-1">
					<h4 class="videoTitle"><?php echo $document?></h4>
					<div class="box">
						<script type="text/javascript">
						var segments;
						var player1;
						var loop_start = 0;
						var loop_end = 0;
						var int_start = parseFloat(0);
						var int_end = parseFloat(0);
						var intervalNo = 4;
						var pauseInterval = [];
						var wordsToBeHighlighted = [];
						var shcut_seg;
						var shcut_index;
						var shcut_text;
						var shcut_timings;
						var temp_hightlight = "";
						var swc = true;
						// whether player can play next segment automatically
						var playNext = true;
						// store state of video playing initiated by user
						var isPlaying = false;
						// track to ensure that video is paused before going into next segment while user is typing
						var lastWordTime;

						// arr to store the current segment text
						var arr = [];
						// endIndex to store the position of text in current segment
						var endIndex = 0;
						// wordStartTimes to store all the timings of the words in a segment
						var wordStartTimes;
						var wordEndTimes;
						// global counter for loop
						var loopCounter = parseInt(0);
						// capture caret positions
						var caretStart = 0;
						var caretEnd = 0;

						// do not autoplay next segment if user is still typing
						var typingTimer;                //timer identifier
						var doneTypingInterval = 3000;  //time in ms (3 seconds)

						// Declarations
						var display_sort = '<i class="ionicons ion-android-textsms"></i> &nbsp;Transcript';
						var display_search = '<i class="ionicons ion-android-search" style="top:-1px"></i> &nbsp;Search Result';
						</script>
						<?php if(isset($elementName)) { ?>
						<!-- LEFT SIDE-->
						<div class="col-md-5">
							<div class="mediaContainer">
								<!-- Video pane=======================================-->
								<div id="alertResultsSTL"></div>
								<div id="alertResults"></div>
								<div class="media-box">
									<div id="player" class="paused scroll-locked">
										<<?php echo $elementStartTag ?>>
										<source src="<?php echo $docInfo['media'] ?>" type="<?php echo $elementType?>">
										<p>Your browser doesn't support HTML5 video.</p>
										<<?php echo $elementEndTag ?>>
										<div class="watermark">
											<div class="watermark-button">
												<i class="fa fa-play fa-3x"></i>
											</div>
										</div>
										<div class="control-box">
											<div class="control-box-inner">
												<div class="pull-right" style="display:none">
													<div class="control-btn scroll-unlock" data-toggle="tooltip" title="Scroll locked"><i class="fa fa-lock"></i></div>
													<div class="control-btn scroll-lock" data-toggle="tooltip" title="Turn on scroll lock"><i class="fa fa-unlock"></i></div>
													<div class="control-btn subtitle-language" data-toggle="tooltip" title="Subtitle language">EN</div>
												</div>
												<div style="display:table-row">
													<div class="control-btn play-btn" data-toggle="tooltip" title="Play"><i class="ionicons ion-ios-play"></i></div>
													<div class="control-btn pause-btn" data-toggle="tooltip" title="Pause"><i class="ionicons ion-ios-pause"></i></div>
													<div class="control-btn replay-btn" data-toggle="tooltip" title="Replay"><i class="ion-android-refresh"></i></div>
													<!-- TODO: put these controls in a drop-up menu -->
													<div class="control-btn prev-segment-btn" data-toggle="tooltip" title="Previous Line"><i class="fa fa-step-backward" style="font-size:17px;margin:2px 3px 0 15px"></i></div>
													<div class="control-btn next-segment-btn" data-toggle="tooltip" title="Next Line"><i class="fa fa-step-forward" style="font-size:17px;margin:2px 1px 0 3px"></i></div>
													<div class="control-time">
														<span class="time-current">-:--</span>
													</div>
													<div class="control">
														<div class="progress">
															<div class="progress-bar">
																<div class="track" style="width:0%"></div>
																<div class="knob" style="left:0%"></div>
															</div>
															<div class="highlights">
															</div>
															<div class="progress-bar">
																<div class="knob" style="left:0%"></div>
															</div>
														</div>
													</div>
													<div class="control-time">
														<span class="time-duration">-:--</span>
													</div>
													<div class="control-btn" style="padding:0;font-size:19px">
														<i id="loop" class="fa fa-repeat fa-flip-horizontal" data-toggle="tooltip" title="Repeat"></i>
													</div>
													<div class="control-time" id="loopCycleContainer" data-toggle="tooltip" title="No of times to Repeat" style="padding-left:0;position:relative;top:1px;display:none;margin:0 30px 0 3px">
														<input onkeydown="return false;" onfocus="this.blur()" id="loopCycle" type="number" min="0" max="5" step="1" value="1" onchange="audioLoop()" style="width:33px;border:0;color:#fff;background:transparent;vertical-align:middle;font-size:14px" />
													</div>
													<div class="control-time" style="padding-left:0" data-toggle="tooltip" title="Speed">
														<input onkeydown="return false;" onfocus="this.blur()" onchange="adjustSpeed()" id="speed" type="number" min="0.5" max="1.5" step="0.1" value ="1.0" style="display:inline;border:0;width:50px;color:#fff;background:transparent;vertical-align:middle;margin:0 0 0 8px" /><span style="color:#fff;position:relative;top:-10px;right:-4px">x</span>
													</div>
													<div class="control-btn" style="padding-left:2px;padding-right:6px;font-size:16px;position:relative;top:-0.5px">
														<i class="fa fa-hand-pointer-o on" style="font-size:19px;position:relative;top:1px" id="swc" data-toggle="tooltip" title="Sync with Cursor in Editor"></i>
													</div>
													<div class="control-btn" style="padding:0;max-width:30px;">
														<div class="volume">
															<div class="volume-holder">
																<div class="volume-bar-holder">
																	<div class="volume-bar">
																		<div class="volume-button-holder">
																			<div class="volume-button"></div>
																		</div>
																	</div>
																</div>
															</div>
															<div id="volwrap" data-toggle="tooltip" title="Volume">
																<div id="ionvolume" style="padding-right:29px;padding-top: 10px;margin-top: -10px;">
																	<i class="ionicons ion-android-volume-mute" style="font-size:21px;color:#fff;vertical-align:middle"></i>
																</div>
																<div class="volume-icon">
																	<span> </span>
																</div>
															</div>
														</div>
													</div>
													<!--<div class="control-btn prev-match-btn" data-toggle="tooltip" title="Previous match"><i class="fa fa-caret-up"></i></div>
														<div class="control-btn next-match-btn" data-toggle="tooltip" title="Next match"><i class="fa fa-caret-down"></i></div>-->
												</div>
											</div>
											<!-- .control-box-inner -->
										</div>
										<!-- .control-box -->
									</div>
									<!-- #player -->
								</div>
								<!-- .media-box -->
							</div>
						</div>
						<!-- /LEFT SIDE-->
						<?php } ?>
						
						
						<div class="col-md-<?php echo (isset($elementName) ? "7" : "12"); ?>">
						
										
							<!-- VIDEO-->
							<!-- <div id="alertResults"></div> -->
							<div class="panel panel-default hidden-xs" style="margin-top:0;
							<?php if(!isset($elementName)) { ?>margin-bottom:-1px;border-bottom-left-radius:0;border-bottom-right-radius:0<?php } ?>">
								<div class="panel-body">
									<div class="panel-header">
										<div class="col-lg-3" style="padding-left:0">
											<h3 id="header-text"><i class="ionicons ion-android-textsms"></i> &nbsp;Transcript</h3>
										</div>
										<div class="col-lg-4 sortDiv">
											<div class="btn-group btn-toggle">
												<button id="sortTime" class="btn btn-xs btn-primary active" onclick="sortByTime()">Time</button>
												<button id="sortConfidence" class="btn btn-xs btn-default" onclick="sortByConfidence()">Confidence Score</button>
											</div>
										</div>
										<div class="col-lg-1 pull-right infoBtn">
											<a href="#infoModal" data-toggle="modal"><i class="ionicons ion-information-circled" style="color:#045bb3;font-size:20px;display:table-cell;"></i></a>
										</div>
										<div class="col-lg-4 pull-right searchBox" style="position:relative;top:-5px;padding-right:0">
											<div class="input-group inline" id="adv-search">
												<input type="text" class="form-control" id="searchText" placeholder="Search..." />
												<div class="input-group-btn">
													<div class="btn-group" role="group">
														<button type="button" onclick="searchSort()" class="btn btn-primary"><i class="fa fa-search" style="position:relative;top:-1px"></i></button>
													</div>
												</div>
											</div>
										</div>
									</div>
									<!-- TABS CONTROLS -->
									<!-- <ul id="myTab" class="nav nav-tabs nav-justified">
										<li class="active"><a data-toggle="tab" href="#home">
											<i class="icon-info-sign"></i> TRANSCRIPT TAB </a>
										</li>
										<li><a data-toggle="tab" href="#profile">
											<i class="icon-info-sign"></i> DESCRIPTION TAB </a>
										</li>
										<li><a data-toggle="tab" href="#search">-->
										<!--<i class="icon-info-sign"></i>SUGGESTED VIDEOS </a></li>
									</ul> -->
									<!-- /TABS CONTROLS -->
									<!-- PANES -->
									<div id="myTabContent" class="tab-content">
										<div id="home" class="tab-pane fade in active">
											<div class="transcriptions" id="transcriptsBox">
												<?php foreach($printScript as $key => $value){
													echo $printScript[$key]['speaker'];
													$t = $printScript[$key]['text'];
													echo $t. "<br/>" ;
													}?>
											</div>
										</div>
										<!-- <div id="profile" class="tab-pane fade widget-tags ">
											<table class="tableContainer">
												<tr>
													<td class="leftTableCol">Title: </td>
													<td class="rightTableCol"><?php //echo $document?><br></td>
												</tr>
												<tr>
													<td class="leftTableCol">Content Type: </td>
													<td class="rightTableCol"><?php //echo $docInfo['type']?><br></td>
												</tr>
												<tr>
													<td class="leftTableCol">Speaker(s): </td>
													<td class="rightTableCol"><?php //echo $docInfo['speakerEdit']?><br></td>
												</tr>
												<tr>
													<td class="leftTableCol">Description: </td>
													<td class="rightTableCol"><?php// echo $docInfo['description']?><br></td>
												</tr>
											</table>
										</div> -->
										<!--<div id="search" class="tab-pane fade widget-tags ">
											</div>-->
											
											
									</div>
									<?php if(!isset($elementName)) { ?>
									<div class="mediaContainer" style="margin-top:30px;border-top:1px solid #ddd;padding:2px 20px 0 20px;background-color:#eee">
										<div id="waveform" style="padding:10px 0 0 0">
											<div class="progress progress-striped active" id="progress-bar" style="height:20px;margin-top:70px">
												<div class="progress-bar progress-bar-info" style="height:20px"></div>
											</div>

											<!-- Here be waveform -->
										</div>
										<div id="wave-spectrogram"></div>
										<br><br>
										<div class="row col-md-12 pull-right" style="color:#000">
											<div class="col-sm-1">
												Zoom Out
											</div>

											<div class="col-sm-3">
											  <input data-action="zoom" type="range" min="0" max="100" value="0" style="width: 100%" />
											</div>

											<div class="col-sm-1">
												Zoom In
											</div>
										</div>
										<br><br>
										
										
										
										<!-- Video pane -->
										<div id="alertResultsSTL"></div>
										<div id="alertResults"></div>
										<div class="media-box" style="background:transparent;box-shadow:none">
											<div id="player" class="paused scroll-locked">
												<<?php echo $elementStartTag ?>>
												<source src="<?php echo $docInfo['media'] ?>" type="<?php echo $elementType?>">
												<p>Your browser doesn't support HTML5 video.</p>
												<<?php echo $elementEndTag ?>>
												<div class="watermark">
													<div class="watermark-button">
														<i class="fa fa-play fa-3x"></i>
													</div>
												</div>
												<div class="control-box">
													<div class="control-box-inner-audio">
														<div class="pull-right" style="display:none">
															<div class="control-btn scroll-unlock" data-toggle="tooltip" title="Scroll locked"><i class="fa fa-lock"></i></div>
															<div class="control-btn scroll-lock" data-toggle="tooltip" title="Turn on scroll lock"><i class="fa fa-unlock"></i></div>
															<div class="control-btn subtitle-language" data-toggle="tooltip" title="Subtitle language">EN</div>
														</div>
														<div style="display:table-row">
															<div class="control-btn play-btn" data-toggle="tooltip" title="Play"><i class="ionicons ion-ios-play"></i></div>
															<div class="control-btn pause-btn" data-toggle="tooltip" title="Pause"><i class="ionicons ion-ios-pause"></i></div>
															<!-- TODO: put these controls in a drop-up menu -->
															<div class="control-btn prev-segment-btn" data-toggle="tooltip" title="Previous Line"><i class="fa fa-step-backward" style="font-size:17px;margin:2px 3px 0 15px"></i></div>
															<div class="control-btn next-segment-btn" data-toggle="tooltip" title="Next Line"><i class="fa fa-step-forward" style="font-size:17px;margin:2px 1px 0 3px"></i></div>
															<div class="control-time">
																<span class="time-current">-:--</span>
															</div>
															<div class="control">
																<div class="progress">
																	<div class="progress-bar">
																		<div class="track" style="width:0%"></div>
																		<div class="knob" style="left:0%"></div>
																	</div>
																	<div class="highlights">
																	</div>
																	<div class="progress-bar">
																		<div class="knob" style="left:0%"></div>
																	</div>
																</div>
															</div>
															<div class="control-time">
																<span class="time-duration">-:--</span>
															</div>
															<div class="control-btn" style="padding:0;font-size:19px">
																<i id="loop" class="fa fa-repeat fa-flip-horizontal" data-toggle="tooltip" title="Repeat"></i>
															</div>
															<div class="control-time" id="loopCycleContainer" data-toggle="tooltip" title="No of times to Repeat" style="padding-left:0;position:relative;top:1px;display:none;margin:0 30px 0 3px">
																<input onkeydown="return false;" onfocus="this.blur()" id="loopCycle" type="number" min="0" max="5" step="1" value="1" onchange="audioLoop()" style="width:33px;border:0;color:#fff;background:transparent;vertical-align:middle;font-size:14px" />
															</div>
															<div class="control-time" style="padding-left:0" data-toggle="tooltip" title="Speed">
																<input onkeydown="return false;" onfocus="this.blur()" onchange="adjustSpeed()" id="speed" type="number" min="0.5" max="1.5" step="0.1" value ="1.0" style="display:inline;border:0;width:50px;color:#fff;background:transparent;vertical-align:middle;margin:0 0 0 8px" /><span style="color:#fff;position:relative;top:-10px;right:-29px">x</span>
															</div>
															<div class="control-btn" style="padding-left:3px;padding-right:7px;font-size:17px">
																<i class="fa fa-hand-pointer-o on" id="swc" data-toggle="tooltip" title="Sync with Cursor in Editor"></i>
															</div>
															<div class="control-btn" style="padding:0;max-width:30px;">
																<div class="volume">
																	<div class="volume-holder">
																		<div class="volume-bar-holder">
																			<div class="volume-bar">
																				<div class="volume-button-holder">
																					<div class="volume-button"></div>
																				</div>
																			</div>
																		</div>
																	</div>
																	<div id="volwrap" data-toggle="tooltip" title="Volume">
																		<div id="ionvolume" style="padding-right:29px;padding-top: 10px;margin-top: -10px;">
																			<i class="ionicons ion-android-volume-mute" style="font-size:21px;color:#fff;vertical-align:middle"></i>
																		</div>
																		<div class="volume-icon">
																			<span> </span>
																		</div>
																	</div>
																</div>
															</div>
															<!--<div class="control-btn prev-match-btn" data-toggle="tooltip" title="Previous match"><i class="fa fa-caret-up"></i></div>
																<div class="control-btn next-match-btn" data-toggle="tooltip" title="Next match"><i class="fa fa-caret-down"></i></div>-->
														</div>
													</div>
													<!-- .control-box-inner -->
												</div>
												<!-- .control-box -->
											</div>
											<!-- #player -->
										</div>
										<!-- .media-box -->
									</div>
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- EDITOR -->
				<div class="col-lg-10 col-lg-offset-1" style="margin-bottom:30px;width=3000px">
					<div class="well"<?php if(!isset($elementName)) { ?> style="border-top-left-radius:0;border-top-right-radius:0;padding-top:15px"<?php } ?>>
						<div class="row">
							<!-- start of text box -->
							<div class="col-md-12">
								<div class="input-group">
									<span class="input-group-btn">
										<button class="btn btn-default" id="btnPrev" onclick="prevSentence()" type="submit" style="display:inline;padding:38px 12px;" disabled="disabled"><i class="ionicons ion-chevron-left"></i></button>
									</span>
									<textarea class="form-control" rows="3" readonly="readonly" id="tbxEdit" placeholder="Click on any sentence of the transcript to edit"></textarea>
									<span class="input-group-btn">
										<button class="btn btn-default" id="btnNext" onclick="nextSentence()" type="submit" style="display:inline;padding:38px 12px" disabled="disabled"><i class="ionicons ion-chevron-right"></i></button>
									</span>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-sm-12" style="margin-top:10px">
								<div class="col-lg-5" style="padding:0;line-height:20px;font-size:15px;margin:6px 0 0 0">
									<i class="fa fa-repeat fa-flip-horizontal" style="font-size:20px;line-height:10px;position:relative;top:3px"></i>&nbsp; No of words per region/repeat: &nbsp;<input onkeydown="return false;" onfocus="this.blur()" id="interval" type="number" min="3" max="30" step="1" value ="4" onchange="intervalNo=this.value;" style="width:35px;border:0;font-weight:bold" />
									<br>
									<i class="fa fa-keyboard-o" style="font-size:25px;line-height:10px;position:relative;top:3px;margin:6px 0 0 0"></i>&nbsp; Autoplay next line after no typing/clicking for: &nbsp;<input onkeydown="return false;" onchange="doneTypingInterval=this.value*1000;" onfocus="this.blur()" id="typing" type="number" min="1" max="10" step="1" value ="3" style="width:35px;border:0;font-weight:bold" /> &nbsp;secs
								</div>
								<div class="col-lg-7" style="padding:0;line-height:20px;font-size:15px;margin:3px 0 0 0">
									<div class="pull-right">
										<form action="./files/saveTranscriptSRT.php" onsubmit="return cancelSubmit()" method="POST">
											<input type="hidden" name="xmlLoc" id="xmlLoc" value="<?php echo $docInfo['xmlLoc'] ?>" />
											<button class="btn btn-success" name="saveSRT" id="saveSRT">
											<i class="ionicons ion-pull-request" style="font-size:18px;position:relative;top:-1px;line-height:10px;margin-right:8px"></i> Save as SRT</button>
										</form>
										<form action="./files/saveTranscriptXML.php" id="save_xml" method="POST">
											<input type="hidden" name="processedXML" id="processedXML" />
											<input type="hidden" name="xmlLoc" id="xmlLoc" value="<?php echo $docInfo['xmlLoc'] ?>" />
											<input type="hidden" name="download" id="download" value="false" />
											<button type="submit" class="btn btn-success"><i class="ionicons ion-pull-request" style="font-size:18px;position:relative;top:-1px;line-height:10px;margin-right:8px"></i> Save Updated XML</button>
											<button type="button" class="btn btn-primary" onclick="saveScript(true)"><i class="ionicons ion-android-archive" style="font-size:18px;position:relative;top:-1px;line-height:10px;margin-right:8px"></i> Save Transcript</button>
										</form>
									</div>
									<span class="pull-right" style="margin-right: 15px">
										<button class="btn btn-default" onclick="$('#shortcuts').toggle();this.blur();">Show/Hide Shortcut Keys</button>
									</span>
								</div>
							</div>
							<div class="col-md-12" id="shortcuts" style="display:none">
								<hr />
								<div class="shortcuts">
									<ul class="nav nav-pills">
										<li role="presentation"><center><kbd>F1</kbd><br><a>Play/Pause</a></center></li>
										<li role="presentation"><center><kbd>F2</kbd><br><a>Prev Line</a></center></li>
										<li role="presentation"><center><kbd>F3</kbd><br><a>Next Line</a></center></li>
										<li role="presentation"><center><kbd>F4/F5</kbd><br><a>Repeat <i class="ionicons ion-minus"></i>/<i class="ionicons ion-plus"></i></a></center></li>
										<li role="presentation"><center><kbd>F6/F7</kbd><br><a>Speed <i class="ionicons ion-minus"></i>/<i class="ionicons ion-plus"></i></a></center></li>
										<li role="presentation"><center><kbd>F8</kbd><br><a>Current Position</a></center></li>
										<li role="presentation"><center><kbd>F9</kbd><br><a>Prev Word</a></center></li>
										<li role="presentation"><center><kbd>F10</kbd><br><a>Next Word</a></center></li>
										<li role="presentation"><center><kbd>F11</kbd><br><a>Sync with Cursor On/Off</a></center></li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- /LEFT SIDE-->
				<!-- Modal box -->
				<div id="infoModal" class="modal fade">
					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
								<h4 class="modal-title"><i class="ionicons ion-information-circled"></i> &nbsp;Information</h4>
							</div>
							<div class="modal-body" id="modal-text">
								<table class="tableContainer">
									<tr>
										<td class="leftTableCol">Title: </td>
										<td class="rightTableCol"><?php echo $document?><br></td>
									</tr>
									<tr>
										<td class="leftTableCol">Content Type: </td>
										<td class="rightTableCol"><?php echo $docInfo['type']?><br></td>
									</tr>
									<tr>
										<td class="leftTableCol">Speaker(s): </td>
										<td class="rightTableCol"><?php echo $docInfo['speakerEdit']?><br></td>
									</tr>
									<tr>
										<td class="leftTableCol">Description: </td>
										<td class="rightTableCol"><?php echo $docInfo['description']?><br></td>
									</tr>
								</table>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</div>
				<!-- /modal box -->
			</div>
		</div>
	</div>
		<?php
		}
		if(!$ready) {
		?>
		<!-- SimpleAjaxUploader -->
		<script src="./files/js/SimpleAjaxUploader.min.js"></script>
		<script type="text/javascript">
		var xml_uploaded = false;
		var media_uploaded = false;
		var xml_name = "";

		function escapeTags(str) {
			return String(str)
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
		}
		window.onload = function() {
			
			var btn = document.getElementById('uploadBtn'),
				btn2 = document.getElementById('uploadBtn2'),
				progressBar = document.getElementById('progressBar'),
				progressBar2 = document.getElementById('progressBar2'),
				progressOuter = document.getElementById('progressOuter'),
				progressOuter2 = document.getElementById('progressOuter2'),
				msgBox = document.getElementById('msgBox'),
				msgBox2 = document.getElementById('msgBox2');

			var uploader = new ss.SimpleUpload({
				button: btn, // file upload button
				url: './files/file_upload.php', // server side handler
				name: 'uploadfile', // upload parameter name
				progressUrl: './files/sessionProgress.php',
				responseType: 'json',
				hoverClass: 'btn-hover',
				focusClass: 'focus',
				disabledClass: 'disabled',
				accept: 'audio/*,video/*',
				maxUploads: 1,
				dropzone: 'dragbox1',
				maxSize: 10240000,
				startXHR: function() {
					progressOuter.style.display = 'block'; // make progress bar visible
					this.setProgressBar(progressBar);
				},
				endXHR: function() {
					btn.style.display = 'none';
					$('#dragbox1').hide();
					progressOuter.style.display = 'none'; // hide progress bar
				},
				onSubmit: function() {
					msgBox.innerHTML = ''; // empty the message box
					btn.style.display = 'none';
					$('#dragbox1').hide();
					//btn.innerHTML = 'Uploading...'; // change button text to "Uploading..."
				},
				onComplete: function(filename, response) {
					//btn.innerHTML = 'Choose File';
					progressOuter.style.display = 'none'; // hide progress bar when upload is completed

					if (!response) {
						msgBox.innerHTML = '<span style="color:#c00">Unable to upload file</span>';
						btn.style.display = 'block';
						$('#dragbox1').show();
						return;
					}

					if (response.success === true) {
						msgBox.innerHTML = '<div style="color:#01a914;font-size:20px;padding:50px 0 110px 0">Media Uploaded Successfully!</div>';
						media_uploaded = true;

						if(media_uploaded && xml_uploaded)
							window.location.href = window.location.pathname+"?document="+xml_name;
					} else {
						if (escapeTags(response.msg))  {
							msgBox.innerHTML = '<span style="color:#c00">' + escapeTags(response.msg) + '</span>';
						} else {
							msgBox.innerHTML = '<span style="color:#c00">An error occurred and the upload failed.</span>';
						}
						btn.style.display = 'block';
						$('#dragbox1').show();
					}
				},
				onError: function() {
					progressOuter.style.display = 'none';
					msgBox.innerHTML = '<span style="color:#c00">Unable to upload file</span>';
					btn.style.display = 'block';
					$('#dragbox1').show();
				},
				onExtError: function() {
					msgBox.innerHTML = '<span style="color:#c00">Invalid file type. Please select a valid media file.</span>';
					btn.style.display = 'block';
					$('#dragbox1').show();
				}
			});

			var uploader2 = new ss.SimpleUpload({
				button: btn2, // file upload button
				url: './files/file_upload.php', // server side handler
				name: 'uploadfile', // upload parameter name
				progressUrl: './files/sessionProgress.php',
				responseType: 'json',
				hoverClass: 'btn-hover',
				focusClass: 'focus',
				disabledClass: 'disabled',
				accept: '.xml',
				maxUploads: 1,
				dropzone: 'dragbox2',
				maxSize: 10240000,
				startXHR: function() {
					progressOuter2.style.display = 'block'; // make progress bar visible
					this.setProgressBar(progressBar2);
				},
				endXHR: function() {
					btn2.style.display = 'none';
					$('#dragbox2').hide();
					progressOuter2.style.display = 'none'; // hide progress bar
				},
				onSubmit: function() {
					msgBox2.innerHTML = ''; // empty the message box
					btn2.style.display = 'none';
					$('#dragbox2').hide();
					//btn.innerHTML = 'Uploading...'; // change button text to "Uploading..."
				},
				onComplete: function(filename, response) {
					//btn.innerHTML = 'Choose File';
					//progressOuter.style.display = 'none'; // hide progress bar when upload is completed

					if (!response) {
						msgBox2.innerHTML = '<span style="color:#c00">Unable to upload file</span>';
						btn2.style.display = 'block';
						$('#dragbox2').show();
						progressOuter2.style.display = 'none'; // hide progress bar
						return;
					}

					if (response.success === true) {
						msgBox2.innerHTML = '<div style="color:#01a914;font-size:20px;padding:50px 0 110px 0">XML Uploaded Successfully!</div>';
						xml_uploaded = true;
						xml_name = response.file.substring(0, response.file.length - 4);

						if(media_uploaded && xml_uploaded)
							window.location.href = window.location.pathname+"?document="+xml_name;
					} else {
						if (escapeTags(response.msg))  {
							msgBox2.innerHTML = '<span style="color:#c00">' + escapeTags(response.msg) + '</span>';
						} else {
							msgBox2.innerHTML = '<span style="color:#c00">An error occurred and the upload failed.</span>';
						}
						btn2.style.display = 'block';
						$('#dragbox2').show();
						progressOuter2.style.display = 'none'; // hide progress bar
					}
				},
				onError: function() {
					progressOuter2.style.display = 'none';
					msgBox2.innerHTML = '<span style="color:#c00">Unable to upload file</span>';
					btn2.style.display = 'block';
					$('#dragbox2').show();
					progressOuter2.style.display = 'none'; // hide progress bar
				},
				onExtError: function() {
					msgBox2.innerHTML = '<span style="color:#c00">Invalid file type. Please select a valid XML file.</span>';
					btn2.style.display = 'block';
					$('#dragbox2').show();
					progressOuter2.style.display = 'none'; // hide progress bar
				}
			});
		};
		</script>
		<?php } else { ?>
		<script src="./files/js/magor.js" type="text/javascript"></script>
		<script src="./files/js/magor-player.js" type="text/javascript"></script>
		<script src="./files/js/magor-filter.js" type="text/javascript"></script>
		<script src="./files/js/bootstrap.min.js" type="text/javascript"></script>
		<script src="./files/js/jquery.highlighttextarea.min.js" type="text/javascript"></script>
		<script src="./files/js/jquery-ui.js" type="text/javascript"></script>
		<script src="./files/js/jquery.a-tools-1.5.2.min.js"></script>
		<?php if(!isset($elementName)) { ?>
		<script src="./files/js/wavesurfer.min.js"></script>
		<!-- spectrogram format renderer -->
		<script src="./files/js/wavesurfer.spectrogram.min.js"></script>
		<script src="./files/js/wavesurfer.regions.js"></script>
		<script src="./files/js/wavesurfer.minimap.min.js"></script>
		<script src="./files/js/videojs.wavesurfer.js"></script>
		<?php } ?>
		<script type="text/javascript">
		<?php if(!isset($elementName)) { ?>
		/*var video = videojs('player',
		{
			controls: true,
			autoplay: false,
			loop: false,
			plugins: {
				wavesurfer: {
					src: "./uploads/Lec10MIT16885JAircraftSystemsEngineeringFall2005.mp4",
					msDisplayMax: 10,
					debug: true,
					
				}
		}});
		*/
		
		
		
		var wavesurfer = WaveSurfer.create({
			container: '#waveform',
			waveColor: 'darkGrey',
			progressColor: 'orange',
			normalize: true,
			pixelRatio: 1,
			scrollParent: true,
			height:120,
			minPxPerSec: 50,
			minimap: true
		});

		/**
		 * Random RGBA color.
		 */
		function randomColor(alpha) {
			return 'rgba(' + [
				~~(Math.random() * 255),
				~~(Math.random() * 255),
				~~(Math.random() * 255),
				alpha || 1
			] + ')';

		}

		// Init & load
		document.addEventListener('DOMContentLoaded', function () {
			wavesurfer.on('ready', function (percents) {
				wavesurfer.zoom(50);
				wavesurfer.setVolume(0);
				// Init spectrogram plugin
				/*var spectrogram = Object.create(WaveSurfer.Spectrogram);
				spectrogram.init({
					wavesurfer: wavesurfer,
					container: '#wave-spectrogram'
				});*/
				addRegion();
			});

			/* Progress bar */
			(function () {
				var progressDiv = document.querySelector('#progress-bar');
				var progressBar = progressDiv.querySelector('.progress-bar');

				var showProgress = function (percent) {
					progressDiv.style.display = 'block';
					progressBar.style.width = percent + '%';
				};

				var hideProgress = function () {
					progressDiv.style.display = 'none';
				};

				wavesurfer.on('loading', showProgress);
				wavesurfer.on('ready', hideProgress);
				wavesurfer.on('destroy', hideProgress);
				wavesurfer.on('error', hideProgress);
			}());

			wavesurfer.load('<?php echo $docInfo['media'] ?>');
			$('#media').on('play', function (e) {
				setTimeout(function () {
				  // Resume play if the element if is paused.
				  if (!player1.paused) {
					wavesurfer.play();
				  }
				}, 250);
			});
			$('#media').on('pause', function (e) {
				setTimeout(function () {
				  // Resume play if the element if is paused.
				  if (player1.paused) {
					wavesurfer.pause();
				  }
				}, 250);
			});
			$('#media').on('timeupdate', function (e) {
				wavesurfer.play(player1.currentTime);
			});
			wavesurfer.on('play', function () {
				setTimeout(function () {
				  // Resume play if the element if is paused.
				  if (player1.paused) {
					wavesurfer.pause();
				  }
				}, 250);
			});
			wavesurfer.on('pause', function () {
				setTimeout(function () {
				  // Resume play if the element if is paused.
				  if (!player1.paused) {
					wavesurfer.play();
				  }
				}, 250);
			});
			wavesurfer.on('seek', function (progress) {
				magor.magorPlayer.currentTime(magor.magorPlayer._duration * progress);
			});

			/* Minimap plugin */
			wavesurfer.initMinimap({
				height: 30,
				waveColor: '#ddd',
				progressColor: '#999',
				cursorColor: '#999'
			});

			// http://www.farmsoftstudios.com/blog/2015/10/programming/adding-wavesurfer-regions-programatically/
			var addRegion = function(frame) {
				var newRegion, options;
				for(var i = 0; i < segments.length; i++) {
					options = {
					id: segments[i].id,
					start: segments[i].startTime/1000,
					end: (segments[i].startTime + segments[i].duration)/1000,
					color: randomColor(0.1)
					};
					newRegion = wavesurfer.addRegion(options);
				}
			}

			// Zoom slider
			var slider = document.querySelector('[data-action="zoom"]');

			slider.value = wavesurfer.params.minPxPerSec;

			slider.addEventListener('input', function () {
				wavesurfer.zoom(Number(this.value));
			});
		});
		<?php } ?>
		$(document).ready(function() {
			// Tooltips for player controls
			$('[data-toggle="tooltip"]').tooltip({
				position: { at: "center bottom" , my: 'center top+10' }
			});
			
			<?php
			echo "segments = [";
			foreach ($printScript as $key => $value)
				foreach ($printScript[$key]['table'] as $line)
					echo 'new magor.Segment('. $line."),\n";
			echo "];";
			?>

			<?php
			if (isset($matchList))
				echo 'var matches = [' . join(', ', $matchList) .'];'. "\n";
			else {
				echo 'if (localStorage.getItem("matches") != undefined) {'."\n";
				echo 'var matches = JSON.parse(localStorage.getItem("matches"));	'. "\n";
				echo 'matches = matches.split(",").map(Number); }'."\n";
				echo 'else'."\n";
				echo 'var matches = [];'. "\n";
			}
			?>
			//console.log(matches);
			//console.log(segments);
	
			magor.magorPlayer = new magor.MagorPlayer(segments);
			magor.magorPlayer.highlightMatches(matches);
			<?php if(isset($seekTime)) {?>;
				var seekTime = <?php echo $seekTime?>;
				if (seekTime != null) magor.magorPlayer.currentTime(seekTime * 1000);
			<?php }?>
			player1 = magor.magorPlayer.$_media[0];
			//console.log($('#player').attr('class'));
			//console.log(magor.magorPlayer.$_media[0]);

			// adjust video height according to panel height
			$('video').height($('.panel').height());
			
			$("#tbxEdit").change(function(){
				// action goes here!!
				//alert("pause");
				magor.magorPlayer.pause();
				isPlaying = false;
			});

			// Shortcut keys
			$(this).keydown(function(e) {
				// F1 & spacebar - toggle play pause
				if(e.keyCode == 112 || (e.keyCode == 32) && !(e.target.nodeName == "INPUT" || e.target.nodeName == "TEXTAREA")) {
					e.preventDefault();
					// update play state
					if(player1.paused)
						isPlaying = true;
					else
						isPlaying = false;
					magor.magorPlayer.togglePlayPause();
				}

				// F2 & F3 - toggle prev/next segment
				else if(e.keyCode == 113 || e.keyCode == 114) {
					e.preventDefault();
					// if video hasn't capture any segment yet
					if(magor.magorPlayer._currentSegments.length == 0) {
						// attempt to play video to get segment
						magor.magorPlayer.play();
						setTimeout(function () {
							// pause the video
							magor.magorPlayer.pause();
						}, 150);
					}
					if(e.keyCode == 113)
						magor.magorPlayer.prevSegment();
					else
						magor.magorPlayer.nextSegment();
				}

				// F4 - turn loop off/decrement
				else if(e.keyCode == 115) {
					e.preventDefault();
					if($("#loopCycle").val() > 1) {
						document.getElementById("loopCycle").stepDown();
					} else {
						$("#loop").removeClass("on");
						player1.loop = false;
						$("#loopCycleContainer").css("display", "none");
					}
				}

				// F5 - turn loop on/increment
				else if(e.keyCode == 116) {
					e.preventDefault();
					if($("#loop").hasClass("on")) {
						document.getElementById("loopCycle").stepUp();
					} else {
						$("#loop").click();
					}
				}

				// F6 - decrease speed
				else if(e.keyCode == 117) {
					e.preventDefault();
					document.getElementById("speed").stepDown();
					adjustSpeed();
				}

				// F7 - increase speed
				else if(e.keyCode == 118) {
					e.preventDefault();
					document.getElementById("speed").stepUp();
					adjustSpeed();
				}

				// F8/F10 - prev/next word
				else if((e.keyCode == 120 || e.keyCode == 121) && !$("#tbxEdit").is('[readonly]')) {
					e.preventDefault();
					var caretPosition = 0;

					// if caret is at textarea, we must assume user may dynamically changed the content inside, therefore update accordingly
					if($("#tbxEdit").is(":focus")) {
						shcut_text = $("#tbxEdit").val().split(" ");
						if(shcut_text[shcut_text.length-1].trim == "")
							shcut_text.pop();
					} else {
						getCurrentWord(false);
					}
					// get current position of caret
					caretPosition = $("#tbxEdit").getSelection().start;

					// find the current index
					var textCount = 0;
					shcut_index = 0;
					for(i = 0; i < shcut_text.length; i++) {
						if(textCount > caretPosition)
							break;
						// capture no of words from textarea start to caret position
						// add space between words
						textCount += shcut_text[i].length + 1;
						shcut_index++;
					}
					// array starts from zero
					shcut_index--;
					// minus one for the extra space we add earlier on in for loop
					textCount--;
					//console.log("Current Text: " + shcut_text[shcut_index] + " (Index: " + shcut_index + ") | Text count: " + textCount);

					var lastIndex, firstIndex;
					// prev word
					if(e.keyCode == 120) {
						// simple case: at start of textarea
						if(shcut_index <= 0) {
							firstIndex = 0;
							lastIndex = shcut_text[0].length;
						}
						else {
							var prevWord = shcut_text[shcut_index-1].length;
							lastIndex = textCount - (shcut_text[shcut_index].length+1);
							firstIndex = lastIndex - prevWord;
						}
					} else {
						// simple case: at end of textarea
						if(shcut_index >= (shcut_text.length-1)) {
							firstIndex = textCount-shcut_text[shcut_index].length;
							lastIndex = textCount;
						}
						else {
							var nextWord = shcut_text[shcut_index+1].length;
							firstIndex = textCount + 1;	// space between words
							lastIndex = firstIndex + nextWord;
						}
					}
					$("#tbxEdit").setSelection(firstIndex, lastIndex);
					caretStart = firstIndex;
					caretEnd = lastIndex;

					// setup loop parameters
					// prevent under & overflow
					if(shcut_index < 1)
						shcut_index = 1;
					if(shcut_index > wordEndTimes.length)
						shcut_index = wordEndTimes.length;

					loop_start = wordStartTimes[shcut_index-1] * 1000;
					endIndex = (shcut_index-1) + (intervalNo-1);
					if(endIndex >= wordEndTimes.length)
						endIndex = wordEndTimes.length-1;
					loop_end = wordEndTimes[endIndex] * 1000;
				}

				// F9 - make caret position at current timing in textarea
				else if(e.keyCode == 119 && !$("#tbxEdit").is('[readonly]')) {
					e.preventDefault();
					getCurrentWord(true);
				}

				// F11 - toggle play as you type
				else if(e.keyCode == 122) {
					e.preventDefault();
					$("#swc").toggleClass("on");
					if($("#swc").hasClass("on")) {
						swc = true;
					} else {
						swc = false;
					}
				}

				// F8 - decrease volume (changed for more useful feature)
				/*else if(e.keyCode == 119) {
					e.preventDefault();
					player1.volume = player1.volume.toFixed(1);
					if(player1.volume > 0) {
						player1.volume -= 0.1;
						if(player1.volume < 0) {
							player1.volume = 0;
						}
						volume = player1.volume;
					}
					volanim();
					var volHeight = parseInt((player1.volume.toFixed(1)*100)*0.8);
					$(".volume-bar").css("height", volHeight);
					$(".volume-holder").show();
					$("#player").find('.volume-holder').fadeOut(100);
				}*/
				// F9 - increase volume (changed for more useful feature)
				/*else if(e.keyCode == 120) {
					e.preventDefault();
					player1.volume = player1.volume.toFixed(1);
					if(player1.volume < 1) {
						player1.volume += 0.1;
						if(player1.volume > 1) {
							player1.volume = 1;
						}
						volume = player1.volume;
					}
					volanim();
					var volHeight = parseInt((player1.volume.toFixed(1)*100)*0.8);
					$(".volume-bar").css("height", volHeight);
					$(".volume-holder").show();
					$("#player").find('.volume-holder').fadeOut(100);
				}*/
				// F9 - decrease words per region (changed for more useful feature)
				/*else if(e.keyCode == 120) {
					e.preventDefault();
					document.getElementById("interval").stepDown();
				}*/
				// F10 - increase words per region (changed for more useful feature)
				/*else if(e.keyCode == 121) {
					e.preventDefault();
					document.getElementById("interval").stepUp();
				}*/
			});
			volume = player1.volume;
			// initialize script by timing
			sortByTime();

		});

		function updateTiming(e) {
			// only proceed event if tbxEdit is editable
			if(e.keyCode != 112 && swc && !$('#tbxEdit').is('[readonly]')) {
				// do not auto play next segment while user still typing
				playNext = false;
				// if not get current word, we need to get index of words again
				if(e.keyCode != 119) {
					// get segment word timings (if not recorded yet)
					if(shcut_seg == null || shcut_seg != $("#getClickedId").val()) {
						// timings are not dynamically updated, therefore only record when it is a new segment
						// however if there is no current segment, we try to get the next segment
						if(magor.magorPlayer._currentSegments[0] == null || magor.magorPlayer._currentSegments[0] == "") {
							magor.magorPlayer._currentSegments[0] = magor.magorPlayer._segmentIdsFromTime(magor.magorPlayer.currentTime()+1000);
						}
						if(magor.magorPlayer._currentSegments.length > 1) {
							var correct_index = 0;
							for(var s = 0; s < magor.magorPlayer._currentSegments.length; s++) {
								if(magor.magorPlayer._currentSegments[s].id == $("#getClickedId").val().split("_")[1]) {
									shcut_timings = magor.magorPlayer._currentSegments[s].wordTimings.split(',');
									correct_index = s;
									break;
								}
							}
						}
						else
							shcut_timings = magor.magorPlayer._currentSegments[0].wordTimings.split(',');
					}

					// if we click prev/next segment, it will start at beginning of segment
					if(e.keyCode == 113 || e.keyCode == 114) {
						shcut_index = 0;
						playNext = true;
					} else {
						var caretPosition = 0;

						// if caret is at textarea, we must assume user may dynamically changed the content inside, therefore update accordingly
						shcut_text = $("#tbxEdit").val().split(" ");
						if(shcut_text[shcut_text.length-1].trim == "")
							shcut_text.pop();
						// get current position of caret
						caretPosition = $("#tbxEdit").getSelection().start;

						// find the current index
						var textCount = 0;
						shcut_index = 0;
						for(i = 0; i < shcut_text.length; i++) {
							if(textCount > caretPosition || shcut_index >= shcut_timings.length)
								break;
							// capture no of words from textarea start to caret position
							// add space between words
							textCount += shcut_text[i].length + 1;
							shcut_index++;
						}
						// array starts from zero
						shcut_index--;
						// minus one for the extra space we add earlier on in for loop
						textCount--;
						// don't update player timing when we set repeat on/off or adjust the speed
						if(!(e.keyCode > 114 && e.keyCode < 119))
							player1.currentTime = shcut_timings[shcut_index];

						// setup loop parameters
						loopCounter = parseInt(0);
						loop_start = shcut_timings[shcut_index] * 1000;
						endIndex = shcut_index + (intervalNo-1);
						if(endIndex >= wordEndTimes.length)
							endIndex = wordEndTimes.length-1;
						loop_end = wordEndTimes[endIndex] * 1000;

						// don't update player timing when we set repeat on/off or adjust the speed
						if(!(e.keyCode > 114 && e.keyCode < 119))
							$("#caretUpdated").val("true");
					}
				}

				// set typing timer
				if(e.keyCode != 112 && e.keyCode != 113 && e.keyCode != 114 && !$('#tbxEdit').is('[readonly]')) {
					// on keyup, start the countdown
					if(!$('#tbxEdit').is('[readonly]')) {
						clearTimeout(typingTimer);
						if ($('#tbxEdit').val()) {
							typingTimer = setTimeout(doneTyping, doneTypingInterval);
						}
					}
				}
				// resume playing at user cursor position text if video is paused due to going into next segment
				if(player1.paused && isPlaying)
					magor.magorPlayer.play();
			}
		}

		// Bind event to update player timing when user edits a word in textarea
		$("#tbxEdit").bind("keyup click", function(e) {
			caretStart = $("#tbxEdit").getSelection().start;
			caretEnd = $("#tbxEdit").getSelection().end;
			updateTiming(e);
		});

		//user is "finished typing," do something
		function doneTyping() {
			playNext = true;
			if(player1.paused && isPlaying)
				magor.magorPlayer.play();
		}

		// Author:  Jacek Becela
		// Source:  http://gist.github.com/399624
		// License: MIT
		jQuery.fn.single_double_click = function(single_click_callback, double_click_callback, timeout) {
			return this.each(function(){
				var clicks = 0, self = this;
				jQuery(this).click(function(event){
					clicks++;
					if (clicks == 1) {
						setTimeout(function(){
							if(clicks == 1) {
								single_click_callback.call(self, event);
							} else {
								double_click_callback.call(self, event);
							}
							clicks = 0;
						}, timeout || 300);
					}
				});
			});
		}

		$("#tbxEdit").single_double_click(function () {
			// single click
		}, function () {
			// double click - replace with accurate caret selection
			caretStart = $("#tbxEdit").getSelection().start;
			caretEnd = $("#tbxEdit").getSelection().end;
		})

		// Sort By actions
		function sortByTime() {
			var result = '<p>';
			var slen = segments.length; // segments length
			var rescroll = 0;
			$('#mode').val("time");

			for(var i = 0; i < slen; i++) {
				// remove any potential highlights done by search
				var regEx = new RegExp("<code>|</code>", "g");
				$('#sid_' + i).html($('#sid_' + i).html().replace(regEx, ""));

				// recover back to the current segment and highlighted
				if($('#getClickedId').val() == "sid_" + i) {
					//result += '<span class="sorted highlight" id="sid_' + i + '">' + $('#sid_' + i).html() + '</span>';
					//$('#tempHighlight').val("sid_" + i);
					rescroll = i;
				}
				result += '<span class="sorted" id="sid_' + i + '">' + $('#sid_' + i).html() + '</span>';
			}
			result += "</p>";
			$(".transcriptions").html(result);
			$("#header-text").html(display_sort);
			$(document).scrollTop( $("#wrap").offset().top - 50);
			var selectedSegment = document.getElementById("sid_" + rescroll);

			// auto scroll to current highlight segment
			$('#transcriptsBox').animate({
				scrollTop: selectedSegment.offsetTop-150
			}, 100);

			$('#transcriptsBox > p > span:has(span.lowConfidence)').addClass('has_low_conf');

		}

		function sortByConfidence() {
			var confCounter = []; // store list of confidence scores, sorted desc to asc
			var slen = segments.length; // segments length
			$('#mode').val("conf");

			// segments array is not sorted by ID
			for(var i = 0; i < slen; i++)
				confCounter.push([segments[i]['id'], segments[i]['confidence']]);
			confCounter.sort(sortCol2Asc);

			var result = '<p>';
			var regEx = new RegExp("<code>|</code>", "g");
			for(var j = 0; j < slen; j++) {
				// remove any potential highlights done by search
				$('#sid_' + confCounter[j][0]).html($('#sid_' + confCounter[j][0]).html().replace(regEx, ""));
				result += '<span class="sorted" id="sid_' + confCounter[j][0] + '">' + $('#sid_' + confCounter[j][0]).html() + '</span>';
			}

			result += "</p>";
			$(".transcriptions").html(result);
			$("#header-text").html(display_sort);
			$(document).scrollTop( $("#wrap").offset().top - 50);

			// auto scroll to the first lowest conf score sentence
			var selectedSegment = document.getElementById("sid_" + confCounter[0][0]);
			$('#transcriptsBox').animate({
				scrollTop: selectedSegment.offsetTop-150
			}, 100);
			$('#transcriptsBox > p > span:has(span.lowConfidence)').addClass('has_low_conf');
		}

		function searchSort() {
			var occurCounter = [];
			// intentionally making search non-regex
			var text = $('#searchText').val().trim().split('.').join("").replace(/[|&;$%@"<>*-={}~`()+,'?\/\[\]]/g, "");
			$('#searchText').val(text);
			$('#mode').val("search");

			// prompt keyword if not found
			if(text == '') {
				alert('Please enter a valid search term or keyword!');
				$('#searchText').val("");
				$('#searchText').focus();
				return false;
			}
			var slen = segments.length; // segments length
			var sentences = [];

			// loop through every segment
			for(var i = 0; i < slen; i++) {
				var arr = [];
				var span_sentence = "";

				// get each word from each segment
				$("#sid_" + i + " span").each(function(index, elem) {
					arr.push($(this).text());
				});

				// find no of matches in the string
				var temp = arr.join(" ");
				var occurence = (temp.match(new RegExp(text, "gi")) || []).length;

				// push into array for sorting by no of matches
				occurCounter.push([i, occurence]);

				// highlight the matches
				var span_low_conf = '';
				var search_string = text.split(" ");

				// replace the entire string sentence first before going into individual word level
				var seg_str = arr.join(" ");
				var seg_str_original_words = arr;
				for(var y = 0; y < search_string.length; y++) {
					var regEx = new RegExp(search_string[y], "gi");
					var replaceWord = '<code>' + search_string[y] + '</code>';
					seg_str = seg_str.replace(regEx, replaceWord);
				}
				arr = seg_str.split(" ");

				for (var m = 0, len = arr.length; m < len; m++) {
					span_low_conf = '';
					// capitalize first letter of each sentence
					if(m == 0)
						arr[0] = arr[0].charAt(0).toUpperCase() + arr[0].substr(1);

					// remove extra highlightings from replace operation above
					if(search_string.length > 1) {
						if(arr[m].includes("<code>")) {
							// check if prev or next word + current word is contained in the search box
							// not the first/last word
							if(m+1 != len && m-1 != -1) {
								// check before first
								if(!text.includes(seg_str_original_words[m-1])) {
									// then after (if before no match)
									if(!text.includes(seg_str_original_words[m+1].replace(".","")))
										arr[m] = seg_str_original_words[m];
								}
							} else if(m+1 == len) {
								// check before only
								if(!text.includes(seg_str_original_words[m-1]))
									arr[m] = seg_str_original_words[m];
							} else {
								// check after only
								if(!text.includes(seg_str_original_words[m+1].replace(".","")))
									arr[m] = seg_str_original_words[m];
							}
						}
					}

					// highlight back low confidence score sentences
					if($("span#sid_"+i+"_"+m).hasClass("lowConfidence"))
						span_low_conf = ' class="lowConfidence"';
					span_sentence += '<span id="sid_'+i+'_'+m+'"' + span_low_conf + '>' + arr[m] + '</span>';
				}
				// add it back into sentences array
				sentences.push(span_sentence);
			}
			// perform sorting by occurence no, then segment id
			occurCounter.sort(sortCol2Desc);

			// display result
			var result = '<p>';
			for(var j = 0; j < slen; j++) {
				// if it matches, show no of occurence
				if(occurCounter[j][1] > 0)
					result += '<kbd>' + occurCounter[j][1] + ' Occurence</kbd> ';
				else {
					// strip away any code tags
					var regEx = new RegExp("<code>|</code>", "gi");
					var replaceWord = "";
					sentences[occurCounter[j][0]] = sentences[occurCounter[j][0]].replace(regEx, replaceWord);
				}
				result += '<span class="sorted" id="sid_' + occurCounter[j][0] + '">' + sentences[occurCounter[j][0]] + '</span>';
				if(occurCounter[j][1] > 0)
					result += "<hr />";
			}
			result += "</p>";
			$(".transcriptions").html(result);
			$("#header-text").html(display_search);
			
			$("#transcriptsBox").animate({ scrollTop: 0 }, 50);
			// de-select both sort buttons
			var btnActive = ['#sortTime','#sortConfidence'];
			for(var i=0; i<btnActive.length; i++) {
				$(btnActive[i]).removeClass('active');
				$(btnActive[i]).removeClass('btn-primary');
				$(btnActive[i]).addClass('btn-default');
			}
			$('#transcriptsBox > p > span:has(span.lowConfidence)').addClass('has_low_conf');
			$('#searchText').blur();
		}

		// Sort second column result
		function sortCol2Desc(a, b) {
			//http://stackoverflow.com/questions/17455780/javascript-sort-2d-array-at-first-by-second-column-desc-then-by-first-column-a
			return a[1] == b[1] ? (a[0] < b[0] ? -1 : 1) : (a[1] < b[1] ? 1 : -1);
		}
		function sortCol2Asc(a,b) {
			return a[1] == b[1] ? 0 : (a[1] < b[1] ? -1 : 1);
		}

		// Toggle between sentences
		function prevSentence() {
			var checking = $('#getClickedId').val().split("_");
			var checkNo = parseInt(checking[1]);
			checkNo--;
			checking[1] = checkNo;
			var nextVal = checking.join("_");

			// disable prev button when it is 0
			if (checkNo == 0) {
				$('#btnPrev').attr('disabled', 'disabled');
			} else {
				$('#btnPrev').removeAttr('disabled');
				$('#btnNext').removeAttr('disabled');
			}

			var arr = [];
			$("#" + nextVal + " span").each(function(index, elem) {
				arr.push($(this).text());
			});
			$('#tbxEdit').val(arr.join(" "));

			// update clicked id for reference later when saving
			document.getElementById('getClickedId').value = nextVal;
			$('#' + nextVal).click();
		}

		function nextSentence() {
			var checking = $('#getClickedId').val().split("_");
			var checkNo = parseInt(checking[1]);
			checkNo++;
			checking[1] = checkNo;
			var nextVal = checking.join("_");

			// re-enable prev button
			if (checkNo > 0 && checkNo != segments.length-1) {
				$('#btnPrev').removeAttr('disabled');
			}
			else if(checkNo == segments.length-1) {
				$('#btnNext').attr('disabled', 'disabled');
			}

			var arr = [];
			$("#" + nextVal + " span").each(function(index, elem) {
				arr.push($(this).text());
			});
			$('#tbxEdit').val(arr.join(" "));

			// update clicked id for reference later when saving
			document.getElementById('getClickedId').value = nextVal;
			$('#' + nextVal).click();
		}

		function getCurrentWord(highlight) {
			// get segment word timings (if not recorded yet)
			if(shcut_seg == null || shcut_seg != $("#getClickedId").val()) {
				// timings are not dynamically updated, therefore only record when it is a new segment
				// however if there is no current segment, we try to get the next segment
				if(magor.magorPlayer._currentSegments[0].wordTimings == "")
					magor.magorPlayer._currentSegments[0] = magor.magorPlayer._segmentIdsFromTime(player1.currentTime*1000 + 1000);
				if(magor.magorPlayer._currentSegments.length > 1) {
					for(var s = 0; s < magor.magorPlayer._currentSegments.length; s++) {
						if(magor.magorPlayer._currentSegments[s].id == $("#getClickedId").val().split("_")[1]) {
							shcut_timings = magor.magorPlayer._currentSegments[s].wordTimings.split(',');
							break;
						}
					}
				}
				else
					shcut_timings = magor.magorPlayer._currentSegments[0].wordTimings.split(',');
			}

			// need to constantly update the array in order to dynamically capture user text
			shcut_text = $("#tbxEdit").val().split(" ");
			var currentTiming = parseFloat(player1.currentTime.toFixed(2));
			var timeCount = 0;
			var caretCount = 0;

			// array starts from 0
			// get the timing of the last word which the speaker said
			for (i = 0; i < shcut_timings.length; i++) {
				// handle cases when user dynamically removes word and array is not defined
				if(currentTiming < parseFloat(shcut_timings[i]) || shcut_text[i] == null)
					break;
				// capture the index of the word
				timeCount++;
				// capture no of words from textarea start to caret position
				// add space between words
				caretCount += shcut_text[i].length + 1;
			}
			// minus one for current word index since we are using the word start time to compare instead of word end time
			timeCount--;
			// minus one for the extra space we add earlier on in caretCount
			caretCount--;
			// if somehow currentTiming is slower than first word timing in sentence, just force set to first word
			if(timeCount < 0)
				timeCount = 0;
			if(caretCount < 0)
				caretCount = shcut_text[timeCount].length;
			//console.log("Last spoken word: " + shcut_text[timeCount] + " (Index: " + timeCount + ") | No of letters captured: " + caretCount);

			// let textarea get focus first
			$('#tbxEdit').focus();
			// set the start and end index of word to be highlighted
			var lastSelectIndex = caretCount;
			var firstSelectIndex = lastSelectIndex - shcut_text[timeCount].length;
			// highlight the word
			if(highlight) {
				$('#tbxEdit').setSelection(firstSelectIndex, lastSelectIndex);
				caretStart = firstSelectIndex;
				caretEnd = lastSelectIndex;
			}

			// update variable to store the currently processed segment id
			shcut_seg = $("#getClickedId").val();
			// update current word index in sentence
			shcut_index = timeCount;

			// setup loop parameters
			// prevent overflow
			if(shcut_index >= wordEndTimes.length)
				shcut_index = wordEndTimes.length-1;

			loop_start = wordStartTimes[shcut_index] * 1000;
			endIndex = shcut_index + (intervalNo-1);
			if(endIndex >= wordEndTimes.length)
				endIndex = wordEndTimes.length-1;
			loop_end = wordEndTimes[endIndex] * 1000;

			// set caret position change to true
			$("#caretUpdated").val("true");
		}

		function goNextSegment() {
			if(!playNext) {//we want it to match
				magor.magorPlayer.pause();
				setTimeout(goNextSegment, 50);//wait 50 millisecnds then recheck
				return;
			}
			magor.magorPlayer.nextSegment();
			if(player1.paused && isPlaying)
				magor.magorPlayer.play();
		}

		function adjustSpeed() {
			player1.playbackRate = $('#speed').val();
			$('#speed').val(parseFloat($('#speed').val()).toFixed(1));
		}

		function audioLoop() {
			if($("#loopCycle").val() == 0) {
				$("#loop").removeClass("on");
				$("#loopCycleContainer").css("display", "none");
				$("#loopCycle").val(1);
			}
			else {
				if(!player1.paused) {
					// check if loop is ON
					if($("#loop").hasClass('on')) {
						// Repeat if Loop ON, otherwise keep playing
						var seeTime = loop_end/1000;
						//console.log(player1.currentTime + "- " + loop_start/1000);
						if (player1.currentTime >= seeTime)
							player1.currentTime = loop_start/1000;
						player1.loop = true;
					} else {
						player1.loop = false;
					}
				}
			}
		}

		//Mute/Unmute control clicked
		$('.muted').click(function() {
			player1.muted = !player1.muted;
			$('.muted').toggleClass("on");
			return false;
		});

		//Volume control clicked
		$('.volumeBar').on('mousedown', function(e) {
			var position = e.pageX - volume.offset().left;
			var percentage = 100 * position / volume.width();
			$('.volumeBar').css('width', percentage+'%');
			player1.volume = percentage / 100;
		});

		// save changes
		$("#tbxEdit").on('change keyup paste', function() {
			var ret = $('#tbxEdit').val().split(" ");
			var text = "";
			for (i = 0; i < ret.length; i++) {
				text += '<span id="' + $('#getClickedId').val() + '_' + i + '">' + ret[i] + '</span>';
			}
			$("#" + $('#getClickedId').val()).html(text);
			caretStart = $("#tbxEdit").getSelection().start;
			caretEnd = $("#tbxEdit").getSelection().end;
		});

		// bind enter key to search box
		$('#searchText').keyup(function(e){
			if(e.keyCode == 13)
				searchSort();
		});
		$('#searchText').focus(function() {
			if(!player1.paused) {
				magor.magorPlayer.pause();
				setTimeout(function() {
					$('#searchText').focus();
				}, 150);
			}
		});

		$('.btn-toggle button').click(function() {
			if($(this).attr('id') == "sortTime") {
				$('#sortTime').addClass('active');
				$('#sortTime').removeClass('btn-default');
				$('#sortTime').addClass('btn-primary');
				$('#sortConfidence').removeClass('active');
				$('#sortConfidence').removeClass('btn-primary');
				$('#sortConfidence').addClass('btn-default');
			} else {
				$('#sortConfidence').addClass('active');
				$('#sortConfidence').removeClass('btn-default');
				$('#sortConfidence').addClass('btn-primary');
				$('#sortTime').removeClass('active');
				$('#sortTime').removeClass('btn-primary');
				$('#sortTime').addClass('btn-default');
			}
			$('#searchText').val("");
		});

		// sticky video when scrolling
		/*$(function() {
			$('#transcriptsBox').affix({
				offset: {
					top: 190
				}
			});
			$('#mainBox').affix({
				offset: {
					top: 198
				}
			});
		});*/

		// save the script
		function saveScript(ajax) {
			var output = "";
			var counter = 0;
			// sort by time first
			$('#sortTime').click();
			if(!player1.paused) {
				player1.pause();
				if(!$("#player").hasClass("paused"))
					$("#player").addClass("paused");
				isPlaying = false;
			}
			$("#transcriptsBox > p > span").each(function(index, elem){
				
				// have to find the correct index in the segments array first
				var current_id = $(this).attr("id").split("_")[1];
				var correct_array_index = 0;
				for(var s = 0; s < segments.length; s++) {
					if(segments[s].id == current_id) {
						correct_array_index = s;
						break;
					}
				}
				// define variables
				var sStartTime = segments[correct_array_index]["startTime"]/1000;
				var sEndTime = (segments[correct_array_index]["startTime"]+segments[correct_array_index]["duration"])/1000;
				var wordTimings = segments[correct_array_index]["wordTimings"].split(",");
				var wordTimingsEnd = segments[correct_array_index]["wordTimingsEnd"].split(",");
				var wordCounter = 0;

				var sentence = '\n\t\t<segment endTime="' + sEndTime + '" id="' + segments[correct_array_index]["id"] + '" spkName="' + segments[correct_array_index]["speakerId"] + '" startTime="' + sStartTime + '">\n\t\t\t<sentence endTime="' + sEndTime + '" id="0" spkName="' + segments[correct_array_index]["speakerId"] + '" startTime="' + sStartTime + '" confidence="' + segments[correct_array_index]["confidence"] + '"';

				// check no of words in sentence first
				// if there is a change in no of words, we randomize the timings of the words
				// using sentence2.startTime - sentence1.startTime / no of words
				var noOfWordsInSentence = $("#" + $(this).attr("id") + " > span").length;

				// check for empty segments without any sentence
				if(noOfWordsInSentence == 1 && $("#" + $(this).attr("id") + "_0").text() == ".") {
					sentence += " />\n\t\t</segment>";
				} else {
					// close the sentence tag
					sentence += ">";

					// minus 1 because split function add a last empty value at the back of array
					if(noOfWordsInSentence != wordTimings.length) {
						// slow down transition into next segment due to dynamically added/removed words
						var difference = parseFloat(((sEndTime - sStartTime)-0.5) / noOfWordsInSentence);
						wordTimings = [], wordTimingsEnd = [];
						for(var x = 0; x < noOfWordsInSentence; x++) {
							wordTimings.push(parseFloat(sStartTime));
							wordTimingsEnd.push((parseFloat(sStartTime) + parseFloat(difference)).toFixed(2));
							sStartTime = (parseFloat(sStartTime) + parseFloat(difference)).toFixed(2);
						}
					}

					// for each sentence
					$("#" + $(this).attr("id") + " > span").each(function(index, elem){
						var cleanedText = ($(this).text().replace(/\s/g,"").replace(/<\/?div[^>]*>/g,"").replace(/\./g, "")).toLowerCase();
						// take care of sentences without words inside
						if(wordTimingsEnd[0] == "" || cleanedText == "")
							return false;
						sentence += '\n\t\t\t\t' + '<word endTime="' + wordTimingsEnd[wordCounter] + '" id="' + wordCounter + '" spkName="' + segments[correct_array_index]["speakerId"] + '" startTime="' + parseFloat(wordTimings[wordCounter]).toFixed(2) + '">' + cleanedText + '</word>';
						wordCounter++;
					});
					sentence += '\n\t\t\t</sentence>\n\t\t</segment>';
				}
				output += sentence;
				counter++;
			});
			
			var xmlHeader = '<?xml version="1.0" encoding="utf-8" ?>\n\n';
			var docHeader = '<document date="<?php echo $docInfo['date']; ?>" name="<?php echo $document; ?>">\n\t<content>';
			var metaData = '\n\t</content>\n\t<metadata>\n\t\t<media name="<?php echo $document . "." . $docInfo['type']; ?>" duration="<?php echo $docInfo['duration']; ?>"/>\n\t\t<speakers>\n'
			+ '<?php $count = 0; foreach($docInfo['speaker'] as $s) { echo '\t\t\t<speaker id="' . $count . '" name="' . $s . '" />\n' ; $count++; } ?>'
			+ '\t\t</speakers>\n\t</metadata>\n';
			var docFooter = '</document>';
			$('#processedXML').val(xmlHeader + docHeader + output + metaData + docFooter);

			if(ajax) {
				$.ajax({
					type: "POST",
					url: "./files/saveTranscriptXML.php",
					data: $("#save_xml").serialize(), // serializes the form's elements.
					success: function(data)
					{
						if(data) {
							if(data == "success")
								alert("Transcript saved successfully!");
							else
								alert("Error saving transcript! " + data);
						}
						else {
							alert('No data received after saving.');
						}
					}
				});
			}
		}

		// Setting the volume of the player
		var volume, vclicking, volhover, storevol;

		// When the user clicks on the volume bar holder, initiate the volume change event
		$("#player").find('.volume-bar-holder').bind('mousedown', function(e) {
			// Clicking of volume is true
			vclicking = true;

			// Y position of mouse in volume slider
			y = $("#player").find('.volume-bar-holder').height() - (e.pageY - $("#player").find('.volume-bar-holder').offset().top);

			// Return false if user tries to click outside volume area
			if(y < 0 || y > $(this).height()) {
				vclicking = false;
				return false;
			}

			// Update CSS to reflect what's happened
			$("#player").find('.volume-bar').css({'height' : y+'px'});
			$("#player").find('.volume-button').css({'top' : (y-($("#player").find('.volume-button').height()/2))+'px'});

			// Update some variables
			player1.volume = $("#player").find('.volume-bar').height() / $(this).height();
			storevol = $("#player").find('.volume-bar').height() / $(this).height();
			volume = $("#player").find('.volume-bar').height() / $(this).height();

			// Run a little animation for the volume icon.
			volanim();
		});
		// When the user clicks on the volume icon, initiate the volume change event
		$("#player").find('#ionvolume i').bind('mousedown', function(e) {
			// Run a little animation for the volume icon.
			volanim();
		});

		$("#loop").bind('click', function(e) {
			$("#loop").toggleClass("on");
			if($("#loop").hasClass("on")) {
				$("#loopCycleContainer").css("display", "inline");
			} else {
				$("#loopCycleContainer").css("display", "none");
			}
		});

		$("#swc").bind('click', function(e) {
			$("#swc").toggleClass("on");
			if($("#swc").hasClass("on")) {
				swc = true;
			} else {
				swc = false;
			}
		});

		// A quick function for binding the animation of the volume icon
		var volanim = function() {
			// Check where volume is and update class depending on that.
			for(var i = 0; i < 1; i += 0.1) {
				var fi = parseInt(Math.floor(i*10)) / 10;
				var volid = (fi * 10)+1;

				if(volume == 0) {
					$("#ionvolume i").removeClass().addClass('ionicons ion-android-volume-off');
				} else {
					$("#ionvolume i").removeClass().addClass('ionicons ion-android-volume-up');
				}
			}
		}
		// Run the volanim function
		volanim();

		// Check if the user is hovering over the volume button
		$("#player").find('.volume').hover(function() {
			volhover = true;
		}, function() {
			volhover = false;
		});

		// For usability purposes then bind a function to the body assuming that the user has clicked mouse
		// down on the progress bar or volume bar
		$('body, html').bind('mousemove', function(e) {
			 // For the volume controls
			if(vclicking == true) {

				// The position of the mouse on the volume slider
				y = $("#player").find('.volume-bar-holder').height() - (e.pageY - $("#player").find('.volume-bar-holder').offset().top);

				// The position the user is moving to on the slider.
				var volMove = 0;

				// If the volume holder box is hidden then just return false
				if($("#player").find('.volume-holder').css('display') == 'none') {
					vclicking = false;
					return false;
				}

				if(y < 0 || y == 0) { // If y is less than 0 or equal to 0 then volMove is 0.

					volume = 0;
					volMove = 0;

				} else if(y > $(this).find('.volume-bar-holder').height() || (y / $("#player").find('.volume-bar-holder').height()) == 1) { // If y is more than the height then volMove is equal to the height

					volume = 1;
					volMove = $("#player").find('.volume-bar-holder').height();

				} else { // Otherwise volMove is just y

					volume = $("#player").find('.volume-bar').height() / $("#player").find('.volume-bar-holder').height();
					volMove = y;
				}

				// Adjust the CSS based on the previous conditional statmeent
				$("#player").find('.volume-bar').css({'height' : volMove+'px'});
				$("#player").find('.volume-button').css({'top' : (volMove+$("#player").find('.volume-button').height())+'px'});

				// Run the animation function
				volanim();

				// Change the volume and store volume
				// Store volume is the volume the user last had in place
				// in case they want to mute the video, unmuting will then
				// return the user to their previous volume.
				player1.volume = volume;
				storevol = volume;
			}

			// If the user hovers over the volume controls, then fade in or out the volume
			// icon hover class
			if(volhover !== undefined) {
				if(volhover == false) {
					$("#player").find('.volume-holder').stop(true, false).fadeOut(100);
					$("#ionvolume").find('i').css('color','#fff');
				}
				else {
					$("#player").find('.volume-holder').fadeIn(100);
					$("#ionvolume").find('i').css('color','#15b2f0');
				}
			}
		});
		// If the user clicks on the volume icon, mute the video, store previous volume, and then
		// show previous volume should they click on it again.
		$("#player").find('#volwrap').bind('mousedown', function() {

			volume = player1.volume; // Update volume

			// If volume is undefined then the store volume is the current volume
			if(typeof storevol == 'undefined') {
				 storevol = player1.volume;
			}
			// If volume is more than 0
			if(volume > 0) {
				// then the user wants to mute the video, so volume will become 0
				player1.volume = 0;
				volume = 0;
				$("#player").find('.volume-bar').css({'height' : '0'});
				volanim();
			}
			else {
				// Otherwise user is unmuting video, so volume is now store volume.
				player1.volume = storevol;
				volume = storevol;
				$("#player").find('.volume-bar').css({'height' : (storevol*100)+'%'});
				volanim();
			}
		});
		$('body, html').bind('mouseup', function(e) {
			vclicking = false;
		});

		 $("#save_xml").on("submit", function(e){
			$("#download").val("true");
			saveScript(false);
			return true;
		})
		
		function cancelSubmit() {
			if(!$('#tbxEdit').is('[readonly]')) {
				alert("Please upload the updated xml file.");
				return false;
			}
			else {	
				return true;
				$.ajax({
					type: "POST",
					url: "./files/saveTranscriptSRT.php",
					data: $("#save_xml").serialize(), // serializes the form's elements.
					success: function(data)
					{
						console.log(data);
				}
				});
			}
		}
		
		</script>
		<!-- get current playing segment id -->
		<input type="hidden" id="getClickedId" value="" />
		<input type="hidden" id="caretUpdated" value="false" />
		<input type="hidden" id="mode" value="time" />
		<?php } ?>

		<footer class="footer">
			<div class="col-lg-10 col-lg-offset-1">
				<p class="text-muted">Developed by <a href="mailto:ytan070@e.ntu.edu.sg">Tan Yan Ling</a>.</p>
			</div>
		</footer>
	</body>
	<script>

	</script>
</html>