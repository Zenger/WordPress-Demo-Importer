<?php
// Plugin Name: Demo Importer
@ob_implicit_flush(true); 

define("MDI_PATH", dirname(__FILE__));


if (isset($_GET['page']) && $_GET['page'] == "mdi") {
	if (isset($_GET['cx']))
	{
		if ($_GET['cx'] == "remove_notice")
		{
			update_option('MDI_SHOWN_NOTICE', true);
		}
	}

	@ini_set('max_execution_time', 0);
	@ini_set('memory_limit', '5120M');
	@set_time_limit ( 0 );
	@ini_set('post_max_size', 8);
	@ini_set('upload_max_filesize', '10M');

}

// Taken from : http://stackoverflow.com/questions/5707806/recursive-copy-of-directory
function zenger_recursive_copy($src,$dst) { 
    $dir = opendir($src); 
    @mkdir($dst); 
    while(false !== ( $file = readdir($dir)) ) { 
        if (( $file != '.' ) && ( $file != '..' )) { 
            if ( is_dir($src . '/' . $file) ) { 
                zenger_recursive_copy($src . '/' . $file,$dst . '/' . $file); 
            } 
            else { 
                copy($src . '/' . $file,$dst . '/' . $file); 
            } 
        } 
    } 
    closedir($dir); 
}
// Taken from: http://stackoverflow.com/questions/147821/loading-sql-files-from-within-php/149456#149456 (as in PHPBB)
class Zenger_SQL_Parse 
{
	function remove_comments(&$output)
	{
	   $lines = explode("\n", $output);
	   $output = "";

	   // try to keep mem. use down
	   $linecount = count($lines);

	   $in_comment = false;
	   for($i = 0; $i < $linecount; $i++)
	   {
	      if( preg_match("/^\/\*/", preg_quote($lines[$i])) )
	      {
	         $in_comment = true;
	      }

	      if( !$in_comment )
	      {
	         $output .= $lines[$i] . "\n";
	      }

	      if( preg_match("/\*\/$/", preg_quote($lines[$i])) )
	      {
	         $in_comment = false;
	      }
	   }

	   unset($lines);
	   return $output;
	}

	//
	// remove_remarks will strip the sql comment lines out of an uploaded sql file
	//
	function remove_remarks($sql)
	{
	   $lines = explode("\n", $sql);

	   // try to keep mem. use down
	   $sql = "";

	   $linecount = count($lines);
	   $output = "";

	   for ($i = 0; $i < $linecount; $i++)
	   {
	      if (($i != ($linecount - 1)) || (strlen($lines[$i]) > 0))
	      {
	         if (isset($lines[$i][0]) && $lines[$i][0] != "#")
	         {
	            $output .= $lines[$i] . "\n";
	         }
	         else
	         {
	            $output .= "\n";
	         }
	         // Trading a bit of speed for lower mem. use here.
	         $lines[$i] = "";
	      }
	   }

	   return $output;

	}

