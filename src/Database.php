<?php
namespace Salesforce;

exit('Not finished yet (database stuff is in Cache class)');

class Database {
	protected $pdo;

	public function __construct($host, $user, $pass, $db) {
		$dsn = "mysql:host=$host;dbname=$db;charset=utf8";
		$this->pdo = new \PDO($dsn, $user, $pass);
	}

	public function pdoConnection() {
		return $this->pdo;
	}

	public function parseColumnList($columns = '*') {
		if ($columns != '*') {
			$columns = (array)$columns;
			$columnList = '`' . implode('`, `', $columns) . '`';
		} else {
			$columnList = '*';
		}

		return $columnList;
	}

	public function select($table, $where = [], $columns = '*') {
		$columnList = $this->parseColumnList($columns);
		list($whereClause, $bind) = !empty($where) ? ' WHERE ' . $this->where($where) : ['', []];
		$query = 'SELECT ' . $columnList . ' FROM ' . $table . $whereClause;
		return $this->query($query, $bind);
	}

	public function insert($table, $where = [], $columns = '*') {
		$columnList = $this->parseColumnList($columns);
		list($whereClause, $bind) = !empty($where) ? ' WHERE ' . $this->where($where) : ['', []];
		$query = 'SELECT ' . $columnList . ' FROM ' . $table . $whereClause;
		return $this->query($query, $bind);
	}

	public function query($sql, $bind = []) {
		$stmt = $this->pdoConnection()->prepare($sql);
		$stmt->execute($bind);
		return $stmt->fetch();
	}

	public function getWhereAndBind($where, $join = 'and')
	{
		if (!is_array($where)) throw new Exception('$where must be an array');

		$and_or = strtolower(trim($join)) == 'and' ? ' AND ' : ' OR ';

		$whereItems = [];
		$bindItems  = [];

		foreach ($where as $column => $value) {
			$operator = '=';
			$columnName = $column;

			preg_match('#(.*?) (<|>|>=|<=|=|!=|LIKE|NOT LIKE|IS NULL|IS NOT NULL|IS|IS NOT)$#i', $column, $matches);

			if (!empty($matches[2])) {
				$columnName = $matches[1];
				$operator   = $matches[2];

				if (is_null($value)) {
					if ($operator === 'IS') {
						$operator = 'IS NULL';
					}

					if ($operator === 'IS NOT') {
						$operator = 'IS NOT NULL';
					}
				} else {
					if ($operator === 'IS') {
						$operator = '=';
					}

					if ($operator === 'IS NOT') {
						$operator = '!=';
					}
				}
			}

			if (is_null($value) || in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
				$ifIsNull = $operator === 'IS NULL' || $operator === '=';

				$whereItems[] = '`' . $columnName . '`' . ($ifIsNull ? 'IS NULL' : 'IS NOT NULL');
				continue;
			}

			$bindItem = $columnName;
			if (array_key_exists($bindItem, $bindItems)) {
				// Append something if it's already taken
				$bindItem .= '0';
			}

			$whereItems[] = '`' . $columnName . '` ' . $operator . ' :' . $bindItem;
			$bindItems[$bindItem] = $value;
		}

		$whereClause = implode($and_or, $whereItems);
		return [
			'where' => $whereClause,
			'bind' => $bindItems,
		];
	}
}