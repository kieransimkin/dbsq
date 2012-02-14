<?php
require_once 'DB.php';

class DBSQ {
	static private $_dsn=null;
	static private $_db=null;
	static private $_lazyLoad='row';
	static private $_foreignKeySeparator='__';
	static private $_cache=array();
	private $_lazyLoadId=null;
	private $_lazyLoadIndexName=null;
	private $_lazyLoadMode=null;
	private $_data=array();
	public function __get($name) { 
		if (isset($this->_data[$name])) { 
			return $this->_data[$name];
		} else if (substr($name,-3,3)=='_id') { 
			return $this->_data[substr($name,0,strlen($name)-3)];	
		} else if ($this->_lazyLoadMode=='row') { 
			$this->_doGetRow();
			return $this->_data[$name];
		} else if ($this->_lazyLoadMode=='col') { 
			return $this->_doGetCol($name);
		} else { 
			throw new Exception('Unable to find property: '.$name);
			return null;
		}
	}
	public function __set($name,$value) { 
		$this->_data[$name]=$value;
	}
	public function __isset($name) { 
		return isset($this->_data[$name]);
	}
	public function __unset($name) { 
		unset($this->_data[$name]);
	}
	public function __toString() { 
		if (isset($this->_data['id'])) { 
			return $this->_data['id'];
		} else {
			return 'null';
		}
	}
	function __construct() { 
		if (get_called_class()=='DBSQ') { 
			throw new Exception('You cannot create instances of the DBSQ class');
		}
	}
	static public function setMySQLCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysql://$username:$password@$host/$database";
		self::$_db=DB::connect(self::$_dsn);
		if (PEAR::isError(self::$_db)) { 
			throw new Exception(self::$_db->getMessage());
			return null;
		}
	}
	static public function setMySQLiCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysqli://$username:$password@$host/$database";
		self::$_db=DB::connect(self::$_dsn);
		if (PEAR::isError(self::$_db)) { 
			throw new Exception(self::$_db->getMessage());
			return null;
		}
	}
	static public function get($id=null,$uniqueindexname='id',$forcelazy=false,$forceprecache=false) { 
		if (is_null($id)) { 
			return self::_getNewInstance();
		}
		if ($forcelazy && $forcelazy!='row' && $forcelazy!='col') { 
			throw new Exception('forcelazy must be row or col');
			return;
		}
		if (isset(self::$_cache[get_called_class().'-'.$uniqueindexname.'-'.$id])) { 
			return self::$_cache[$uniqueindexname.'-'.$id];
		}
		$new=self::_getNewInstance();
		$new->_data[$uniqueindexname]=$new->_lazyLoadId=$id;
		$new->_lazyLoadIndexName=$uniqueindexname;
		$mew->_lazyLoadMode='done';
		if ((self::$_lazyLoad || $forcelazy) && !$forceprecache) { 
			if ($forcelazy) { 
				$new->_lazyLoadMode=$forcelazy;
			} else { 
				$new->_lazyLoadMode=self::$_lazyLoad;
			}
			self::$_cache[get_called_class().'-'.$uniqueindexname.'-'.$id]=$new;
			return $new;
		}
		$new->_doGetRow();
		self::$_cache[get_called_class().'-'.$uniqueindexname.'-'.$id]=$new;
		return $new;
	}
	public function save() { 
		if (func_num_args()==1) { 
			if (func_get_arg(0)===null) { 
				@unset($this->_data['id']);
			} else { 
				$this->_data['id']=func_get_arg(0);
			}
		}
		if (!isset($this->_data['id'])) { 
			return $this->_create();
		} else { 
			$this->_update();
			return $this->_data['id'];
		}
	}
	// This will need customizing for non-MySQL DBs:
	public function lastInsertID() { 
		$res=self::$_db->getOne('select last_insert_id()');
		if (PEAR::isError($res)) { 
			throw new Exception($res->getMessage());
			return null;
		}
		return $res;
	}
	public function affectedRows() { 
		$res=self::$_db->affectedRows();
		if (PEAR::isError($res)) { 
			throw new Exception($res->getMessage());
			return null;
		}
		return $res;
	}
	static public function getAll($where="1 = 1",$args=array(),$classname=null) { 
		if (get_called_class()=='DBSQ' && !is_null($classname)) { 
			$res=self::$_db->getAll($where, $args,DB_FETCHMODE_ASSOC);
		} else { 
			$res=self::$_db->getAll('select * from `'.get_called_class().'` WHERE '.$where, $args,DB_FETCHMODE_ASSOC);
			$classname=null;
		}
		if (PEAR::isError($res)) { 
			throw new Exception($res->getMessage());
			return null;
		}
		$ret=array();
		foreach ($res as $row) { 
			if (isset(self::$_cache[$classname.'-id-'.$row['id']])) { 
				$new=self::$_cache[$classname.'-id-'.$row['id']];
			} else { 
				$new=self::_getNewInstance($classname);
				$new->_lazyLoadMode='col';
				$new->_lazyLoadId=$row['id'];
				$new->_lazyLoadIndexName='id';
			}
			$new->_loadDataRow($res);
			self::$_cache[$classname.'-id-'.$row['id']]=$new;
			$ret[]=$new;
		}
		return $ret;
	}
	private function _setDataVal($key,$val) { 
		if (substr($key,-3,3)=='_id') { 
			$okey=$key;
			$key=substr($key,0,strlen($key)-3);
			$bits=explode(self::$_foreignKeySeparator,$key,2);
			$prefix='';
			$varname=$key;
			if (count($bits)>1) { 
				$prefix=$bits[0];
				$varname=$bits[1];
			}
			if (!is_null($val) && class_exists($varname)) { 
				$new=$varname::get($val);
				if (strlen($prefix)>0) { 
					$this->_data[$prefix.self::$_foreignKeySeparator.$varname]=$new;
				} else { 
					$this->_data[$varname]=$new;
				}
			} else { 
				$this->_data[$okey]=$val;
			}
		} else { 
			$this->_data[$key]=$val;
		}
	}
	private function _loadDataRow($data) { 
		foreach ($data as $key => $val) { 
			$this->_setDataVal($key,$val);
		}
	}
	private function _create() { 
		$ldata=$this->_data;
		unset($ldata['id']);
		$res=self::$_db->autoExecute(get_called_class(),$ldata,DB_AUTOQUERY_INSERT);
		if (PEAR::isError($res)) { 
			throw new Exception($res->getMessage());
			return null;
		}
		return ($this->_data['id']=$this->lastInsertID());
	}
	private function _update() { 
		$ldata=$this->_data;
		$id=$ldata['id'];
		unset($ldata['id']);
		$res=self::$_db->autoExecute(get_called_class(),$ldata,DB_AUTOQUERY_UPDATE, 'id='.$id);
		if (PEAR::isError($res)) { 
			throw new Exception($res->getMessage());
			return null;
		}
		return $res;
	}
	private function _getFieldArray() { 
		return array_keys($this->_data);
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
		if (PEAR::isError($res)) { 
			throw new Exception($res->getMessage());
			return null;
		}
		$this->_setDataVal($colname,$res);
		return $res;
	}
	private function _doGetRow() { 
		$this->_assertLazyLoadSetup();
		$res=self::$_db->getRow('select * from `'.get_called_class().'` WHERE ? = ? LIMIT 1', array($this->_lazyLoadIndexName, $this->_lazyLoadId),DB_FETCHMODE_ASSOC);
		if (PEAR::isError($res)) { 
			throw new Exception($res->getMessage());
			return null;
		}
		$this->_loadDataRow($res);
	}
	static private function _getNewInstance($classname=null) { 
		if (is_null($classname) { 
			$classname=get_called_class();
		}
		if ($classname=='DBSQ') { 
			throw new Exception('You cannot create instances of the DBSQ class');
			return null;
		}
		$new=new $classname;
		return $new;
	}
}
