<?php
/* DBSQ - A powerful database model for PHP
 * Version 1.0
 *
 * (c) Copyright Kieran Simkin 2012
 * http://slinq.com/
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met: 
 * 
 *  1. Redistributions of source code must retain the above copyright notice, this
 *     list of conditions and the following disclaimer. 
 *  2. Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution. 
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
require_once 'DB.php';
class DBSQ_Exception extends Exception { }
class DBSQ {
	
	/* These can be overridden in model extension classes: */
	static private $_lazyLoad='row';
	static private $_profileQueryTime=false;
	static private $_foreignKeySeparator='__';

	/* These are internal and shouldn't be fiddled with: */
	static private $_dsn=null;
	static private $_db=null;
	static private $_cache=array();
	static private $_queryTime=0;
	static private $_queryTimeStartPoint=null;
	static private $_classNamePrefix='';
	static private $_classNameSuffix='';
	private $_lazyLoadId=null;
	private $_lazyLoadIndexName=null;
	private $_lazyLoadMode=null;
	private $_data=array();
	private $_localQueryTime=0;

	public function __get($name) { 
		if (array_key_exists($name,$this->_data)) { 
			return $this->_data[$name];
		} else if (substr($name,-3,3)=='_id') { 
			$fieldname=substr($name,0,strlen($name)-3);
			return $this->$fieldname->id;
		} else if ($this->_lazyLoadMode=='row') { 
			$this->_doGetRow();
			$this->_lazyLoadMode='done';
			return $this->_data[$name];
		} else if ($this->_lazyLoadMode=='col') { 
			try { 
				return $this->_doGetCol($name);
			} catch (DBSQ_Exception $e) { 
				$this->_doGetRow();
				$this->_lazyLoadMode='done';
				return $this->_data[$name];
			}
		} else { 
			throw new DBSQ_Exception('Unable to find property: '.$name);
			return null;
		}
	}
	public function rawGet($name) { 
		if (array_key_exists($name,$this->_data)) { 
			return $this->_data[$name];
		} else { 
			return null;
		}
	}
	public function __set($name,$value) { 
		if (is_object($value)) { 
			$this->_data[$name]=$value;
		} else { 
			$this->_setDataVal($name,$value);
		}
	}
	public function __isset($name) { 
		return array_key_exists($name,$this->_data);
	}
	public function __unset($name) { 
		unset($this->_data[$name]);
	}
	public function __toString() { 
		if (array_key_exists('id',$this->_data)) { 
			return (string)$this->_data['id'];
		} else {
			if ($this->_assertLazyLoadSetup(true)) { 
				return $this->__get('id');
			} else { 
				return 'null';
			}
		}
	}
	public function save() { 
		if (func_num_args()==1) { 
			if (func_get_arg(0)===null) { 
				unset($this->_data['id']);
			} else { 
				$this->_data['id']=func_get_arg(0);
			}
		}
		if (!isset($this->_data['id'])) { 
			$ret=$this->_create();
			$this->_lazyLoadId=$ret;
			$this->_lazyLoadIndexName='id';
			$this->_lazyLoadMode='col';
			return $ret;
		} else { 
			$this->_update();
			return $this->_data['id'];
		}
	}
	public function getDataArray() { 
		$ldata=$this->_data;
		$ldata=$this->_convertObjectsToIDs($ldata);
		return $ldata;
	}
	function __construct() { 
		if (self::_getTableName()=='dbsq') { 
			throw new DBSQ_Exception('You cannot create instances of the DBSQ class');
		}
	}
	public function setFromArray($array=array()) {
		foreach ($array as $key => $val) { 
			$this->$key = $val;
		}
	}
	public function setFromFilteredArray($array=array(),$fields=array()) { 
		foreach ($array as $key => $val) { 
			if (in_array($key,$fields)) { 
				$this->$key=$val;
			}
		}
	}
	static public function setClassNamePrefix($prefix) { 
		self::$_classNamePrefix=$prefix;
	}
	static public function setClassNameSuffix($suffix) { 
		self::$_classNameSuffix=$suffix;
	}
	static public function setMySQLCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysql://$username:$password@$host/$database";
		self::_startTime();
		self::$_db=DB::connect(self::$_dsn);
		self::_endTime();
		if (PEAR::isError(self::$_db)) { 
			throw new DBSQ_Exception(self::$_db->getMessage(),self::$_db->getCode());
			return null;
		}
	}
	static public function setMySQLiCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysqli://$username:$password@$host/$database";
		self::_startTime();
		self::$_db=DB::connect(self::$_dsn);
		self::_endTime();
		if (PEAR::isError(self::$_db)) { 
			throw new DBSQ_Exception(self::$_db->getMessage(),self::$_db->getCode());
			return null;
		}
	}
	static public function query($query,$params=array()) { 
		self::_assertDbConnected();
		return self::$_db->query($query,$params);
	}
	static public function getOne($query,$params=array()) { 
		self::_assertDbConnected();
		return self::$_db->getOne($query,$params);
	}
	static public function getRow($query,$params=array(),$fetchmode=DB_FETCHMODE_DEFAULT) { 
		self::_assertDbConnected();
		return self::$_db->getRow($query,$params,$fetchmode);
	}
	static public function getCol($query,$col=0,$params=array()) { 
		self::_assertDbConnected();
		return self::$_db->getCol($query,$col,$params);
	}
	static public function getAssoc($query, $force_array = false, $params = array(), $fetchmode = DB_FETCHMODE_DEFAULT, $group = false) { 
		self::_assertDbConnected();
		return self::$_db->getAssoc($query,$force_array,$params,$fetchmode,$group);
	}
	static public function get($id=null,$uniqueindexname='id',$forcelazy=false,$forceprecache=false) { 
		if (is_null($id)) { 
			return self::_getNewInstance();
		}
		if ($forcelazy && $forcelazy!='row' && $forcelazy!='col') { 
			throw new DBSQ_Exception('forcelazy must be row or col or false');
			return;
		}
		if (isset(self::$_cache[self::_getTableName().'-'.$uniqueindexname.'-'.$id])) { 
			return self::$_cache[self::_getTableName().'-'.$uniqueindexname.'-'.$id];
		}
		$new=self::_getNewInstance();
		$new->_data[$uniqueindexname]=$new->_lazyLoadId=$id;
		$new->_lazyLoadIndexName=$uniqueindexname;
		$new->_lazyLoadMode='done';
		if ((self::$_lazyLoad || $forcelazy) && !$forceprecache) { 
			if ($forcelazy) { 
				$new->_lazyLoadMode=$forcelazy;
			} else { 
				$new->_lazyLoadMode=self::$_lazyLoad;
			}
			self::$_cache[self::_getTableName().'-'.$uniqueindexname.'-'.$id]=$new;
			return $new;
		}
		if ($forcelazy=='col') { 
			$new->_lazyLoadMode='col';
			$new->_doGetCol($uniqueindexname);
		} else { 
			$new->_doGetRow();
		}
		self::$_cache[self::_getTableName().'-'.$uniqueindexname.'-'.$id]=$new;
		return $new;
	}
	// This will need customizing for non-MySQL DBs:
	static public function lastInsertID() { 
		self::_startTime();
		$res=self::getOne('select last_insert_id()');
		self::_endTime();
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage(),$res->getCode());
			return null;
		}
		return $res;
	}
	static public function foundRows() { 
		self::_startTime();
		$res=self::getOne('select found_rows()');
		self::_endTime();
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage(),$res->getCode());
			return null;
		}
		return $res;
	}
	static public function affectedRows() { 
		self::_assertDbConnected();
		self::_startTime();
		$res=self::$_db->affectedRows();
		self::_endTime();
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage(),$res->getCode());
			return null;
		}
		return $res;
	}
	static public function rawGetAll($query,$args=array(),$fetchmode=DB_FETCHMODE_ASSOC) { 
		self::_assertDbConnected();
		return self::$_db->getAll($query,$args,$fetchmode);
	}
	static public function getAll($where="1 = 1",$args=array(),$classname=null,$suffix='',$nocalcfoundrows=false) { 
		self::_assertDbConnected();
		self::_startTime();
		if (self::_getTableName()=='dbsq' && !is_null($classname)) { 
			$classname=self::_getTableName($classname);
			$res=self::$_db->getAll($where.' '.$suffix, $args,DB_FETCHMODE_ASSOC);
		} else { 
			if (!$nocalcfoundrows) { 
				$res=self::$_db->getAll('select sql_calc_found_rows * from `'.self::_getTableName().'` WHERE '.$where.' '.$suffix, $args,DB_FETCHMODE_ASSOC);
			} else { 
				$res=self::$_db->getAll('select * from `'.self::_getTableName().'` WHERE '.$where.' '.$suffix, $args,DB_FETCHMODE_ASSOC);
			}
			$classname=self::_getTableName();
		}
		self::_endTime($classname);
		if (is_null($res)) { 
			throw new DBSQ_Exception('No results');
			return null;
		}
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage(),$res->getCode());
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
			$new->_loadDataRow($row);
			self::$_cache[$classname.'-id-'.$row['id']]=$new;
			$ret[]=$new;
		}
		return $ret;
	}
	static public function create($data) { 
		$new=self::_getNewInstance();
		foreach ($data as $key => $val) { 
			$new->$key=$val;
		}
		$id=$new->_create();
		$new->_data['id']=$new->_lazyLoadId=$id;
		$new->_lazyLoadIndexName='id';
		$new->_lazyLoadMode='col';
		return $new;
	}
	public function _get_lazyLoadId() { 
		return $this->_lazyLoadId;	
	}
	public function _get_lazyLoadIndexName() { 
		return $this->_lazyLoadIndexName;
	}
	private function _setDataVal($key,$val) { 
		if (is_object($val)) { 
			$val=(string)$val;
		}
		if (substr($key,-3,3)=='_id') { 
			$okey=$key;
			$key=substr($key,0,strlen($key)-3);
			$bits=explode(self::$_foreignKeySeparator,$key,2);
			$prefix='';
			$varname=ucfirst($key);
			if (count($bits)>1) { 
				$prefix=$bits[0];
				$varname=ucfirst($bits[1]);
			}
			if (!is_null($val)) { 
				try { 
					$new=$varname::get($val,'id','col');
					if (strlen($prefix)>0) { 
						$this->_data[$prefix.self::$_foreignKeySeparator.strtolower($varname)]=$new;
						unset($this->_data[$prefix.self::$_foreignKeySeparator.strtolower($varname).'_id']);
					} else { 
						$this->_data[strtolower($varname)]=$new;
						unset($this->_data[strtolower($varname).'_id']);
					}
				} catch (Exception $e) { 
					$this->_data[$okey]=$val;
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
		self::_assertDbConnected();
		$ldata=$this->_data;
		unset($ldata['id']);
		$ldata=$this->_convertObjectsToIDs($ldata);
		self::_startTime();
		$res=self::$_db->autoExecute(self::_getTableName(),$ldata,DB_AUTOQUERY_INSERT);
		self::_endTime($this);
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage(),$res->getCode());
			return null;
		}
		return ($this->_data['id']=$this->lastInsertID());
	}
	private function _update() { 
		self::_assertDbConnected();
		$ldata=$this->_data;
		$id=$ldata['id'];
		unset($ldata['id']);
		$ldata=$this->_convertObjectsToIDs($ldata);
		self::_startTime();
		$res=self::$_db->autoExecute(self::_getTableName(),$ldata,DB_AUTOQUERY_UPDATE, 'id='.$id);
		self::_endTime($this);
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage(),$res->getCode());
			return null;
		}
		return $res;
	}
	private function _getFieldArray() { 
		return array_keys($this->_data);
	}
	static private function _assertDbConnected() { 
		if (is_null(self::$_db)) { 
			throw new DBSQ_Exception('DB not connected');
		}
	}
	private function _assertLazyLoadSetup($nothrow=false) { 
		if (is_null($this->_lazyLoadId) || is_null($this->_lazyLoadIndexName) || is_null($this->_lazyLoadMode)) { 
			if (!$nothrow) { 
				throw new DBSQ_Exception('You need to load the object before you can read from it!');
			}
			return false;
		} else { 
			return true;
		}
	}
	private function _doGetCol($colname) { 
		$this->_assertLazyLoadSetup();
		self::_startTime();
		$res=self::getOne('select ! from `'.self::_getTableName().'` WHERE ! = ? LIMIT 1', array($colname, $this->_lazyLoadIndexName, $this->_lazyLoadId));
		self::_endTime($this);
		if (is_null($res)) { 
			throw new DBSQ_Exception('Unable to lookup column: '.$colname);
			return null;
		}
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage(),$res->getCode());
			return null;
		}
		$this->_setDataVal($colname,$res);
		return $res;
	}
	private function _doGetRow() { 
		$this->_assertLazyLoadSetup();
		self::_startTime();
		$res=self::getRow('select * from `'.self::_getTableName().'` WHERE ! = ? LIMIT 1', array($this->_lazyLoadIndexName, $this->_lazyLoadId),DB_FETCHMODE_ASSOC);
		self::_endTime($this);
		if (is_null($res)) { 
			throw new DBSQ_Exception('Unable to lookup row');
			return null;
		}
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage(),$res->getCode());
			return null;
		}
		$this->_loadDataRow($res);
	}
	static private function _getNewInstance($classname=null) { 
		if (is_null($classname)) { 
			$classname=self::$classNamePrefix.self::_getTableName().self::$classNameSuffix;
		} else { 
			$classname=self::$classNamePrefix.$classname.self::$classNameSuffix;
		}
		if ($classname=='dbsq') { 
			throw new DBSQ_Exception('You cannot create instances of the DBSQ class');
			return null;
		}
		$new=new $classname;
		return $new;
	}
	static private function _startTime() { 
		if (self::$_profileQueryTime) { 
			self::$_queryTimeStartPoint=microtime();
		}
	}
	static private function _endTime($o=null) { 
		if (self::$_profileQueryTime) { 
			if (is_null(self::$_queryTimeStartPoint)) { 
				throw new DBSQ_Exception('Can\'t end time before we\'ve started it');
				return;
			}
			if (is_object($o)) { 
				// $o is $this
			} else if (is_string($o)) { 
				// $o is a class name
			} else { 
				// $o is null
			}
			self::$_queryTime+=microtime()-self::$_queryTimeStartPoint;
		}
	}
	static private function _convertObjectsToIDs($data) { 
		foreach ($data as $key => &$val) { 
			if (is_object($val)) { 
				$data[$key.'_id']=(string)$val;
				unset($data[$key]);
			}
		}
		return $data;
	}
	static protected function _getTableName($classname=null) { 
		if (is_null($classname)) { 
			$classname=get_called_class();
		}
		if (strlen(self::$classNamePrefix)>0 && substr($classname,0,strlen(self::$classNamePrefix))==$classNamePrefix) { 
			return strtolower(substr($classname,strlen(self::$classNamePrefix)));	
		} else { 
			return strtolower($classname);
		}
	}
}
