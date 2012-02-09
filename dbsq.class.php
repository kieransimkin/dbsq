<?php
require_once 'MDB2.php';

class DBSQ {
	static private $_connected=false;
	static private $_dsn=null;
	private $data=array();
	static function setMySQLCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysql://$username:$password@$host/$database";
	}
	
	function __construct($classname) { 

	}
	public function get() { 

	}
	public function query($query,$params=array()) { 
		$res=$db->query($query,$params);
		if (PEAR::isError($res)) { 

		} else { 
			return $res;
		}
	}
	public function connect() { 
		self::$_db=MDB2::factory(self::$_dsn);
		if (PEAR::isError($db)) { 
			throw new Exception($db->getMessage());
		}
	}
	public function disconnect() { 
		self::$_db->disconnect();
	}
	
}
