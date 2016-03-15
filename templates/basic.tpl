{section name=title loop=$title}
 	<a href="{$link[title]}"><h1>{$title[title]}</h1></a>
 	<p>{$description[title]}<br>
 	Categor&iacute;as: 
 	{section name=category loop=$category[title]}
		{if $smarty.section.category.last}
			<a href="{$categoryLink[title][category]}">{$category[title][category]}</a>
		{else}
			<a href="{$categoryLink[title][category]}">{$category[title][category]}</a>,
		{/if}
	{/section}<br>
	<small>Autor: {$author[title]}</small><br>
 	<small>publicado por {$pubDate[title]}</small></p><br />
{/section}
