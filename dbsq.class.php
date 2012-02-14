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
		if ($classname=='DBSQ') { 
			throw new Exception('You cannot create instances of the DBSQ class');
			return null;
		}
		$new=new $classname;
		return $new;
	}
	static public function get($id=null,$uniqueindexname='id',$forcelazy=false,$forceprecache=false) { 
		if (is_null($id)) { 
			return self::_getNewInstance();
		}
		if ($forcelazy && $forcelazy!='row' && $forcelazy!='col') { 
			throw new Exception('forcelazy must be row or col');
			return;
		}
		$new=self::_getNewInstance();
		$new->_data[$uniqueindexname]=$new->_lazyLoadId=$id;
		$new->_lazyLoadIndexName=$uniqueindexname;
		if ((self::$_lazyLoad || $forcelazy) && !$forceprecache) { 
			if ($forcelazy) { 
				$new->_lazyLoadMode=$forcelazy;
			} else { 
				$new->_lazyLoadMode=self::$_lazyLoad;
			}
			return $new;
		}
		$new->_doGetRow($id,$uniqueindexname);
	}
	private function _assertLazyLoadSetup() { 
		if (is_null($this->_lazyLoadId) || is_null($this->_lazyLoadIndexName) || is_null($this->_lazyLoadMode)) { 
			throw new Exception('You need to load the object before you can read from it!');
			return;
		}
	}
	private function _doGetCol($colname) { 
		$this->_assertLazyLoadSetup();
		$res=self::$_db->getOne('select ? from `'.get_called_class().'` WHERE ? = ? LIMIT 1', array($colname, $this->_lazyLoadIndexName, $this->_lazyLoadId));
		return $res;
	}
	private function _doGetRow() { 
		$this->_assertLazyLoadSetup();
		$res=self::$_db->getRow('select * from `'.get_called_class().'` WHERE ? = ? LIMIT 1', array($this->_lazyLoadIndexName, $this->_lazyLoadId),DB_FETCHMODE_ASSOC);
		$this->_loadDataRow($res);
	}
	static public function getAll($where="1 = 1",$args=array()) { 
		$res=self::$_db->getAll('select * from `'.get_called_class().'` WHERE '.$where, $args,DB_FETCHMODE_ASSOC);
		$ret=array();
		foreach ($res as $row) { 
			$new=self::_getNewInstance();
			$new->_lazyLoadMode='col';
			$new->_lazyLoadId=$row['id'];
			$new->_lazyLoadIndexName='id';
			$new->_loadDataRow($res);
			$ret[]=$new;
		}
		return $ret;
	}
	private function _loadDataRow($data) { 
		foreach ($data as $key => $val) { 
			if (substr($key,-3,3)=='_id') { 
				$key=substr($key,0,strlen($key)-3);
				$bits=explode("__",$key,2);
				$prefix='';
				$varname=$key;
				if (count($bits)>1) { 
					$prefix=$bits[0];
					$varname=$bits[1];
				}
				if (class_exists($varname)) { 
					$new=$varname::get($val);
					if (strlen($prefix)>0) { 
						$this->_data[$prefix.'__'.$varname]=$new;
					} else { 
						$this->_data[$varname]=$new;
					}
				} else { 
					$this->_data[$key]=$val;
				}
			} else { 
				$this->_data[$key]=$val;
			}
		}
	}
	
}
