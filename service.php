<?php

	use Goutte\Client;

	class Marti extends Service 
	{
		/**
		 * Function executed when the service is called
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _main(Request $request)
		{
			if(empty($request->query)) {
				$responseContent = $this->allStories($request->query);
				$response = new Response();
				$response->setResponseSubject("Noticias de hoy");
				$response->createFromTemplate("allStories.tpl", $responseContent);
				return $response;
			}

			$normalizeChars = array('Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'î'=>'i', 'Î'=>'I');
			if (explode(" ", strtoupper(trim(strtr($request->query, $normalizeChars))))[0] == "CATEGORIA") {
				if (!isset(explode(" ", (trim($request->query)))[1])) {
					$responseContent = array("chicken" => "filthy");
					$response = new Response();
					$response->setResponseSubject("Error Categoría");
					$response->createFromTemplate("emptyCat.tpl", $responseContent);
					return $response;
				}

				$responseContent = $this->listArticles($request->query);
				$response = new Response();
				$response->setResponseSubject($request->query);
				$response->createFromTemplate("catArticles.tpl", $responseContent);
				return $response;
			}

			if (explode(" ", strtoupper(trim($request->query)))[0] == "BUSCAR") {
				if (!isset(explode(" ", (trim($request->query)))[1])) {
					$responseContent = array("chicken" => "filthy");
					$response = new Response();
					$response->setResponseSubject("Error De Búsqueda");
					$response->createFromTemplate("emptySearch.tpl", $responseContent);
					return $response;
				}

				$responseContent = $this->searchArticles($request->query, $request->body);
				$response = new Response();
				$response->setResponseSubject($request->query);
				$response->createFromTemplate("searchArticles.tpl", $responseContent);
				return $response;
			}
		}

		/**
		 * Call to show the news
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _historia(Request $request)
		{
			// no allow blank entries
			if (empty($request->query))
			{
				$response = new Response();
				$response->setResponseSubject("Busqueda en blanco");
				$response->createFromText("Su busqueda parece estar en blanco, debe decirnos que articulo quiere leer");
				return $response;
			} 

			// get the pieces 
			$pieces = explode("/", $request->query);

			// check media queries @TODO never show them in first place
			if (strtoupper($pieces[0]) == "MEDIA")
			{
				$response = new Response();
				$response->setResponseSubject("Material no disponible");
				$response->createFromText("Lo siento, pero esta p&aacute;gina solamente contiene videos y fotos. Por limitaciones de capacidad, no podemos mandar archivos extensos por ahora");
				return $response;
			}

			// send the actual response
			$responseContent = $this->story($request->query);
			$response = new Response();
			$response->setResponseSubject(str_replace("-", " ", $pieces[1]));
			$response->createFromTemplate("story.tpl", $responseContent);
			return $response;

		}

		private function searchArticles($query)
		{
			$apiUrl = "http://www.martinoticias.com/post.api?";

			// Setup client
			$client = new Client();

			// Keep integral part of query
			$query = explode(" ", $query);
			array_shift($query);
			$rowLimit = 20;

			// Set starting row
			if (count($query) > 1)
				$rowLimit = array_shift($query);

			// Limit amount of articles to return
			if (count($query) > 1)
				$increment = array_shift($query);

			$query = urlencode(implode(" ", $query));

			// Fetch json data from search API
			$apiData = file_get_contents($apiUrl . "&startrow=0&rowlimit=$rowLimit&searchtype=all&keywords=$query&zone=allzones&order=date");
			$jsonData = json_decode($apiData, true);

			// Fetch rows of data
			$totalRows = $jsonData['d']['postquery']['PrimaryQueryResult']['RelevantResults']['TotalRows'];
			$rows = $jsonData['d']['postquery']['PrimaryQueryResult']['RelevantResults']['Table']['Rows']['results'];

			$data = array();
			$i = 0;
			$articles = array();

			// Search through each row, fetch each cell, store as associative array.
			foreach ($rows as $row) {
				$categories = array();
				foreach ($row['Cells']['results'] as $cell) {
					$data[$cell['Key']] = $cell['Value'];
				}

				// Get author, if none, show anonymous
				$author = $data['searchArticleAuthor'];
				if (strlen(trim($author)) < 1) $author = "desconcido";

				// Generate link for story api
				$link = implode("/",
								array("content",
									$data['searchArticleSlug'],
									$data['searchArticleID'])
								) . ".html";
				$link = $this->utils->getLinkToService("MARTINOTICIAS", "HISTORIA $link");
				foreach (explode(";", $data['searchArticleZone']) as $cat) {
					$categories[] = array(
						'name' => $cat,
						'link' => $this->utils->getLinkToService("MARTINOTICIAS", "CATEGORIA $cat")
					);
				}

				// Store list of articles
				$articles[] = array(
					'pubDate'      => $data['searchArticlePubDate'],
					'description'  => $data['HitHighlightedSummary'],
					//'category'     => explode(";", $data['searchArticleZone']),
					'category'     => $categories,
					'title'        => $data['searchArticleTitle'],
					'tags'         => $data['searchArticleTag'],
					'author'       => $author,
					'link'         => $link
				);
			}

			// Return response content
			$newLimit = $rowLimit + $increment;
			$responseContent = array(
				'articles'  => $articles,
				'totalRows' => $totalRows,
				'rows'      => $rowLimit,
				'more'      => $apiUrl . "&startrow=0&rowlimit=$newLimit&searchtype=all&keywords=$query&zone=allzones&order=date"
			);
			return $responseContent;
		}

		private function listArticles($query) {
			// Setup client
			$client = new Client();

			// Setup crawler
			$crawler = $client->request('GET', "http://www.martinoticias.com/api/epiqq");

			// Keep integral part of query
			$query = explode(" ", $query);
			array_shift($query);
			$query = implode(" ", $query);

			// Collect articles by category
			$articles = array();

			$crawler->filter('channel item')->each(function($item, $i) use (&$articles, $query) {
				//If category matches, add to list of articles
				$item->filter('category')->each(function($cat, $i) use (&$articles, $query, $item) {
					if (strtoupper($cat->text()) == strtoupper($query)) {
						$title = $item->filter('title')->text();
						$link = $this->utils->getLinkToService("MARTINOTICIAS", "HISTORIA {$this->urlSplit($item->filter('link')->text())}");
						$pubDate = $item->filter('pubDate')->text();
						$description = $item->filter('description')->text();
						$author = "desconocido";
						if ($item->filter('author')->count() > 0) {
							$authorString = explode(" ", trim($item->filter('author')->text()));
							$author = substr($authorString[1], 1, strpos($authorString[1], ")") - 1) . " ({$authorString[0]})";
						}

						$articles[] = array(
							"title"       => $title,
							"link"        => $link,
							"pubDate"     => $pubDate,
							"description" => $description,
							"author"      => $author
						);
					}
				});
			});

			// Return response content
			$responseContent = array(
				"articles" => $articles
			);
			return $responseContent;
		}

		/**
		 * Get all stories from a query 
		 */
		private function allStories($query) {
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$guzzle->setDefaultOption('verify', false);
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.martinoticias.com/api/epiqq");

			$articles = array();
			$crawler->filter('item')->each(function($item, $i) use (&$articles) {
				if ($item->filter('category')->text() != "Fotogalerías") {
					$title = $item->filter('title')->text();
					$description = $item->filter('description')->text();
					$link = $this->urlSplit($item->filter('link')->text());
					$pubDate = $item->filter('pubDate')->text();
					$category = $item->filter('category')->each(function($category, $j) {
						return $category->text();
					});

					if ($item->filter('author')->count() == 0) {
						$author = "desconocido";
					} else {
						$authorString = explode(" ", trim($item->filter('author')->text()));
						$author = substr($authorString[1], 1, strpos($authorString[1], ")") - 1) . " ({$authorString[0]})";
					}

					$categoryLink = array();
					foreach ($category as $currCategory) {
						$categoryLink[] = $currCategory;
					}

					$articles[] = array(
						"title"        => $title,
						"link"         => $link,
						"pubDate"      => $pubDate,
						"description"  => $description,
						"category"     => $category,
						"categoryLink" => $categoryLink,
						"author"       => $author
					);
				}
			});

			// return response content
			return array("articles" => $articles);
		}

		/**
		 * Get an specific news to display
		 */
		private function story($query) 
		{
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$guzzle->setDefaultOption('verify', false);
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.martinoticias.com/$query");

			// search for result
			if ($crawler->filter('#article h1')->count() != 0)
			{
				$title = $crawler->filter('#article h1')->text();
			}

			if ($crawler->filter('#article .article_txt_intro')->count() != 0)
			{
				$intro = $crawler->filter('#article .article_txt_intro')->text();
			}

			if ($crawler->filter('#article .contentImage img')->count() != 0)
			{
				$imgUrl = $crawler->filter('#article .contentImage img')->attr("src");
				$imgAlt = $crawler->filter('#article .contentImage img')->attr("alt");
			}

			if ($crawler->filter('#article .articleContent .zoomMe p')->count() != 0)
			{
				$content = $crawler->filter('#article .articleContent .zoomMe p')->each(function($content, $i) {
					return $content->text();
				});;
			}

			if (isset($imgUrl))
			{
				$imgName = $this->utils->generateRandomHash() . "." . basename($imgUrl); //explode(".", explode("/", trim($imgUrl))[count(explode("/", trim($imgUrl))) - 1])[count(explode(".", explode("/", trim($imgUrl))[count(explode("/", trim($imgUrl))) - 1])) - 1];
				$img = \Phalcon\DI\FactoryDefault::getDefault()->get('path')['root'] . "/temp/$imgName";
				file_put_contents($img, file_get_contents($imgUrl));
				$this->utils->optimizeImage($img, 300);
			}

			// create a json object to send to the template
			return array(
				"title" => $title,
				"intro" => $intro,
				"img" => $img,
				"imgAlt" => $imgAlt,
				"content" => $content
			);
		}

		// http://www.martinoticias.com/content/blah
		private function urlSplit($url) {
			$url = explode("/", trim($url));
			unset($url[0]);
			unset($url[1]);
			unset($url[2]);
			return implode("/", $url);
		}
	}
?>