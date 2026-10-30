<?php
function preprocessOfxFile($file_path)
{
    $ofxContent = file_get_contents($file_path);
    $ofxContent = str_replace("<", "\n<", $ofxContent);
    $sgmlStart = stripos($ofxContent, "<OFX>");
    $ofxHeader = trim(substr($ofxContent, 0, $sgmlStart));
    $ofxSgml = trim(substr($ofxContent, $sgmlStart));
    return convertSgmlToXml($ofxSgml);
}

function convertSgmlToXml($sgml)
{
    $lines = explode("\n", $sgml);
    $xml = "";
    foreach ($lines as $line) {
        $xml .= closeUnclosedTags(trim($line)) . "\n";
    }
    return trim($xml);
}

function closeUnclosedTags($line)
{
    if (preg_match("/<([A-Za-z0-9.]+)>([^<]+)$/", $line, $matches)) {
        return "<{$matches[1]}>{$matches[2]}</{$matches[1]}>";
    }
    return $line;
}
