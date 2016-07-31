<?php
	echo "\n\t----------------------- Initiating Script: numbers -----------------------\n";
	
	for ( $i=1; $i<=100; $i++ ) {
		if ( $i % 3 == 0 && $i % 5 == 0 ) {
			echo "\tfoobar";
		}
		elseif ( $i % 3 == 0 ) {
			echo "\tfoo";
		}
		elseif ( $i % 5 == 0 ) {
			echo "\tbar";
		}
		else {
			echo "\t".$i;
		}
		
		if ( $i % 10 == 0 ) {
			echo "\n";
		}
	}
	echo "\n\n";
?>