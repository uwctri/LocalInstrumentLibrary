<?php

namespace UWMadison\LocalInstrumentLibrary;

use Files;
use Exception;

/** @var \UWMadison\LocalInstrumentLibrary\LocalInstrumentLibrary $module */

if (empty($_GET['doc_id'])) throw new Exception("Missing doc_id parameter for file download.");

$fileAttrs = Files::getEdocContentsAttributes($_GET['doc_id']);
if (!$fileAttrs) exit("File not found.");
list($mimeType, $docName, $contents) = $fileAttrs;

header("Content-Type: " . $mimeType);
header("Content-Disposition: attachment; filename=\"" . basename($docName) . "\"");
header("Content-Length: " . strlen($contents));
echo $contents;
exit;
