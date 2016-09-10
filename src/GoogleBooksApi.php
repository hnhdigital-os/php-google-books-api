<?php

namespace Bluora\GoogleBooksApi;

use Bluora\SharedApiTraits\EnvironmentVariablesTrait;
use GuzzleHttp\Client;

class GoogleBooksApi implements \Iterator, \Countable
{
    use EnvironmentVariablesTrait;

    /**
     * Environment Variable Name.
     *
     * @var string
     */
    protected $env_name = 'GOOGLE_BOOKS_API_';

    /**
     * Google Books API Key
     *
     * @var string
     */
    private $client_key;

    /**
     * Google Books API URI
     *
     * @var string
     */
    private $client_uri = 'https://www.googleapis.com/books/v1/';

    /**
     * Google Books API Path
     *
     * @var string
     */
    private $client_path = 'volumes';

    /**
     * Total number of requests made.
     *
     * @var integer
     */
    private $request_count = 0;

    /**
     * Limit the number of requests that can be made.
     *
     * @var integer
     */
    private $request_limit = false;

    /**
     * Results by page.
     *
     * @var array
     */
    private $page_results = [];

    /**
     * Total pages in result.
     *
     * @var integer
     */
    private $result_total_pages = 0;

    /**
     * Total books in result.
     *
     * @var integer
     */
    private $result_total_count = 0;

    /**
     * Total records per page.
     *
     * @var integer
     */
    private $result_records_per_page = 10;

    /**
     * Current page.
     *
     * @var integer
     */
    private $current_page = 1;

    /**
     * Current record.
     *
     * @var integer
     */
    private $current_record = 1;

    /**
     * Current result.
     *
     * @var integer
     */
    private $current_result = [];

    /**
     * API call had an error.
     *
     * @var integer
     */
    private $had_error = false;

    /**
     * Current query.
     *
     * @var array
     */
    private $paramaters = [
        'q' => '',
        'download' => '',
        'filter' => '',
        'startIndex' => 0,
        'maxResults' => 10,
        'printType' => '',
        'projection' => '',
        'orderBy' => ''
    ];

    /**
     * Create an instance of the client.
     *
     * @param array $config - Override or provide the environment variables.
     *
     * @return GoogleBooksApi
     */
    public function __construct($config = [])
    {
        $this->setEnv('key', $config);
        $this->setEnv('uri', $config);
        $this->setEnv('path', $config);
        return $this;
    }

    /**
     * Set the record limit.
     *
     * @param  integer $limit
     *
     * @return GoogleBooksApi
     */
    public function limit($limit)
    {
        $this->request_limit = $limit;
        if ($limit > 40) {
            $this->take(40);
        } else {
            $this->take($limit);
        }
        return $this;
    }

    /**
     * Get the first result.
     *
     * @return array|null
     */
    public function first()
    {
        $this->limit(1);
        $this->rewind();
        if (count($this->current_result)) {
            return $this->current_result[0];
        }
        return null;
    }

    /**
     * Set the API to perform a search.
     *
     * @return GoogleBooksApi
     */
    public function books()
    {
        $this->client_path = 'volumes';
        return $this;
    }

    /**
     * Set the API to list user's public bookshelves.
     *
     * @param int $user_id
     *
     * @return GoogleBooksApi
     */
    public function bookshelves($user_id)
    {
        $this->client_path = sprintf('users/%d/bookshelves', $user_id);
        return $this;
    }

    /**
     * Set the API to list user's volumes on their public bookshelf.
     *
     * @param int $user_id
     * @param int $bookshelf_id
     *
     * @return GoogleBooksApi
     */
    public function bookshelfBooks($user_id, $bookshelf_id)
    {
        $this->client_path = sprintf('users/%d/bookshelves/%d/volumes', $user_id, $bookshelf_id);
        return $this;
    }

    /**
     * Add to the query.
     *
     * @return GoogleBooksApi
     */
    private function add($paramater, $name, $value = false)
    {
        if ($value === false) {
            $this->paramaters[$paramater] = $name;
        } else {
            $this->paramaters[$paramater][$name] = $value;
        }
        return $this;
    }

    /**
     * Add a `download` paramater.
     *
     * @param  $value
     *
     * @return GoogleBooksApi      
     */
    public function download($value)
    {
        if ($value === 'epub') {
            $this->add('download', $value);
        }
        return $this;
    }

    /**
     * Add a `langRestrict` paramater.
     *
     * @param string $value
     *
     * @return GoogleBooksApi
     */
    public function language($value)
    {
        $this->add('langRestrict', $value);
        return $this;
    }

    /**
     * Add a `filter` paramater.
     *
     * @param string $value
     *
     * @return GoogleBooksApi
     */
    public function filter($value)
    {
        if (in_array($value, ['partial', 'full', 'free-ebooks', 'paid-ebooks', 'ebooks'])) {
            $this->add('filter', $value);
        }
        return $this;
    }

    /**
     * Add a `orderBy` paramater.
     *
     * @param  $value
     *
     * @return GoogleBooksApi      
     */
    public function order($value)
    {
        if (in_array($value, ['newest', 'relevance'])) {
            $this->add('orderBy', $value);
        }
        return $this;
    }

    /**
     * Add a `projection` paramater.
     *
     * @param  $value
     *
     * @return GoogleBooksApi      
     */
    public function projection($value)
    {
        if (in_array($value, ['full', 'lite'])) {
            $this->add('projection', $value);
        }
        return $this;
    }

