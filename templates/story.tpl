<h1>{$title}</h1>

<p>{$intro}</p>

{if not empty($img)}
	{img src="{$img}" alt="{$imgAlt}" width="100%"}
{/if}

{section name=content loop=$content}
	<p>{$content[content]}</p>
{/section}

{space5}

<center>
	{button href="MARTI" caption="M&aacute;s noticias"}
	{button href="PIZARRA Miren esto: {$url}" caption="Compartir" color="grey"}
</center>
