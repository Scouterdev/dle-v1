<?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group 
-----------------------------------------------------
 https://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004-2026 SoftNews Media Group
=====================================================
 This code is protected by copyright
=====================================================
 File: go.php
-----------------------------------------------------
 Use: Forwarding links
=====================================================
*/

if( !defined( 'DATALIFEENGINE' ) ) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../' );
	die( "Hacking attempt!" );
}

function deny() {
	global $config;
    header('HTTP/1.1 403 Forbidden');
	header('Location: ' . $config['http_home_url']);
    exit('Access denied');
}

function isRedirectLoop($url, $currentHost) {
	$p = parse_url($url);
	if (!$p) {
		return false;
	}

	$path  = $p['path']  ?? '';
	$query = $p['query'] ?? '';
	$host  = '';

	if (!empty($p['host'])) {
		$host = strtolower(preg_replace('/^www\./i', '', $p['host']));
	}

	if (basename($path) === 'go.php') {
		return true;
	}

	if ($host === '' || $host === $currentHost) {
		parse_str($query, $qs);
		if (isset($qs['do']) && $qs['do'] === 'go') {
			return true;
		}
	}

	return false;
}

if (empty($_SERVER['HTTP_REFERER'])) {
    deny();
}

$ref = parse_url($_SERVER['HTTP_REFERER']);
if (!$ref || empty($ref['host'])) {
    deny();
}

$currentHost = strtolower(preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST']));
$refHost     = strtolower(preg_replace('/^www\./i', '', $ref['host']));

if ($currentHost !== $refHost) {
    deny();
}

$area = $_GET['area'] ?? '';

$raw = $_GET['url'] ?? '';

if ($raw === '') {
	deny();
}

$url = base64_decode($raw, true);

if ($url === false) {
	deny();
}

header('X-XSS-Protection: 1; mode=block');

if (($area == 'comments' AND $config['comm_noreferrer']) OR (!$area AND $config['news_noreferrer'])) {
	header('Referrer-Policy: no-referrer');
}

$url = trim(str_replace(["\r", "\n"], '', $url));

$parsed = parse_url($url);

if (($parsed['scheme'] ?? '') === null) {

	if ($url[0] === '/') {
		if (isRedirectLoop($url, $currentHost)) deny();
		header('Location: ' . $url, true, 302);
		exit;
	}

	$final = '/' . ltrim($url, '/');
	if (isRedirectLoop($final, $currentHost)) deny();
	header('Location: ' . $final, true, 302);
	exit;
}

$scheme = strtolower($parsed['scheme']);
if (!in_array($scheme, ['http', 'https'], true)) {
	deny();
}

$host = strtolower($parsed['host'] ?? '');
if ($host === $currentHost && isRedirectLoop($url, $currentHost)) {
	deny();
}

header('Location: ' . $url, true, 302);
exit;
