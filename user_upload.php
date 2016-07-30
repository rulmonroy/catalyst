<?php
	// Argument handler
	// -----------------------------------
	$argsok = false;
	$optshort = " u:p:h:";
	$optlong = array ("create_table","file:","help","dry_run");
	$options = getopt($optshort,$optlong);
	
	var_dump($options);
	
	// --help command.
	if (array_key_exists("help",$options)) {				
		show_help();
	}
	elseif (array_key_exists("create_table",$options)) {
		if ( !array_key_exists("u",$options) || !array_key_exists("p",$options) || !array_key_exists("h",$options) ) {
			echo "Database information not provided. Please, use -u -p -h to specify parameters.\nType: --help for full command line options.\n\n";
			exit;
		}
		elseif (array_key_exists("dry_run",$options)) {
			echo "--dry_run and --create_table commands can't be used at the same time. Please, verify and try again.\nType: --help for full command line options.\n\n";
		}
		echo "User table will be created. Do you wish to continue? (type 'yes' or 'no'): ";
		$handle = fopen ("php://stdin","r");
		$answer = fgets($handle);
		fclose($handle);
		
		if(strtolower(trim($answer)) != 'yes'){
			echo "Process ended by user.\n\n";
			exit;
		} 
	}
	if (array_key_exists("file",$options)) {
		echo "File provided: ".$options["file"]."\n\n";
	}
	else {
		echo "Filename not provided. Please, use --file [filename] to specify the file to be imported.\nType: --help for full command line options.\n\n";
	}
	
	if ($argsok) {
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
		
		$file = fopen('users.csv', 'r');
		$hdr = false;
		$headers = array();
		$usr_count = 0;
		
		while ($file != NULL && ($line = fgetcsv($file)) !== FALSE) {
			if ($hdr == false) {
				$hdr = true;
				$headers = array_map('trim',$line);
			}
			else {	// Insert row into table
				$email = trim(strtolower($line[array_search('email', $headers)]));
				
				if (filter_var($email,FILTER_VALIDATE_EMAIL) !== false) {
					$name = trim(ucfirst(strtolower(addslashes($line[array_search('name', $headers)]))));
					$surname = trim(ucfirst(strtolower(addslashes($line[array_search('surname', $headers)]))));
					
					$sql = "INSERT INTO users (email, name, surname) VALUES ('".$email."','".$name."','".$surname."')";
					
					if ($dbconn->query($sql) === TRUE) {
						$usr_count++;
					} 
					else {
						echo "\n\nError adding user: " . $dbconn->error;
					}	
				}	
				else {
					echo "\nUser NOT ADDED!: ".$name." ".$surname." has an invalid email: ".$email;
				}			
			}
		}
		fclose($file);
		$dbconn->close();
		echo "\n\nImport completed. ".$usr_count." users added to database.\n\n";
	}
	
	function show_help() {
		echo "\nUSER UPLOAD SCRIPT HELP\n
		==============================================================================================================\n
		This script parses the contents of a CSV file containing a list of users and imports the contents to\n
		the 'users' table into the selected database. Please note that every preexistent information will be\n
		deleted from the table.\n
		Users table is created with 3 fields: Name, Surname and Email.\n\n
		
		OPTIONS:
			--file [filepath/filename.csv]	The name of the file to be imported. Only CSV format is acceptable.\n\n
			  
			-u [username]			MySQL database username.\n
			-p [password]			MySQL database password.\n
			-h [hostname]			MySQL database hostname or IP address.\n\n
		  
			--create_table			The script generates the users table in case it has not still been created.\n
			--dry_run			Parses the CSV file and analyzes data validty. No changes to database are performed.\n\n
		
		EXAMPLES:\n
			Imports data from CSV file to users table in the selected database:\n
				user_upload.php --file home/root/share/users.csv -u root -p root -h localhost\n\n
			
			Perform a validation of the CSV file information without affecting database:\n
				user_upload.php --file home/root/share/users.csv --dry_run\n\n
			
			Only creates users table without importing data:\n
				user_upload.php -create_table -u root -p root -h localhost\n
		==============================================================================================================\n\n";
	}
?>
