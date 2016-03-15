{section name=articleIndex loop=$articles}
 	<a href="{$articles[articleIndex].link}"><h1>{$articles[articleIndex].title}</h1></a>
 	<p>{$articles[articleIndex].description}<br>
 	Categor&iacute;as: 
 	{section name=category loop=$articles[articleIndex].category}
		{if $smarty.section.category.last}
			<a href="{$articles[articleIndex].categoryLink[category]}">{$articles[articleIndex].category[category]}</a>
		{else}
			<a href="{$articles[articleIndex].categoryLink[category]}">{$articles[articleIndex].category[category]}</a>, 
		{/if}
	{/section}<br>
    <small>Autor: {$articles[articleIndex].author}</small><br>
 	<small>publicado por {$articles[articleIndex].pubDate}</small></p>
{/section}
