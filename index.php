<?

// Settings
DEFINE('TOTAL_SAMPLE_ROWS', 100000);

// Init Slim
require 'Slim/Slim.php';
$app = new Slim();

// ------------------------------
// API Methods

// Seed the database with content
$app->get('/seed/:db_type', function($db_type='mysql') {
	$time_start = microtime(true);

	// Connect to db
	$dbh = connect($db_type);

	// MySQL
	if ($db_type == 'mysql') {

		// Empty db
		$sql = "TRUNCATE data";
		$sth = $dbh->prepare($sql);
		if (!$sth->execute()) error($sth);

		// Create a bunch of sample data.
		for ($i=0; $i<TOTAL_SAMPLE_ROWS; $i++) {
			insertRandomMySQL($dbh);
		}

	// MongoDB
	} else {

		// Empty db
		$dbh->data->remove();

		// Create a bunch of sample data
		for ($i=0; $i<TOTAL_SAMPLE_ROWS; $i++) {
			insertRandomMongo($dbh);
		}

		// Define indexes on the collection
		$dbh->data->ensureIndex(array('filter' => 1));
		$dbh->data->ensureIndex(array('sort' => 1));
	}

	// Return how long it took
	$response = new stdClass;
	$response->rows = TOTAL_SAMPLE_ROWS;
	$response->time = round(microtime(true) - $time_start, 1);
	exit(json_encode($response));

});

// Index, return 100 results
$app->get('/:db_type', function($db_type='mysql') {

	// connect
	$dbh = connect($db_type);

	// MySQL
	if ($db_type == 'mysql') {
		$sql = "SELECT id FROM data WHERE filter=0 ORDER BY sort LIMIT 100";
		$sth = $dbh->prepare($sql);
		if (!$sth->execute()) error($sth);
		$result = $sth->fetchAll(PDO::FETCH_OBJ);

	//Mongo
	} elseif ($db_type == 'mongo') { 

		// Query DB
		$cursor = $dbh->data->find(array("filter" => 0), array('id'))
			->sort(array('sort' => 1))
			->limit(100);

		// Create an array.  iterator_to_array() was of no use cause I want to
		// strip out the _id field
		$result = array();
		foreach ($cursor as $item) {
			$result[] = array('id' => $item['id']);
		}
	}

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
	$dbh = connect();
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
	$dbh = connect();
	insertRandomMySQL($dbh);

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
	$dbh = connect();
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

// Connect to database, switching between types.  Pulling in credentials from env variables
function connect($db_type) {

	// Shared vars
	$db   = 'api_speedtest';
	$host = "localhost";

	// Switch on db type
	switch($db_type) {
		case 'mysql':
			$user = $_SERVER['MYSQL_USER'];
			$pass = $_SERVER['MYSQL_PASS'];
			return new PDO("mysql:host=$host;dbname=$db", $user, $pass);
		case 'mongo':
			$dbh = new Mongo("mongodb://$host");
			return $dbh->$db;
		default:
			$response = new stdClass;
			$response->stat = 'error';
			$response->msg  = 'unknown db';
			exit(json_encode($response));
	}
	
}

// Shared function for creating random rows in MySQL
function insertRandomMySQL($dbh) {

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


// Shared function for creating random rows
function insertRandomMongo($dbh) {

	// Make up data. Filter should contain a smaller range of
	// possiblities ... 1/100th less variant
	$filter = round(mt_rand(0, TOTAL_SAMPLE_ROWS/100));
	$sort   = mt_rand(0, TOTAL_SAMPLE_ROWS);

	// Write to DB
	$data = new stdClass;
	$data->id     = $dbh->data->count();
	$data->filter = $filter;
	$data->sort   = $sort;
	$dbh->data->insert($data, array("safe" => true));

}

// Display an error response
function error($sth) {
	$response = new stdClass;
	$response->stat = 'error';
	$response->msg  = implode(',', $sth->errorInfo());
	exit(json_encode($response));
}