	function split_sql_file($sql, $delimiter)
	{
	   // Split up our string into "possible" SQL statements.
	   $tokens = explode($delimiter, $sql);

	   // try to save mem.
	   $sql = "";
	   $output = array();

	   // we don't actually care about the matches preg gives us.
	   $matches = array();

	   // this is faster than calling count($oktens) every time thru the loop.
	   $token_count = count($tokens);
	   for ($i = 0; $i < $token_count; $i++)
	   {
	      // Don't wanna add an empty string as the last thing in the array.
	      if (($i != ($token_count - 1)) || (strlen($tokens[$i] > 0)))
	      {
	         // This is the total number of single quotes in the token.
	         $total_quotes = preg_match_all("/'/", $tokens[$i], $matches);
	         // Counts single quotes that are preceded by an odd number of backslashes,
	         // which means they're escaped quotes.
	         $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$i], $matches);

	         $unescaped_quotes = $total_quotes - $escaped_quotes;

	         // If the number of unescaped quotes is even, then the delimiter did NOT occur inside a string literal.
	         if (($unescaped_quotes % 2) == 0)
	         {
	            // It's a complete sql statement.
	            $output[] = $tokens[$i];
	            // save memory.
	            $tokens[$i] = "";
	         }
	         else
	         {
	            // incomplete sql statement. keep adding tokens until we have a complete one.
	            // $temp will hold what we have so far.
	            $temp = $tokens[$i] . $delimiter;
	            // save memory..
	            $tokens[$i] = "";

	            // Do we have a complete statement yet?
	            $complete_stmt = false;

	            for ($j = $i + 1; (!$complete_stmt && ($j < $token_count)); $j++)
	            {
	               // This is the total number of single quotes in the token.
	               $total_quotes = preg_match_all("/'/", $tokens[$j], $matches);
	               // Counts single quotes that are preceded by an odd number of backslashes,
	               // which means they're escaped quotes.
	               $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);

	               $unescaped_quotes = $total_quotes - $escaped_quotes;

	               if (($unescaped_quotes % 2) == 1)
	               {
	                  // odd number of unescaped quotes. In combination with the previous incomplete
	                  // statement(s), we now have a complete statement. (2 odds always make an even)
	                  $output[] = $temp . $tokens[$j];

	                  // save memory.
	                  $tokens[$j] = "";
	                  $temp = "";

	                  // exit the loop.
	                  $complete_stmt = true;
	                  // make sure the outer loop continues at the right point.
	                  $i = $j;
	               }
	               else
	               {
	                  // even number of unescaped quotes. We still don't have a complete statement.
	                  // (1 odd and 1 even always make an odd)
	                  $temp .= $tokens[$j] . $delimiter;
	                  // save memory.
	                  $tokens[$j] = "";
	               }

	            } // for..
	         } // else
	      }
	   }

	   return $output;
	}
}

class ZengerDemoImporter 
{
	public static function init()
	{
		add_action('admin_menu', array('ZengerDemoImporter', 'add_menu_page'));
	}

	public static function show_notice()
	{
		if ( ! get_option('MDI_SHOWN_NOTICE') )
		{
			?>
			 <div class="updated">
		        <p>[Zenger] Click <a href="<?php echo admin_url("tools.php?page=mdi"); ?>">Here</a> if you want to Import the Demo Data. <a href="<?php echo admin_url("tools.php?page=mdi"); ?>">Import</a> | <a href="<?php echo admin_url("tools.php?page=mdi&cx=remove_notice"); ?>">Dismiss</a></p>
		    </div>
		    <?php
		}	
	}

	public static function add_menu_page()
	{
		add_submenu_page( 'tools.php', 'Zenger Import', 'Zenger Import Demo', 'manage_options', 'mdi', array('ZengerDemoImporter', 'render_page')  );
	}

