<?php

namespace UWMadison\LocalInstrumentLibrary;

use Files;

/** @var \UWMadison\LocalInstrumentLibrary\LocalInstrumentLibrary $module */

if (empty($_GET['doc_id']))
    throw new \Exception("Missing doc_id parameter for file download.");

$doc_id = $_GET['doc_id'];

// Fetch file attributes and contents using the REDCap core Files class
$fileAttrs = Files::getEdocContentsAttributes($doc_id);
if (!$fileAttrs)
    exit("File not found.");
list($mimeType, $docName, $contents) = $fileAttrs;

// Stream the file contents to the browser
header("Content-Type: " . $mimeType);
header("Content-Disposition: attachment; filename=\"" . basename($docName) . "\"");
header("Content-Length: " . strlen($contents));
echo $contents;
exit;
