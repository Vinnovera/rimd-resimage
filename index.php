<?php

require 'resimage.php';

$img = getParam('image');
$w = intval(getParam('w'));
$h = intval(getParam('h'));

// Extracts parameters
function getParam($name) {
	if (isset($_GET[$name])) return htmlspecialchars($_GET[$name], ENT_QUOTES, 'UTF-8');
	return '';
}

$ri = new ResImage($img, $w, $h);