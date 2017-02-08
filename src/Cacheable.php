<?php
namespace Salesforce;

trait Cacheable {
	protected static $pdo;
	protected static $useCache = false;
	protected static $table = 'salesforce_cache';

	public static function cacheInit($host, $user, $pass, $db) {
		$dsn = "mysql:host=$host;dbname=$db;charset=utf8";
		self::$pdo = new \PDO($dsn, $user, $pass);
		self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		if (self::$pdo) {
			self::$useCache = true;
		}
	}

	public static function cachePdoConnection() {
		return self::$pdo;
	}

	public static function cacheExec($sql, $bind = []) {
		$statement = self::cachePdoConnection()->prepare($sql);
		$success = $statement->execute($bind);
		return $success ? $statement : false;
	}

	public function cacheGetBySearch($components) {
		$searchHash = md5(serialize($components));
		$type = 'Search' . $components['from'];

		$sql = 'SELECT `data` FROM `' . self::$table . '` WHERE `type` = :type AND `sfid` = :sfid';
		$bind = [
			'type' => $type,
			'sfid' => $searchHash
		];

		$result = self::cacheGet($sql, $bind);

		if (!$result) {
			$query  = $this->compiled();
			$url    = $this->getQueryUrl($query);
			$result = @json_decode(static::$client->get($url)->getBody());

			if (!empty($result->totalSize)) {
				self::cachePutById(
					$type,
					$searchHash,
					json_encode($result)
				);
			}

			return @json_decode($result);
		}

		return @json_decode($result[0]['data']);
	}

	public function cacheGetByQuery($components) {
		$searchHash = md5(serialize($components));
		$type = 'Query' . $components['from'];

		$sql = 'SELECT `data` FROM `' . self::$table . '` WHERE `type` = :type AND `sfid` = :sfid';
		$bind = [
			'type' => $type,
			'sfid' => $searchHash
		];

		$result = self::cacheGet($sql, $bind);

		if (!$result) {
			$query = $this->compiled();
			$url = $this->getQueryUrl($query);
			$result = @json_decode(static::$client->get($url)->getBody());

			if (!empty($result->totalSize)) {
				self::cachePutById(
					$type,
					$searchHash,
					json_encode($result)
				);
			}

			return $result;
		}

		return @json_decode($result[0]['data']);
	}

	public static function cacheGetById($type, $sfid) {
		$sql = 'SELECT `data` FROM `' . self::$table . '` WHERE `type` = :type AND `sfid` = :sfid';
		$bind = ['type' => $type, 'sfid' => $sfid];
		$result = self::cacheGet($sql, $bind);

		if (!$result) {
			$attributes = @json_decode(static::$client->get(static::getResourceUrl($sfid))->getBody());
			self::cachePutById(
				$type,
				$sfid,
				json_encode($attributes),
				date('Y-m-d H:i:s', strtotime($attributes->CreatedDate)),
				date('Y-m-d H:i:s', strtotime($attributes->LastModifiedDate))
			);
			return $attributes;
		}

		return @json_decode($result[0]['data']);
	}

	public static function cachePutById($type, $sfid, $data, $sf_created_at = null, $sf_updated_at = null) {
		$sql = 'INSERT INTO `' . self::$table . '` SET `type` = :type, `sfid` = :sfid, `data` = :data, `created_at` = NOW(), `updated_at` = NOW()';

		$bind = ['type' => $type, 'sfid' => $sfid, 'data' => $data];

		if ($sf_created_at) {
			$sql .= ', `sf_created_at` = :sf_created_at';
			$bind['sf_created_at'] = $sf_created_at;
		}

		if ($sf_updated_at) {
			$sql .= ', `sf_updated_at` = :sf_updated_at';
			$bind['sf_updated_at'] = $sf_updated_at;
		}

		return self::cachePut($sql, $bind);
	}

	public static function cacheGet($sql, $bind = []) {
		$result = self::cacheExec($sql, $bind);
		return $result ? $result->fetchAll() : false;
	}

	public static function cachePut($sql, $bind = []) {
		return self::cacheExec($sql, $bind);
	}
}