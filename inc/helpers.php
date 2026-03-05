<?php

function h(?string $s): string {
    return htmlspecialchars($s ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function scoreClass(int $score): string {
    if ($score >= 70) return "score-excellent";
    if ($score >= 50) return "score-good";
    if ($score >= 30) return "score-medium";
    return "score-low";
}

function fmtPrice($val): string {
    if ($val === null) return '<span class="na">N/A</span>';
    return number_format((int)$val, 0, ',', ' ');
}

function metroLabel($val): string {
    if ($val === null) return '<span class="na">N/A</span>';
    $m = (int)$val;
    return ($m > 0) ? (string)$m : '<span class="na">N/A</span>';
}

function renderFeatures(?string $features): string {

    $features = trim((string)$features);

    if ($features === "")
        return '<span class="na">N/A</span>';

    $parts = preg_split('/\s*[,;\n]\s*/u',$features,-1,PREG_SPLIT_NO_EMPTY);

    if(!$parts)
        return '<span class="na">N/A</span>';

    $max = 8;
    $out = [];

    foreach(array_slice($parts,0,$max) as $p){

        $p = trim($p);
        if($p==="") continue;

        $out[] = '<span class="tag">'.h($p).'</span>';
    }

    if(count($parts) > $max){
        $out[] = '<span class="tag tag-more">+'.(count($parts)-$max).'</span>';
    }

    return implode(" ",$out);
}