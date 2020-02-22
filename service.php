<?php

use Framework\Crawler;
use Apretaste\Request;
use Apretaste\Response;
use Apretaste\Challenges;
use Framework\Utils;

class Service
{
	/**
	 * Function executed when the service is called
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _main(Request $request, Response &$response)
	{
		$response->setCache('day');
		$response->setLayout('marti.ejs');
		$response->setTemplate('allStories.ejs', $this->allStories(), [__DIR__.'/images/marti-logo.png']);
	}

	/**
	 * Call to show the news
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _buscar(Request $request, Response &$response)
	{
		$buscar = $request->input->data->busqueda;
		$isCategory = $request->input->data->isCategory === 'true';

		// no allow blank entries
		if (empty($buscar)) {
			$response->setLayout('Marti.ejs');
			$response->setTemplate('text.ejs', [
					'title' => 'Su b&uacute;squeda parece estar en blanco',
					'body' => 'Debe decirnos sobre que tema desea leer.'
			]);

			return;
		}
		// search by the query
		$articles = $this->searchArticles($buscar);

		// error if the searche return empty
		if (empty($articles)) {
			$response->setLayout('marti.ejs');
			$response->setTemplate('text.ejs', [
					'title' => 'Su b&uacute;squeda no produjo resultados',
					'body' => 'Su b&uacute;squeda no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.'
			]);

			return;
		}

		$responseContent = [
				'articles' => $articles,
				'type' => $isCategory ? 'Categoria: ':'Buscar: ',
				'search' => $buscar
		];

		$response->setLayout('marti.ejs');
		$response->setTemplate('searchArticles.ejs', $responseContent);
	}

	/**
	 * Call to show the news
	 *
	 * @param \Apretaste\Request $request
	 * @param \Apretaste\Response $response
	 *
	 * @throws \Framework\Alert
	 */
	public function _historia(Request $request, Response &$response)
	{
		$history = $request->input->data->historia;

		// get the pieces
		$pieces = explode('/', $history);

		// send the actual response

		$responseContent = $this->story($history);

		// filter notices without content
		if (empty($responseContent['content'])) {
			$response->setLayout('marti.ejs');
			$response->setTemplate('text.ejs', [
					'title' => 'Esta noticia solo contiene un archivo de audio o video',
					'body' => 'No podremos mostrársela, por favor intente con otra.'
			]);
		} else {
			// get the image if exist
			$images = [];
			if (!empty($responseContent['img'])) {
				$images = [TEMP_PATH.'cache/'.$responseContent['img']];
			}

			if (isset($request->input->data->busqueda)) {
				$responseContent['backButton'] = "{'command':'MARTI BUSCAR', 'data':{'busqueda':'{$request->input->data->busqueda}', isCategory: false}}";
			} else {
				$responseContent['backButton'] = "{'command':'MARTI'}";
			}

			//$response->setCache();
			$response->setLayout('marti.ejs');
			$response->setTemplate('story.ejs', $responseContent, $images);

			Challenges::complete('read-marti', $request->person->id);
		}
	}

	/**
	 * Search stories
	 *
	 * @param String
	 *
	 * @return array
	 * */
	private function searchArticles($query)
	{
		$url = 'https://www.radiotelevisionmarti.com/s?k='.urlencode($query).'&tab=news&pi=1&r=any&pp=50';
		Crawler::start($url);

		// Collect saearch by category
		$articles = [];
		Crawler::filter('li.fui-grid__inner > .media-block')->each(function ($item, $i) use (&$articles) {
			// get data from each row

			/** @var  \Symfony\Component\DomCrawler\Crawler $item */
			$date = $item->filter('.date')->text();
			$title = $item->filter('.media-block__title')->text();
			$description = $item->filter('a p')->count() > 0 ? $item->filter('a p')->text():'';
			$link = $item->filter('a')->attr('href');

			// store list of articles

			$articles[] = [
					'pubDate' => $date,
					'description' => $description,
					'title' => $title,
					'link' => $link
			];
		});

		return $articles;
	}

