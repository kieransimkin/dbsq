<?php
require_once '../../dbsq.class.php';
function __autoload($class) { 
	require_once 'models/'.$class.'.model.php';
}
DBSQ::setMySQLCredentials('root','','test');
$user=user::get(1);
$userfile=user_file::get();
$userfile->user_id=$user;

