<?php
class Database{
	const dsn_prefix = "mysql:";//can be others
	const host = "Set the hostname where the DB is stored here";//localhost
	const port = 3306;
	const db_name = "Set My database name here";
	const user = "Set My Username here";
	const pass = "Set My Password here";
	const persitent_connection = FALSE;
	const log_activity = TRUE;

	private $full_dsn = NULL;
	private $options = NULL;
	private $currentStatement = NULL;

	private $dbo = NULL;//Database connection object
	private static $instance = NULL;

	public function __construct(){
		/*
		if(self::log_activity==TRUE)
			$this->log_object = new Logs("database");
		*/
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
		}//try
		catch (PDOException $e)
		{
			return 'PDO Connection failed: ' . $e->getMessage();
		}//catch
		return TRUE;
	}
	public function FetchOne($query,$params=NULL,$fetch_mode=PDO::FETCH_NAMED){
		$result = NULL;
		try{
			$this->currentStatement = $this->dbo->prepare($query);
			if(is_array($params))
				$this->currentStatement->execute($params);
			else
				$this->currentStatement->execute();
			$result = $this->currentStatement->fetch($fetch_mode);
		}//try
		catch(PDOException $ex){echo "An Error occured!";}//catch
		return $result;
	}
	public function FetchAll($query,$params=NULL,$fetch_mode=PDO::FETCH_NAMED){
		$result = NULL;
		try{
			$this->currentStatement = $this->dbo->prepare($query);
			if(is_array($params))
				$this->currentStatement->execute($params);
			else
				$this->currentStatement->execute();

			$result = $this->currentStatement->fetchAll($fetch_mode);
		}//try
		catch(PDOException $ex){echo "An Error occured!";}//catch
		return $result;
	}
	public function Execute($query,$params=NULL){
		$this->currentStatement = $this->dbo->prepare($query);
		if(is_array($params))
			$this->currentStatement->execute($params);
		else
			$this->currentStatement->execute();
		return $this->currentStatement;
	}
	public function Rows(){
		return $this->currentStatement->rowCount();
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
		return $this->currentStatement->columnCount();
	}
	public function FetchColumn($query,$columnNumber=0){
		$this->currentStatement = $this->dbo->prepare($query);
		$this->currentStatement->execute();

		$result = $stmt->fetchColumn($columnNumber);
		return $result;
	}
	public function LastInsertId(){
		return $this->currentStatement->lastInsertId();
	}
	public static function GetInstance()
	{
		if (self::$instance === null)//don't check connection, check instance
		{
			self::$instance = new Database();
		}
		return self::$instance;
	}
}

//Usage:
//$val = Database::GetInstance()->FetchOne($query);
//echo $val["colomn_name"];
//$result = Database::GetInstance()->FetchAll($query);
//foreach($result as $val){
//	echo $val["colomn_name"];
//}
//etc...

?>