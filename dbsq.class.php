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
	private $_lazyLoadId=null;
	private $_lazyLoadIndexName=null;
	private $_lazyLoadMode=null;
	private $_data=array();
	private $_localQueryTime=0;

	public function __get($name) { 
		if (isset($this->_data[$name])) { 
			return $this->_data[$name];
		} else if (substr($name,-3,3)=='_id') { 
			return $this->_data[substr($name,0,strlen($name)-3)];	
		} else if ($this->_lazyLoadMode=='row') { 
			$this->_doGetRow();
			$new->_lazyLoadMode='done';
			return $this->_data[$name];
		} else if ($this->_lazyLoadMode=='col') { 
			return $this->_doGetCol($name);
		} else { 
			throw new DBSQ_Exception('Unable to find property: '.$name);
			return null;
		}
	}
	public function __set($name,$value) { 
		$this->_setDataVal($name,$value);
	}
	public function __isset($name) { 
		return isset($this->_data[$name]);
	}
	public function __unset($name) { 
		unset($this->_data[$name]);
	}
	public function __toString() { 
		if (isset($this->_data['id'])) { 
			return (string)$this->_data['id'];
		} else {
			return (string)'null';
		}
	}
	function __construct() { 
		if (get_called_class()=='DBSQ') { 
			throw new DBSQ_Exception('You cannot create instances of the DBSQ class');
		}
	}
	static public function setMySQLCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysql://$username:$password@$host/$database";
		self::_startTime();
		self::$_db=DB::connect(self::$_dsn);
		self::_endTime();
		if (PEAR::isError(self::$_db)) { 
			throw new DBSQ_Exception(self::$_db->getMessage());
			return null;
		}
	}
	static public function setMySQLiCredentials($username,$password,$database,$host='localhost') { 
		self::$_dsn="mysqli://$username:$password@$host/$database";
		self::_startTime();
		self::$_db=DB::connect(self::$_dsn);
		self::_endTime();
		if (PEAR::isError(self::$_db)) { 
			throw new DBSQ_Exception(self::$_db->getMessage());
			return null;
		}
	}
	static public function get($id=null,$uniqueindexname='id',$forcelazy=false,$forceprecache=false) { 
		if (is_null($id)) { 
			return self::_getNewInstance();
		}
		if ($forcelazy && $forcelazy!='row' && $forcelazy!='col') { 
			throw new DBSQ_Exception('forcelazy must be row or col');
			return;
		}
		if (isset(self::$_cache[get_called_class().'-'.$uniqueindexname.'-'.$id])) { 
			return self::$_cache[get_called_class().'-'.$uniqueindexname.'-'.$id];
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
				unset($this->_data['id']);
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
	static public function lastInsertID() { 
		self::_startTime();
		$res=self::$_db->getOne('select last_insert_id()');
		self::_endTime();
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage());
			return null;
		}
		return $res;
	}
	static public function affectedRows() { 
		self::_startTime();
		$res=self::$_db->affectedRows();
		self::_endTime();
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage());
			return null;
		}
		return $res;
	}
	static public function getAll($where="1 = 1",$args=array(),$classname=null) { 
		self::_startTime();
		if (get_called_class()=='DBSQ' && !is_null($classname)) { 
			$res=self::$_db->getAll($where, $args,DB_FETCHMODE_ASSOC);
		} else { 
			$res=self::$_db->getAll('select * from `'.get_called_class().'` WHERE '.$where, $args,DB_FETCHMODE_ASSOC);
			$classname=get_called_class();
		}
		self::_endTime($classname);
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage());
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
		if (is_object($val)) { 
			$val=(string)$val;
		}
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
		$ldata=$this->_convertObjectsToIDs($ldata);
		self::_startTime();
		$res=self::$_db->autoExecute(get_called_class(),$ldata,DB_AUTOQUERY_INSERT);
		self::_endTime($this);
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage());
			return null;
		}
		return ($this->_data['id']=$this->lastInsertID());
	}
	private function _update() { 
		$ldata=$this->_data;
		$id=$ldata['id'];
		unset($ldata['id']);
		$ldata=$this->_convertObjectsToIDs($ldata);
		self::_startTime();
		$res=self::$_db->autoExecute(get_called_class(),$ldata,DB_AUTOQUERY_UPDATE, 'id='.$id);
		self::_endTime($this);
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage());
			return null;
		}
		return $res;
	}
	private function _getFieldArray() { 
		return array_keys($this->_data);
	}
	private function _assertLazyLoadSetup() { 
		if (is_null($this->_lazyLoadId) || is_null($this->_lazyLoadIndexName) || is_null($this->_lazyLoadMode)) { 
			throw new DBSQ_Exception('You need to load the object before you can read from it!');
			return;
		}
	}
	private function _doGetCol($colname) { 
		$this->_assertLazyLoadSetup();
		self::_startTime();
		$res=self::$_db->getOne('select ! from `'.get_called_class().'` WHERE ! = ?', array($colname, $this->_lazyLoadIndexName, $this->_lazyLoadId));
		self::_endTime($this);
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage());
			return null;
		}
		$this->_setDataVal($colname,$res);
		return $res;
	}
	private function _doGetRow() { 
		$this->_assertLazyLoadSetup();
		self::_startTime();
		$res=self::$_db->getRow('select * from `'.get_called_class().'` WHERE ! = ?', array($this->_lazyLoadIndexName, $this->_lazyLoadId),DB_FETCHMODE_ASSOC);
		self::_endTime($this);
		if (PEAR::isError($res)) { 
			throw new DBSQ_Exception($res->getMessage());
			return null;
		}
		$this->_loadDataRow($res);
	}
	static private function _getNewInstance($classname=null) { 
		if (is_null($classname)) { 
			$classname=get_called_class();
		}
		if ($classname=='DBSQ') { 
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
}
