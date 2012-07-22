<?

// Settings
DEFINE('TOTAL_SAMPLE_ROWS', 100000);

// Init Slim
require 'Slim/Slim.php';
$app = new Slim();

// ------------------------------
// API Methods

// Seed the database with content
$app->get('/seed', function() {

	// Connect to db
	$dbh = connectMySQL();

	// Empty db
	$sql = "TRUNCATE data";
	$sth = $dbh->prepare($sql);
	$sth->execute();

	// Create a bunch of sample data.
	$time_start = microtime(true);
	for ($i=0; $i<TOTAL_SAMPLE_ROWS; $i++) {
		insertRandom($dbh);
	}

	// Return how long it took
	$response = new stdClass;
	$response->rows = $rows;
	$response->time = round(microtime(true) - $time_start, 1);
	exit(json_encode($response));

});

// Index
$app->get('/', function() {

	// Return 100 results
	$dbh = connectMySQL();
	$sql = "SELECT id FROM data WHERE filter=0 ORDER BY sort LIMIT 100";
	$sth = $dbh->prepare($sql);
	if (!$sth->execute()) error($sth);
	$result = $sth->fetchAll(PDO::FETCH_OBJ);

	// Output
	$response = new stdClass;
	$response->stat = 'ok';
	$response->result = $result;
	exit(json_encode($response));

});


// Show
$app->get('/show', function() {	

	// Pick a random number, this is the id we'll find
	$id = mt_rand(0, TOTAL_SAMPLE_ROWS);

	// Run query
	$dbh = connectMySQL();
	$sql = "SELECT id, filter FROM data WHERE id=:id";
	$sth = $dbh->prepare($sql);
	$sth->bindParam(':id', $id, PDO::PARAM_INT);
	if (!$sth->execute()) error($sth);
	$result = $sth->fetch(PDO::FETCH_OBJ);

	// Output
	$result->stat = 'ok';
	exit(json_encode($result));

});

// Insert
$app->get('/insert', function() {

	// Create a row with random data
	$dbh = connectMySQL();
	insertRandom($dbh);

	// Output the new id
	$response = new stdClass;
	$response->id = $dbh->lastInsertId();
	$response->stat = 'ok';
	exit(json_encode($response));

});

// Update
$app->get('/update', function() {

	// Pick a random id to update
	$id = mt_rand(0, TOTAL_SAMPLE_ROWS);
	$filter = round(mt_rand(0, TOTAL_SAMPLE_ROWS/100));

	// Run query
	$dbh = connectMySQL();
	$sql = "UPDATE data SET filter=:filter WHERE id=:id";
	$sth = $dbh->prepare($sql);
	$sth->bindParam(':id',     $id,     PDO::PARAM_INT);
	$sth->bindParam(':filter', $filter, PDO::PARAM_INT);
	if (!$sth->execute()) error($sth);

	// Output a status
	$response = new stdClass;
	$response->stat = 'ok';
	exit(json_encode($response));

});

// Finish
$app->run();


// ------------------------------
// Shared functions

// Connect to MySQL DB.  Pulling in credentials from env variables
function connectMySQL() {
	$user = $_SERVER['MYSQL_USER'];
	$pass = $_SERVER['MYSQL_PASS'];
	$db   = 'api_speedtest';
	$host = "localhost";
	return new PDO("mysql:host=$host;dbname=$db", $user, $pass);
}

// Shared function for creating random rows
function insertRandom($dbh) {

	// Make up data. Filter should contain a smaller range of
	// possiblities ... 1/100th less variant
	$filter = round(mt_rand(0, TOTAL_SAMPLE_ROWS/100));
	$sort   = mt_rand(0, TOTAL_SAMPLE_ROWS);

	// Write to DB
	$sql = "INSERT INTO data SET filter=:filter, sort=:sort";
	$sth = $dbh->prepare($sql);
	$sth->bindParam(':filter', $filter, PDO::PARAM_INT);
	$sth->bindParam(':sort',   $sort,   PDO::PARAM_INT);
	if (!$sth->execute()) error($sth);

}

// Display an error response
function error($sth) {
	$response = new stdClass;
	$response->stat = 'error';
	$response->msg  = implode(',', $sth->errorInfo());
	exit(json_encode($response));
}