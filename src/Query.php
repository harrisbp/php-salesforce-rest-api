<?php
namespace Salesforce;

use GuzzleHttp\Client as HttpClient;

class Query {
    protected static $booted = false;

    /**
     * Holds the components of the query
     *
     * @var array
     */
    protected $components = [
        'select' => '',
        'where' => '',
        'from' => '',
        'limit' => null,
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

    public function resetComponents() {
        $this->components = [
            'select' => '',
            'where' => '',
            'from' => '',
            'limit' => null,
        ];
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
        $this->count = 0;
        $this->records = [];

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
            throw new Exception('Nothing specified for From in query');
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

        $query = 'SELECT ' . $this->components['select'] . ' FROM ' . $this->escape($this->components['from']);

        if ($this->components['where']) {
            $query .= ' WHERE ' . $this->components['where'];
        }

        if ($this->components['limit']) {
            $query .= ' limit ' . $this->components['limit'];
        }

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
        $this->components['from'] = $from;
        return $this;
    }

    public function limit($limit) {
        $this->components['limit'] = (int)$limit;
        return $this;
    }

    public function where($key_or_array_or_callable, $value_or_null = null, $operation = '=', $join = 'and') {
        $and_or = strtolower(trim($join)) == 'and' ? ' AND ' : ' OR ';

        if (is_string($key_or_array_or_callable) || is_numeric($key_or_array_or_callable)) {
            $key = $key_or_array_or_callable;

            if (is_null($value_or_null)) {
                throw new Exception('No key value specified for where()');
            }

            $value = $value_or_null;

            // Check if there's already a where clause created, unless we're at the start of a group
            if ($this->components['where'] && substr($this->components['where'], -1) !== '(') {
                $this->components['where'] .= $and_or;
            }

            if (stripos($operation, 'like') === false) {
                $operator = $operation;
            } else {
                if (stripos($operation, 'not') !== false) {
                    $this->components['where'] .= '(NOT ';
                }

                $operator = 'LIKE';
            }

            $this->components['where'] .= $this->escape($key) . ' ' . $operator . ' ';

            if (is_numeric($value)) {
                $this->components['where'] .= $value;
            } else if (is_bool($value)) {
                $this->components['where'] .= $value ? 'true' : 'false';
            } else {
                $this->components['where'] .= '\'' . $this->escape($value) . '\'';
            }

            if ($operation == 'notlike') {
                $this->components['where'] .= ')';
            }

            return $this;
        }

        if (is_array($key_or_array_or_callable)) {
            $where_array = $key_or_array_or_callable;

            foreach ($where_array as $key => $value) {
                $this->where($key, $value, $operation, $join);
            }

            return $this;
        }

        if (is_callable($key_or_array_or_callable)) {
            if ($this->components['where']) {
                $this->components['where'] .= $and_or;
            }

            $this->components['where'] .= '(';

            $key_or_array_or_callable($this);

            $this->components['where'] .= ')';

            return $this;
        }

        throw new Exception('Invalid input for where()');
    }

    public function orWhere($key_or_array_or_callable, $value_or_null = null, $operation = '=')
    {
        return $this->where($key_or_array_or_callable, $value_or_null, $operation, 'or');
    }

    public function whereLike($key_or_array_or_callable, $value_or_null = null) {
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
}