	public static function render_page()
	{
		echo "<div class='wrap'>";
		echo "<h2>Zenger Importer</h2> <br />";
		$rules = array(
			'Max Execution Time' => array( ini_get('max_execution_time'), 0 , 'max_execution_time' ),
			'Memory Limit'       => array( ini_get('memory_limit'), 256, 'memory_limit'),
			'Post Max Size'      => array( ini_get('post_max_size') , 8, 'post_max_size'),
			'File Upload Size'   => array( ini_get('upload_max_filesize') , 10, 'upload_max_filesize'),
		);

		$fail = false;

		foreach ( $rules as $t => $v)
		{
			if ( (int)$v[0] < $v[1])
			{
				echo  "<p>" . $t . " must be higher than: " . $v[1] . ". Currently is : ".$v[0]."</p>";
				$fail = true;
			}

		}

		if ( !is_readable( MDI_PATH . "/sql/sqlfile.sql")) {
			echo "<p>" . MDI_PATH . "/sql/sqlfile.sql doesn't exist or isn't readable! </p>";
			$fail = true;
		}

		if (!$fail)
		{

			if (!isset($_POST['cx_action']))
			{


			?>	

			<div id="message" class='error'><p>This will destroy all your existing content. Are you sure you want to continue?</p></div>
			<br>
			<form action="" onsubmit='return confirm("Are you really, really sure you want to DELETE all your content and Import the demo?")' method="post">
				<label for="">Import All Demo Data</label>
				<input type="hidden" name="cx_action" value="import_mdi" />
				<input type="submit" onclick="return confirm('Are you sure you want to delete all your existing content and import the demo data?')" class="btn btn-primary" value="Import" />
			</form>
			<?php
			} 
			else 
			{
				global $wpdb;

				// Clean Up
				echo "<p>Cleaning Up</p>";
				$cleanup = array(
					"DROP TABLE IF EXISTS `{$wpdb->prefix}layerslider`;",
					"DROP TABLE IF EXISTS `{$wpdb->prefix}postmeta`;",
					"DROP TABLE IF EXISTS `{$wpdb->prefix}posts`; ",
					"DROP TABLE IF EXISTS `{$wpdb->prefix}revslider_settings`; ",
					"DROP TABLE IF EXISTS `{$wpdb->prefix}revslider_sliders`; ",
					"DROP TABLE IF EXISTS `{$wpdb->prefix}revslider_slides`; ",
					"DROP TABLE IF EXISTS `{$wpdb->prefix}term_relationships`; ",
					"DROP TABLE IF EXISTS `{$wpdb->prefix}term_taxonomy`; ",
					"DROP TABLE IF EXISTS `{$wpdb->prefix}term_terms`; ",
				);


				foreach($cleanup as $task)
				{
					$query = $wpdb->query ( $task );
				}

				if (!$wpdb->last_error)
				{
					echo "Tables Cleaned!";
				}
				else
				{
					echo "Error: " . $wpdb->last_error;
					$stop = true;
				}

				@ob_flush(); 
				@flush();

				$upload      = wp_upload_dir();
				$upload_path = $upload['basedir'];
				$uploade     = explode("wp-content", $upload_path);
				$upload_path = str_replace($uploade[0], "", $upload_path);
				$upload_path_e = str_replace("/", "\\\\/", $upload_path);

				$site_url_e = str_replace("/", "\\\\/", get_option('siteurl'));

				$replaces = array(
					'ms_t_8_'                           => $wpdb->prefix,         // prefix
					'http://demo.1theme.com/Zenger'       => get_option('siteurl'), // urls
					'http:\\\\/\\\\/demo.1theme.com\\\\/Zenger' => $site_url_e,           // urls
					'wp-content/uploads/sites/8'        => $upload_path,          // upload dir unescaped
					'wp-content\\\\/uploads\\\\/sites\\\\/8'  => $upload_path_e         // upload dir escaped
				);

				$SQL = file_get_contents( MDI_PATH . "/sql/sqlfile.sql" );

				

				foreach($replaces as $replace => $with)
				{
					$SQL = str_replace($replace, $with, $SQL);
				}


				if (!$stop)
				{
					echo "<p> Importing the huge SQL file. </p>";
					@ob_flush(); 
					@flush();

					$sql_query = trim($SQL);
					$sql_query = Zenger_SQL_Parse::remove_remarks($sql_query);
					$sql_query = Zenger_SQL_Parse::split_sql_file($sql_query, ';');

					

					$i = 1;
					foreach($sql_query as $sql)
					{
						$i++;
						$wpdb->query($sql);
					}

					if (!$wpdb->last_error)
					{
						echo "<p>SQL File Imported. ".$i." queries where run.</p>";
						@ob_flush(); 
						@flush();
					}
					else
					{
						echo "<p>Error: " . $wpdb->last_error . "</p>";
						@ob_flush(); 
						@flush();
						$stop = true;
					}
				}
				

				// Move Files
				if (!$stop)
				{
					echo "<p> Moving Upload Images </p>";
					@ob_flush(); 
					@flush();

					$uploads_dir = wp_upload_dir();
					zenger_recursive_copy(MDI_PATH . "/uploads/", $uploads_dir['basedir'] );

					echo "<p>All Uploads Have Been Imported </p>";
					@ob_flush(); 
					@flush();

				}

				@ob_flush(); 
				@flush();


				if (!$stop)
				{
					echo "<div id='message' class='updated'><p> The Demo Data Hase Been Imported </p> </div>";
				}
				@ob_flush(); 
				@flush();
			}
		}
		else
		{
			echo "<div id='message' class='error'><p>Can not continue, unless all requirements are met.</p></div>";
		}

		echo "</div>";
	}
}

add_action('init',          array('ZengerDemoImporter', 'init') );
add_action('admin_notices', array('ZengerDemoImporter', 'show_notice'));
