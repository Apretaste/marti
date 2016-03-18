<h1>Categoria: {$category}</h1>

{foreach from=$articles item=article}
	<b>{link href="MARTI HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{space5}
	{$article['description']}<br/>
	<small>
		<font color="gray">{$article['author']}, {$article['pubDate']|date_format}</font>
	</small>
	{space15}
{/foreach}