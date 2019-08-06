<?php

declare(strict_types=1);

/**
 * Script for vBulletin powered forum.
 * Display the list of tables, the list of usergroups, the list of last
 * registered users, ...
 */
namespace Avonture;

ini_set('display_errors', '1');

date_default_timezone_set('Europe/Paris');

define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));

/** group id of banned users 
 * Note that the ID depends on your own installation
 * See the getListOfUserGroups() function 
 */
define('BANNED_USER_GROUP', 8);

/** group id of miserable users
 * Note that the ID depends on your own installation
 * See the getListOfUserGroups() function 
 */
define('MISERABLE_USER_GROUP', 9);

// Password is MySecretPassword
// Get a new one by running
//		echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);
define('APP_PASSWORD', '$2y$10$TTbdrdohinmZm61HE5J6J.6jpDGJOPYA6gW7TQ8EkGVnqAZRmLyC2');

if (!defined('VB_ENTRY')) {
    define('VB_ENTRY', 1);
}

class vBulletin
{
    static private $config = [];
    static private $db = null;
    static private $forumURL = '';

    public function __construct() {

        self::$config = self::getConfig();
        self::$forumURL = self::getForumURL();

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
            'host'     => $dbServer,
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
     * Run a query, return an array
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
     * Run a query, return a recordset of associative objects
     */
    private function getRecordsetAssociative(string $sSQL): array
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

        $html = '<h2 class="title is-2">List of tables</h2>';
        $html .= '<pre>'.print_r($tables, true).'</pre>';

        echo self::returnHTML($html);

        return;

    }

    /**
     * Get the list of usergroups
     */
    public function getListOfUserGroups()
    {
        $groups = self::getRecordsetAssociative('SELECT usergroupid, title, description FROM usergroup');

        $html = '<h2 class="title is-2">List of usergroup</h2>';
        $html .= '<pre>'.print_r($groups, true).'</pre>';

        echo self::returnHTML($html);

        return;

    }

    /**
     * Get the forum URL.
     * !!! Assume this script has been stored in the root folder of the forum !!!
     */
    public function getForumURL(): string
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");

        $actual_link = $protocol . "://{$_SERVER['HTTP_HOST']}";

        return $actual_link;
    }

    /**
     * Get the list of tables in the vBulletin database
     */
    public function getListOfRecentUsersHavingBiography()
    {
        $sSQL = 
            'SELECT U.*, U.userid, U.username, U.usergroupid, U.joindate, U.posts, '.
                'U.homepage, U.usertitle, UF.field1 as biography ' .
            'FROM user U '.
            'LEFT JOIN userfield UF ON (UF.userid = U.userid) OR IsNull(UF.userid) ' .
            // Don't take already banned users
            'WHERE (usergroupid NOT IN (' . BANNED_USER_GROUP . ', ' . MISERABLE_USER_GROUP .')) '.
            // Only keep users having a biography; very often a spam text
            'AND (UF.field1 <> \'\') ' .
            'ORDER BY joindate DESC LIMIT 150';

        $users = self::getRecordsetAssociative($sSQL);

        // Prepare the HTML table for the display; using bulma css		
        $html = '<table class="table is-bordered is-striped is-narrow is-hoverable">' . 
            '<thead>'.
                '<tr>'.
                    '<th>Profile</th>' .
                    '<th>Join date</th>' .
                    //'<th>User group</th>' .
                    '<th>#Posts</th>' .
                    '<th>Homepage</th>' .
                    '<th>Biography</th>' .
                '</tr>'.
            '</thead>' .
            '<tbody>';

        foreach ($users as $user) {
            $link = self::getProfileLink($user->userid, $user->username);
            $link = '<a href="'.$link.'" target="_blank">%s</a>';

            $joindate = date('Y-m-d', ((int) $user->joindate));

            $html .= 
                '<tr>'.
                    '<td>' . sprintf($link, $user->username) . '</td>' .
                    '<td>' . $joindate . '</td>' .
                    //'<td>' . $user->usergroupid . ' - ' . $user->usertitle . '</td>' .
                    '<td>' . $user->posts . '</td>' .
                    '<td style="word-wrap: break-word;max-width: 150px !important;">' . $user->homepage . '</td>' .
                    '<td style="word-wrap: break-word;max-width: 600px !important;">' . $user->biography . '</td>' .
                '</tr>';
        }

        $html .= '</tbody></table>';

        $html = '<h2 class="title is-2">List of recent users not banned and having a biography filled in</h2>' .
            '<div class="table-container">' . $html . '</div>';

        echo self::returnHTML($html);

        return;

    }

    /**
     * Create a link to the user's profile
     */
    private function getProfileLink(string $userId, string $userName)
    {
        return self::$forumURL . '/member/' . $userId .'-' . $userName;
    }

    /**
     * Return a valid html5 document
     */
    public function returnHTML(string $html) 
    {
        echo '<html lang="en">' .
            '<head>' .
                '<meta charset="UTF-8" />' .
                '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.7.5/css/bulma.css">' .
            '</head>' .
            '<body>' .
            $html .
            '</body>' . 
            '</html>';
    }

}

/* --------------------------- */
/* ------- Entry point ------- */
/* --------------------------- */

/**
 * Check if the password is valid; if not, show a 'Please provide password' form 
 * and stop immediately
 */
function checkPassword()
{
    @session_start();

    // Get the password from the session if any
    $password = isset($_SESSION['password']) ? $_SESSION['password'] : '';

    // Get the password from the query string
    if ('' == $password) {
        if (isset($_POST['password'])) {
            $password = trim(filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING));
        }
    }

    // Verify if the filled in password is the expected one
    if (!password_verify($password, APP_PASSWORD)) {
        header('HTTP/1.0 403 Forbidden');
        $vBulletin = new vBulletin();

        // Create a form; use Bulma to have a nice form
        $html=
            '<div class="container" style="padding-top:25px;">
                <div class="columns is-5-tablet is-4-desktop is-3-widescreen">
                    <div class="field">
                        <p class="control has-icons-left">
                            <form class="box" action="' . $_SERVER['PHP_SELF'] . '" method="POST">
                                <input class="input" type="password" placeholder="Password" name="password">
                                <span class="icon is-small is-left">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </form>
                        </p>
                    </div>
                </div>
            </div>';

        // Reset
        $_SESSION['password'] = '';

        echo $vBulletin->returnHTML($html);
        unset($vBulletin);
        die();
    }

    if ('' == ($_SESSION['password'] ?? '')) {
        $_SESSION['password'] = $password;
    }
}

// Die if the password isn't supplied
checkPassword();

// Ok, password validated, we can continue
$vBulletin = new vBulletin();

echo '<h1 class="title is-1">' . $vBulletin->getForumURL() . '</h1>';

// Display the list of tables in the vBulletin installation
//$vBulletin->getListOfTables();

// Display the list of user groups defined
//$vBulletin->getListOfUserGroups();

/** Display the list of users, recently created, those having a biography
 * This list help to monitor fake users, just created accounts with.
 * spam content in their biography
 */
$vBulletin->getListOfRecentUsersHavingBiography();

?>