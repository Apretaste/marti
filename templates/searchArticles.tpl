<h1>Buscar: {$search|ucfirst}</h1>

{foreach from=$articles item=article name=arts}
	<b>{link href="MARTI HISTORIA {$article['link']}" caption="{$article['title']}"}</b><br/>
	{space5}
	{$article['description']}<br/>
	{space15}
{/foreach}

{space5}

<center>
	{button href="MARTI" caption="Titulares"}
</center>