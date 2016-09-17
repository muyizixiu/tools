<?php
/*
*mysql tools for comparing two mysql database,exporting sql to build tables;
*
*@author muyizixiu
*@version 1.0
*
*/

class mysqlTool{
	private const $column_key = array("UNI"=>"unique","PRI"=>"primary");

	/*
	 * @param array $host array("host"=>"127.0.0.1","port"=>"3600","user"=>"root","passwd"=>"","db"=>"");
	 * 
	 * @return array array(0=>array("table_name"=>"test","engine"=>"innodb","table_comment"=>"it is a test table")) 
	*
	*
	*/
	public function getTableInfoFromDB($host){
		$mysql = $this->connect($host);
		$table = $this->getAll($mysql,"select table_name,engine,table_comment from information_schema.tables where table_schema = '{$host['db']}'");
		$mysql->close();
		return $table;
	}

	/*
	 * get column info from a table
	 *
	 * @
	 */
	public function getColumnInfoFromTable($host,$tablename){
		$mysql = $this->connect($host);
		$data = $this->getAll($mysql,"select column_name,column_default,is_nullable,column_type,extra,column_comment from information_schema.columns where table_schema='{$host['db']}' and table_name = '$tablename'");
		$statistic = $this->getAll($mysql,"select non_unique,index_name,seq_in_index,column_name,column_key,comment from information_schema.table_statistics where table_schema = '{$host['db']}' and table_name = {$tablename}";
		$mysql->close();
		return array("column"=>$data,"index"=>$statistic);
	}

	public function getSqlToCreateTable(array $table,$dbname,$tableinfo){
		if(!empty($lackedKeys = this->getArrayLackedKey(array("index","column"),$table)){
			throw new Exception("illgal argument,lack of ".implode(",",$lackedKeys));
		}
		if(!empty($lackedKeys = $this->getArrayLackedKey(array("column_name","column_default","is_nullable","column_type","extra","column_comment "),$table["column"]))){
			throw new Exception("illgal argument,lack of ".implode(",",$lackedKeys));
		}
		if(!empty($lackedKeys = $this->getArrayLackedKey(array("column_key","index_name","seq_in_index","column_name","comment"),$table["index"]))){
			throw new Exception("illgal argument,lack of ".implode(",",$lackedKeys));
		}
		if(!empty($lackedKeys = $this->getArrayLackedKey(array("table_name","engine","table_comment"),$tableinfo))){
			throw new Exception("illgal argument,lack of ".implode(",",$lackedKeys));
		}
		$columnSql = array();
		foreach($table["column"]) as $v){
			$str = $v["column_name"]." ".$v["column_type"].($v["is_nullable"] ? " " :"not null ").($v["extra"] ? $v["extra"] : " ")."comment '".$v["column_comment"]."'";
			$columnSql[] = $str;
		}
		$indexSql = array();
		foreach($table["index"] as $v)){
			$indexSql[$v["index_name"]] = $v;
			$indexSql[$v["index_name"]]["key"][$v["seq_in_index"]-1] = $v['column_name'];
		}
		foreach($indexSql as $k => $v){
			$indexSql[$k] = ($v["column_key"] ? $self::$column_key[$v["column_key"]] : "")." key ( ".implode(",",$v["key"]).")";
		}
		$sql = "create table {$dbname}.{$tableinfo['table_name']}(".implode(",\n",array_merge($columnSql,$indexSql)).")engine {$tableinfo['engine']} comment {$tableinfo['table_comment']};";
		return $sql;
	}
	/*
	 * connect to mysql server and return mysqli object
	 *
	 * @param array $host array("host"=>"127.0.0.1","port"=>"3600","user"=>"root","passwd"=>"","db"=>"");
	 * @return mysqli
	 */
	private function connect($host){
		$mysql = new mysqli($host["host"],$host["user"],$host["passwd"],"",$host["port"]);
		if($err = $mysql->connect_error()){
			throw new Exception($err);
		}
		return $mysql;
	}
	/*
 	 * get all result from a query
	 *
	 * @param mysqli $mysql
	 * @param string $sql
	 * @return array
	 */
	private function  getAll(mysqli $mysql,$sql){
		$result = $mysql->query($sql);
		$mysql_result = $mysql->use_result();
		$data = $mysql_result->fetch_all();
		return $data;
	}
	/*
	 * compare two array by one associated
	 *
	 * @param array $arr0
	 * @param array $arr1
	 * @return array
	 */
	private function diff($arr0,$arr1,$column){
		$arr=array(array(),array());
		foreach($arr0 as $v){
			$arr[0][$v[$column]] = $v;
		}
		foreach($arr1 as $v){
			$arr[1][$v[$column]] = $v;
		}
		$intersect = array_intersect_key($arr[0],$arr[1]);
		return array(array_diff_key($arr[0],$intersect),array_diff_key($arr[1],$intersect));
	}

	/*
 	 * check if  a array have all the keys against $column;
	 *
	 * @param array $column keys to check
	 * @param array $arr array to be checked
	 * @return array return keys of lacked by the array ,empty means that a array has all needed keys
	 */
	private function getArrayLackedKey(array $column,array $arr){
		$keys = array_keys($arr);
		return array_diff($keys,$column);
	}

	/*
	 * compare two database`s tables
	 *
	 * @param array $host0
	 * @param array $host1
	 * @dbname string $dbname
	 * @return array table`s difference between two database
	 */
	public function diffDB($host0,$host1,$dbname){
		$host1["db"] = $host0["db"] = $dbname;
		$table0 = $this->getTableInfoFromDB($host0);
		$table1 = $this->getTableInfoFromDB($host1);
		return $this->diff($table0,$table1,"table_name");
	}
}


$dbname = "";
$host0 = array();
$host0["dbname"] = $dbname;
$tool = new mysqlTool();
$dbTable = $tool->diffDB($host0,array(),$dbname);
foreach($dbTable[0]) as $v){
	$table = $tool->getTableInfoFromDB($host0);
	$tool->getSqlToCreateTable($table,$dbname,$v);
}
