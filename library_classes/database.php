<?php /* $Id: database.php 46 2009-06-11 06:46:34Z samwilson $ */

require_once 'HTML/Table.php';
require_once 'html.php';

class Database {

    private $mdb2;
    private $schema;

    public function __construct($dsn) {
        require_once 'MDB2.php';
        $this->mdb2 = MDB2::connect($dsn);
        if (PEAR::isError($this->mdb2)) {
            new Error_Page($this->mdb2->message, $this->mdb2->userinfo);
        }
        $this->mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);
        $this->buildSchema();
    }

    public function buildSchema() {
        $schema = array();
        $db_name = $this->mdb2->getDatabase();
        $tables = $this->fetchAll("SHOW FULL TABLES");
        foreach ($tables as $table) {
            $table_name = $table["tables_in_$db_name"];
            $table_description = $this->fetchAll("DESCRIBE $table_name");
            $defining_sql = $this->fetchAll("SHOW CREATE TABLE ".$this->esc($table_name));
            if (isset($defining_sql[0])) {
                if (isset($defining_sql[0]['create table'])) {
                    $defining_sql = $defining_sql[0]['create table'];
                } elseif (isset($defining_sql[0]['create view'])) {
                    $defining_sql = $defining_sql[0]['create view'];
                }
            } else {
                $defining_sql = "";
            }
            preg_match_all("|FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)`|", $defining_sql, $matches);
            $foreign_keys = array(); // column_name => referenced_table
            if (count($matches[1])>0&&count($matches[2])>0) {
                $foreign_keys = array_combine($matches[1], $matches[2]);
            }
            $columns = array();
            foreach ($table_description as $column) {
                $column_name = $column['field'];
                $column['references'] = (isset($foreign_keys[$column_name]))
                ? $foreign_keys[$column_name]
                : false;
                $columns[$column_name] = $column;
            }
            // Add all this table's data to the main schema array.
            $schema[$table_name] = array(
                'type'=>$table['table_type'],
                'columns' => $columns,
                'defining_sql' => $defining_sql
            );
        }

        // Get extra constraint information about views.
        foreach ($schema as $name=>$table) {
            if ($table['type']!='VIEW' || substr($name,0,7)=='report_') continue;
            preg_match_all('|`(\w*?)`\.`(\w*?)` AS `(\w*?)`|', $table['defining_sql'], $matches);
            if (count($matches[0])>0) {
                for ($i=0; $i<count($matches[0]); $i++) {
                    $view_col_name = $matches[3][$i];
                    $base_col_name = $matches[2][$i];
                    $base_tbl_name = $matches[1][$i];
                    if (!isset($schema[$base_tbl_name])) {
                        continue; // Give up on aliased tables. @TODO
                    }
                    $schema[$name]['columns'][$view_col_name]['references'] = $schema[$base_tbl_name]['columns'][$base_col_name]['references'];
                }
            }
        }

        $this->schema = $schema;
    }

    function query($sql) {
        $res = $this->mdb2->query($sql);
        if (PEAR::isError($res)) {
            $err = array('Error message'=>'Error!','Native message' => $res->userinfo, 'Last executed query'=>$sql);            
            $err_part_pattern = "/\[(.*?): (.*)\]/m";
            preg_match_all($err_part_pattern, $res->userinfo, $err_parts);
            if ( isset($err_parts[2]) && in_array('Last executed query',$err_parts[2])) {
                $err = array_combine($err_parts[1], $err_parts[2]);
            }
            $err['Last executed query'] = str_replace(",", ",\n", $err['Last executed query']);
            new Error_Page($err['Error message'], "
                <p>".$err['Native message']."</p>
                <h2>Last executed query:</h2>
                <pre>".$err['Last executed query']."</pre>
            ");
        } else {
            return $res;
        }
    }

    function save($data) {
        $table = $data['table'];
        $fields = $this->getColumnNames($table);
        if (!isset($table)) {
            new Error_Page('Save Failed!','No table was specified (to which to save the data).');
        }
        $is_update = false;
        $sql = "INSERT INTO `$table` SET ";
        if (!empty($data['id'])) {
            // Allow ID to be specified for new rows; i.e. only UPDATE when ID
            // is set and the row to which it refers actually exists.
            $tmp_sql = "SELECT count(*) FROM `$table` WHERE id='".$this->esc($data['id'])."'";
            if ( $this->fetchOne($tmp_sql)>0 ) {
                $is_update = true;
                $sql = "UPDATE `$table` SET ";
            }
        }

        // Loop through each field, formatting its data and adding the SQL.
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $field_data = $data[$field];
                $field_type = $this->getColumnType($table, $field);
                $foreign_table = $this->getReferencedTableName($table, $field);
                
                // Dates
                if ($field_type=='date') {
                    $field_data = "'".isodate($field_data)."'";

                    // Booleans
                } elseif ($field_type=='int(1)'||$field_type=='tinyint(1)') {
                    //echo $field." ";
                    //var_dump($field_data);
                    //die();
                    if ($field_data==null
                        || $field_data=='') {
                        $field_data = 'NULL';
                    } elseif ($field_data=='0'
                        || $field_data===0
                        || strcasecmp($field_data,'false')===0
                        || strcasecmp($field_data,'off')===0
                        || strcasecmp($field_data,'no')===0) {
                        $field_data = 0;
                    } else {
                        $field_data = 1;
                    }

                // BLOBs
                } elseif ($field_type=='blob' || $field_type=='tinyblob'
                    ||$field_type=='mediumblob' || $field_type=='longblob') {
                    // Processed below, after we've got a row ID.
                    if (is_array($field_data) && isset($field_data['error']) && $field_data['error']==UPLOAD_ERR_OK) {
                        $field_data = "'TEMPDATA'"; // In case this field is NOT NULL.
                        $blobs[] = $field;
                    } else {
                        continue;
                    }

                // Foreign keys
                } elseif ($foreign_table && $field_data<=0) {
                    $field_data = 'NULL';

                    // Numbers
                } elseif (!is_numeric($field_data)
                    && (substr($field_type,0,3)=='int'
                        ||substr($field_type,0,7)=='decimal'
                        ||substr($field_type,0,5)=='float')
                ) {
                    $field_data = 0;

                } else {
                    $field_data = "'".$this->esc($field_data)."'";
                }

                $sql .= "`$field` = $field_data, ";
            }
        } // end main field/data loop.

        $sql = substr($sql, 0, -2); // Remove last comma-space.
        if ($is_update) {
            $sql .= " WHERE id='".$this->esc($data['id'])."'";
        }

        // Execute the SQL built above.
        if ($this->query($sql)) {
            if (!$is_update) {
                $id = mysql_insert_id();
            } else {
                $id = $data['id'];
            }
            // Process blobs (store in filesystem).
            if (isset($blobs)) {
                foreach ($blobs as $blob) {
                    //if (is_array($blob) && isset($blob['error']) && $blob['error']==UPLOAD_ERR_OK) {
                    $ds = DIRECTORY_SEPARATOR;
                    $dest_dir = DATA_DIR.$ds.$table.$ds.$id;
                    if (!file_exists($dest_dir)) {
                        mkdir($dest_dir, 0700, true);
                    }
                    // Save file metadata in the blob field:
                    $metadata = serialize(array('filename'=>$data[$blob]['name']));
                    $this->query("UPDATE `$table` set `$blob` = '$metadata' WHERE `id`='$id'");
                    $dest_filename = $dest_dir.$ds.$blob; //.$file_extension;
                    if (!move_uploaded_file($data[$blob]['tmp_name'], $dest_filename)) {
                        die("The file could not be saved to $dest_filename.");
                    }
                    //}
                }
            }

            return true;
        } else {
            return false;
        }
    }

    function fetchOne($sql) {
        $r = $this->query($sql);
        $results = $r->fetchOne();
        return $results;
    }

    function fetchRow($sql) {
        $r = $this->query($sql);
        if ($r->numRows()==0) {
            return false;
        }
        $results = $r->fetchRow();
        return $results;
    }

    function fetchAll($sql) {
        $r = $this->query($sql);
        $results = $r->fetchAll();
        return $results;
    }

    function esc($str) {
        return $this->mdb2->escape($str);
    }

    /**
     * Whether a given table exists or not.
     * @param string $table_name The table name to check the existence of.
     * @return boolean
     */
    function tableExists($table_name) {
        return isset($this->schema[$table_name]);
    }
    
    function getTables() {
        $out = array();
        foreach ($this->schema as $name=>$table) {
            $out[$name] = $table['type'];
        }
        return $out;
    }
    
    function getTableType($table) {
        $tables = $this->getTables();
        return $tables[$table];
    }

    /**
     * @param bool $including_hidden Whether or not to include tables whose name
     * begins with an underscore (for it is thus that special tables are
     * signified).
     * @param string To add an additional NOT LIKE clause if wanted.
     */
    function getTableNames($including_hidden=false, $exclude_pattern='') {
        return array_keys($this->schema);
    }

    /**
     * Gets the name of the table referred to by $tbl.$field
     * @return string|false Name of table, or false if not a FK.
     */
    function getReferencedTableName($table, $column) {
        return $this->schema[$table]['columns'][$column]['references'];
    }
    
    /**
     * Get a list of which columns refer to which tables.    
     * @return array Of the format: array(table => <tblname>, column => <colname>)    
     */    
    function getTablesReferringTo($table) {
        $out = array();
        foreach ($this->schema as $table_name=>$table_details) {
            if ($table_details['type']!='BASE TABLE') continue; 
            foreach ($table_details['columns'] as $column_name=>$column_details) {
                if (isset($column_details['references']) && $column_details['references']==$table) {
                    $out[] = array('column'=>$column_name, 'table'=>$table_name);
                }
            }
        }
        return $out;
    }

    function getColumnNames($tbl) {
        return array_keys($this->schema[$tbl]['columns']);
    }

    function getColumnType($table, $column) {
        return $this->schema[$table]['columns'][$column]['type'];
    }

    function getColumnTypes($table) {
        $out = array();
        foreach ($this->schema[$table]['columns'] as $col) {
            $out[] = $col['type'];
        }
        return $out;
    }

    /**
     *
     * @param string $table
     * @return string|false
     */
    function getFirstNonIdColumnName($table) {
        foreach ($this->getColumnNames($table) as $col) {
            if ($col!='id') return $col;
        }
        return false;
    }

    /**
     * Get a human-readable title for a given row: either the first column that
     * is not 'id'; or if this is a foreign key, then the row title of the row
     * referred to.
     *
     * @param string $table
     * @param string $row_id
     * @return string The row title.
     */
    public function getRowTitle($table, $row_id) {
        $first_non_id_column_name = $this->getFirstNonIdColumnName($table);
        $first_non_id_column_value = $this->fetchOne(
            "SELECT `$first_non_id_column_name` FROM `$table` WHERE id=".$this->esc($row_id)
        );
        // If the first non-ID column is a foreign key, follow it and get the
        // title of the foreign row ... ad infinitum!
        if ($foreign_table = $this->getReferencedTableName($table, $first_non_id_column_name)) {
            return $this->getRowTitle($foreign_table, $first_non_id_column_value);
        } else {
            return $first_non_id_column_value;
        }
    }

}

?>