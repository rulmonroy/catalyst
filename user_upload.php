<?php
	// Setting db connection values
	$servername = "localhost";
	$username 	= "root";
	$password 	= "raul01";
	$dbname 	= "catalyst";

	// Establishing DB Connection
	$dbconn = new mysqli($servername, $username, $password, $dbname);
	
	// Check connection
	if ($dbconn->connect_error) {
		die("Connection failed: " . $dbconn->connect_error);
	} 
	
	echo "Connected to DB successfully!!";
	
	// Table dropping
	$sql = "DROP TABLE users";

	// Attempt at dropping table
	if ($dbconn->query($sql) === TRUE) {
		echo "\n\nUsers table was dropped.";
	} 
	else {
		echo "\n\nError dropping users table: " . $dbconn->error;
	}
	
	// Table creation
	$sql = "CREATE TABLE users (
				email VARCHAR(50) PRIMARY KEY,
				name VARCHAR(30) NOT NULL, 
				surname VARCHAR(30) NOT NULL
			)";
			
	// Attempt to create table
	if ($dbconn->query($sql) === TRUE) {
		echo "\n\nusers table created successfully!!";
	} 
	else {
		echo "\n\nError creating users table: " . $dbconn->error;
	}
	
	//---------- Loading CSV file into database
	
	$file = fopen('c:\xampp\htdocs\catalyst\users.csv', 'r');
	$hdr = false;
	$headers = array();
	
	while ($file != NULL && ($line = fgetcsv($file)) !== FALSE) {
		if ($hdr == false) {
			$hdr = true;
			$headers = array_map('trim',$line);
		}
		else {	// Insert row into table
			$email = addslashes($line[array_search('email', $headers)]);
			$name = ucfirst(strtolower(addslashes($line[array_search('name', $headers)])));
			$surname = ucfirst(strtolower(addslashes($line[array_search('surname', $headers)])));
			
			$sql = "INSERT INTO users (email, name, surname) VALUES ('".$email."','".$name."','".$surname."')";
			
			if ($dbconn->query($sql) === TRUE) {
				echo "\nUser added: ".print_r($line);
			} 
			else {
				echo "\n\nError adding user: " . $dbconn->error;
			}
		}
	}
	fclose($file);
	
	$dbconn->close();
?>