    /**
     * Add a `q` paramater.
     *
     * @param string $name
     * @param string|bool $value
     *
     * @return GoogleBooksApi
     */
    public function query($name, $value = false)
    {
        if ($value === false) {
            $value = $name;
            $name = '';
        }
        if (in_array($name, ['', 'intitle', 'inauthor', 'inpublisher', 'subject', 'isbn', 'lccn', 'oclc'])) {
            $this->add('q', $name, $value);
        }
        return $this;
    }

    /**
     * Add a `startIndex` paramater.
     *
     * @param  $value
     * 
     * @return GoogleBooksApi
     */
    public function skip($value)
    {
        $this->current_page = floor($value / $this->result_records_per_page);

        if ($value !== false && $value >= 0) {
            $this->add('startIndex', $value);
        }
        return $this;
    }

    /**
     * Add a `maxResults` paramater.
     *
     * @param  $value
     * 
     * @return GoogleBooksApi
     */
    public function take($value = false)
    {
        if ($value >= 1 && $value <= 40) {
            $this->add('maxResults', $value);
            $this->result_records_per_page = $value;
        } else {
            $this->add('maxResults', null);
        }
        return $this;
    }

    /**
     * Add the `printType` paramater.
     *
     * @param string $value
     *
     * @return GoogleBooksApi      
     */
    public function type($value)
    {
        if (in_array($value, ['all', 'books', 'magazines'])) {
            $this->add('printType', $value);
        }
        return $this;
    }

    /**
     * Get the data from the API.
     *
     * @throws InvalidResponseException When the client got an invalid response
     * 
     * @return GoogleBooksApi
     */
    public function get()
    {
        // Key and uri are required.
        if (!$this->hasConfig('key') || !$this->hasConfig('uri')) {
           throw new Exception\MissingConfigException('Missing required API config.');
        }

        // Return cached results for this page.
        if (isset($this->page_results[$this->current_page])) {
            $this->current_result = $this->page_results[$this->current_page];
            return $this;
        }

        $this->result_records_per_page * $this->current_page;

        // Build query.
        $query_data = [];
        foreach ($this->paramaters as $key => $value) {
            if (is_array($value)) {
                $query_data[$key] =  '';
                foreach ($value as $field => $field_value) {
                    $query_data[$key] .= (!empty($field)) ? $field.':' : '';
                    $query_data[$key] .= urldecode($field_value);
                }
            } elseif (strlen($value) > 0) {
                $query_data[$key] = $value;
            }
        }


        $response = (new Client())->request('GET', $this->client_path, [
            'base_uri'    => $this->client_uri,
            'query'       => $query_data,
            'http_errors' => false,
        ]);

        if (($status = $response->getStatusCode()) != 200) {
            $this->had_error = true;
            $this->last_error = 'Invalid response. Status: '.$status.'. Body: '.$response->getBody();
            return $this;
        }

        $current_result = json_decode($response->getBody(), true);

        $this->result_total_count = intval($current_result['totalItems']);
        $this->result_total_pages = ceil($this->result_total_count / $this->result_records_per_page);

        if (isset($current_result['items']) && count($current_result['items'])) {
            foreach ($current_result['items'] as $key => $item_detail) {

                $detail = [];

                switch ($item_detail['kind']) {
                    case 'books#volume':
                        $detail = $item_detail['volumeInfo'];
                        if (!empty($item_detail['searchInfo']['textSnippet'])) {
                            $detail['searchInfo'] = $item_detail['searchInfo']['textSnippet'];
                        }
                        if (!empty($detail['industryIdentifiers']) && is_array($detail['industryIdentifiers'])) {
                            foreach ($detail['industryIdentifiers'] as $value) {
                                $detail[$value['type']] = $value['identifier'];
                            }
                            unset($detail['industryIdentifiers']);
                        }
                        break;
                }

                $this->current_result[] = $detail;
            }
        }

        $this->page_results[$this->current_page] = $this->current_result;
        $this->request_count++;

        return $this;
    }

    /**
     * Most recent query failed.
     *
     * @return boolean
     */
    public function error()
    {
        return $this->had_error;
    }

    /**
     * Rewind the result.
     *
     * @return array
     */
    public function rewind()
    {
        $this->current_page = 1;
        $this->current_record = 0;
        $this->get();
        return $this->current();
    }

    /**
     * Get the current result.
     *
     * @return array|null
     */
    public function current()
    {
        if ($this->request_count == 0) {
            $this->get();
        }
        if (isset($this->current_result[$this->current_record])) {
            return $this->current_result[$this->current_record];
        }
        return null;
    }

    /**
     * Get the row key.
     *
     * @return integer
     */
    public function key() 
    {
        return (($this->current_page - 1) * $this->result_records_per_page) + $this->current_record;
    }

    /**
     * Rewind the result.
     *
     * @return array
     */
    public function next()
    {
        $this->current_record++;
        if ($this->current_record >= $this->result_records_per_page) {
            $this->current_page++;
            $this->current_record = 0;
            $this->get();
        }
        return $this->current();
    }

    /**
     * Return the count of results.
     *
     * @return integer
     */
    public function count()
    {
        if ($this->request_count == 0) {
            $this->rewind();
        }
        return $this->result_total_count;
    }

    /**
     * Return the total pages.
     *
     * @return integer
     */
    public function totalPages()
    {
        if ($this->request_count == 0) {
            $this->rewind();
        }
        return $this->result_total_pages;
    }

    /**
     * Has results.
     *
     * @return booleanc
     */
    public function valid()
    {
        if ($this->request_count == 0) {
            return true;
        }
        if ($this->request_limit !== false && $this->request_limit < $this->key()+1) {
            return false;
        }
        return !$this->error() && ((($this->current_page - 1) * $this->result_records_per_page) + $this->current_record) <= $this->result_total_count;
    }
}
