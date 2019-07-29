<?php

/**
 * Display the last registered users with a few properties like his homepage,
 * his biography, ...
 *
 * Exclude banned and miserable users.
 *
 * With this list, it becomes easier to see recent users and those with spammed 
 * links, promotional biography, ...
 *
 * User profile link is provided so it's easy to see the profile of the user and,
 * then, ban it if needed
 */

namespace Avonture;

ini_set('display_errors', '1');

date_default_timezone_set('Europe/Paris');

define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));

define('FORUM_URL', 'https://forum.joomla.fr');

// group id of banned users
define('BANNED_USER_GROUP', 8);

// group id of miserable users
define('MISERABLE_USER_GROUP', 9);

if (!defined('VB_ENTRY')) {
    define('VB_ENTRY', 1);
}

class vBulletin
{
	static private $config = [];
	static private $db = null;
	
	public function __construct() {		
	
		self::$config = self::getConfig();
		
	}
	
	/**
	 * Get the configuration of the current vBulletin installation
	 */
	private function getConfig(): array
	{		
		self::$config = [];
		
		require_once(CWD . '/core/includes/config.php');
		
		$vbulletin = new \stdClass();
		$vbulletin->config = $config;
		unset($vbulletin);
		
		return $config;
	}
	
	/**
	 * Initialize the database connection
	 */
	private function initDatabase() {
		
		if (null !== self::$db) {
			return true;
		}
			
		// Retrieve credentials needed to establish a data connection
		$dbServer = self::$config['MasterServer']['servername'];
		$dbName   = self::$config['Database']['dbname'];
		$dbUser   = self::$config['MasterServer']['username'];
		$dbPasswd = self::$config['MasterServer']['password'];

		$dbConnection = [
			'dsn'      => "mysql:host=$dbServer;dbname=$dbName;port=3306;charset=utf8",
			'host'     => $dbserver,
			'port'     => '3306',
			'dbname'   => $dbName,
			'username' => $dbUser,
			'password' => $dbPasswd,
			'charset'  => 'utf8',
		];
		
		$options   = [
			\PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		];
					
		try
		{
			self::$db = new \PDO(
				$dbConnection['dsn'], 
				$dbConnection['username'], 
				$dbConnection['password'], 
				$options);
				
			
		} catch (\PDOException $e) {
			
			die('fail connect mysql - ' . $e->getMessage());
			
		}
		
		return;
		
	}
	
	/**
	 * Run a query, return a recordset
	 */
	private function getRecordset(string $sSQL): array
	{
		self::initDatabase();
		 
		$statement = self::$db->prepare($sSQL);
		 
		$statement->execute();
		 
		$rows = $statement->fetchAll(\PDO::FETCH_NUM);
		 
		return $rows;
	}
	
	/**
	 * Run a query, return a recordset of associate objects
	 */
	private function getRecordsetAssociate(string $sSQL): array
	{
		self::initDatabase();
		 
		$statement = self::$db->prepare($sSQL);
		 
		$statement->execute();
		 
		$rows = $statement->fetchAll(\PDO::FETCH_CLASS);
		 
		return $rows;
	}
	
	/**
	 * Get the list of tables in the vBulletin database
	 */
	public function getListOfTables()
	{
		$tables = self::getRecordset('SHOW TABLES');
		
		header('Content-Type: application/json');
		echo json_encode($tables, JSON_PRETTY_PRINT);

		return;
				
	}
	
	/**
	 * Get the list of tables in the vBulletin database
	 */
	public function getListOfUserGroups()
	{
		$groups = self::getRecordset('SELECT * FROM usergroup');
		
		header('Content-Type: application/json');
		echo json_encode($groups, JSON_PRETTY_PRINT);

		return;
				
	}
	
	/**
	 * Get the list of tables in the vBulletin database
	 */
	public function getListOfUsers()
	{
		$sSQL = 
			'SELECT U.userid, U.username, U.usergroupid, U.joindate, '.
				'U.homepage, U.usertitle, UF.field1 as biography ' .
			'FROM user U '.
			'LEFT JOIN userfield UF ON (UF.userid = U.userid) OR IsNull(UF.userid) ' .
			'WHERE (usergroupid NOT IN (' . BANNED_USER_GROUP . ', ' . MISERABLE_USER_GROUP .')) '.
			'ORDER BY joindate DESC LIMIT 1000';
			
		// Describe the table, show the list of fields	
		//$users = self::getRecordset('DESCRIBE user');
		//header('Content-Type: application/json');
		//die(json_encode($users, JSON_PRETTY_PRINT));

		$users = self::getRecordsetAssociate($sSQL);
		$html = '<ul>';
		
		foreach ($users as $user) {
			$link = self::getProfileLink($user->userid, $user->username);
			$link = '<a href="'.$link.'" target="_blank">%s</a>';
			
			$joindate = date('Y-m-d H:i:s', $user->joindate);
			
			$html .= 
				'<li>'.
					sprintf($link, $user->userid . '-' . $user->username) .
					' - ' .
					$joindate .
					' - ' .
					$user->usergroupid .
					' - ' .
					$user->usertitle .
					' - ' .
					$user->homepage .
					' - ' .
					$user->biography .
				'</li>';
		}
		
		$html .= '<ul>';
		
		echo $html;

		return;
				
	}
	
	public function getProfileLink(int $userId, string $userName)
	{
		return FORUM_URL . '/member/' . $userId .'-' . $userName;
	}
	
}

/* Entry point */

// Basic protection based on the IP
$IP = $_SERVER['REMOTE_ADDR'];

if ($IP !== '109.89.243.23') {
	die('Forbidden access');
}

$vBulletin = new vBulletin();
//$vBulletin->getListOfUserGroups();
//$vBulletin->getListOfTables();
$vBulletin->getListOfUsers();
			
?>			