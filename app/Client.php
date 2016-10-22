<?php
namespace App;

use GuzzleHttp\ClientInterface;
use MaartenGDev\CacheInterface;

class Client
{
    /**
     * @var ClientInterface
     */
    private $client;
    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(ClientInterface $client, CacheInterface $cache)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

    public function search($study, $location)
    {
        $key = $study . $location;

        $cache = $this->cache->has($key, function ($cache) use ($key) {
            return $cache->get($key);
        });

        if ($cache) {
            return $cache;
        }

        $this->cache->store($key, json_encode($this->getData($study, $location)));

        return $this->cache->get($key);
    }

    protected function getUrl($page, $study, $location)
    {
        $query = http_build_query([
            't' => $study,
            's' => '',
            'z' => 'opleiding',
            'l' => 'Nederland',
            'b' => 'False',
            'c' => '',
            'lw' => '',
            'n' => '',
            'pg' => $page,
            'v' => '',
            'srt' => 'relevantie',
            'InputTrefwoordenPlaceholderGeenTekst' => 'Voer een tekst in',
            'InputTrefwoordenPlaceholder' => 'Opleiding bedrijf crebo id',
            'p' => $location
        ]);

        return getenv('JOB_API_URL') . '?' . $query;
    }

    protected function getData($study, $location)
    {
        $pages = 1;
        $page = 1;

        $companies = [];


        while ($page <= $pages) {

            $url = $this->getUrl($page, $study, $location);

            $response = $this->client->request('GET', $url);

            $response = $response->getBody();

            $doc = new \DOMDocument();

            $doc->loadHTML(str_replace('js?&region=NL', 'js?region=NL', $response));

            $xpath = new \DOMXpath($doc);

            $elements = $xpath->query("//div[@class='item row']");
            $pages = $xpath->query("//a[@class ='page-link']");

            $pages = $pages->length - 2;

            foreach ($elements as $element) {
                $company = (object)[
                    'name' => '',
                    'address' => '',
                    'postal' => '',
                    'city' => '',
                    'telephone' => ''
                ];

                foreach ($element->childNodes as $node) {
                    $counter = 0;

                    if ($node->childNodes !== null) {
                        foreach ($node->childNodes as $companyDivElement) {
                            $nodeTag = $companyDivElement->nodeName;
                            $elementsInCompanyItem = $companyDivElement->childNodes;

                            if ($nodeTag === "h4") {
                                $company->name = trim(str_replace([PHP_EOL], [' '], $elementsInCompanyItem[0]->nodeValue));
                            }

                            if ($nodeTag === "p") {
                                if ($elementsInCompanyItem->length == 1) {

                                    if ($counter === 0) {
                                        $company->address = $elementsInCompanyItem[0]->nodeValue;
                                        $counter++;
                                    } else {
                                        $company->telephone = $elementsInCompanyItem[0]->nodeValue;
                                    }

                                } else {
                                    $company->postal = $companyDivElement->childNodes[0]->nodeValue;
                                    $company->city = $companyDivElement->childNodes[1]->nodeValue;
                                }
                            }
                        }
                    }
                }
                $companies[] = $company;
            }
            $page++;
        }
        return $companies;
    }
}