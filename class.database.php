<?php
class Database{
	const dsn_prefix = "mysql:";//The database to use (can be something else than mysql)
	const host = "localhost";//The Database host
	const port = 3306;//The default port of your Database (for MySQL, it's 3306)
	const db_name = "your_Database_Name";//The Database name
	const user = "your_user_id";//The user name used to connect to the Database
	const pass = "your_password";//The password used to connect to the Database
	const persitent_connection = FALSE;
	const log_activity = TRUE;

	const STATEMENT = 'statement';
	const SQL = 'sql';
	const WHERE_CLAUSE = 'where_clause';
	const PARAM_ARRAY = 'params';

	private $log_object = NULL;
	private $full_dsn = NULL;
	private $options = NULL;
	private $currentStatement = NULL;
	private $params = NULL;

	private $dbo = NULL;//Database connection object
	private static $instance = NULL;

	public function __construct(){
		$connection_state = FALSE;
		$this->full_dsn = self::dsn_prefix.'host='.self::host.';port='.self::port.';dbname='.self::db_name;
		$this->options = array(
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
			,PDO::ATTR_PERSISTENT => self::persitent_connection
			,PDO::ATTR_EMULATE_PREPARES => FALSE
			,PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
			);
		try
		{
			$this->dbo = new PDO($this->full_dsn, self::user, self::pass, $this->options);
			$connection_state = TRUE;
		}//try
		catch (PDOException $pdo_ex)
		{
			echo  'PDO Connection failed: ' . $pdo_ex->getMessage();
			$connection_state = FALSE;
		}//catch
		return $connection_state;
	}
	public function FetchOne($query, $params=NULL, $fetch_mode=PDO::FETCH_NAMED){
		$result = NULL;
		try{
			$this->Execute($query, $params);
			$result = $this->currentStatement->fetch($fetch_mode);
		}//try
		catch(PDOException $ex){
			echo "PDO FetchOne Error : ".$ex->getMessage();
		}//catch
		return $result;
	}
	public function FetchAll($query,$params=NULL,$fetch_mode=PDO::FETCH_NAMED){
		$result = NULL;
		try{
			$this->Execute($query, $params);
			$result = $this->currentStatement->fetchAll($fetch_mode);
		}//try
		catch(PDOException $pdo_ex){
			echo "PDO FetchAll Error ".$pdo_ex->getMessage();
		}//catch
		catch(Exception $ex){
			echo 'PDO FetchAll Exception '.$ex->getMessage();
		}
		return $result;
	}
	public function Execute($query,$params=NULL){
		try{
			$this->currentStatement = $this->dbo->prepare($query);
			if(is_array($params)){
				$this->ResetParameters();
				$this->currentStatement->execute($params);
			}//if
			else
				$this->currentStatement->execute($this->params);
		}//try
		catch(PDOException $ex){
			echo "PDO Execute Error : ".$ex->getMessage();
		}
		return $this->currentStatement;
	}
	public function Rows(){
		return $this->currentStatement->rowCount();
	}
	public static function RowCountQuick($query){
		$db = new Database();
		return $db->Rows();
	}
	public static function CountResultsQuick($result){
		return count($result);
	}
	public function NumRows(){
		return $this->Rows();
	}
	public function RowCount(){
		return $this->Rows();
	}
	public function ColumnCount(){
		return $this->Columns();
	}
	public function Columns(){
		$colcount = $this->currentStatement->columnCount();
		return $colcount;
	}
	public function FetchColumn($query,$columnNumber=0){
		$this->currentStatement = $this->dbo->prepare($query);
		$this->currentStatement->execute();

		$result = $stmt->fetchColumn($columnNumber);
		return $result;
	}
	public static function GetInstance()
	{
		//don't check connection, check instance
		if (self::$instance === null){
			self::$instance = new Database();
		}//if
		return self::$instance;
	}
	public function LastInsertId(){
		return $this->currentStatement->lastInsertId();
	}
	public function GetDatabaseName(){
		return self::db_name;
	}
	public function TransactionBegin($savepoint=NULL){
		try{
			$this->dbo->beginTransaction();
		}//try
		catch(PDOException $ex){
			echo "Commit error : ".$ex->getMessage();
		}//catch
		if(!empty($savepoint)){
			$this->TransactionSavepoint($savepoint);
		}//if
	}
	public function TransactionSavepoint($savepoint_name){
		$this->Execute('SAVEPOINT :savepointname', array(':savepointname',$savepoint_name));
	}
	public function TransactionCommit(){
		try{
			$this->dbo->commit();
		}//try
		catch(PDOException $ex){
			echo "Commit error : ".$ex->getMessage();
		}//catch
	}
	public function TransactionRollback($savepoint=NULL){
		if(!empty($savepoint)){
			$this->Execute('ROLLBACK TO SAVEPOINT :savepointname', array(':savepointname',$savepoint));
		}//else
		else{
			try{
				$this->dbo->rollBack();
			}//try
			catch(PDOException $ex){
				echo "Rollback error : ".$ex->getMessage();
			}//catch
		}//else
	}
	public function ResetParameters(){
		$this->params = NULL;
	}
	public function AddParameter($field_name, $field_value, $value_type=PDO::PARAM_STR){
		if(!is_null($field_value) || $field_value!=''){
			$this->params []= array(':'.$field_name => $field_value);
		}//if
	}//AddParameter
	/*
	 * Gets the SQL query part of each parameterizes field
	 * @example : field1=:field1 AND field2=:field2
	 * @return string sql string to be used in WHERE clause
	 */
	public function GetParamsForQuery(){
		$sql = NULL;
		if(is_array($this->params)){
			$i = 0;
			$nb_param = count($this->params);
			foreach($this->params as $field => $val){
				$sql .= ' '.$field.'='.substr($field,1,strlen($field));
				if($i>=0 && $i<$nb_param && $nb_param>1){
					$sql .= ' AND ';
				}//if
				$i++;
			}//foreach
		}//if
		return $sql;
	}//GetParamsForQuery
	public function InSqlStatement($field_prefix, $array_simple_values=array()){
		//WHERE 1
		//AND field_name IN (:field_prefix_1, :field_prefix2, ...)
		$statement = NULL;
		$param_array = array();
		$elements = count($array_simple_values);

		for($i=0;$i<$elements;$i++){
			$field = ':'.$field_prefix.$i;//field
			$value = $array_simple_values[$i];//value

			$current = array($field => SqlPreventInjection($value));//param

			$param_array = array_merge($param_array, $current);//:my_field => $value 
			$statement .= $field.',';//:my_field
		}//for

		$statement = rtrim($statement,',');

		$return = array(self::STATEMENT => $statement, self::PARAM_ARRAY => $param_array);
		return $return;
	}
	public function InSqlGenerateArray($field, $array_simple_values=array()){
		$nb_elements = count($array_simple_values);
		$array_to_return = NULL;
		if($nb_elements>0){
			$array_to_return = Database::GetInstance()->InSqlStatement($field, $array_simple_values);
		}//if
		return $array_to_return;
	}
	public function InSqlGenerateParam($current_params_array=NULL, $statement_param_array=NULL){
		$array_to_return = NULL;
		if(is_array($statement_param_array) &&
			isset($statement_param_array[self::STATEMENT]) &&
			isset($statement_param_array[self::PARAM_ARRAY]))
		{
			$array_to_return = array_merge($current_params_array, $statement_param_array[self::PARAM_ARRAY]);
		}//if
		return $array_to_return;
	}
	public function GenerateSqlFieldEqualsValue($field, $value=NULL){
		$return = NULL;
		if(!empty($value)){
			$return = array(
				self::SQL => ' AND '.$field.'=:'.$field,
				self::PARAM_ARRAY => array(':'.$field => SqlPreventInjection($value))
			);
		}//if
		return $return;
	}
	public function GenerateSqlFieldInValues($field, $array_values=NULL){
		
		$return = NULL;
		if(!empty($array_values) && is_array($array_values)){
			$statement_and_params = $this->InSqlGenerateArray($field, $array_values);
			$return = array(
					self::SQL => ' AND '.$field.' IN ('.$statement_and_params[self::STATEMENT].')',
					self::PARAM_ARRAY => $statement_and_params[self::PARAM_ARRAY]
			);
		}//if
		return $return;
	}
	public function GenerateSqlFieldMoreOrEqualsThanValue($field, $value=NULL){
		$return = NULL;
		if(!empty($value)){
			$return = array(
					self::SQL => ' AND '.$field.'>=:'.$field,
					self::PARAM_ARRAY => array(':'.$field => SqlPreventInjection($value))
			);
		}//if
		return $return;
	}
	public function GenerateSqlFieldLessOrEqualsThanValue($field, $value=NULL){
		$return = NULL;
		if(!empty($value)){
			$return = array(
					self::SQL => ' AND '.$field.'<=:'.$field,
					self::PARAM_ARRAY => array(':'.$field => SqlPreventInjection($value))
			);
		}//if
		return $return;
	}
	public function GenerateSqlFieldBetweenValues($field, $min=NULL, $max=NULL){
		$return = NULL;
		if(!empty($min) || !empty($max)){
			if(!empty($min) && empty($max)){
				$max = $min;
			}//if
			elseif(empty($min) && !empty($max)){
				$min = $max;
			}//elseif
			$return = array(
				//self::SQL => ' AND ('.$field.'>=:'.$field.'_min AND '.$field.'<=:'.$field.'_max)',
				self::SQL => ' AND ('.$field.' BETWEEN :'.$field.'_min AND :'.$field.'_max)',
				self::PARAM_ARRAY => array(
					':'.$field.'_min' => SqlPreventInjection($min)
					,':'.$field.'_max' => SqlPreventInjection($max)
				)
			);
		}//if
		return $return;
	}
	public function MergeQueryAndParams($query, $params, $statements_and_params){
		foreach($statements_and_params as $field => $array){
			if(isset($array[self::SQL]) && is_array($array[self::PARAM_ARRAY])){
				$query .= $array[self::SQL]."\r\n";
				$params = array_merge($params, $array[self::PARAM_ARRAY]);
			}//if
		}//foreach
		return array(self::SQL => $query, self::PARAM_ARRAY => $params);
	}
}
-//Usage:
-//$val = Database::GetInstance()->FetchOne($query);
-//echo $val["colomn_name"];
-//$result = Database::GetInstance()->FetchAll($query);
-//foreach($result as $val){
-//	echo $val["colomn_name"];
-//}
-//etc...