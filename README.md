# catalyst
PHP Evaluation - Catalyst
Developed by: Raul Monroy

==============================================================================================================
		This script parses the contents of a CSV file containing a list of users and imports the contents to
		the 'users' table into the selected database. Please note that every preexistent information will be
		deleted from the table.
		"Users" table is created with 3 fields: Name, Surname and Email.
		
		OPTIONS:
			--file [filepath/filename.csv]	The name of the file to be imported. Only CSV format is acceptable.
			  
			-u [username]			MySQL database username.
			-p [password]			MySQL database password.
			-h [hostname]			MySQL database hostname or IP address.
		  
			--create_table			The script generates the "users" table in case it has not still been created.
			--dry_run				Parses the CSV file and analyzes data validty. No changes to database are performed.
		
		EXAMPLES:
			Imports data from CSV file to users table in the selected database:
				user_upload.php --file home/root/share/users.csv -u root -p root -h localhost
			
			Perform a validation of the CSV file information without affecting database:
				user_upload.php --file home/root/share/users.csv --dry_run
			
			Only creates users table without importing data:
				user_upload.php -create_table -u root -p root -h localhost
==============================================================================================================
		
Tested in Ubuntu 14.04, MySQL 5.5, PHP 5.5.9, Apache 2.4.7

REQUIREMENTS:
	- mysqli php extension