	/**
	 * Get the array of news by content
	 *
	 * @param String
	 *
	 * @return array
	 */
	private function listArticles($query)
	{
		// Setup crawler
		Crawler::start('http://www.martinoticias.com/api/epiqq');

		// Collect articles by category
		$articles = [];
		Crawler::filter('channel item')->each(function ($item, $i) use (&$articles, $query) {
			// if category matches, add to list of articles
			/** @var \Symfony\Component\DomCrawler\Crawler $item */
			$item->filter('category')->each(function ($cat, $i) use (&$articles, $query, $item) {
				if (strtoupper($cat->text()) === strtoupper($query)) {
					$title = $item->filter('title')->text();
					$link = $this->urlSplit($item->filter('link')->text());
					$pubDate = $item->filter('pubDate')->text();
					$description = $item->filter('description')->text();
					$author = 'desconocido';
					if ($item->filter('author')->count() > 0) {
						$authorString = explode(' ', trim($item->filter('author')->text()));
						$author = substr($authorString[1], 1, strpos($authorString[1], ')') - 1)." ({$authorString[0]})";
					}

					$articles[] = [
							'title' => $title,
							'link' => $link,
							'pubDate' => $pubDate,
							'description' => $description,
							'author' => $author
					];
				}
			});
		});

		// Return response content
		return ['articles' => $articles];
	}

	/**
	 * Get all stories from a query
	 *
	 * @return array
	 */
	private function allStories()
	{
		Crawler::start('http://www.martinoticias.com/api/epiqq');

		$articles = [];
		Crawler::filter('item')->each(function ($item, $i) use (&$articles) {

			/** @var \Symfony\Component\DomCrawler\Crawler $item */

			// get the link to the story
			$link = $this->urlSplit($item->filter('link')->text());

			// do not show anything other than content
			$pieces = explode('/', $link);
			if (strtoupper($pieces[0]) !== 'A') {
				return;
			}

			// get all other parameters

			$title = $item->filter('title')->text();
			$description = $item->filter('description')->text();
			$pubDate = $item->filter('pubDate')->text();
			setlocale(LC_ALL, 'es_ES.UTF-8');
			$fecha = strftime('%B %d, %Y.', strtotime($pubDate));
			$hora = date_format((new DateTime($pubDate)), 'h:i a');
			$pubDate = $fecha.' '.$hora;
			$category = [];
			$item->filter('category')->each(function ($cate) use (&$category) {
				if ($cate->text() != 'Titulares' && !in_array($cate->text(), $category)) {
					$category[] = $cate->text();
				}
			});

			if ($item->filter('author')->count() == 0) {
				$author = '';
			} else {
				$author = trim($item->filter('author')->text());
				$author = str_replace(['(', ')'], '', substr($author, strpos($author, '(')));
			}

			$categoryLink = [];
			foreach ($category as $currCategory) {
				$categoryLink[] = $currCategory;
			}

			//if(count(array_intersect(["OCB Direct Packages", "OCB Direct Programs"], $category))==0)
			if (!stripos(implode($category), 'OCB') && !stripos(implode($category), 'Televisión')) {
				$articles[] = [
						'title' => $title,
						'link' => $link,
						'pubDate' => $pubDate,
						'description' => $description,
						'category' => $category,
						'categoryLink' => $categoryLink,
						'author' => $author
				];
			}
		});

		// return response content
		return ['articles' => $articles];
	}

	/**
	 * Get an specific news to display
	 *
	 * @param String
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function story($query)
	{
		Crawler::start("http://www.martinoticias.com/$query");

		// search for title
		$title = Crawler::filter('.col-title h1, h1.title')->text();

		// get the intro
		$titleObj = Crawler::filter('.intro p');
		$intro = $titleObj->count() > 0 ? $titleObj->text():'';

		// get the images
		$imageObj = Crawler::filter('figure.media-image img');
		$imgUrl = '';
		$imgAlt = '';
		$img = '';
		$imgName = '';
		if ($imageObj->count() !== 0) {
			$imgUrl = trim($imageObj->attr('src'));
			$imgAlt = trim($imageObj->attr('alt'));

			// get the image
			if (!empty($imgUrl)) {
				$imgName = Utils::randomHash().'.'.pathinfo($imgUrl, PATHINFO_EXTENSION);
				$img = TEMP_PATH."cache/$imgName";
				file_put_contents($img, Crawler::get($imgUrl));
			}
		}

		// get the array of paragraphs of the body
		$paragraphs = Crawler::filter('div.wsw p:not(.ta-c)');
		$content = [];
		foreach ($paragraphs as $p) {
			$content[] = trim($p->textContent);
		}

		// create a json object to send to the template
		return [
				'title' => $title,
				'intro' => $intro,
				'img' => $imgName,
				'imgAlt' => $imgAlt,
				'content' => $content,
				'url' => "http://www.martinoticias.com/$query"
		];
	}

	/**
	 * Get the link to the news starting from the /content part
	 *
	 * @param String
	 *
	 * @return String
	 *                http://www.martinoticias.com/content/blah
	 */
	private function urlSplit($url)
	{
		$url = explode('/', trim($url));
		unset($url[0], $url[1], $url[2]);

		return implode('/', $url);
	}
}
