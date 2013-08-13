<?php
/* $Id: html.php 46 2009-06-11 06:46:34Z samwilson $ */
require_once 'HTML/Page2.php';

class Error_Page {
    
    function __construct($title, $message) {
        $page = new HTML_Page2();
    	$page->setTitle($title);
        $page->addStylesheet('screen.css');
    	$page->addBodyContent("<h1>$title</h1><div>$message</div>");
    	$page->display();
    	die();
    }
    
}

class HTML_DB_Table {

    private $table_name;
    private $order_dir;
    private $order_by;
    private $show;
    private $from;
    private $actions;
    private $db;
    private $where_clause;

    public function __construct($db, $table, $where_clause='', $editable=true, $sortable=true, $pageable=true) {
        $this->db = $db;
        $this->table_name = $table;
        $this->where_clause = $where_clause;
        $this->editable = $editable;
        $this->sortable = $sortable;
        $this->pageable = $pageable;
    }
        
    public function toHtml() {
        
        // Prepare column names and types.
        $column_names = $this->db->getColumnNames($this->table_name);
        $column_types = $this->db->getColumnTypes($this->table_name);
        // Get index of ID column.
        $id_index = key(preg_grep("|^id$|i", $column_names));
        // Remove ID column.
        unset($column_names[$id_index]); unset($column_types[$id_index]);
        $columns = array_combine($column_names, $column_types);

        // Set some defaults.
        $this->from = (isset($_GET['from']) && is_numeric($_GET['from'])) ? $_GET['from'] : 0;
        $this->show = (isset($_GET['show']) && is_numeric($_GET['show'])) ? $_GET['show'] : 50;
        $this->order_by = (isset($_GET['order_by'])) ? $_GET['order_by'] : $column_names[1];
        $this->order_dir = (isset($_GET['order_dir'])) ? $_GET['order_dir'] : 'ASC';

        // Get rows to be displayed:
        $sql = "SELECT * FROM ".$this->db->esc($this->table_name)." ";
        if (!empty($this->where_clause)) {
            $sql .= "WHERE $this->where_clause ";
        }

        // Before tacking on the ORDER BY and LIMIT bits, check for total
        // number of rows found (optimising a bit on the way).
        $total_found_count = $this->db->fetchOne(str_replace('*', 'count(*)', $sql));

        // Sort and limit, if required.
        if ($this->sortable) $sql .= "ORDER BY $this->order_by $this->order_dir ";
        $sql .= "LIMIT $this->from,$this->show";

        // Then find the required rows.
        $rows = $this->db->fetchAll($sql);

        // Get total number of rows in this table:
        $total_row_count = $this->db->fetchOne(
            "SELECT count(*) FROM ".$this->db->esc($this->table_name)
        );

    
        $headers = $this->getFormattedHeaderRow(array_keys($columns));

        // Build the actual table.
        require_once 'HTML/Table.php';
        $html_table = new HTML_Table();
        $html_table->addRow($headers, null, 'th');
        foreach ($rows as $row) {
            $id = $row['id'];
            $cells = array();
            $anchor_name = $this->table_name.'-'.$id;
            $edit_link = "<a name='$anchor_name'
                href='form.php?table=$this->table_name&id=$id&return_to=".
                urlencode($_SERVER['REQUEST_URI']."#$anchor_name")."'>".
                "<img src='images/edit.png' title='Edit this row.' /></a>";
            foreach ($columns as $col_name=>$col_type) {
                $cell_type = $col_type;
                $cell_value = $row[$col_name];

                // Do any formatting to any particular cell types here.

                // Booleans
                if ($cell_type=='int(1)'||$cell_type=='tinyint(1)') {
                    if ($cell_value==='1') $cell_value = 'Yes';
                    elseif ($cell_value==='0') $cell_value = 'No';
                    else $cell_value = '';

                    // Foreign keys
                } elseif (
                    ($foreign_table=$this->db->getReferencedTableName($this->table_name,$col_name))
                    && $cell_value>0
                ) {
                    $cell_value = $this->db->getRowTitle($foreign_table, $cell_value);

                    // BLOBS
                } elseif ($cell_type=='blob' || $cell_type=='tinyblob'
                    ||$cell_type=='mediumblob' || $cell_type=='longblob') {
                    $ds = DIRECTORY_SEPARATOR;
                    $blob_filename = $this->table_name.$ds.$id.$ds.$col_name;
                    if (file_exists(DATA_DIR.$ds.$blob_filename)) {
                        $file_metadata = unserialize($cell_value);
                        $cell_value = "<a href='files.php?table=$this->table_name&column=$col_name&id=$id&format=full'>
                        ".$file_metadata['filename']."
                        <img src='files.php?table=$this->table_name&column=$col_name&id=$id&format=thumb' />
                        </a>";
                    } else {
                        $cell_value = "";
                    }
                }

                $cells[] = $cell_value;
            }
            if ($this->editable) {
                $row = array_merge(array($edit_link), $cells);
            } else {
                $row = $cells;
            }
            $html_table->addRow($row);
        }

        // Add bottom headers and centre 'Actions' columns.
        //$html_table->addRow($headers, null, 'th');
        $html_table->setColAttributes(0,array('class'=>'centre'));
        $html_table->setColAttributes($html_table->getColCount()-1,array('class'=>'centre'));

        // Build caption actions.
        if ($total_found_count>$this->show) {
            $caption = "";
            if ($this->pageable) {
                if ($this->from-$this->show<0) {
                    $caption .= " <img src='images/prevpage_disabled.png' alt='Left arrow.' />";
                } else {
                    $prev_url = "?table=$this->table_name&from=".($this->from-$this->show)."&show=$this->show&order_by=$this->order_by&order_dir=$this->order_dir";
                    $caption .= " <a href='$prev_url' title='View previous $this->show rows.'>
                    <img src='images/prevpage.png' alt='Left arrow.' /></a>";
                }
            }
            $caption .= " $this->from&ndash;".($this->from+count($rows))." of $total_found_count rows ";
            if ($this->pageable) {
                if ($this->from+$this->show>$total_found_count) {
                    $caption .= " <img src='images/nextpage_disabled.png' alt='Right arrow.' />";
                } else {
                    $next_url = "?table=$this->table_name&from=".($this->from+$this->show)."&show=$this->show&order_by=$this->order_by&order_dir=$this->order_dir";
                    $caption .= " <a href='$next_url' title='View next $this->show rows.'>
                    <img src='images/nextpage.png' alt='Left arrow.' /></a>";
                }
            }
            $html_table->setCaption($caption);
        }

        return $html_table->toHtml();
    }

    /**
     * @param array An array containing the column names as keys.
     * @return array
     */
    private function getFormattedHeaderRow($column_names) {
        $headers = array();
        if ($this->editable) $headers[] = 'Edit';
        foreach ($column_names as $header) {
            if ($this->sortable) {
                if ($this->order_dir=='ASC') {
                    $new_order_dir = 'DESC';
                    $new_order_title = 'Sort by '.titlecase($header).', descending.';
                } else {
                    $new_order_dir = 'ASC';
                    $new_order_title = 'Sort by '.titlecase($header).', ascending.';
                }
                $headers[] = "<a
                    href='?table=$this->table_name&show=$this->show&from=$this->from&order_by=$header&order_dir=$new_order_dir'
                    title='$new_order_title'
                    >".titlecase($header)."</a>";
            } else {
                $headers[] = titlecase($header);
            }
        }

        return $headers;
    } // getFormattedHeaderRow()

} // DB_Table

?>