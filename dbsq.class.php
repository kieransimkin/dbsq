<?php
require_once 'DB.php';

class DBSQ {
	static private $_dsn=null;
	static private $_db=null;
	private $_data=array();
	static function setMySQLCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysql://$username:$password@$host/$database";
		self::$_db=DB::connect(self::$_dsn);
	}
	static function setMySQLiCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysqli://$username:$password@$host/$database";
		self::$_db=DB::connect(self::$_dsn);
	}
	public static function get($id,$uniqueindexname='id') { 
		$res=self::$_db->getRow('select * from `'.get_called_class().'` WHERE ? = ?', array($uniqueindexname, $id),DB_FETCHMODE_ASSOC);
		$classname=get_called_class();
		$new = new $classname;
		$new->_loadData($res);
	}
	public static function getAll($where="1 = 1",$args=array()) { 
		$res=self::$_db->query('select * from `'.get_called_class().'` WHERE '.$where, $args);
	}
	
}
