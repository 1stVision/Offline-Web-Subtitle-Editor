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
 * @author Hew Jun Wei Zach (c) 2016
 * @author Tan Yan Ling (c) 2017
 */

// get location of XML and the new XML text
if(!empty($_POST['xmlLoc']) && !empty($_POST['processedXML'])) {
	$xml_loc = '.' . $_POST['xmlLoc'];

	// check that file location is writeable
	if(is_writable($xml_loc)) {
		if($_POST['download'] == "false") {
			// remove file first
			if(file_exists($xml_loc))
				unlink($xml_loc);

			// put the new XML text into existing file
			file_put_contents($xml_loc, $_POST['processedXML'], FILE_APPEND | LOCK_EX);
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
			echo $_POST['processedXML'];
			fclose($output) or die("Can't close php://output");
		}
	} else
		echo "XML Location (" . $xml_loc . ") is not writeable!";
}
else
	echo "Missing XML Location or Missing Processed XML Data!";
?>