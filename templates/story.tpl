<h1>{$title}</h1>
<p>{$intro}</p>
{if isset($img)}
	{img src="{$img}" alt="{$imgAlt}"}
{/if}
{section name=content loop=$content}
 	<p>{$content[content]}</p>
{/section}
<br>
<b>Comments</b><br>
{section name=comment loop=$comments}
    <p>
        <small>Autor: {$comments[comment].author}</small><br>
        <small>{$comments[comment].date}</small><br>
        {$comments[comment].body}
    </p>
{/section}
