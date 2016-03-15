{section name=articleIndex loop=$articles}
 	<a href="{$articles[articleIndex].link}"><h1>{$articles[articleIndex].title}</h1></a>
 	<p>{$articles[articleIndex].description}<br>
    <small>Autor: {$articles[articleIndex].author}</small><br>
 	<small>publicado por {$articles[articleIndex].pubDate}</small></p>
{/section}
