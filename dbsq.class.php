<?php
require_once 'DB.php';

class DBSQ {
	static private $_dsn=null;
	static private $_db=null;
	static private $_lazyLoad='row';
	private $_lazyLoadId=null;
	private $_lazyLoadIndexName=null;
	private $_lazyLoadMode=null;
	private $_data=array();
	static function setMySQLCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysql://$username:$password@$host/$database";
		self::$_db=DB::connect(self::$_dsn);
	}
	static function setMySQLiCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysqli://$username:$password@$host/$database";
		self::$_db=DB::connect(self::$_dsn);
	}
	static private function _getNewInstance() { 
		$classname=get_called_class();
		$new=new $classname;
		return $new;
	}
	static public function get($id,$uniqueindexname='id',$forcelazy=false,$forceprecache=false) { 
		if ($forcelazy && $forcelazy!='row' && $forcelazy!='col') { 
			throw new Exception('forcelazy must be row or col');
			return;
		}
		$new=self::_getNewInstance();
		$new->_lazyLoadId=$id;
		$new->_lazyLoadIndexName=$uniqueindexname;
		if ((self::$_lazyLoad || $forcelazy) && !$forceprecache) { 
			if ($forcelazy) { 
				$new->_lazyLoadMode=$forcelazy;
			} else { 
				$new->_lazyLoadMode=self::$_lazyLoad;
			}
			return $new;
		}
		$new->_doGetRow($id,$uniqueindexname
	}

	private function _doGetRow() { 
		$res=self::$_db->getRow('select * from `'.get_called_class().'` WHERE ? = ?', array($this->_lazyLoadIndexName, $this->_lazyLoadId),DB_FETCHMODE_ASSOC);
		$this->_loadDataRow($res);
	}
	private function _doGetCol($
	static public function getAll($where="1 = 1",$args=array()) { 
		$res=self::$_db->query('select * from `'.get_called_class().'` WHERE '.$where, $args);
	}
	
}
