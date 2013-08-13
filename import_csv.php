<?php
/* $Id$ */
require_once 'common.php';

class CAWITM_CsvImporter extends CAWITM {

    function __construct() {
        parent::__construct();

        if ($this->auth && $this->auth->getUsername()!='admin') {
            $this->htmlPage->addBodyContent("<p class='bad message'>
                Only administrators are allowed to access this page, sorry.
                </p>");
            $this->htmlPage->display();
            die();
        }

        require_once 'HTML/QuickForm.php';
        require_once 'File/CSV/DataSource.php';
        if (isset($_POST['import'])) {
            $this->importFromLocalFile();
        } else {
            $this->loadUploadForm();
        }
        $this->htmlPage->display();
    }

    /**
     * If the columns to be imported have been selected, do the actual import into
     * the database.
     */
    function importFromLocalFile() {
        // We're passing in the temp file's hash, rather than it's full path,
        // to prevent arbitrary deletes.
        $filename = TEMP_DIR."/{$_POST['filename_hash']}.csv";

        // Make sure the file's still there.
        if (!file_exists($filename)) die("An error occured.  Please try again.");

        header("Content-type:text/plain");

        // Go through each row of import data and match up each column to a field.
        require_once 'File/CSV/DataSource.php';
        $import = new File_CSV_DataSource($filename);
        $rows = $import->getRows();
        $total_updated = 0;
        for ($row_num=0; $row_num<count($rows); $row_num++) {
            $row = $rows[$row_num];
            echo "Importing row $row_num:  ";
            foreach ($_POST['columns'] as $col_num=>$field_name) {
                if ( !empty($field_name) && isset($row[$col_num]) ) {
                    $row_data[$field_name] = $row[$col_num];
                    echo $row[$col_num]."\t";
                }
            }
            echo "\n";

            // Save the row to the DB.
            $row_data['table'] = $_POST['table'];
            if ($this->db->save($row_data)) $total_updated++;

        }

        // Delete the temporary uploaded file.
        unlink($filename);

        echo "\n\n\nSuccessfully imported $total_updated rows into ".titlecase($_POST['table']).".";
        die();

    }

    /**
     *
     */
    function loadUploadForm() {
        $this->htmlPage->addBodyContent("<h2>Import data from a CSV file</h2>");
        if (count($this->db->getTableNames())) {
            $form = new HTML_QuickForm();
            $form->addElement('static','','Notes:','This file must contain a header row,');
            $form->addElement('static','','','and must not exceed '.ini_get('upload_max_filesize').'.');
            $form->addElement('file','csv_file','Select file:');

            $db_tables = array_combine($this->db->getTableNames(), titlecase($this->db->getTableNames()));
            $db_tables = array_merge(array(''=>''), $db_tables); // Add a blank top selection.
            $form->addElement('select','table','Import into:', $db_tables);
            $form->addRule('table', 'Please select a destination for your import.', 'required');

            $form->addElement('submit','upload','Import');
            $form->addRule('csv_file', 'You must select a file to import.', 'uploadedfile');
            $form->addRule('csv_file', 'You must select a CSV file to import.', 'filename', '|.*\.csv|');
            $this->htmlPage->addBodyContent($form);

            if ($form->validate()) {
                $form->process(array($this, 'importCsv'));
            }

        } else {
            $this->htmlPage->addBodyContent(
                $this->getMessage("No tables found in database.", "indifferent")
            );


        }

    }

    /**
     *
     */
    function importCsv($form) {
        $table_name = $form['table'];
        $this->htmlPage->addBodyContent("<h2>Importing <code>".$form['csv_file']['name']."</code>
            into ".titlecase($table_name).".</h2>");

        // Move the uploaded file somewhere more local.
        $uploaded_file_hash = md5(time());
        $uploaded_filename = TEMP_DIR."/$uploaded_file_hash.csv";
        move_uploaded_file($form['csv_file']['tmp_name'], $uploaded_filename);

        require_once 'File/CSV/DataSource.php';
        $csv_datasource = new File_CSV_DataSource($uploaded_filename);

        $this->htmlPage->addBodyContent("<p>This file contains ".$csv_datasource->countRows()."
            rows.</p><p>Please review the table
            below to ensure that your data is imported into the correct fields.
            Only those fields that are selected in the drop-down menus at the right
            will be imported.</p>"
        );
        require_once 'HTML/Table.php';
        $table = new HTML_Table();
        $table->addRow(array(
            'File headers:',
            'First row of import:',
            'Last row of import:',
            'Import to this field:'
            ), 'th');
        $headers = $csv_datasource->getHeaders();
        $rows = $csv_datasource->getRows();
        $row_count = count($rows);
        for ($i=0; $i<count($headers); $i++) {
            $first_row_data = (isset($rows[0][$i])) ? $rows[0][$i] : '';
            $last_row_data = (isset($rows[$row_count-1][$i])) ? $rows[$row_count-1][$i] : '';
            $table->addRow(array(
                    titlecase($headers[$i]),
                    $first_row_data,
                    $last_row_data,
                    $this->getFieldSelect($table_name, $i, $headers[$i])
                ));
        }
        $table->setRowType(0, 'th');
        $this->htmlPage->addBodyContent("
            <form action='' method='post'>
            <p style='display:none'>
                <input type='hidden' name='filename_hash' value='".$uploaded_file_hash."' />
                <input type='hidden' name='table' value='$table_name' />
            </p>
            ".$table->toHtml()."
            <p class='submit'>
                <input type='submit' name='import' value='Import ".$form['csv_file']['name']." &rarr;' />
            </p>
            </form>
        ");
    }



    /**
     * Return the HTML needed for the select elements used to map incoming columns
     * to their respective database fields.  If a column name is close to a field
     * name, we try to select it.
     *
     * @return string HTML
     */
    function getFieldSelect($table_name, $col_number, $col_name) {

        // Setup objects
        global $db;
        require_once 'HTML/QuickForm/select.php';
        $out = new HTML_QuickForm_select("columns[$col_number]");

        // Populate the options, including a blank first option.
        $field_names = $this->db->getColumnNames($table_name);
        $out->load(array_merge(
                array(''=>''),
                array_combine($field_names, titlecase($field_names))
            ));

        // If the field name is just the column header lowercase and underscored.
        // Works for most fields, and is easy for the user to make work for all.
        $poss_field_name = strtolower(preg_replace('| |', '_', trim($col_name)));
        $out->setSelected(array($poss_field_name));
        return $out->toHtml();
    }

} // class

new CAWITM_CsvImporter();
