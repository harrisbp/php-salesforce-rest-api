<?php
namespace Salesforce;

use GuzzleHttp\Client as HttpClient;

class Query {
    protected static $booted = false;

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
    protected $where = '';

    /**
     * @var string
     */
    protected $from = '';

    /**
     * @var int
     */
    protected $limit = null;

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
    protected static $baseUri = "query/";

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
        $query = $this->compiled();
        $url = $this->getQueryUrl($query);
        $result = @json_decode(static::$client->get($url)->getBody());

        if (!$result) {
            throw new Exception('Could not load results');
        }

        if (isset($result->totalSize)) {
            $this->count = $result->totalSize;
        }
        if (!empty($result->records)) {
            $this->records = $result->records;
        }

        return $this;
    }

    public function records() {
        return $this->records;
    }

    public function count() {
        return $this->count;
    }

    public function compiled() {
        if (!$this->from) {
            throw new Exception('Nothing specified for From in query');
        }

        if (empty($this->select)) {
            $fullClass = "\\Salesforce\\Resource\\$this->from";
            $resource = new $fullClass();
            $metadata = $resource::getMetadata();
            $fields = $metadata->getAllFields();
            foreach($fields as $field) {
                $this->select($field->name);
            }
        }

        $query = 'SELECT ' . $this->select . ' FROM ' . $this->escape($this->from);

        if ($this->where) {
            $query .= ' WHERE ' . $this->where;
        }

        if ($this->limit) {
            $query .= ' limit ' . $this->limit;
        }

        return $query;
    }

    /**
     * Mimicks mysql_real_escape_string
     *
     * @param $string
     * @return string
     */
    public function escape($string) {
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
                $this->select = '';
                return $this;
            }

            if ($this->select) {
                $this->select .= ', ';
            }

            if (count($args) > 1) {
                $escaped = [];
                foreach ($args as $arg) {
                    $escaped[] = $this->escape($arg);
                }

                $this->select .= implode(', ', $escaped);
            } else {
                $this->select .= $this->escape($args[0]);
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
        $this->from = $from;
        return $this;
    }

    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    public function where($key_or_array_or_callable, $value_or_null = null, $operation = '=', $join = 'and') {
        $and_or = $join == 'and' ? ' AND ' : ' OR ';

        if (is_string($key_or_array_or_callable)) {
            $key = $key_or_array_or_callable;

            if (is_null($value_or_null)) {
                throw new Exception('No key value specified for where()');
            }

            $value = $value_or_null;

            // Check if there's already a where clause created, unless we're at the start of a group
            if ($this->where && substr($this->where, -1) !== '(') {
                $this->where .= $and_or;
            }

            if (stripos($operation, 'like') === false) {
                $operator = $operation;
            } else {
                if (stripos($operation, 'not') !== false) {
                    $this->where .= '(NOT ';
                }

                $operator = 'LIKE';
            }

            $this->where .= $this->escape($key) . ' ' . $operator . ' ';

            if (is_numeric($value)) {
                $this->where .= $value;
            } else {
                $this->where .= '\'' . $this->escape($value) . '\'';
            }

            if ($operation == 'notlike') {
                $this->where .= ')';
            }

            return $this;
        }

        if (is_array($key_or_array_or_callable)) {
            $where_array = $key_or_array_or_callable;

            foreach ($where_array as $key => $value) {
                $this->where($key, $value);
            }

            return $this;
        }

        if (is_callable($key_or_array_or_callable)) {
            if ($this->where) {
                $this->where .= $and_or;
            }

            $this->where .= '(';

            $key_or_array_or_callable($this);

            $this->where .= ')';

            return $this;
        }

        throw new Exception('Invalid input for where()');
    }

    public function orWhere($key_or_array_or_callable, $value_or_null = null, $operation = '=')
    {
        return $this->where($key_or_array_or_callable, $value_or_null, $operation, 'or');
    }

    public function where_like($key_or_array_or_callable, $value_or_null = null) {
        return $this->where($key_or_array_or_callable, $value_or_null, 'like');
    }

    public function orWhereLike($key_or_array_or_callable, $value_or_null = null) {
        return $this->where($key_or_array_or_callable, $value_or_null, 'like', 'or');
    }

    public function whereNotLike($key_or_array_or_callable, $value_or_null = null) {
        return $this->where($key_or_array_or_callable, $value_or_null, 'notlike');
    }

    public function orWhereNotLike($key_or_array_or_callable, $value_or_null = null) {
        return $this->where($key_or_array_or_callable, $value_or_null, 'notlike', 'or');
    }

    /*
    public function parameterizedSearch() {
        // https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_search.htm
    }
    */
}