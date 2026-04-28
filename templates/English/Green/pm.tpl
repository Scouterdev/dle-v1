<article class="block story">
	<h1 class="title h2">Personal messages</h1>
	<div class="pm-box">
		<nav id="pm-menu">
			[inbox]<span>Messages list</span>[/inbox]
			[new_pm]<span>New message</span>[/new_pm]
		</nav>
		<div class="pm_status">
			{pm-progress-bar}
			{proc-pm-limit} % / ({pm-limit} messages)
		</div>
	</div>
	[pmlist]
	<div class="pmlist">
		{pmlist}
	</div>
	[/pmlist]
</article>
<div class="block">
	[newpm]
	<h4 class="title">New message</h4>
	<div class="addform addpm">
		<ul class="ui-form">
			<li class="form-group combo">
				<div class="combo_field">
					<input placeholder="Имя адресата" type="text" name="name" value="{author}" class="wide" required>
				</div>
				<div class="combo_field">
					<input placeholder="Тема сообщения" type="text" name="subj" value="{subj}" class="wide" required>
				</div>
			</li>
			<li id="comment-editor">{editor}</li>    
		[recaptcha]
			<li>{recaptcha}</li>
		[/recaptcha]
		[question]
			<li class="form-group">
				<label for="question_answer">Question: {question}</label>
				<input placeholder="Answer" type="text" name="question_answer" id="question_answer" class="wide" required>
			</li>
		[/question]
		</ul>
		<div class="form_submit">
			[sec_code]
				<div class="c-capcha">
					{sec_code}
					<input placeholder="Enter the code" title="Enter the code" type="text" name="sec_code" id="sec_code" required>
				</div>
			[/sec_code]
			<button class="btn" type="submit" name="add"><b>Send</b></button>
			<button class="btn" type="button" onclick="dlePMPreview()">Preview</button>
		</div>
	</div>
	[/newpm]
</div>

[readpm]
<div class="comments">
	<div class="comments_box">
		<div class="comments_box_in">
			<div class="box_in">
				<h4>{subj}</h4>
				[not-self-dialog]Correspondence with the user: {user-dialog}[/not-self-dialog]
			</div>
			[messages]
			<div class="comment[online] online[/online]">
				[online]<span class="status online">Online</span>[/online]
				<span class="status offline">Offline</span>
				<div class="com_info">
					<div class="avatar">
						<span class="cover" style="background-image: url({foto});">{login}</span>
					</div>
					<div class="com_user">
						<b class="name">{author}</b>
						<span class="grey date">{date}</span>
					</div>
				</div>
				<div class="com_content">
					<div class="text">{text}</div>
					[signature]<div class="signature">--------------------<br>{signature}</div>[/signature]
				</div>
				<div class="com_tools">
					<div class="com_tools_links grey">
						<span class="edit_btn">
							[pm-edit]
								<i title="Edit">Edit</i>
							[/pm-edit]
						</span>
						[reply]<svg class="icon icon-reply"><use xlink:href="#icon-reply"></use></svg><span>Quote</span>[/reply]
						[ignore]<svg class="icon icon-reply"><use xlink:href="#icon-author"></use></svg><span>Ignore</span>[/ignore]
						[complaint]<svg class="icon icon-compl"><use xlink:href="#icon-compl"></use></svg><span>Complain</span>[/complaint]
						[del]<svg class="icon icon-del"><use xlink:href="#icon-del"></use></svg><span>Delete</span>[/del]
					</div>
				</div>
			</div>
			[/messages]
			<h4>Reply to a message</h4>
			{editor}
			<br><button class="btn btn-big" type="submit" name="submit" title="Ответить"><b>Reply</b></button>
		</div>
	</div>
</div>
[/readpm]