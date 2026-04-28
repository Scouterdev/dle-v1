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
 File: keywords.php
-----------------------------------------------------
 Use: Generation of keywords
=====================================================
*/

if(!defined('DATALIFEENGINE')) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../../' );
	die( "Hacking attempt!" );
}

if( !$is_logged OR !$user_group[$member_id['user_group']]['allow_admin'] ) { die ("error"); }

if( !isset($_REQUEST['user_hash']) OR !$_REQUEST['user_hash'] OR $_REQUEST['user_hash'] != $dle_login_hash ) {
		
	die ("error");
	
}

$key = isset($_REQUEST['key']) ? intval( $_REQUEST['key'] ) : 0;
$ai = isset($_REQUEST['ai']) ? intval( $_REQUEST['ai'] ) : 0;

if( $ai && ( !$config['enable_ai'] OR !in_array($member_id['user_group'], explode(',', trim($config['ai_groups'])) ) ) ) {
	die ("AI not allowed");
}

$parse = new ParseFilter();
$short_story = isset($_REQUEST['short_txt']) ? $parse->BB_Parse($parse->process($_REQUEST['short_txt']), false) : '';
$full_story = isset($_REQUEST['full_txt']) ? $parse->BB_Parse($parse->process($_REQUEST['full_txt']), false) : '';

if( dle_strlen($full_story) > 12 ) $story = $full_story; else $story = $short_story;

$metatags = create_metatags($story, true);

$metatags['description'] = str_replace("&amp;","&", $metatags['description'] );
$metatags['description'] = str_replace("&quot;", '"', $metatags['description'] );
$metatags['description'] = str_replace("&#039;", "'", $metatags['description']);
$metatags['description'] = stripslashes($metatags['description']);
$metatags['keywords'] = str_replace("&quot;", '"', $metatags['keywords']);
$metatags['keywords'] = str_replace("&#039;", "'", $metatags['keywords']);
$metatags['keywords'] = stripslashes($metatags['keywords']);

if ($key == 1) $content = $metatags['description']; else $content = $metatags['keywords'];

$success = true;
$error = '';

if( $ai ) {
	
	$story = clear_content($story, 0, false);

	if($story) {
		$ai_conf = array('apiUrl' => $config['ai_endpoint'], 'apiKey' => $config['ai_key'], 'model' => $config['ai_mode']);

		if ($key == 1) {
			$result = ExecuteAI($story, $lang['ai_command_16'], $ai_conf);
		} else {
			$result = ExecuteAI($story, $lang['ai_command_17'], $ai_conf);
			if($result['ok'] AND $result['text']) {
				$result['text'] = implode(',', array_map(fn($v) => rtrim(trim($v), '.'), explode(',', $result['text'])));
			}
		}

		if ($result['ok']) {
			$result['text'] = strip_tags( $result['text'] );
			$content = $result['text'];
		} else {
			$success = false;
			$error = $lang['ai_error'].'<br>'.$result['error'];
		}
	}

}

echo json_encode(array("success" => $success, "content" => $content, "error" => $error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
