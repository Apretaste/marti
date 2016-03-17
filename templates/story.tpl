<h1>{$title}</h1>

<p>{$intro}</p>

{if isset($img)}
	{img src="{$img}" alt="{$imgAlt}" width="100%"}
{/if}

{section name=content loop=$content}
 	<p>{$content[content]}</p>
{/section}