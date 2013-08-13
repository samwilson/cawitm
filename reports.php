<?php /* $Id: reports.php 46 2009-06-11 06:46:34Z samwilson $ */
require_once 'common.php';

class CAWITM_Reports extends CAWITM {

    private $defining_sql; // What made the current view.
    private $report_name;

    function __construct() {
        parent::__construct();

        $table_names = preg_grep('|^report_|i', $this->db->getTableNames());
        if (isset($_GET['report']) && in_array($_GET['report'],$table_names)) {
            $this->report_name = $_GET['report'];
            $defining_sql = $this->db->fetchRow("SHOW CREATE VIEW `$this->report_name`");
            $this->defining_sql = $defining_sql['create view'];

            // Export CSV:
            if ( isset($_GET['format']) && $_GET['format']=='csv') {
                $this->exportCsv($this->createTempTable(), $this->report_name);
            }

            // Display HTML table:
            $this->htmlPage->addBodyContent("<h2>".titlecase($this->report_name)."</h2>");
            if (!$this->loadFormVariablesForm($this->report_name)) {
                $this->htmlPage->addBodyContent($this->getHtmlTable($this->report_name));
            }
            $this->htmlPage->addBodyContent($this->getHighlightedQuery());
        }
        $this->htmlPage->display();
    }

    private function loadFormVariablesForm($report) {
        preg_match_all('|`([a-z_]+?)`\.`([a-z_]+?)`[=<>A-Za-z0-9_ ]+?\'.?{(.*?)}.?\'|', $this->defining_sql, $matches);
        if (count(array_unique($matches[3]))>0) {
            $base_tables = $matches[1];
            $base_columns = $matches[2];
            $user_variables = array_unique($matches[3]);
            require_once 'HTML/QuickForm.php';
            $var_form = new HTML_QuickForm('','post',"reports.php?report=$this->report_name");
            $var_form->addElement('header','','Report Variables');
            for ($i=0;$i<count($user_variables);$i++) {
                $var_name = $user_variables[$i];
                $base_col_name = $base_columns[$i];
                $base_table_name = $base_tables[$i];
                $var_type = $this->db->getColumnType($base_tables[$i], $base_col_name);
                if (substr($var_type,0,4)=='date'||substr($var_type,0,8)=='datetime') {
                    $input_type = 'date';
                    $years = $this->db->fetchAll("SELECT 
                        MIN(YEAR($base_col_name)) AS `min`, MAX(YEAR($base_col_name)) AS `max` 
                        FROM $base_table_name WHERE YEAR($base_col_name)!=0");
                    $options = array('minYear'=>$years[0]['min'], 'maxYear'=>$years[0]['max']);
                } else {
                    $input_type = 'select';
                    //$non_id_col = $this->db->getFirstNonIdColumnName($base_table_name);
                    $sql = "SELECT $base_col_name FROM $base_table_name GROUP BY $base_col_name";
                    $options = array();
                    foreach ($this->db->fetchAll($sql) as $option) {
                        $options[$option[$base_col_name]] = $option[$base_col_name];
                    }
                }
                $var_form->addElement($input_type,"user_variables[$var_name]",titlecase($var_name).": ",$options);
            }
            $var_form->addElement('submit','','Get Report');
            $this->htmlPage->addBodyContent($var_form);
        } else {
            return false;
        }
        if (isset($_POST['user_variables'])) {
            $_SESSION['user_variables'] = $_POST['user_variables'];
            $this->createTempTable();
            $this->htmlPage->addBodyContent($this->getHtmlTable("temp_$this->report_name", $this->report_name));
        }
        return true;
    }

    function createTempTable() {
        $select_stmt = substr($this->defining_sql, stripos($this->defining_sql,'SELECT'));
        if (isset($_SESSION['user_variables'])) {
            foreach ($_SESSION['user_variables'] as $key=>$val) {
                if (is_array($val)) $val = isodate($val); // Only dates are passed as arrays.
                $select_stmt = preg_replace("|\{$key\}|", $val, $select_stmt);
            }
            $this->db->query("CREATE TEMPORARY TABLE IF NOT EXISTS temp_$this->report_name AS $select_stmt");
            return "temp_$this->report_name";
        } else {
            return $this->report_name;
        }
    }

    private function getHtmlTable($view, $headers_view=null) {
        if ($headers_view==null) $headers_view = $view;
        $rows = $this->db->fetchAll("SELECT * FROM `".$this->db->esc($view)."`");
        require_once 'HTML/Table.php';
        $html_table = new HTML_Table();
        $html_table->setCaption(count($rows)." rows shown.  <a href='?report=$this->report_name&format=csv' title='Download CSV file of this table.'><img src='images/export.png' /></a>");
        $html_table->addRow(titlecase($this->db->getColumnNames($headers_view)), null, 'th');
        foreach ($rows as $row) {
            $row = array_values($row); // remove keys.
            $html_table->addRow($row);
        }
        return $html_table->toHtml();
    }

    private function exportCsv($view, $headers_view=null) {
        if ($headers_view==null) $headers_view = $view;
        $filename = TEMP_DIR.DIRECTORY_SEPARATOR.md5(time()).".csv";
        $file = fopen($filename, "w");

        // Write column headers.
        $headers = titlecase($this->db->getColumnNames($headers_view));
        $header_line = "";
        foreach ($headers as $header) {
            $header_line .= '"'.$header.'",';
        }
        fwrite($file, substr($header_line,0,-1)."\n"); // Knock off the trailing comma.

        // Write body of file.
        $rows = $this->db->fetchAll("SELECT * FROM $view");
        foreach ($rows as $row) {
            $line = "";
            foreach ($row as $col) {
                $line .= '"'.$col.'",';
            }
            fwrite($file, substr($line,0,-1)."\n"); // Knock off the trailing comma.
        }

        // Output file to client.
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.ms-excel;'); // This should work for IE & Opera
        header("Content-type: application/x-msexcel");     // This should work for the rest
        header('Content-Disposition: attachment; filename='.date('Y-m-d').'_'.$view.'.csv');
        header('Content-Transfer-Encoding: none');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: '.filesize($filename));
        ob_clean();
        flush();
        readfile($filename);
        unlink($filename);
        exit;

    }
    
    function getHighlightedQuery() {
        $sql = substr($this->defining_sql, stripos($this->defining_sql,'select'));
        $sql = str_ireplace(",", ",\n\t", $sql);
        foreach (array('SELECT','FROM','WHERE','ORDER BY','GROUP BY') as $keyword) {
            $sql = str_ireplace($keyword, "\n$keyword", $sql);
        }
        $this->htmlPage->addStylesheet("syntax_highlighting.css");
        require_once "Text/Highlighter.php";
        $highlighter =& Text_Highlighter::factory("SQL");
        $out = "<h2>Query</h2>".
        "<p>Below is the query used to produce this report.</p>".
        "<div>".$highlighter->highlight($sql)."</div>";
        return $out;
    }

}

new CAWITM_Reports();