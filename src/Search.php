<?php
namespace Salesforce;

use GuzzleHttp\Client as HttpClient;

class Search {
    use Cacheable;

    protected static $booted = false;

    /**
     * Holds the components of the query
     *
     * @var array
     */
    protected $components = [
        'select' => '',
        'find' => '',
        'searchGroup' => '',
        'from' => null,
    ];

    /**
     * Holds the columns for a SELECT
     *
     * @var string
     */
    protected $select = '';

    /**
     * Holds the WHERE clause
     *
     * @var string
     */
    protected $find = '';

    /**
     * Holds the search group
     *
     * @var string
     */
    protected $searchGroup = '';


    /**
     * @var string
     */
    protected $from = '';

    /**
     * HTTP Client Instance
     * @see \GuzzleHttp\Client
     */
    protected static $client;

    /**
     * @var array
     */
    protected $records = [];

    /**
     * @int array
     */
    protected $count = 0;

    /**
     * Base URI of resource requests
     * @var string
     */
    protected static $baseUri = "search/";

    /**
     * Set the client instance.
     *
     * @param  \GuzzleHttp\Client $client
     * @return void
     */
    public static function setHttpClient(HttpClient $client) {
        static::$client = $client;
    }

    /**
     * Returns the httpClient
     * @return HttpClient
     */
    public static function getHttpClient() {
        return static::$client;
    }

    public function resetComponents() {
        $this->components = [
            'select' => '',
            'find' => '',
            'searchGroup' => '',
            'from' => null,
        ];
    }

    /**
     * getBaseUrl
     * @return string
     */
    public function getBaseUrl() {
        return static::$baseUri . '/';
    }

    public function encodeQuery($query) {
        $query = urlencode($query);
        // Keep commas and single quotes
        // $query = str_replace(['%2C', '%27'], [',', '\''], $query);
        return $query;
    }

    /**
     * getBaseUrl
     * @return string
     */
    protected function getQueryUrl($query) {
        return static::$baseUri . '?q=' . $this->encodeQuery($query);
    }

    public function execute() {
        $this->count = 0;
        $this->records = [];

        if (self::$useCache) {
            $result = self::cacheGetBySearch($this->components);
        } else {
            $query = $this->compiled();
            $url = $this->getQueryUrl($query);
            $result = @json_decode(static::$client->get($url)->getBody());
        }

        if (!$result) {
            throw new Exception('Could not load results');
        }

        if (isset($result->totalSize)) {
            $this->count = $result->totalSize;
        }

        if (!empty($result->searchRecords)) {
            $this->records = $result->searchRecords;
            $this->count   = count($result->searchRecords);
        }

        $this->resetComponents();

        return $this;
    }

    public function records() {
        return $this->records;
    }

    public function count() {
        return $this->count;
    }

    public function compiled() {
        if (!$this->components['from']) {
            throw new Exception('Nothing specified for From in search query');
        }

        if (!$this->components['select']) {
            throw new Exception('Nothing specified for Select in search query');
        }

        if (!$this->components['searchGroup']) {
            throw new Exception('Nothing specified for Search Group in search query');
        }

        if (empty($this->components['select'])) {
            $fullClass = "\\Salesforce\\Resource\\{$this->components['from']}";
            $resource = new $fullClass();
            $metadata = $resource::getMetadata();
            $fields = $metadata->getAllFields();
            foreach($fields as $field) {
                $this->select($field->name);
            }
        }

        $query = 'FIND {' . $this->components['find'] . '} IN '
            . $this->components['searchGroup'] . ' FIELDS '
            . 'RETURNING ' . $this->components['from']
            . '(' . $this->components['select'] . ')';

        return $query;
    }
    /**
     * Mimicks mysql_real_escape_string
     *
     * @param $string
     * @return string
     */
    public function escape($string)
    {
        $search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
        $replace = array("\\\\","\\0","\\n", "\\r", "\\'", '\"', "\\Z");
        return str_replace($search, $replace, $string);
    }

    public function select()
    {
        $args = func_get_args();

        if (empty($args)) {
            throw new Exception('Nothing specified for select');
        }

        if (is_string($args[0])) {
            // Keep the array empty and we'll get all the fields later via metadata
            // Salesforce query cannot select all fields with *
            if ($args[0] == '*') {
                $this->components['select'] = '';
                return $this;
            }

            if ($this->components['select']) {
                $this->components['select'] .= ', ';
            }

            if (count($args) > 1) {
                $escaped = [];
                foreach ($args as $arg) {
                    $escaped[] = $this->escape($arg);
                }

                $this->components['select'] .= implode(', ', $escaped);
            } else {
                $this->components['select'] .= $this->escape($args[0]);
            }

            return $this;
        }

        if (!is_array($args[0])) {
            throw new Exception('Invalid input for select');
        }

        foreach ($args[0] as $field) {
            $this->select($field);
        }

        return $this;
    }

    public function from($from) {
        $this->components['from'] = $this->escape(ucfirst($from));
        return $this;
    }

    public function in($searchGroup) {
        $searchGroup = strtoupper($searchGroup);
        if (!in_array($searchGroup, ['ALL', 'NAME', 'EMAIL', 'PHONE', 'SIDEBAR'])) {
            throw new Exception('Search group for find must be one of: ' . implode(', ', $searchGroup));
        }

        $this->components['searchGroup'] = $this->escape($searchGroup);
        return $this;
    }

    public function find($value_or_array_or_callable, $join = 'and') {
        switch (strtolower(trim($join))) {
            case 'and' :
                $and_or = ' AND ';
                break;

            case 'or' :
                $and_or = ' OR ';
                break;

            case 'andnot' :
                $and_or = ' AND NOT ';
                break;

            default :
                throw new Exception('Invalid value for Search $join');
                break;
        }

        if (is_string($value_or_array_or_callable) || is_numeric($value_or_array_or_callable)) {
            $value = trim($value_or_array_or_callable);

            // Check if there's already a where clause created, unless we're at the start of a group
            if ($this->components['find'] && substr($this->components['find'], -1) !== '(') {
                $this->components['find'] .= $and_or;
            }

            $quoted = false;
            if (strpos($value, '"') === 0) {
                if (substr($value, -1) !== '"') {
                    throw new Exception('Mismatched quotes for find() value');
                }

                $quoted = true;
                $value  = substr($value, 1, strlen($value) - 2);;
            }

            $this->components['find'] .= ($quoted ? '"' : '') . $this->escape($value) . ($quoted ? '"' : '');

            return $this;
        }

        if (is_array($value_or_array_or_callable)) {
            $find_array = $value_or_array_or_callable;

            foreach ($find_array as $value) {
                $this->find($value, $join);
            }

            return $this;
        }

        if (is_callable($value_or_array_or_callable)) {
            if ($this->components['find']) {
                $this->components['find'] .= $and_or;
            }

            $this->components['find'] .= '(';

            $value_or_array_or_callable($this);

            $this->components['find'] .= ')';

            return $this;
        }

        throw new Exception('Invalid input for where()');
    }

    public function orFind($value_or_array_or_callable)
    {
        return $this->find($value_or_array_or_callable, 'or');
    }

    public function notFind($value_or_array_or_callable)
    {
        return $this->find($value_or_array_or_callable, 'andnot');
    }
}