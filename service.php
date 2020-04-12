<?php

use Framework\Alert;
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
	public function _main(Request $request, Response $response)
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
	public function _buscar(Request $request, Response $response)
	{
		$buscar = $request->input->data->busqueda;

		if (!isset($request->input->data->isCategory)) {
			$request->input->data->isCategory = false;
		}
		$isCategory = $request->input->data->isCategory;
		$isCategory = $isCategory === true || $isCategory === 'true' || $isCategory === 1;

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

		// error if the search return empty
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
	public function _historia(Request $request, Response $response)
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
	 * Secure crawling
	 *
	 * @param \Symfony\Component\DomCrawler\Crawler $item
	 * @param $path
	 * @param string $operation
	 * @param null $argument
	 *
	 * @return string | \Symfony\Component\DomCrawler\Crawler
	 */
	private static function craw($item, $path, $operation = 'text', $argument = null)
	{
		try {
			if ($item === null) {
				$filtered = Crawler::filter($path);
			} else {
				$filtered = $item->filter($path);
			}

			if ($operation === null) {
				return $filtered;
			}

			if ($filtered->count() > 0) {
				return $filtered->$operation($argument);
			}
		} catch (Exception $e) {
			$alert = new Alert(500, '[MARTI] Problem with crawling '.$path);
			$alert->post();
		}

		return '';
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
		self::craw(null, 'li.fui-grid__inner > .media-block', 'each', function ($item, $i) use (&$articles) {

			// get data from each row
			$date = self::craw($item, '.date');
			$title = self::craw($item, '.media-block__title');
			$description = self::craw($item, 'a p');
			$link = self::craw($item, 'a', 'attr', 'href');

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
		Crawler::start('http://www.radiotelevisionmarti.com/api/epiqq');

		// Collect articles by category
		$articles = [];
		self::craw(null, 'channel item', 'each', function ($item, $i) use (&$articles, $query) {

			// if category matches, add to list of articles
			self::craw($item, 'category', 'each', function ($cat, $i) use (&$articles, $query, $item) {
				if (strtoupper($cat->text()) === strtoupper($query)) {
					$title = self::craw($item, 'title');
					$link = $this->urlSplit(self::craw($item, 'link'));
					$pubDate = self::call($item, 'pubDate');
					$description = self::craw($item, 'description');
					$author = 'desconocido';

					$authorText = self::craw($item, 'author');
					if (!empty($authorText)) {
						$authorString = explode(' ', trim($authorText));
						if (isset($authorString[1])) {
							$author = substr($authorString[1], 1, strpos($authorString[1], ')') - 1)." ({$authorString[0]})";
						}
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
		self::craw(null, 'item', 'each', function ($item, $i) use (&$articles) {

			// get the link to the story
			$link = $this->urlSplit(self::craw($item, 'link'));

			// do not show anything other than content
			$pieces = explode('/', $link);
			if (strtoupper($pieces[0]) !== 'A') {
				return;
			}

			// get all other parameters
			$title = self::craw($item, 'title');
			$description = self::craw($item, 'description');
			$pubDate = self::craw($item, 'pubDate');
			$fecha = strftime('%B %d, %Y.', strtotime($pubDate));
			$hora = date_format((new DateTime($pubDate)), 'h:i a');
			$pubDate = $fecha.' '.$hora;
			$category = [];

			self::craw($item, 'category', 'each', function ($cate) use (&$category) {
				if ($cate->text() !== 'Titulares' && ! in_array($cate->text(), $category, true)) {
					$category[] = $cate->text();
				}
			});

			$author = trim(self::craw($item, 'author'));
			if ($author !== '') {
				$author = str_replace(['(', ')'], '', substr($author, strpos($author, '(')));
			}

			$categoryLink = [];
			foreach ($category as $currCategory) {
				$categoryLink[] = $currCategory;
			}

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
		Crawler::start("https://www.radiotelevisionmarti.com/a/$query");

		// search for title
		$title = self::craw(null, '.col-title h1, h1.title');

		// get the intro
		$intro = self::craw(null, '.intro p');

		// get the images
		$imageObj = self::craw(null, 'figure.media-image img');
		$imgUrl = self::craw(null, 'figure.media-image img', 'attr', 'src');
		$imgAlt = self::craw(null, 'figure.media-image img', 'attr', 'alt');
		$imgName = '';

		// get the image
		if (!empty($imgUrl)) {
			$imgName = Utils::randomHash().'.'.pathinfo($imgUrl, PATHINFO_EXTENSION);
			$img = TEMP_PATH."cache/$imgName";
			file_put_contents($img, Crawler::get($imgUrl));
		}

		// build the content
		$content = [];
		self::craw(null, 'div.wsw p:not(.ta-c)', 'each', static function ($paragraph) use (&$content) {
			$content[] = trim($paragraph->text());
		});

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
	 * @param string $url
	 * @return string
	 */
	private function urlSplit($url)
	{
		$url = explode('/', trim($url));
		unset($url[0], $url[1], $url[2]);
		return implode('/', $url);
	}
}
