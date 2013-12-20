<?php
class Database{
	const dsn_prefix = "mysql:";
	const host = "localhost";
	const port = 3306;
	const db_name = "your_DB";
	const user = "you_user_id";
	const pass = "you_password";
	const persitent_connection = FALSE;
	const log_activity = TRUE;

	private $log_object = NULL;
	private $full_dsn = NULL;
	private $options = NULL;
	private $currentStatement = NULL;

	private $dbo = NULL;//Database connection object
	private static $instance = NULL;

	public function __construct(){
		$connection_state = FALSE;
		$this->full_dsn = self::dsn_prefix."host=".self::host.";port=".self::port.";dbname=".self::db_name;
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
		catch (PDOException $e)
		{
			echo  'PDO Connection failed: ' . $e->getMessage();
			$connection_state = FALSE;
		}//catch
		return $connection_state;
	}
	public function FetchOne($query,$params=NULL,$fetch_mode=PDO::FETCH_NAMED){
		$result = NULL;
		try{
			$this->Execute($query,$params);
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
		catch(PDOException $ex){
			echo "PDO FetchAll Error ".$ex->getMessage();
		}//catch
		return $result;
	}
	public function Execute($query,$params=NULL){
		try{
			$this->currentStatement = $this->dbo->prepare($query);
			if(is_array($params))
				$this->currentStatement->execute($params);
			else
				$this->currentStatement->execute();
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
		if (self::$instance === null)//don't check connection, check instance
		{
			self::$instance = new Database();
		}
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
		//$this->dbo->("", $value)
		$this->Execute("SAVEPOINT :savepointname", array(':savepointname',$savepoint_name));
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
			$this->Execute("ROLLBACK TO SAVEPOINT :savepointname". array(':savepointname',$savepoint));
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
}

-//Usage:
-//$val = Database::GetInstance()->FetchOne($query);
-//echo $val["colomn_name"];
-//$result = Database::GetInstance()->FetchAll($query);
-//foreach($result as $val){
-//	echo $val["colomn_name"];
-//}
-//etc...
?>