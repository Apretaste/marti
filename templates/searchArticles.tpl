{section name=articleIndex loop=$articles}
 	<a href="{$articles[articleIndex].link}"><h1>{$articles[articleIndex].title}</h1></a>
 	<p>{$articles[articleIndex].description}<br>
    Categor&iacute;as:
    {section name=catIndex loop=$articles[articleIndex].category}
        <a href="{$articles[articleIndex].category[catIndex].link}">
            {$articles[articleIndex].category[catIndex].name}
        </a>
        {if !$smarty.section.catIndex.last},{/if}
    {/section}<br>
    <small>Autor: {$articles[articleIndex].author}</small><br>
 	<small>publicado por {$articles[articleIndex].pubDate}</small></p>
{/section}