<?php
	use Goutte\Client;

	class Service
	{
		/**
		 * Function executed when the service is called
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _main(Request $request, Response $response)
		{
			$pathToService = Utils::getPathToService($response->serviceName);
			$response->setCache("day");
			$response->setLayout('marti.ejs');			
			$response->setTemplate("allStories.ejs", $this->allStories(), ["$pathToService/images/marti-logo.png"]);
			
		}

		/**
		 * Call to show the news
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _buscar(Request $request, Response $response)
		{
			$buscar = $request->input->data->searchQuery;
			$isCategory = $request->input->data->isCategory;
			
			// no allow blank entries
			if(empty($buscar)){
			$response->setLayout('Marti.ejs');
			$response->setTemplate('text.ejs', [
				"title" => "Su busqueda parece estar en blanco",
				"body" => "debe decirnos sobre que tema desea leer"
			]);

			return;
		}
			// search by the query
			$articles = $this->searchArticles($buscar);

			// error if the searche return empty
			if(empty($articles))
		{

			$response->setLayout('marti.ejs');
			$response->setTemplate("text.ejs", [
				"title" => "Su busqueda parece estar en blanco",
				"body" => html_entity_decode("Su busqueda no gener&oacute; ning&uacute;n resultado. Por favor cambie los t&eacute;rminos de b&uacute;squeda e intente nuevamente.")
			]);

			return;
		}
        
		$responseContent = [
			"articles" => $articles,
			"type" => $isCategory ? "Categoria: " : "Buscar: ",
			"search" => $buscar
		];

		$response->setLayout('marti.ejs');
		$response->setTemplate("searchArticles.ejs", $responseContent);
	
			
		}

		/**
		 * Call to show the news
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _historia(Request $request, Response $response)
		{ 
			$history = $request->input->data->historia;

			// get the pieces
			$pieces = explode("/", $history);

			// send the actual response
			
			$responseContent = $this->story($history);
			

			// get the image if exist
			$images = array();
			if( ! empty($responseContent['img'])) $images = array($responseContent['img']);

			// subject chenges when user comes from the main menu or from buscar
			if(strlen($pieces[1]) > 5) $subject = str_replace("-", " ", ucfirst($pieces[1]));
			else $subject = "La historia que pidio";

			if(isset($request->input->data->busqueda)) 
				$responseContent['backButton'] = "{'command':'MARTI BUSCAR', 'data':{'searchQuery':'{$request->input->data->busqueda}'}}";
			else
				$responseContent['backButton'] = "{'command':'MARTI'}";

			
			$response->setCache();
			$response->setLayout('marti.ejs');
			$response->setTemplate("story.ejs", $responseContent, $images);
			
		}

		/**
		 * Call list by categoria
		 *
		 * @param Request
		 * @return Response
		 * */
		public function _categoria(Request $request, Response $response)
		{
			if (empty($request->query))
			{
				
				$response->setCache();
				$response->setLayout('marti.tpl');
				$response->createFromText("Su busqueda parece estar en blanco, debe decirnos sobre que categor&iacute;a desea leer");
				return $response;
			}

			$responseContent = array(
				"articles" => $this->listArticles($request->query)["articles"],
				"category" => $request->query
			);

			
			$response->setEmailLayout('marti.tpl');
			$response->setTemplate("catArticles.tpl", $responseContent);
			return $response;
		}

		/**
		 * Search stories
		 *
		 * @param String
		 * @return Array
		 * */
		private function searchArticles($query)
		{
			// Setup crawler
			$client = new Client();
			$url = "http://www.martinoticias.com/s?k=".urlencode($query)."&tab=news&pi=1&r=any&pp=50";
			$crawler = $client->request('GET', $url);
			// Collect saearch by category
			$articles = array();
			$crawler->filter('.row > .small-thums-list.follow-up-list > li')->each(function($item, $i) use (&$articles)
			{
				// get data from each row
				$date = $item->filter('.date')->text();
				$title = $item->filter('.media-block__title')->text();
				$description = $item->filter('a p')->count()>0 ? $item->filter('a p')->text():"";
				$link = $item->filter('a')->attr("href");

				// store list of articles
				$articles[] = array(
					"pubDate" => $date,
					"description" => $description,
					"title"	=> $title,
					"link" => $link
				);
			});

			return $articles;
		}

		/**
		 * Get the array of news by content
		 *
		 * @param String
		 * @return Array
		 */
		private function listArticles($query)
		{
			// Setup crawler
			$client = new Client();
			$crawler = $client->request('GET', "http://www.martinoticias.com/api/epiqq");

			// Collect articles by category
			$articles = array();
			$crawler->filter('channel item')->each(function($item, $i) use (&$articles, $query)
			{
				// if category matches, add to list of articles
				$item->filter('category')->each(function($cat, $i) use (&$articles, $query, $item)
				{
					if (strtoupper($cat->text()) == strtoupper($query))
					{
						$title = $item->filter('title')->text();
						$link = $this->urlSplit($item->filter('link')->text());
						$pubDate = $item->filter('pubDate')->text();
						$description = $item->filter('description')->text();
						$author = "desconocido";
						if ($item->filter('author')->count() > 0)
						{
							$authorString = explode(" ", trim($item->filter('author')->text()));
							$author = substr($authorString[1], 1, strpos($authorString[1], ")") - 1) . " ({$authorString[0]})";
						}

						$articles[] = array(
							"title" => $title,
							"link" => $link,
							"pubDate" => $pubDate,
							"description" => $description,
							"author" => $author
						);
					}
				});
			});

			// Return response content
			return array("articles" => $articles);
		}

		/**
		 * Get all stories from a query
		 *
		 * @return Array
		 */
		private function allStories()
		{
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.martinoticias.com/api/epiqq");

			$articles = array();
			$crawler->filter('item')->each(function($item, $i) use (&$articles)
			{
				// get the link to the story
				$link = $this->urlSplit($item->filter('link')->text());

				// do not show anything other than content
				$pieces = explode("/", $link);
				if (strtoupper($pieces[0]) != "A") return;

				// get all other parameters
				
				$title = $item->filter('title')->text();
				$description = $item->filter('description')->text();
				$pubDate = $item->filter('pubDate')->text();
				setlocale(LC_ALL, 'es_ES.UTF-8');
				$fecha = strftime("%B %d, %Y.",strtotime($pubDate)); 
				$hora = date_format((new DateTime($pubDate)),'h:i a');
				$pubDate = $fecha." ".$hora;
				$category = array();
				$item->filter('category')->each(function($cate) use(&$category) {
					if ($cate->text()!="Titulares" && !in_array($cate->text(),$category)) $category[]= $cate->text();
				});

				if ($item->filter('author')->count() == 0) $author = "";
				else
				{
					$author = trim($item->filter('author')->text());
                    $author = str_replace(['(',')'],'',substr($author, strpos($author, "(")));
				}

				$categoryLink = array();
				foreach ($category as $currCategory) $categoryLink[] = $currCategory;

				//if(count(array_intersect(["OCB Direct Packages", "OCB Direct Programs"], $category))==0)
				if(!stripos(implode($category), "OCB") && !stripos(implode($category), "Televisión"))
					$articles[] = array(
						"title" => $title,
						"link" => $link,
						"pubDate" => $pubDate,
						"description" => $description,
						"category" => $category,
						"categoryLink" => $categoryLink,
						"author" => $author
					);
			});

			// return response content
			return array("articles" => $articles);
		}

		/**
		 * Get an specific news to display
		 *
		 * @param String
		 * @return Array
		 */
		private function story($query)
		{
			// create a new client
			$client = new Client();
			$guzzle = $client->getClient();
			$client->setClient($guzzle);

			// create a crawler
			$crawler = $client->request('GET', "http://www.martinoticias.com/$query");

			// search for title
			$title = $crawler->filter('.col-title h1, h1.title')->text();

			// get the intro
			$titleObj = $crawler->filter('.intro p');
			$intro = $titleObj->count()>0 ? $titleObj->text() : "";

			// get the images
			$imageObj = $crawler->filter('figure.media-image img');
			$imgUrl = ""; $imgAlt = ""; $img = "";
			if ($imageObj->count() != 0)
			{
				$imgUrl = trim($imageObj->attr("src"));
				$imgAlt = trim($imageObj->attr("alt"));

				// get the image
				if ( ! empty($imgUrl))
				{
					$imgName = Utils::generateRandomHash() . "." . pathinfo($imgUrl, PATHINFO_EXTENSION);
					$img = \Phalcon\DI\FactoryDefault::getDefault()->get('path')['root'] . "/temp/$imgName";
					file_put_contents($img, file_get_contents($imgUrl));
				}
			}

			// get the array of paragraphs of the body
			$paragraphs = $crawler->filter('div.wsw p:not(.ta-c)');
			$content = array();
			foreach ($paragraphs as $p)
			{
				$content[] = trim($p->textContent);
			}

			// create a json object to send to the template
			return array(
				"title" => $title,
				"intro" => $intro,
				"img" => $img,
				"imgAlt" => $imgAlt,
				"content" => $content,
				"url" => "http://www.martinoticias.com/$query"
			);
		}

		/**
		 * Get the link to the news starting from the /content part
		 *
		 * @param String
		 * @return String
		 * http://www.martinoticias.com/content/blah
		 */
		private function urlSplit($url)
		{
			$url = explode("/", trim($url));
			unset($url[0]);
			unset($url[1]);
			unset($url[2]);
			return implode("/", $url);
		}

		/**
		 * Return a generic error email, usually for try...catch blocks
		 *
		 * @auhor salvipascual
		 * @return Respose
		 */
		
	}
?>
