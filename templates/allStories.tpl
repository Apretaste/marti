<h1>Noticias de hoy</h1>

{foreach from=$articles item=article}
	<b>{link href="MARTI HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{space5}
	{$article['description']}<br/>
	<small>
		<font color="gray">{$article['author']}, {$article['pubDate']|date_format}</font>
		<br/>
		Categor&iacute;as: 
		{foreach from=$article['category'] item=category name=cats}
			{link href="MARTI CATEGORIA {$category}" caption="{$category}"} 
			{if not $smarty.foreach.cats.last}{separator}{/if}
		{/foreach}
	</small>
	{space15}
{/foreach}