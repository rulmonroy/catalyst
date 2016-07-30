<?php
	// Argument handler
	// -----------------------------------
	$argsok 	= false;
	$optshort 	= "u:p:h:";
	$optlong 	= array ("create_table","file:","help","dry_run");
	$options 	= getopt($optshort,$optlong);
	
	$parse 		= false;		// Activate file parsing and data validation.
	$import 	= false;		// Flag to trigger table rebuild and data import.
	$preparedb 	= false;		// Flag to trigger database table creation.
	$suspend	= false;		// Flag to suspend script.
	
	var_dump($options);
	
	// --help command.
	if (array_key_exists("help",$options)) {				
		show_help();
	}
	// --create_table command.
	elseif (array_key_exists("create_table",$options)) {
		if ( !array_key_exists("u",$options) || !array_key_exists("p",$options) || !array_key_exists("h",$options) ) {
			echo "Database information not provided. Please, use -u -p -h to specify parameters.\nType: --help for full command line options.\n\n";
			$suspend = true;
		}
		elseif (array_key_exists("dry_run",$options)) {		// Can't conflict with dry_run
			echo "dry_run and create_table commands can't be used at the same time. Please, verify and try again.\nType: --help for full command line options.\n\n";
			$suspend = true;
		}
		$preparedb = true;
	}
	// --file command.
	elseif (array_key_exists("file",$options)) {
		
		if ( strtolower(substr($options["file"],strlen($options["file"])-3)) != ".csv" ) {
			echo "Invalid file. Only CSV files are accepted.\nType: --help for more details.\n\n";
			exit;
		}
		else {
			$parse = true;
			
			if (!array_key_exists("dry_run",$options)) {	
				if ( !array_key_exists("u",$options) || !array_key_exists("p",$options) || !array_key_exists("h",$options) ) {
					echo "Database information not provided. Please, use -u -p -h to specify parameters.\nType: --help for full command line options.\n\n";
					exit;
				}
				else {
					$import = true;
				}
			}
		}	
	}
	else {
		echo "Filename not provided. Please, use --file [filename] to specify the file to be imported.\nType: --help for full command line options.\n\n";
	}
	
	// import --dry_run --create_table
	if ( ($import || $preparedb) && !$suspend ) {	
		$dbconn = dbConnect("catalyst",$options["h"],$options["u"],$options["p"]);
		
		if ($dbconn != NULL) {			
			if ( chkTableExist($dbconn,"users") ) {
				if ($preparedb) {	// --create_table
					echo "Users table already exists in database!\nProcess terminated.\n\n";
					$suspend = true;
				}
				else {				// import
					echo "All data in the 'users' table will be replaced. Do you wish to continue? (type 'yes' or 'no'): ";
					$handle = fopen ("php://stdin","r");
					$answer = fgets($handle);
					fclose($handle);
									
					if(strtolower(trim($answer)) != 'yes'){	
						echo "Process ended by user.\n\n";
						$import = false;
					} 
				}
			}
			elseif ($preparedb) {	// --create_table
				echo "User table will be created. Do you wish to continue? (type 'yes' or 'no'): ";
				$handle = fopen ("php://stdin","r");
				$answer = fgets($handle);
				fclose($handle);
		
				if(strtolower(trim($answer)) != 'yes'){	
					echo "Process ended by user.\n\n";
					$suspend = true;
				} 
				else {
					$usrexist = false;
					
					if ( CreateTable($dbconn) ) {
						echo "Users table was created successfully!\n\n";
						$suspend;
					}
				}
			}
			
			// --------- Rebuild table ----------
			if ( $import && !$suspend ) {	// import
				$sql = "DROP TABLE users";

				if ($dbconn->query($sql) !== TRUE) {
					$suspend = true;
					echo "\n\nError dropping users table: " . $dbconn->error;
				} 	
				else {
					if ( CreateTable($dbconn) ) {
						echo "Users table was created successfully!\n\n";	
					}		
					else {
						$suspend = true;
					}
				}			
			}
		}
		else {
			$suspend = true;
		}
	}
	
	//---------- Parse CSV file -----------
	if ( $parse && !$suspend ) {				// import --dry_run
		$file = fopen($options["file"], 'r');
		$hdr = false;
		$headers = array();
		$usr_count = 0;
		$usr_valid = 0;
		$allEmails = array();
		
		while ($file != NULL && ($line = fgetcsv($file)) !== FALSE) {
			if ($hdr == false) {
				$hdr = true;
				$headers = array_map('trim',$line);
			}
			else {	// Insert row into table
				$email = trim(strtolower($line[array_search('email', $headers)]));
				
				if ( filter_var($email, FILTER_VALIDATE_EMAIL) !== false ) {										// Valid email
					$name = trim(ucfirst(strtolower(addslashes($line[array_search('name', $headers)]))));
					$surname = trim(ucfirst(strtolower(addslashes($line[array_search('surname', $headers)]))));
					$usr_count++;
					
					if ( $import ) { // import
						$sql = "INSERT INTO users (email, name, surname) VALUES ('".$email."','".$name."','".$surname."')";
					
						if ($dbconn->query($sql) === TRUE) {
							$usr_valid++;
						} 
						else {
							echo "\n\nError adding user: " . $dbconn->error;
						}	
					}
					else {			// --dry_run
						$found = array_search($email, $allEmails);
						
						if ( $found == false && $found != NULL ) {
							array_push($allEmails, $email);
							$usr_valid++;
						}
						else {
							echo "\n\nError validating user: Email is duplicated. Name: " . $name . " " . $surname;
						}					
					}
				}	
				else {
					if ( $import ) {	// import
						echo "\nError adding user: ".$name." ".$surname." has an invalid email: ".$email;	
					}
					else {				// --dry_run
						echo "\nError validating user: ".$name." ".$surname." has an invalid email: ".$email;
					}
					
				}			
			}
		}
		fclose($file);
		
		if ( $import ) {
			echo "\n\nProcess completed successfully! ".$usr_valid."/".$usr_count." imported to database.\n\n";	
		}			
	}
	if ($dbconn != NULL) {
		$dbconn->close();	
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
	
	function chkTableExist($handle, $table) {
		$res = $handle->query("SHOW TABLES LIKE '".$table."'");

		if( isset($res->num_rows) ) {
			if ( $res->num_rows > 0 )
				return true;
		}
		return false;
	}
	
	function dbConnect($db,$host,$user,$pass){
		// Establishing DB Connection
		$dbconn = new mysqli($host, $user, $pass, $db);
		
		// Check connection
		if ($dbconn->connect_error) {
			die("Connection failed: " . $dbconn->connect_error);
			return NULL;
		} 
		else {
			echo "\nConnected successfully\n";
		}
		return $dbconn;
	}
	
	function CreateTable($dbconn) {
		$sql = "CREATE TABLE users (
					email VARCHAR(50) PRIMARY KEY,
					name VARCHAR(30) NOT NULL, 
					surname VARCHAR(30) NOT NULL
				)";
				
		// Attempt to create table
		if ($dbconn->query($sql) === TRUE) {
			return true;
		} 
		else {
			echo "\n\nError creating users table: " . $dbconn->error."/n/n";
			return false;
		}
	}
?>
