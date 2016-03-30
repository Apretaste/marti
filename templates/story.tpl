<h1>{$title}</h1>

<p>{$intro}</p>

{if not empty($img)}
	{img src="{$img}" alt="{$imgAlt}" width="100%"}
{/if}

{section name=content loop=$content}
	<p>{$content[content]}</p>
{/section}

<center>
	{button href="MARTI" caption="M&aacute;s noticias"}
</center>