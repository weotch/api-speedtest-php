<?

// Settings
DEFINE('TOTAL_SAMPLE_ROWS', 100000);

// Init Slim
require 'Slim/Slim.php';
$app = new Slim();

// ------------------------------
// API Methods

// Seed the database with content
$app->get('/seed/(:db_type/)', function($db_type='mysql') {
	$time_start = microtime(true);

	// Connect to db
	$dbh = connect($db_type);

	// MySQL
	if ($db_type == 'mysql') {

		// Empty db
		$sql = "TRUNCATE data";
		$sth = $dbh->prepare($sql);
		if (!$sth->execute()) mysqlError($sth);

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

// Show.  Select a random row
$app->get('/show/(:db_type/)', function($db_type='mysql') {	

	// Connect
	$dbh = connect($db_type);

	// Pick a random number, this is the id we'll find
	$id = mt_rand(0, TOTAL_SAMPLE_ROWS);

	// MySQL
	if ($db_type == 'mysql') {
		$sql = "SELECT id, filter FROM data WHERE id=:id";
		$sth = $dbh->prepare($sql);
		$sth->bindParam(':id', $id, PDO::PARAM_INT);
		if (!$sth->execute()) mysqlError($sth);
		$result = $sth->fetch(PDO::FETCH_ASSOC);

	// Mongo
	} else {
		$result = $dbh->data->findOne(array("_id" => $id), array('_id', 'filter'));
		checkForMongoError($dbh);
		$result['id'] = $result['_id'];
		unset($result['_id']);
	}

	// Output
	$result['stat'] = 'ok';
	exit(json_encode($result));

});

// Insert.  Create a row with random data
$app->get('/insert/(:db_type/)', function($db_type='mysql') {

	// Connect
	$dbh = connect($db_type);

	// MySQL
	if ($db_type == 'mysql') {
		insertRandomMySQL($dbh);
		$new_id = $dbh->lastInsertId();

	// Mongo
	} else {
		$new_id = insertRandomMongo($dbh);
	}
	

	// Output the new id
	$response = new stdClass;
	$response->id = $new_id;
	$response->stat = 'ok';
	exit(json_encode($response));

});

// Update
$app->get('/update/(:db_type/)', function($db_type='mysql') {

	// Pick a random id to update
	$id = mt_rand(0, TOTAL_SAMPLE_ROWS);
	$filter = round(mt_rand(0, TOTAL_SAMPLE_ROWS/100));

	// Connect
	$dbh = connect($db_type);

	/// MySQL
	if ($db_type == 'mysql') {
		$sql = "UPDATE data SET filter=:filter WHERE id=:id";
		$sth = $dbh->prepare($sql);
		$sth->bindParam(':id',     $id,     PDO::PARAM_INT);
		$sth->bindParam(':filter', $filter, PDO::PARAM_INT);
		if (!$sth->execute()) mysqlError($sth);

	// Mongo
	} else {
		try {
			$dbh->data->update(array('_id' => $id), 
				array('$set' => array("filter" => $filter)),
				array('safe' => true));
		} catch(MongoException $e) {
			checkForMongoError($dbh);
		}
	}

	// Output a status
	$response = new stdClass;
	$response->stat = 'ok';
	exit(json_encode($response));

});

// Just return a canned response, no DB call
$app->get('/static/', function() {
	$response = new stdClass;
	$response->stat = 'ok';
	$response->static = true;
	exit(json_encode($response));
});

// Index, return 100 results.  This has to be defined last because of the
// optional agument it takes will catch /show, for instance.  There is a WHERE
// condition where we look for a filter set to '0'
$app->get('/(:db_type/)', function($db_type='mysql') {

	// connect
	$dbh = connect($db_type);

	// MySQL
	if ($db_type == 'mysql') {
		$sql = "SELECT id FROM data WHERE filter=0 ORDER BY sort LIMIT 100";
		$sth = $dbh->prepare($sql);
		if (!$sth->execute()) mysqlError($sth);
		$result = $sth->fetchAll(PDO::FETCH_ASSOC);

	//Mongo
	} else { 

		// Query DB
		$cursor = $dbh->data->find(array("filter" => 0), array('_id'))
			->sort(array('sort' => 1))
			->limit(100);
		checkForMongoError($dbh);

		// Create an array for result.  iterator_to_array() was of no use cause I want to
		// customize the name of the id field (don't want the API to use _id)
		$result = array();
		foreach ($cursor as $item) {
			$result[] = array('id' => $item['_id']);
		}
	}

	// Output
	$response = new stdClass;
	$response->stat = 'ok';
	$response->result = $result;
	exit(json_encode($response));

});

// Finish
$app->run();


// ------------------------------
// Shared functions

// Connect to database, switching between types.  Pulling in credentials from env variables
function connect($db_type) {

	// Switch on db type
	switch($db_type) {
		case 'mysql':
			$user = $_ENV['MYSQL_USER'];
			$pass = $_ENV['MYSQL_PASS'];
			$host = empty($_ENV['MYSQL_HOST']) ? 'localhost' : $_ENV['MYSQL_HOST'];
			$db   = empty($_ENV['MYSQL_DB']) ? 'api_speedtest' : $_ENV['MYSQL_DB'];
			return new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
		case 'mongo':
			$user  = empty($_ENV['MONGO_USER']) ? '' : $_ENV['MONGO_USER'];
			$pass  = empty($_ENV['MONGO_PASS']) ? '' : $_ENV['MONGO_PASS'];
			$host  = empty($_ENV['MONGO_HOST']) ? 'localhost' : $_ENV['MONGO_HOST'];
			$db    = empty($_ENV['MONGO_DB']) ? 'api_speedtest' : $_ENV['MONGO_DB'];
			$creds = empty($user) && empty($pass) ? '' : $user . ':'. $pass . '@';
			$dbh   = new Mongo("mongodb://{$creds}{$host}");
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
	if (!$sth->execute()) mysqlError($sth);

}


// Shared function for creating random rows
function insertRandomMongo($dbh) {

	// Make up data. Filter should contain a smaller range of
	// possiblities ... 1/100th less variant
	$filter = round(mt_rand(0, TOTAL_SAMPLE_ROWS/100));
	$sort   = mt_rand(0, TOTAL_SAMPLE_ROWS);

	// Create document
	$data = new stdClass;
	$data->_id     = $dbh->data->count();
	$data->filter = $filter;
	$data->sort   = $sort;

	// Write it
	try {
		$dbh->data->insert($data, array('safe' => true));
	} catch(MongoException $e) {
		checkForMongoError($dbh);
	}

	// Return the id we used.  I couldn't find a Mongo specific way to do this besides
	// http://stackoverflow.com/questions/4525556/mongodb-php-get-id-of-new-document.
	// The docs (http://www.php.net/manual/en/mongocollection.insert.php) make me think
	// that if I had instantiated a collection (new MongoCollection()) I would get the
	// id back in the result from insert().
	return $data->_id;
	
}

// Display an error response for MySQL Queries
function mysqlError($sth) {
	$response = new stdClass;
	$response->stat = 'error';
	$response->msg  = implode(',', $sth->errorInfo());
	exit(json_encode($response));
}

// Handle mongo errors.  Note, writes throw exceptions, so this is really just for find().
// Well, I am using it to spit our write errors that get caught as exceptions, though.
// Honestly, though, I couldn't get selects to throw an error when passing bad data.  So
// in production, I think we should just see if results are returned when they're expected
// and that's how we check.  Like if you give a bad key name in a document, there is no error.
function checkForMongoError($dbh) {

	// Check for error
	$err = $dbh->lastError();
	if (empty($err['err'])) return;

	// There was an error
	$response = new stdClass;
	$response->stat = 'error';
	$response->msg  = $err['err'];
	exit(json_encode($response));
}

