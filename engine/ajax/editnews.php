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
 File: editnews.php
-----------------------------------------------------
 Use: AJAX news edit
=====================================================
*/

if(!defined('DATALIFEENGINE')) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../../' );
	die( "Hacking attempt!" );
}

$parse = new ParseFilter();

if( !$is_logged ) die( "error" );

$id =  isset($_REQUEST['id']) ? intval( $_REQUEST['id'] ) : 0;
$_REQUEST['action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if( !$id ) die( "error" );

if( $_REQUEST['action'] == "edit" ) {

	$dark_theme = "";

	if (defined('TEMPLATE_DIR')) {
		$template_dir = TEMPLATE_DIR;
	} else $template_dir = ROOT_DIR . "/templates/" . $config['skin'];

	if (is_file($template_dir . "/info.json")) {

		$data = json_decode(trim(file_get_contents($template_dir . "/info.json")), true);

		if (isset($data['type']) and $data['type'] == "dark") {
			$dark_theme = " dle_theme_dark";
		}
	}

	$row = $db->super_query("SELECT p.id, p.autor, p.date, p.short_story, p.full_story, p.xfields, p.title, p.category, p.approve, p.allow_br, e.reason, e.edited_now FROM " . PREFIX . "_post p LEFT JOIN " . PREFIX . "_post_extras e ON (p.id=e.news_id) WHERE p.id = '$id'" );
	
	if( $id != $row['id'] ) die( "error" );
	
	$cat_list = explode( ',', $row['category'] );
	$categories_list = CategoryNewsSelection($cat_list, 0);

	if ($config['allow_multi_category']) {

		$cats = "<select data-placeholder=\"{$lang['addnews_cat_sel']}\" name=\"catlist[]\" id=\"category\" onchange=\"onCategoryChange(this)\" style=\"width:100%;max-width:24em;height:10em;\" multiple=\"multiple\">";
	} else {

		$cats = "<select data-placeholder=\"{$lang['addnews_cat_sel']}\" name=\"catlist[]\" id=\"category\" onchange=\"onCategoryChange(this)\" style=\"width:100%;max-width:24em;\">";
	}

	$cats .= $categories_list . "</select>";	

	$have_perm = 0;

	if( $user_group[$member_id['user_group']]['allow_edit'] AND $row['autor'] == $member_id['name'] ) {
		$have_perm = 1;
	}
	
	if( $user_group[$member_id['user_group']]['allow_all_edit'] ) {
		$have_perm = 1;
		
		if ($member_id['cat_add']) $allow_list = explode( ',', $member_id['cat_add'] );
		else $allow_list = explode( ',', $user_group[$member_id['user_group']]['cat_add'] );
		
		foreach ( $cat_list as $selected ) {
			if( $allow_list[0] != "all" AND !in_array( $selected, $allow_list ) ) $have_perm = 0;
		}
	}
	
	if( $user_group[$member_id['user_group']]['max_edit_days'] ) {
		$newstime = strtotime( $row['date'] );
		$maxedittime = $_TIME - ($user_group[$member_id['user_group']]['max_edit_days'] * 3600 * 24);
		if( $maxedittime > $newstime ) $have_perm = 0;
	}
	
	if( ($member_id['user_group'] == 1) ) {
		$have_perm = 1;
	}

	
	if( !$have_perm ) die( $lang['editnews_error'] );

	$edit_alert = '';
	$save_edit_alert = '';

	if ($config['alert_edit_now']) {

		if (isset($row['edited_now']) and $row['edited_now']) $row['edited_now'] = json_decode($row['edited_now'], true);
		else $row['edited_now'] = array('name' => '', 'time' => '');

		if ($row['edited_now']['name'] and $row['edited_now']['name'] != $member_id['name']  and time() < $row['edited_now']['time'] + 60) {

			$lang['edit_news_alert'] = str_replace('{name}', $row['edited_now']['name'], $lang['edit_news_alert']);

			$edit_alert = <<<HTML
DLEPush.warning('{$lang['edit_news_alert']}', '', 20000);
HTML;
		} else {

			$db->query("UPDATE " . PREFIX . "_post_extras SET edited_now='" . $db->safesql(json_encode(array('name' => $member_id['name'], 'time' => time()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "' WHERE news_id='{$row['id']}'");

			$save_edit_alert = <<<HTML
setTimeout(function() {
	save_edit_alert();
}, 20000);
HTML;
		}
	}

	$news_txt = $row['short_story'];
	$full_txt = $row['full_story'];
	$author = urlencode($row['autor']);

	$news_txt = $parse->decodeBBCodes($news_txt, true, true);
	$full_txt = $parse->decodeBBCodes($full_txt, true, true);

	if( $row['approve'] ) {
		$fix_approve = "checked";
	} else $fix_approve = "";
	
	$row['title'] = $parse->decodeBBCodes( $row['title'], false );

	$xfields = DLEXFields::FieldsList($row, 'site');
	$xfieldsdata = DLEXFields::xfieldsdataload($row['xfields']);
	$xfbuffer = "";

	foreach ($xfields['fields'] as $name => $value) {
		
		if (isset($xfieldsdata[$name]) OR $config['quick_edit_mode']) {

			$xfbuffer .= $xfields['fields'][$name];

		} else continue;

	}

	$p_name = urlencode($row['autor']);

	if($config['bbimages_in_wysiwyg']) {
		$implugin = 'dleimage';
	} else $implugin = 'image';
	
	$groups_list = [];

	foreach ($user_group as $gid => $gdata) {
		if ($gid == 1) continue;
		$groups_list[$gid] = $gdata['group_name'];
	}

	$dlehide_groups_json = json_encode($groups_list, JSON_UNESCAPED_UNICODE);

	$image_upload = array();
	
	if ( $user_group[$member_id['user_group']]['allow_image_upload'] ) {

		$image_upload[0] = "dleupload ";

		$image_upload[1] = <<<HTML
var dle_image_upload_handler = (blobInfo, progress) => new Promise((resolve, reject) => {
  var xhr, formData;

  xhr = new XMLHttpRequest();
  xhr.withCredentials = false;
  xhr.open('POST', dle_root + 'index.php?controller=ajax&mod=upload');
  
  xhr.upload.onprogress = (e) => {
    progress(e.loaded / e.total * 100);
  };

  xhr.onload = function() {
    var json;

    if (xhr.status === 403) {
      reject('HTTP Error: ' + xhr.status, { remove: true });
      return;
    }

    if (xhr.status < 200 || xhr.status >= 300) {
      reject('HTTP Error: ' + xhr.status);
      return;
    }

    json = JSON.parse(xhr.responseText);

    if (!json || typeof json.link != 'string') {

		if(typeof json.error == 'string') {
			reject(json.error);
		} else {
			reject('Invalid JSON: ' + xhr.responseText);	
		}
		
		var editor = tinymce.activeEditor;
		var node = editor.selection.getEnd();
		editor.selection.select(node);
		editor.insertContent('');
		
      return;
    }

	if( json.flink ) {
		
		resolve(json.link);

		setTimeout(() => {
			var editor = tinymce.activeEditor;
			if (!editor) return;

			var imageElement = editor.getBody().querySelector('img[src^="'+json.link+'"]');

			if (imageElement) {

				editor.dom.setStyles(imageElement, {
                	'display': 'block',
                	'margin-left': 'auto',
                	'margin-right': 'auto'
           		});

				var linkElement = editor.dom.create('a', {
					href: json.flink,
					class: 'highslide'
				});

				editor.dom.insertAfter(linkElement, imageElement);
				linkElement.appendChild(imageElement);
				var brElement = editor.dom.create('br');
            	editor.dom.insertAfter(brElement, linkElement);
				editor.selection.setCursorLocation(brElement, 0);
			}

		}, 300);

		$('#mediaupload').remove();

	} else {
		resolve(json.link);
		$('#mediaupload').remove();
	}
	
  };

  xhr.onerror = function () {
    reject('Image upload failed due to a XHR Transport error. Code: ' + xhr.status);
  };

  formData = new FormData();
  formData.append('qqfile', blobInfo.blob(), blobInfo.filename());
  formData.append("subaction", "upload");
  formData.append("news_id", "{$row['id']}");
  formData.append("area", "short_story");
  formData.append("author", "{$p_name}");
  formData.append("mode", "quickload");
  formData.append("editor_mode", "tinymce");
  formData.append("user_hash", "{$dle_login_hash}");    
  
  xhr.send(formData);
});
HTML;

		$image_upload[2] = <<<HTML
paste_data_images: true,
automatic_uploads: true,
images_upload_handler: dle_image_upload_handler,
images_reuse_filename: true,
image_uploadtab: false,
images_file_types: 'gif,jpg,png,jpeg,bmp,webp,avif',
file_picker_types: 'image',

file_picker_callback: function (cb, value, meta) {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');

    input.addEventListener('change', (e) => {
      const file = e.target.files[0];

		var filename = file.name;
		filename = filename.split('.').slice(0, -1).join('.');
	
      const reader = new FileReader();
      reader.addEventListener('load', () => {

        const id = filename;
        const blobCache =  tinymce.activeEditor.editorUpload.blobCache;
        const base64 = reader.result.split(',')[1];
        const blobInfo = blobCache.create(id, file, base64);
        blobCache.add(blobInfo);

        cb(blobInfo.blobUri());

      });
      reader.readAsDataURL(file);
    });

    input.click();
},
HTML;
		
			} else {
				
				$image_upload[0] = "";
				$image_upload[1] = "";
				$image_upload[2] = "";
				
			}	
			
			if( $user_group[$member_id['user_group']]['allow_file_upload'] ) {
				$image_upload[0] = "dleupload ";
			}
$chat_gpt = array(0 => '', 1 => '', 2 => '', 3 => '', 4 => '');

if ( $config['enable_ai'] AND in_array($member_id['user_group'], explode(',', trim($config['ai_groups'])) ) ) {

	$chat_gpt[0] = 'ai ';
	$chat_gpt[1] = 'aidialog ';
	$chat_gpt[2] = 'aishortcuts ';
	$chat_gpt[3] = "ai_request,
	ai_shortcuts: [
		{ title: 'Summarize content', prompt: '{$lang['ai_command_1']}', selection: true },
		{ title: 'Improve writing', prompt: '{$lang['ai_command_2']}', selection: true },
		{ title: 'Simplify language', prompt: '{$lang['ai_command_3']}', selection: true },
		{ title: 'Expand upon', prompt: '{$lang['ai_command_4']}', selection: true },
		{ title: 'Trim content', prompt: '{$lang['ai_command_5']}', selection: true },
		{
			title: 'Change tone', subprompts: [
			{ title: 'Professional', prompt: '{$lang['ai_command_6']}', selection: true },
			{ title: 'Casual', prompt: '{$lang['ai_command_7']}', selection: true },
			{ title: 'Direct', prompt: '{$lang['ai_command_8']}', selection: true },
			{ title: 'Confident', prompt: '{$lang['ai_command_9']}', selection: true },
			{ title: 'Friendly', prompt: '{$lang['ai_command_10']}', selection: true },
			]
		},
		{
			title: 'Change style', subprompts: [
			{ title: 'Business', prompt: '{$lang['ai_command_11']}', selection: true },
			{ title: 'Legal', prompt: '{$lang['ai_command_12']}', selection: true },
			{ title: 'Journalism', prompt: '{$lang['ai_command_13']}', selection: true },
			{ title: 'Medical', prompt: '{$lang['ai_command_14']}', selection: true },
			{ title: 'Poetic', prompt: '{$lang['ai_command_15']}', selection: true },
			]
		},
		{
			title: 'Translate', subprompts: [
			{ title: 'Translate to English', prompt: 'Translate this content to English language.', selection: true },
			{ title: 'Translate to Russian', prompt: 'Translate this content to Russian language.', selection: true },
			{ title: 'Translate to German', prompt: 'Translate this content to German language.', selection: true },
			{ title: 'Translate to Spanish', prompt: 'Translate this content to Spanish language.', selection: true },
			{ title: 'Translate to Portuguese', prompt: 'Translate this content to Portuguese language.', selection: true },
			{ title: 'Translate to French', prompt: 'Translate this content to French language.', selection: true },
			{ title: 'Translate to Norwegian', prompt: 'Translate this content to Norwegian language.', selection: true },
			{ title: 'Translate to Ukrainian', prompt: 'Translate this content to Ukrainian language.', selection: true },
			{ title: 'Translate to Japanese', prompt: 'Translate this content to Japanese language.', selection: true },
			{ title: 'Translate to Korean', prompt: 'Translate this content to Korean language.', selection: true },
			{ title: 'Translate to Simplified Chinese', prompt: 'Translate this content to Simplified Chinese language.', selection: true },
			{ title: 'Translate to Hebrew', prompt: 'Translate this content to Hebrew language.', selection: true },
			{ title: 'Translate to Hindi', prompt: 'Translate this content to Hindi language.', selection: true },
			{ title: 'Translate to Arabic', prompt: 'Translate this content to Arabic language.', selection: true },
			]
		},
	],
";

	if ($config['ai_tokens'] == '') $config['ai_tokens'] = 'null';
	if ($config['ai_completion_tokens'] == '') $config['ai_completion_tokens'] = 'null';
	if ($config['ai_temperature'] == '') $config['ai_temperature'] = 'null';

	$chat_gpt[4] = <<<HTML
const gptFetchApi = import(dle_root + "public/editor/tiny_mce/plugins/ai/fetch/index.js").then(module => module.fetchEventSource);

const gpt_api_key = '{$config['ai_key']}';
const gpt_api_endurl = '{$config['ai_endpoint']}';
const gpt_api_mode = '{$config['ai_mode']}';
const gpt_api_tokens = {$config['ai_tokens']};
const gpt_api_completion_tokens = {$config['ai_completion_tokens']};
const gpt_api_temperature = {$config['ai_temperature']};

const ai_request = (request, respondWith) => {
  respondWith.stream((signal, streamMessage) => {

    const conversation = request.thread.flatMap((event) => {
      if (event.response) {
        return [
          { role: 'user', content: `Question:\\n\${event.request.query}\\n\\nContext:\\n\${event.request.context}` },
          { role: 'assistant', content: event.response.data }
        ];
      } else {
        return [];
      }
    });

    const pluginSystemMessages = request.system.map((content) => content);

	const systemMessages = [
		{
			role: 'system',
			content: [
			...pluginSystemMessages,
			'You must NOT use Markdown in any form. Markdown is strictly forbidden. Do NOT use ``` or ` or any Markdown syntax. Any Markdown syntax makes the response invalid. If you need to return code, wrap it ONLY in <pre class="language-markup"><code></code></pre> formatted and converted special characters to HTML entities. Do NOT use backticks.'
			].join(' ')
		}
	];

    const content = request.context.length === 0 || conversation.length > 0
      ? request.query
      : `Question:\\n\${request.query}\\n\\nContext:\\n\${request.context}`;

    const messages = [
	  ...systemMessages,
      ...conversation,
      { role: 'user', content }
    ];

    var requestBody = {
      model: gpt_api_mode,
      messages,
      stream: true
    };

	if(gpt_api_temperature !== null ) {
		requestBody = { ...requestBody, temperature: gpt_api_temperature };
	}

	if(gpt_api_tokens !== null ) {
		requestBody = { ...requestBody, max_tokens: gpt_api_tokens };
	}

	if(gpt_api_completion_tokens !== null ) {
		requestBody = { ...requestBody, max_completion_tokens: gpt_api_completion_tokens };
	}

    const openAiOptions = {
      signal,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer \${gpt_api_key}`
      },
      body: JSON.stringify(requestBody)
    };

    const onopen = async (response) => {
      if (response) {
        const contentType = response.headers.get('content-type');
        if (response.ok && contentType?.includes('text/event-stream')) {
          return;
        } else if (contentType?.includes('application/json')) {
          const data = await response.json();
          if (data.error) {
            throw new Error(`\${data.error.type}: \${data.error.message}`);
          } else if(data.detail){
			 throw new Error(`\${data.detail}`);
		  }
        }
      } else {
        throw new Error('Failed to communicate with the ChatGPT API');
      }
    };

    const onmessage = (ev) => {
      const data = ev.data;
      if (data !== '[DONE]') {
        const parsedData = JSON.parse(data);
        const firstChoice = parsedData?.choices[0];
        const message = firstChoice?.delta?.content;
        if (message) {
			streamMessage(message);
        }
      }
    };

    const onerror = (error) => {
      throw error;
    };

    return gptFetchApi
    .then(fetchEventSource =>
      fetchEventSource(gpt_api_endurl, {
        ...openAiOptions,
        openWhenHidden: true,
        onopen,
        onmessage,
        onerror
      })
    )
    .then(async (response) => {
      if (response && !response.ok) {
        const data = await response.json();
        if (data.error) {
          throw new Error(`\${data.error.type}: \${data.error.message}`);
        }
      }
    })
    .catch(onerror);
  });
};
HTML;

}			
			if( @file_exists( ROOT_DIR . '/templates/'. $config['skin'].'/editor.css' ) ) $editor_template_css = ", dle_root + 'templates/{$config['skin']}/editor.css'"; else $editor_template_css = '';
			
			$js_code = <<<HTML
<script>
var text_upload = "{$lang['bb_t_up']}";

setTimeout(function() {

	tinymce.remove('textarea.wysiwygeditor');
	
	tinyMCE.baseURL = dle_root + 'public/editor/tiny_mce';
	tinyMCE.suffix = '.min';

	var dle_theme = '{$dark_theme}';
	dle_theme = dle_theme.trim();

	if(dle_theme != '') {
		$('body').addClass( dle_theme );
	}

	{$image_upload[1]}
	{$chat_gpt[4]}

	if (typeof getBaseSize === "function") {
		var height = 400 * getBaseSize();
		var body_extra_size;
		if( getBaseSize() > 1 && (body_extra_size = getFontSizeBase()) )  {
			var body_extra = 'body { --font-size-base: '+ body_extra_size +'; }';
		}
	} else {
		var height = 400;
		var body_extra = '';
	}

	tinymce.init({
		selector: 'textarea.wysiwygeditor',
		language : "{$lang['language_code']}",
		directionality: '{$lang['direction']}',
		element_format : 'html',		
		body_class: dle_theme,
		content_style: body_extra,
		skin: dle_theme == 'dle_theme_dark' ? 'oxide-dark' : 'oxide',

		width : "100%",
		height : height,
		min_height: 50,
		max_height: height,
		autoresize_bottom_margin: 1,
		statusbar: false,

		deprecation_warnings: false,
		promotion: false,
		cache_suffix: '?v={$config['cache_id']}',
		license_key: 'gpl',
		sandbox_iframes: false,
		
		plugins: "{$chat_gpt[0]}autoresize accordion fullscreen advlist autolink lists link image charmap anchor searchreplace visualblocks visualchars nonbreaking table codemirror dlebutton codesample quickbars autosave wordcount pagebreak toc",
		
		setup: function(editor) {
			editor.on('PreInit', function() {
				var shortEndedElements = editor.schema.getVoidElements();
				shortEndedElements['path'] = {};
				shortEndedElements['source'] = {};
				shortEndedElements['use'] = {};

				editor.schema.addCustomElements('dlehide');
           		editor.schema.addValidElements('dlehide[class|data-allowed-groups|contenteditable]');
			});
		},
		paste_postprocess: (editor, args) => {
			args.node.innerHTML = DLEclearPasteText(args.node.innerHTML, editor);
		},

		indentation : '20px',
		relative_urls : false,
		convert_urls : false,
		remove_script_host : false,
		verify_html: false,
		nonbreaking_force_tab: true,
		branding: false,
		link_default_target: '_blank',
		browser_spellcheck: true,
		pagebreak_separator: '{PAGEBREAK}',
		pagebreak_split_block: true,
		editable_class: 'contenteditable',
		noneditable_class: 'noncontenteditable',
		text_patterns: [],
		image_advtab: true,
		image_caption: true,
		image_dimensions: true,
		a11y_advanced_options: true,
		
		{$image_upload[2]}
		{$chat_gpt[3]}

		draggable_modal: true,
		menubar: false,
		
		toolbar: [
			'{$chat_gpt[1]}bold italic underline strikethrough align bullist numlist link unlink dleleech table {$image_upload[0]} {$implugin} dlemp dlaudio dletube dleemo dlequote dlehide dlespoiler codesample code dlemore',
			'mathml fontformatting forecolor backcolor pastetext | outdent indent subscript superscript anchor accordion pagebreak dlepage hr charmap searchreplace toc dletypo visualblocks | restoredraft undo redo removeformat fullscreen'
		],
		
		toolbar_mode: 'floating',
		toolbar_groups: {
		  
		  fontformatting: {
			icon: 'change-case',
			tooltip: 'Formatting',
			items: 'blocks styles fontfamily fontsize lineheight'
		  },
		  
		  align: {
			icon: 'align-center',
			tooltip: 'Formatting',
			items: 'alignleft aligncenter alignright alignjustify'
		  },
		  
		  dle: {
			icon: 'icon-dle',
			tooltip: 'DLE Tags',
			items: 'dlequote dlespoiler accordion dlehide codesample | pagebreak dlepage'
		  }
		  
		},
		
		statusbar: false,
		contextmenu: 'image table lists',
		extended_mathml_attributes: ['linebreak'],
		extended_valid_elements: 'dlehide[class|contenteditable|data-allowed-groups]',
  		custom_elements: 'dlehide',
		
		block_formats: 'Header 1=h1;Header 2=h2;Header 3=h3;Header 4=h4;Header 5=h5;Header 6=h6;Tag (p)=p;Tag (div)=div;',
		style_formats: [
			{ title: 'Information Block', block: 'div', wrapper: true, styles: { 'color': '#333333', 'border': 'solid 1px #00897B', 'padding': '0.78em', 'background-color': '#E0F2F1', 'box-shadow': 'rgba(126,142,177,0.2) 0 .35em .7em' } },
			{ title: 'Warning Block', block: 'div', wrapper: true, styles: { 'border': 'solid 1px #FF9800', 'padding': '0.78em', 'background-color': '#FFF3E0', 'color': '#aa3510', 'box-shadow': 'rgba(126,142,177,0.2) 0 .35em .7em' } },
			{ title: 'Error Block', block: 'div', wrapper: true, styles: { 'border': 'solid .1px #FF5722', 'padding': '0.78em', 'background-color': '#FBE9E7', 'color': '#9c1f1f', 'box-shadow': 'rgba(126,142,177,0.2) 0 .35em .7em' } },
			{ title: 'Borders', block: 'div', wrapper: true, styles: { 'border': 'solid .1px #ccc', 'padding': '0.78em' } },
			{ title: 'Borders top and bottom', block: 'div', wrapper: true, styles: { 'border-top': 'solid 1px #ccc', 'border-bottom': 'solid 1px #ccc', 'padding': '.78em 0' } },
			{ title: 'Use a shadow', block: 'div', wrapper: true, styles: { 'box-shadow': 'rgba(126,142,177,0.2) 0 .35em .7em' } },
			{ title: 'Increased letter spacing', inline: 'span', styles: { 'letter-spacing': '.1em' } },
			{ title: 'Сapital letters', inline: 'span', styles: { 'text-transform': 'uppercase' } },
			{ title: 'Gray background', block: 'div', wrapper: true, styles: { 'color': '#fff', 'background-color': '#607D8B', 'padding': '0.78em' } },
			{ title: 'Brown background', block: 'div', wrapper: true, styles: { 'color': '#fff', 'background-color': '#795548', 'padding': '0.78em' } },
			{ title: 'Blue background', block: 'div', wrapper: true, styles: { 'color': '#104d92', 'background-color': '#E3F2FD', 'padding': '0.78em' } },
			{ title: 'Green background', block: 'div', wrapper: true, styles: { 'color': '#fff', 'background-color': '#009688', 'padding': '0.78em' } },
		],

		image_class_list: [
			{ title: 'None', value: '' },
			{ title: 'Image Border', value: 'image-bordered' },
			{ title: 'Image Shadow', value: 'image-shadows' },
			{ title: 'Image Padding', value: 'image-padded' },
			{ title: 'Borders Padding', value: 'image-bordered image-padded' },
			{ title: 'Shadow Padding', value: 'image-shadows image-padded' },
		],
		
		codesample_languages: [
			{ text: 'HTML/XML', value: 'markup' },
			{ text: 'JavaScript', value: 'javascript' },
			{ text: 'CSS', value: 'css' },
			{ text: 'PHP', value: 'php' },
			{ text: 'SQL', value: 'sql' },
			{ text: 'Ruby', value: 'ruby' },
			{ text: 'Python', value: 'python' },
			{ text: 'Java', value: 'java' },
			{ text: 'C', value: 'c' },
			{ text: 'C#', value: 'csharp' },
			{ text: 'C++', value: 'cpp' }
		],

		font_size_formats: '0.8em 0.9em 1em 1.1em 1.2em 1.3em 1.4em 1.5em 2em 2.5em 3em',
		font_size_input_default_unit: "em",
		quickbars_insert_toolbar: false,
		quickbars_selection_toolbar: 'bold italic underline | quicklink | dlequote dlespoiler dlehide | forecolor backcolor | styles | blocks fontsize',
		quickbars_image_toolbar: 'alignleft aligncenter alignright | image link',
  
		formats: {
		  bold: {inline: 'b'},  
		  italic: {inline: 'i'},
		  underline: {inline: 'u', exact : true},  
		  strikethrough: {inline: 's', exact : true}
		},
		
		toc_depth : 4,
		
		dle_root : dle_root,
		dle_upload_area : "short_story",
		dle_upload_user : "{$p_name}",
		dle_upload_news : "{$row['id']}",
		dle_user_groups: {$dlehide_groups_json},
		
		content_css: [dle_root + 'public/editor/css/content.css'{$editor_template_css}]
	});

}, 100);

</script>
HTML;

		
	$params = "class=\"wysiwygeditor\"";
	$box_class = "wseditor dlefastedit-editor";

	if( $news_txt OR ($config['quick_edit_mode'] AND !$config['disable_short']) ) {
	
		$short_area = <<<HTML
<div class="xfieldsrow"><b>{$lang['s_fshort']}</b>
<div class="{$box_class}{$dark_theme}">
<textarea id="news_txt" name="news_txt" {$params}>{$news_txt}</textarea>
</div>
</div>
HTML;

	} else $short_area = '';

	if($full_txt OR ($config['quick_edit_mode'] AND !$config['disable_short']) ) {
	
		$full_area = <<<HTML
<div class="xfieldsrow"><b>{$lang['s_ffull']}</b>
<div class="{$box_class}{$dark_theme}">
<textarea id="full_txt" name="full_txt" {$params}>{$full_txt}</textarea>
</div>
</div>
HTML;

	} else $full_area = '';

	if($lang['direction'] == 'rtl') $rtl_prefix ='_rtl'; else $rtl_prefix = '';

	$buffer = <<<HTML
<script src="{$_ROOT_DLE_URL}public/js/sortable.js?v={$config['cache_id']}"></script>
<script src="{$_ROOT_DLE_URL}public/fileuploader/plupload/plupload.full.min.js?v={$config['cache_id']}"></script>
<script src="{$_ROOT_DLE_URL}public/fileuploader/plupload/i18n/{$lang['language_code']}.js?v={$config['cache_id']}"></script>
<script src="{$_ROOT_DLE_URL}public/calendar/calendar.js?v={$config['cache_id']}"></script>
<link href="{$_ROOT_DLE_URL}public/calendar/calendar.css?v={$config['cache_id']}" rel="stylesheet" type="text/css">
<form name="ajaxnews{$id}" id="ajaxnews{$id}" metod="post" action="">
<div><input type="text" name="title" class="quick-edit-text" value="{$row['title']}"></div>
<div class="xfieldsrow"><div class="xfieldscolleft">{$lang['ajax_edit_cat']}</div><div class="xfieldscolright">{$cats}</div></div>
{$short_area}
{$full_area}
{$xfbuffer}
<div class="xfieldsrow"><div class="xfieldscolleft">{$lang['reason']}</div><div class="xfieldscolright"><input type="text" name="reason" class="quick-edit-text" value="{$row['reason']}"></div></div>
<div class="xfieldsrow"><label class="form-check-label"><input class="form-check-input" type="checkbox" name="approve" value="1" {$fix_approve}><span>{$lang['add_al_ap']}</span></label></div>
</form>
{$js_code}
<script>
{$xfields['js_functions']}
setTimeout(function() {
	$(".xfieldsrow textarea.quick-edit-textarea").each(function () {
		this.style.height = "auto";
		if (this.scrollHeight > 300) {
			this.style.height = "300px";
			this.style.overflowY = "auto";
		} else {
			this.style.height = this.scrollHeight + "px";
			this.style.overflowY = "hidden";
		}
	}).on("input", function () {
		this.style.height = "auto";
		if (this.scrollHeight > 300) {
			this.style.height = "300px";
			this.style.overflowY = "auto";
		} else {
			this.style.height = this.scrollHeight + "px";
			this.style.overflowY = "hidden";
		}
	});
	{$xfields['js_scripts']}
}, 100);

    var elemfont = document.createElement('i');
    elemfont.className = 'mediaupload-icon';
	elemfont.style.position = 'absolute';
	elemfont.style.left = '-9999px';
	document.body.appendChild(elemfont);

	if ($( elemfont ).css('font-family') !== 'mediauploadicons') {
		$('head').append('<link rel="stylesheet" type="text/css" href="' + dle_root +'public/fileuploader/fileuploader{$rtl_prefix}.css">');
	}

    document.body.removeChild(elemfont);
	
	function xfimagedelete( xfname, xfvalue ) {
		
		DLEconfirmDelete( '{$lang['image_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');
			
			$.post(dle_root + 'index.php?controller=ajax&mod=upload', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$row['id']}', author: '{$author}', 'images[]' : xfvalue }, function(data){
	
				HideLoading('');
				
				$('#uploadedfile_'+xfname).html('');
				$('#xf_'+xfname).val('');
				$('#xfupload_' + xfname + ' .qq-upload-button').removeAttr('disabled');
				
				if (typeof file_uploaders[xfname] !== 'undefined') {
					file_uploaders[xfname].disableBrowse(false);
					file_uploaders[xfname].refresh();
				}
				
				$('#mediaupload').remove();
				
			});
			
		} );

		return false;

	};
	function xffiledelete( xfname, xfvalue ) {
		
		DLEconfirmDelete( '{$lang['file_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');
			
			$.post(dle_root + 'index.php?controller=ajax&mod=upload', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$row['id']}', author: '{$author}', 'files[]' : xfvalue }, function(data){
	
				HideLoading('');
				
				$('#uploadedfile_'+xfname).html('');
				$('#xf_'+xfname).val('');
				$('#xf_'+xfname).hide('');
				$('#xfupload_' + xfname + ' .qq-upload-button').removeAttr('disabled');
				
				if (typeof file_uploaders[xfname] !== 'undefined') {
					file_uploaders[xfname].disableBrowse(false);
					file_uploaders[xfname].refresh();
				}
				
				$('#mediaupload').remove();
			});
			
		} );

		return false;

	};
	
	function xfaddalt( id, xfname ) {
	
		var sel_alt = $('#xf_'+id).data('alt').toString().trim();
		sel_alt = sel_alt.replace(/"/g, '&quot;');
		sel_alt = sel_alt.replace(/'/g, '&#039;');

		DLEprompt('{$lang['bb_descr']}', sel_alt, '{$lang['p_prompt']}', function (r) {
			r = r.replace(/</g, '');
			r = r.replace(/>/g, '');
			r = r.replaceAll(',', '&#44;');
			r = r.replaceAll('|', '&#124;');
			
			$('#xf_'+id).data('alt', r);
			xfsinc(xfname);
		
		}, true);
		
	};
	
	function xfsinc(xfname) {
	
		var order = [];
		
		$( '#uploadedfile_' + xfname + ' .uploadedfile' ).each(function() {
			var xfurl = $(this).data('id').toString().trim();
			var xfalt = $(this).data('alt').toString().trim();
			
			if(xfalt) {
				order.push(xfalt + '|'+ xfurl);
			} else {
				order.push(xfurl);
			}

		});
	
		$('#xf_' + xfname).val(order.join(','));
	};

	function save_edit_alert() {

		$.post( dle_root + "index.php?controller=ajax&mod=adminfunction", { 'id': '{$row['id']}', action: 'saveeditnews', user_hash: '{$dle_login_hash}' }, function(data){

			if (data.success) {
			
				setTimeout(function() {
					save_edit_alert();
				}, 20000);
				
			}
			
		}, "json");

	};

	{$edit_alert}
	{$save_edit_alert}	
</script>	
HTML;

} elseif( $_REQUEST['action'] == "save" ) {
	
	if( !isset($_REQUEST['user_hash']) OR !$_REQUEST['user_hash'] OR $_REQUEST['user_hash'] != $dle_login_hash ) {
		
		die ("error");
	
	}
	
	$row = $db->super_query( "SELECT id, date, xfields, title, category, approve, short_story, full_story, autor, alt_name FROM " . PREFIX . "_post where id = '{$id}'" );
	
	if( $id != $row['id'] ) die( "News Not Found" );

	if (!isset($_POST['catlist']) OR (isset($_POST['catlist']) AND !is_array($_POST['catlist']))) $_POST['catlist'] = array();

	if (!count($_POST['catlist'])) {
		$catlist = array();
		$catlist[] = '0';
	} else $catlist = $_POST['catlist'];

	$category_list = array();

	foreach ($catlist as $value) {
		$category_list[] = intval($value);
	}

	$catlist = $category_list;
	$category_list = $db->safesql(implode(',', $category_list));
	$row['date'] = strtotime($row['date']);
	
	$full_link = DLEUrl::BuildUrl('showfull', ['category' => get_url( $category_list ), 'year' => date('Y', $row['date']), 'month' => date('m', $row['date']), 'day' => date('d', $row['date']), 'news_name' => $row['alt_name'], 'newsid' => $row['id']]);
	
	$have_perm = 0;
	
	if ($member_id['cat_add']) $allow_list = explode(',', $member_id['cat_add']);
	else $allow_list = explode(',', $user_group[$member_id['user_group']]['cat_add']);

	if( $user_group[$member_id['user_group']]['allow_all_edit'] ) {
		$have_perm = 1;
		
		foreach ( $catlist as $selected ) {
			if( $allow_list[0] != "all" and ! in_array( $selected, $allow_list ) ) $have_perm = 0;
		}
	}
	
	if( $user_group[$member_id['user_group']]['allow_edit'] and $row['autor'] == $member_id['name'] ) {
		$have_perm = 1;
	}
	
	if( $user_group[$member_id['user_group']]['max_edit_days'] ) {
		$newstime = $row['date'];
		$maxedittime = $_TIME - ($user_group[$member_id['user_group']]['max_edit_days'] * 3600 * 24);
		if( $maxedittime > $newstime ) $have_perm = 0;
	}
	
	if( ($member_id['user_group'] == 1) ) {
		$have_perm = 1;
	}
	
	if( !$have_perm ) die( "Access it is refused" );

	$approve = isset(  $_REQUEST['approve'] ) ? intval(  $_REQUEST['approve'] ) : 0;

	if( !$user_group[$member_id['user_group']]['moderation'] ) $approve = 0;
	
	if ($user_group[$member_id['user_group']]['moderation']) {
		foreach ($catlist as $selected) {
			if ($allow_list[0] != "all" and !in_array($selected, $allow_list)) {
				$approve = 0;
			}
		}
	}

	$_POST['title'] = $db->safesql( $parse->process( trim( strip_tags ($_POST['title'] ) ) ) );

	$parse->allow_code = false;

	$news_txt = isset($_POST['news_txt']) ? $db->safesql($parse->BB_Parse( $parse->process( $_POST['news_txt'] ))) : '';
	$full_txt = isset($_POST['full_txt']) ?  $db->safesql($parse->BB_Parse( $parse->process( $_POST['full_txt'] ))) : '';

	$stop = "";
	$_POST['category'] = $catlist;
	$parsed_fields = DLEXFields::Parse($row['xfields']);

	if (DLEXFields::$error) {
		$stop .= DLEXFields::$error;
	}
	
	$filecontents = $parsed_fields['filecontents'];
	$xf_search_words = $parsed_fields['search_words'];
	unset($parsed_fields);

	$editreason = $db->safesql( htmlspecialchars( strip_tags( stripslashes( trim( $_POST['reason'] ) ) ), ENT_QUOTES, 'UTF-8' ) );
	
	if( $editreason != "" ) $view_edit = 1;
	else $view_edit = 0;
	$added_time = time();
	
	if( !trim($_POST['title']) ) die( $lang['add_err_7'] );

	if ($parse->not_allowed_text ) die( $lang['news_err_39'] );

	if($stop) die($stop);

	$db->query( "UPDATE " . PREFIX . "_post SET title='{$_POST['title']}', short_story='{$news_txt}', full_story='{$full_txt}', xfields='{$filecontents}', category='{$category_list}', approve='{$approve}', allow_br='0' WHERE id = '{$id}'" );
	$db->query( "UPDATE " . PREFIX . "_post_extras SET editdate='$added_time', editor='{$member_id['name']}', reason='$editreason', view_edit='$view_edit' WHERE news_id = '{$id}'" );

	$db->query( "DELETE FROM " . PREFIX . "_xfsearch WHERE news_id = '{$id}'" );

	if ( count($xf_search_words) AND $approve ) {
					
		$temp_array = array();
					
		foreach ( $xf_search_words as $value ) {
						
			$temp_array[] = "('" . $id . "', '" . $value[0] . "', '" . $value[1] . "')";
		}
					
		$xf_search_words = implode( ", ", $temp_array );
		$db->query( "INSERT INTO " . PREFIX . "_xfsearch (news_id, tagname, tagvalue) VALUES " . $xf_search_words );
	}

	if( $category_list != $row['category'] OR $approve != $row['approve'] ) {
		
		$db->query( "DELETE FROM " . PREFIX . "_post_extras_cats WHERE news_id = '{$id}'" );

		if($approve) {

			$cat_ids = array ();
	
			$cat_ids_arr = explode( ",",  $category_list );
	
			foreach ( $catlist as $value ) {
	
				$cat_ids[] = "('" . $id . "', '" . trim( $value ) . "')";
	
			}
	
			$cat_ids = implode( ", ", $cat_ids );
			$db->query( "INSERT INTO " . PREFIX . "_post_extras_cats (news_id, cat_id) VALUES " . $cat_ids );

		}

	}
	
	$db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '25', '{$_POST['title']}')" );

	if ( $config['allow_alt_url'] AND !$config['seo_type'] ) $cprefix = "full_"; else $cprefix = "full_".$id;

	clear_cache(array('news_', $cprefix, 'related_', 'rss', 'stats'));

	if( $config['news_indexnow'] AND ($approve OR (!$approve AND $approve != $row['approve'] ) ) ) {

		DLESEO::IndexNow( $full_link );

	}

	$buffer = "ok";

} else die( "error" );

$db->close();

echo $buffer;
?>