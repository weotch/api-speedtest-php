<?

// Init Slim
require 'Slim/Slim.php';
$app = new Slim();

// Index
$app->get('/'), function() {

	// Return 100 results


});


// Show
$app->get('/show'), function() {

	// Pick a random number, this is the id we'll find

});

// Insert
$app->get('/'), function() {

	// Pick a random number, this is the id we'll find

});

// Update
$app->get('/'), function() {

	// Pick a random number, this is the id we'll find

});

// Finish
$app->run();