<?php
//------------------------------------------------------------------------------
//  Pi.Alert
//  Open Source Network Guard / WIFI & LAN intrusion detector 
//
//  db.php - Front module. Server side. DB common file
//------------------------------------------------------------------------------
//  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
//  leiweibau 2025+   https://github.com/leiweibau          GNU GPLv3
//------------------------------------------------------------------------------

//------------------------------------------------------------------------------
// DB File Path
$DBFILE = '../../../db/pialert.db';
$DBFILE_TOOLS = '../../../db/pialert_tools.db';


//------------------------------------------------------------------------------
// Connect DB
//------------------------------------------------------------------------------
function SQLite3_connect ($trytoreconnect) {
  global $DBFILE;
  try
  {
    // connect to database
    return new SQLite3($DBFILE, SQLITE3_OPEN_READWRITE);
  }
  catch (Exception $exception)
  {
    // sqlite3 throws an exception when it is unable to connect
    // try to reconnect one time after 3 seconds
    if($trytoreconnect)
    {
      sleep(3);
      return SQLite3_connect(false);
    }
  }
}


//------------------------------------------------------------------------------
// Open DB
//------------------------------------------------------------------------------
function OpenDB () {
  global $DBFILE;
  global $db;

  if(strlen($DBFILE) == 0)
  {
    die ('Database no available');
  }

  $db = SQLite3_connect(true);
  if(!$db)
  {
    die ('Error connecting to database');
  }
  
  $db->busyTimeout(2000);
  $db->exec('PRAGMA journal_mode = wal;');
}


//------------------------------------------------------------------------------
// Connect DB-Tools
//------------------------------------------------------------------------------
function SQLite3_tools_connect ($trytoreconnect) {
  global $DBFILE_TOOLS;
  try
  {
    // connect to database
    return new SQLite3($DBFILE_TOOLS, SQLITE3_OPEN_READWRITE);
  }
  catch (Exception $exception)
  {
    // sqlite3 throws an exception when it is unable to connect
    // try to reconnect one time after 3 seconds
    if($trytoreconnect)
    {
      sleep(3);
      return SQLite3_tools_connect(false);
    }
  }
}


//------------------------------------------------------------------------------
// Open DB-Tools
//------------------------------------------------------------------------------
function OpenDB_Tools () {
  global $DBFILE_TOOLS;
  global $db_tools;

  if(strlen($DBFILE_TOOLS) == 0)
  {
    die ('Tools Database no available');
  }

  $db_tools = SQLite3_tools_connect(true);
  if(!$db_tools)
  {
    die ('Error connecting to Tools database');
  }
  
  $db_tools->busyTimeout(2000);
  $db_tools->exec('PRAGMA journal_mode = wal;');
}
//------------------------------------------------------------------------------
// Prepared statement helpers
//------------------------------------------------------------------------------
function db_sqlite_type($value) {
  if (is_int($value)) {
    return SQLITE3_INTEGER;
  }
  if (is_null($value)) {
    return SQLITE3_NULL;
  }
  return SQLITE3_TEXT;
}

// Parameters may be scalar values or [value, SQLITE3_*] pairs.
function db_execute_prepared($database, $sql, $parameters = array()) {
  $statement = $database->prepare($sql);
  if ($statement === false) {
    return false;
  }

  foreach ($parameters as $placeholder => $parameter) {
    $value = $parameter;
    $type = db_sqlite_type($value);
    if (is_array($parameter) && count($parameter) === 2) {
      $value = $parameter[0];
      $type = $parameter[1];
    }

    if (!$statement->bindValue($placeholder, $value, $type)) {
      return false;
    }
  }

  return $statement->execute();
}

// Return a comma-separated placeholder list and its matching parameters.
// Callers must reject an empty list instead of creating an empty IN clause.
function db_in_placeholders($prefix, $values, $type = SQLITE3_TEXT) {
  $placeholders = array();
  $parameters = array();

  foreach (array_values($values) as $index => $value) {
    $placeholder = ':' . $prefix . '_' . $index;
    $placeholders[] = $placeholder;
    $parameters[$placeholder] = array($value, $type);
  }

  return array(implode(', ', $placeholders), $parameters);
}
?>
