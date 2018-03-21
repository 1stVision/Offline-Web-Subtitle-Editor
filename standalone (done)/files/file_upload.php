<?php
require('Uploader.php');

// Directory where we're storing uploaded videos
// Remember to set correct permissions or it won't work
$upload_dir = '../uploads/';
$valid_extensions = "";

$uploader = new FileUpload('uploadfile');
$ext = $uploader->getExtension(); // Get the extension of the uploaded file
// Handle the upload
$result = $uploader->handleUpload($upload_dir, $valid_extensions);

if (!$result)
  exit(json_encode(array('success' => false, 'msg' => $uploader->getErrorMsg())));

echo json_encode(array('success' => true, 'file' => $uploader->getFileName()));