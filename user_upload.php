<?php
/*
	Script to parse a CSV file with user data and import it into table "users" of selected database.
*/
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
	
	echo "\n\t-------------------- Initiating Script: user_upload --------------------\n";
	
	// --help command.
	if (array_key_exists("help",$options)) {				
		show_help();
	}
	// --create_table command.
	elseif (array_key_exists("create_table",$options)) {
		if ( !array_key_exists("u",$options) || !array_key_exists("p",$options) || !array_key_exists("h",$options) ) {
			echo "\n\t>> Database information not provided. Please, use -u -p -h to specify parameters.\n\t>> Type: --help for full command line options.\n\n";
			$suspend = true;
		}
		elseif (array_key_exists("dry_run",$options)) {		// Can't conflict with dry_run
			echo "\n\t>> dry_run and create_table commands can't be used at the same time. Please, verify and try again.\n\t>> Type: --help for full command line options.\n\n";
			$suspend = true;
		}
		$preparedb = true;
	}
	// --file command.
	elseif (array_key_exists("file",$options)) {
		$extension = substr($options["file"],strlen($options["file"])-4);
		
		if ( strtolower($extension) != ".csv" ) {
			echo "\n\t>> Invalid file. Only CSV files are accepted.\n\t>> Type: --help for more details.\n\n";
			$suspend = true;
		}
		else {
			$parse = true;
			$filename = strtolower($options["file"]);
			
			if ( file_exists($filename) && ($file = fopen($filename, 'r')) !== false ) {
				echo "\n\t>> CSV file successfully opened.\n";	
				
				if ( !array_key_exists("dry_run",$options) && !$suspend ) {	
					if ( !array_key_exists("u",$options) || !array_key_exists("p",$options) || !array_key_exists("h",$options) ) {
						echo "\n\t>> Database information not provided. Please, use -u -p -h to specify parameters.\n\t>> Type: --help for full command line options.\n\n";
						$suspend = true;
					}
					else {
						$import = true;
					}
				}
			}				
			else {
				echo "\n\t>> File doesn't exist or could not be opened. Please, verify file path and name.\n\t>> Type: --help for full command line options.\n\n";
				$suspend = true;		
			}			
		}	
	}
	else {
		echo "\n\t>> Filename not provided. Please, use --file [filename] to specify the file to be imported.\n\t>> Type: --help for full command line options.\n\n";
	}
	
	// import --dry_run --create_table
	if ( ($import || $preparedb) && !$suspend ) {	
		$dbconn = dbConnect("catalyst",$options["h"],$options["u"],$options["p"]);
		
		if ($dbconn != NULL) {			
			if ( chkTableExist($dbconn,"users") ) {
				if ($preparedb) {	// --create_table
					echo "\t>> Users table already exists in database!\n\t>> Process terminated\n\n";
					$suspend = true;
				}
				else {				// import
					echo "\n\t>> All data in the 'users' table will be replaced. Do you wish to continue? (type 'yes' or 'no'): ";
					$handle = fopen ("php://stdin","r");
					$answer = fgets($handle);
					fclose($handle);
									
					if(strtolower(trim($answer)) != 'yes'){	
						echo "\t>> Process ended by user.\n\n";
						$suspend = true;
					} 
				}
			}
			elseif ($preparedb) {	// --create_table
				echo "\n\t>> User table will be created. Do you wish to continue? (type 'yes' or 'no'): ";
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
						echo "\n\t>> Users table was created successfully!\n\n";
					}
					$suspend;
				}
			}
			
			// --------- Rebuild table ----------
			if ( $import && !$suspend ) {	// import
				$sql = "DROP TABLE users";

				if ($dbconn->query($sql) !== TRUE) {
					$suspend = true;
					echo "\n\t>> Error dropping users table: " . $dbconn->error . "\n\n";
				} 	
				else {
					if ( CreateTable($dbconn) ) {
						echo "\n\t>> Users table was rebuilt successfully!\n\n";	
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
		//$file = fopen($options["file"], 'r');
		$hdr = false;
		$headers = array();
		$usr_count = 0;
		$usr_valid = 0;
		$allEmails = array();
		$pattern = array('!','*','?',']'.'[');
		
		while ($file != NULL && ($line = fgetcsv($file)) !== FALSE && !$suspend) {
			if ($hdr == false) {
				$hdr = true;
				$headers = array_map('trim',$line);
				$headers = array_map('strtolower',$headers);
				
				if ( array_search("name", $headers) === false || array_search("surname", $headers) === false || array_search("email", $headers) === false ) {
					echo "\n\t\t- File error: The provided CSV file doesn't contain all required columns (name, surname, email)\n\n";
					$suspend = true;
				}
			}
			else {	// Insert row into table
				$email = trim(strtolower($line[array_search('email', $headers)]));
				$usr_count++;
				
				if ( filter_var($email, FILTER_VALIDATE_EMAIL) !== false ) {										// Valid email
					$name = trim(ucfirst(strtolower($line[array_search('name', $headers)])));
					$surname = trim(ucfirst(strtolower($line[array_search('surname', $headers)])));
					$found = array_search($email, $allEmails);
						
					if ( isset($found) && $found == false ) {
						array_push($allEmails, $email);
												
						if ( $import ) { // import
							$name = str_replace($pattern,'',$dbconn->real_escape_string($name));
							$surname = str_replace($pattern,'',$dbconn->real_escape_string($surname));
							$sql = "INSERT INTO users (email, name, surname) VALUES ('".$dbconn->real_escape_string($email)."','".$name."','".$surname."')";
					
							if ($dbconn->query($sql) === TRUE) {
								$usr_valid++;
							} 
							else {
								echo "\n\t\t- Error adding user: " . $dbconn->error;
							}	
						}
						else {
							$usr_valid++;
						}
					}
					else {
						echo "\n\t\t- Error parsing user: Email is duplicated. Name: " . $name . " " . $surname . " - Email: ".$email."\n";
					}					
				}	
				else {
					if ( empty($email) ) {	
						echo "\n\t\t- Error parsing user: ".$name." ".$surname." has an empty email.\n";	
					}
					else {				
						echo "\n\t\t- Error parsing user: ".$name." ".$surname." has an invalid email: ".$email."\n";
					}
				}			
			}
		}
		if ( $import && !$suspend ) {
			echo "\n\n\t>> Process complete! ".$usr_valid."/".$usr_count." users imported to database.\n\n";	
		}
		elseif ( $parse && !$suspend ) {
			echo "\n\n\t>> Validation complete! ".$usr_valid."/".$usr_count." users can be imported into database.\n\n";	
		}
		else {
			echo "\n\n\t>> Errors found. Process interrupted. \n\n";
		}
	}
	if (isset($dbconn) && $dbconn != NULL) {
		$dbconn->close();	
	}
	if (isset($file) && $file != NULL) {
		fclose($file);	
	}
	echo "\n\t-------------------- script finished --------------------\n\n";
	
/* -------------------------------------------------------------------------------------------------------------------
	Show_Help
   -------------------------------------------------------------------------------------------------------------------*/
	function show_help() {
		echo "\n\tUSER UPLOAD SCRIPT HELP\n
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
	
/* -------------------------------------------------------------------------------------------------------------------
	chkTableExist: Check if a table exist in selected database.
	Return:
		true: Table found.
		false: Not found.
   -------------------------------------------------------------------------------------------------------------------*/	
	function chkTableExist($handle, $table) {
		$res = $handle->query("SHOW TABLES LIKE '".$table."'");

		if( isset($res->num_rows) ) {
			if ( $res->num_rows > 0 )
				return true;
		}
		return false;
	}

/* -------------------------------------------------------------------------------------------------------------------
	dbConnect: Open connection with database.
	Return: 
		NULL: Connection failed.
		Connection Object: When db connection was successful.
   -------------------------------------------------------------------------------------------------------------------*/
	function dbConnect($db,$host,$user,$pass){
		// Establishing DB Connection
		@$dbconn = new mysqli($host, $user, $pass, $db);
		
		// Check connection
		if ($dbconn->connect_error) {
			echo "\n\t>> Connection failed: " . $dbconn->connect_error ."\n\n";
			return null;
		} 
		else {
			echo "\n\t>> Connected successfully to ".$db."\n";
		}
		return $dbconn;
	}

/* -------------------------------------------------------------------------------------------------------------------
	CreateTable: Insert users table into database.
	Return:
		true: Table inserte successfully
		false: Error creating table.
   -------------------------------------------------------------------------------------------------------------------*/
	function CreateTable($dbconn) {
		$sql = "CREATE TABLE users (
					email VARCHAR(50) PRIMARY KEY,
					name VARCHAR(30) NOT NULL, 
					surname VARCHAR(30) NOT NULL
				)";
				
		// Attempt to create table
		if (@$dbconn->query($sql) === TRUE) {
			return true;
		} 
		else {
			echo "\n\t>> Error creating users table: " .$dbconn->error;
			echo "\n\n";
			return false;
		}
	}
?>
