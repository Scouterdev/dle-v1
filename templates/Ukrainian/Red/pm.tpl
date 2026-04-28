<article class="block story shadow">
	<div class="wrp">
		<div class="head">
			<h1 class="title h2 ultrabold">Особисті повідомлення</h1>
		</div>
		<div class="pm-box">
			<nav id="pm-menu">
				[inbox]<span>Список повідомлень</span>[/inbox]
				[new_pm]<span>Створити повідомлення</span>[/new_pm]
			</nav>
			<div class="pm_status">
				{pm-progress-bar}
				<div class="grey">{proc-pm-limit} % / ({pm-limit} повідомлення)</div>
			</div>
		</div>
		[pmlist]
		<div class="pmlist">
			{pmlist}
		</div>
		[/pmlist]
	</div>
</article>
[newpm]
<div class="block">
	<div class="wrp">
		<h4 class="block_title ultrabold">Створити повідомлення</h4>
		<ul class="ui-form">
			<li class="form-group combo">
				<div class="combo_field">
					<input placeholder="Ім'я адресата" type="text" name="name" value="{author}" class="wide" required>
				</div>
				<div class="combo_field">
					<input placeholder="Тема повідомлення" type="text" name="subj" value="{subj}" class="wide" required>
				</div>
			</li>
			<li id="comment-editor">{editor}</li>
		[recaptcha]
			<li>{recaptcha}</li>
		[/recaptcha]
		[question]
			<li class="form-group">
				<label for="question_answer">Питання: {question}</label>
				<input placeholder="Відповідь" type="text" name="question_answer" id="question_answer" class="wide" required>
			</li>
		[/question]
		</ul>
		<div class="form_submit">
			[sec_code]
				<div class="c-capcha">
					{sec_code}
					<input placeholder="Повторіть код" title="Повторіть код" type="text" name="sec_code" id="sec_code" required>
				</div>
			[/sec_code]
			<button class="btn" type="submit" name="add"><b class="ultrabold">Відправивши</b></button>
			<button class="btn btn_border" type="button" onclick="dlePMPreview()"><b class="ultrabold">Попередній перегляд</b></button>
		</div>
	</div>
</div>
[/newpm]
[readpm]
<div class="block comments shadow">
	<div class="wrp">
		<div style="margin-bottom:1.5em">
			<h4>{subj}</h4>
			[not-self-dialog]Листування з користувачем: {user-dialog}[/not-self-dialog]
		</div>
	[messages]
		<div class="comment com_author">
			<div class="avatar">
				<span class="cover" style="background-image: url({foto});">{login}</span>
				<span class="com_decor"></span>
			</div>
			<div class="com_content">
				<div class="com_info">
					<b class="name">{author}</b>
					[online]<span title="Онлайн" class="status online">Онлайн</span>[/online]
					[offline]<span title="Офлайн" class="status offline">Офлайн</span>[/offline]
					<span class="grey date">{date}</span>
				</div>
				<div class="text">
					{text}
					[signature]<div class="signature">--------------------<br />{signature}</div>[/signature]
				</div>
				<div class="com_tools">
					<div class="com_tools_links grey">
						<span class="edit_btn" title="Редагувати">
						[pm-edit]<i></i>[/pm-edit]
						</span>
						[reply]<svg class="icon icon-meta_reply"><use xlink:href="#icon-meta_reply"></use></svg><span>Цитувати</span>[/reply]
						[complaint]<svg class="icon icon-compl"><use xlink:href="#icon-compl"></use></svg><span>Скарга</span>[/complaint]
						[del]<svg class="icon icon-cross"><use xlink:href="#icon-cross"></use></svg><span>Видалити</span>[/del]
						[ignore]<svg class="icon icon-meta_views"><use xlink:href="#icon-meta_views"></use></svg><span>Заблокувавши</span>[/ignore]
					</div>
				</div>
			</div>
		</div>
	[/messages]
		<div style="border-top: 1px dashed #eceded;padding: 20px 0;">
			{editor}
			<br><button class="btn btn-big" type="submit" name="submit" title="Відповівши"><b>Відповівши</b></button>
		</div>
	</div>
</div>
[/readpm]