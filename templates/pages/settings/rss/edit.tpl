{include file='header.tpl'}
<h1>{$title}</h1>
<form method="POST">
{fill_form_values}
{if $formErrors}
<p>
The following errors were encountered in your submission:
</p>
<ul class="error"></ul>
{/if}
<fieldset>
    <legend>RSS configuration</legend>

	<label for="edit[rss_feed_title]">RSS feed title</label>
	<input id="edit[rss_feed_title]" name="edit[rss_feed_title]" maxlength="120" size="60" value="" type="text" /><div class="description">Title to use for RSS display.</div>

	<label for="edit[rss_feed_url]">RSS feed URL</label>
	<input id="edit[rss_feed_url]" name="edit[rss_feed_url]" maxlength="120" size="60" value="" type="text" /><div class="description">The full URL from which we pull RSS items.</div>

	<label for="edit[rss_feed_items]">RSS feed item limit</label>
	<input id="edit[rss_feed_items]" name="edit[rss_feed_items]" maxlength="4" size="4" value="" type="text" /><div class="description">Number of feed items to display</div>
</fieldset>
{/fill_form_values}

<input type="hidden" name="edit[step]" value="perform" />
<input type="submit" name="submit" value="submit" />
<input type="reset" name="reset" value="reset" />
</form>
{include file='footer.tpl'}
