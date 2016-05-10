<h1>Categoria: {$category}</h1>

{if $articles|count eq 0}
	<p>Lo siento, a&uacute;n no tenemos historias para esta categor&iacute;a :'-(</p>
{/if}

{foreach from=$articles item=article}
	<b>{link href="MARTI HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{space5}
	{$article['description']|truncate:200:" ..."}<br/>
	<small>
		<font color="gray">{$article['author']}, {$article['pubDate']|date_format}</font>
	</small>
	{space15}
{/foreach}

{space5}

<center>
	{button href="MARTI" caption="M&aacute;s noticias"}
</center>