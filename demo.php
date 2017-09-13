<?php
/*
*配置文件
*/
$_DB['db_mysql']['pconnect'] = true;//设置是否长连接
$_DB['db_mysql']['charset'] = 'utf8';//设置连接编码
$_DB['db_mysql']['server'] = '127.0.0.1';
$_DB['db_mysql']['username'] = 'root';
$_DB['db_mysql']['password'] = '123123';
$_DB['db_mysql']['database_name'] = 'db_test';
$_DB['db_mysql']['database_type'] = 'mysql';
$_DB['db_mysql']['port'] = 3306;
$_DB['db_mysql']['redis_cache'] = true;
$_DB['db_mysql']['redis_cache_time'] = (0.1*60);

$_DB['db_redis']['host'] = '127.0.0.1';
$_DB['db_redis']['password'] = '123123';
$_DB['db_redis']['port'] = 6379;
$_DB['db_redis']['timeout'] = 300;
common_db_pdo_connect();
common_db_redis_connect();

$sql = "select * from orders where id = 1 ";
var_dump($_DB['db']->query($sql,true)->fetch(PDO::FETCH_ASSOC));//单条
var_dump($_DB['db']->log());

$sql = "select * from orders ";
var_dump($_DB['db']->query($sql,true)->fetchAll(PDO::FETCH_ASSOC));//多条
var_dump($_DB['db']->log());

$_DB['db']->current_cache = true;
var_dump($_DB['db']->get('orders',['id','orders_no'],['id'=>'1']));//单条
var_dump($_DB['db']->log());

$_DB['db']->current_cache = true;
var_dump($_DB['db']->select('orders',['id','orders_no']));//多条
var_dump($_DB['db']->log());

function common_db_pdo_connect(){
	global $_DB;
	include_once (ABSPATH . '/include/pdo/my_medoo.php');
	$_DB['db'] = new my_medoo($_DB['db_mysql']);
}
function common_db_redis_connect(){
	global $_DB;
	$_DB['redis'] = new redis();
	$_DB['redis']->connect($_DB['db_redis']['host']);
	$_DB['redis']->auth($_DB['db_redis']['password']);
}