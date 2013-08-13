<?php /* $Id: form.php 47 2009-06-11 06:59:03Z samwilson $ */
require_once 'common.php';

class CAWITM_Form extends CAWITM {

    private $table_name;
    private $id;

    function __construct() {
        parent::__construct();

        require_once 'HTML/QuickForm.php';
        require_once 'HTML/QuickForm/radio.php';
        require_once 'HTML/QuickForm/group.php';

        if (!isset($_REQUEST['table']) 
            || !$this->db->tableExists($_REQUEST['table'])) {
            header("Location:http://".$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\')."index.php");
            die();
        }
        // TODO: refactor $table to refer to $this->table_name.
        $table = $_REQUEST['table'];
        $this->table_name = $_REQUEST['table'];
        if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
            $id = $_REQUEST['id'];
        } else {
            $id = false;
        }
        $this->id = $id; // TODO: change $id below to $this->id.

        $action_text = ($id) ? "Editing row $id of" : "Adding new row to";
        $this->htmlPage->addBodyContent("<h2>$action_text <em class='sc'>".titlecase($table)."</em></h2>");
        if ($this->db->getTableType($this->table_name)=='VIEW') {
            $this->htmlPage->addBodyContent("<p class='indifferent message'>
            This is a restricted view of this data, so related tables are not shown.
            </p>");
        }
                
        // If editing a row:
        if ($id) {
            $sql = "SELECT * FROM `$table` WHERE id=$id";
            $data = $this->db->fetchRow($sql);

        // If creating a new row:
        } else {
            $data = array();
        }

        $form = new HTML_QuickForm($_SERVER['PHP_SELF']);

        // Loop through each form field.
        foreach ($this->db->getColumnNames($table) as $col_name) {
            $col_label = titlecase($col_name).': ';
            $col_type = $this->db->getColumnType($table,$col_name);
            $options = null; // These two are used by a few of the different
            $values = null; //  column types below; we're just resetting them.

            // ID shouldn't be edited.
            if ($col_name=='id') {
                if ($id) {
                    $form->addElement('hidden', $col_name, $id);
                }

                // Foreign keys
            } elseif ($foreign_table = $this->db->getReferencedTableName($table,$col_name)) {
                if ($this->db->fetchOne("SELECT COUNT(*) FROM ".$this->db->esc($foreign_table))>100) {
                    require_once 'HTML/QuickForm/text.php';
                    $element = new HTML_QuickForm_text($col_name, $col_label);
                    $message = "<em>ID of related record shown (there are too many possibilities to fit in a drop-down list).</em><br />";
                } else {
                    $foreign_select = "SELECT * FROM ".$this->db->esc($foreign_table).
                    " ORDER BY ".$this->db->getFirstNonIdColumnName($foreign_table);
                    $foreign_rows = $this->db->fetchAll($foreign_select);
                    $values = array(0=>"");
                    foreach ($foreign_rows as $r) {
                        $values[$r['id']] = $this->db->getRowTitle($foreign_table, $r['id']);
                    }
                    require_once 'HTML/QuickForm/select.php';
                    $element = new HTML_QuickForm_select($col_name, $col_label, $values);
                    $message = "";
                }
                $edit_message = ($this->id && isset($data[$col_name])) ? "<a href='form.php?table=$foreign_table&id=".$data[$col_name]."'>[Edit]</a>, " : "";
                require_once 'HTML/QuickForm/static.php';
                $fk_group = new HTML_QuickForm_group(null, $col_label, array(
                    $element,
                    new HTML_QuickForm_static(null, null, $message." <strong>".
                    titlecase($foreign_table).": <a href='index.php?table=$foreign_table'>[Browse]</a>, ".
                    $edit_message." or ".
                    "<a name='$col_name'
                    href='form.php?table=$foreign_table&return_to=".urlencode($_SERVER['REQUEST_URI']."#$col_name")."'>
                    [Add]</a>.</strong>  ".
                    "Changes to this form will be lost! <img src='images/warning.png'>"
                    )
                ), null, false);
                $form->addElement($fk_group);

                // Date
            } elseif ($col_type=='date') {
                $options = array('addEmptyOption'=>true, 'emptyOptionValue'=>0, 'emptyOptionText'=>'');
                $form->addElement('date',$col_name, $col_label, $options);

                // Boolean
            } elseif ($col_type=='tinyint(1)' || $col_type=='int(1)') {
                $col_label = substr($col_label, 0, -2)."?";
                $bool_group = new HTML_QuickForm_group($col_name,$col_label,array(
                        new HTML_QuickForm_radio($col_name,null,'Yes',1),
                        new HTML_QuickForm_radio($col_name,null,'No',0)
                    ), null, false);
                $form->addElement($bool_group);

                // Enumerated fields
            } elseif (substr($col_type,0,4)=='enum') {
                preg_match_all("|'(.*?)'|", $col_type, $matches);
                $values = $matches[1];
                $form->addElement('select', $col_name, $col_label, array_combine($values,$values));

                // Numbers
            } elseif (substr($col_type,0,3)=='int'||substr($col_type,0,5)=='float') {
                preg_match("|\(([0-9]+),?.*\)|", $col_type, $length);
                $length = (isset($length[1])) ? $length[1] : 30;
                $options = array('size'=>$length);
                $form->addElement('text',$col_name, $col_label, $options);
                $form->addRule($col_name, "This should be a number.", 'numeric', 'client');
                $form->addRule($col_name, "Must not exceed $length digits.",'maxlength',$length);

                // VARCHARs
            } elseif (substr($col_type,0,7)=='varchar') {
                preg_match("|\(([0-9]+)\)|", $col_type, $length);
                if (isset($length) && isset($length[1]) && $length[1]<70) {
                    $options = array('size'=>$length[1]);
                    $field_type = 'text';
                } else {
                    $options = array('rows'=>2,'cols'=>66);
                    $field_type = 'textarea';
                }
                $form->addElement($field_type, $col_name, $col_label, $options);
                $form->addRule($col_name, "Must not exceed ".$length[1]." characters.",
                    'maxlength', $length[1]);

                // Text
            } elseif ($col_type=='text'||$col_type=='longtext') {
                $form->addElement('textarea',$col_name,$col_label,array('rows'=>3,'cols'=>66));

                // Blobs (stored on filesystem, not in DB)
            } elseif ($col_type=='blob' || $col_type=='tinyblob'
                ||$col_type=='mediumblob' || $col_type=='longblob') {
                require_once 'HTML/QuickForm/file.php';
                require_once 'HTML/QuickForm/static.php';
                $file_element = new HTML_QuickForm_file($col_name,$col_label);
                if ($id) {
                    $ds = DIRECTORY_SEPARATOR;
                    $filename = $table.$ds.$id.$ds.$col_name;
                    if (file_exists(DATA_DIR.$ds.$filename)) {
                        $sql = "SELECT $col_name FROM $table WHERE id=$id";
                        $filename = unserialize($this->db->fetchOne($sql));
                        $blob_info = "Currently: ".
                        "<a href='files.php?table=$table&column=$col_name&id=$id&format=full'>
                        ".$filename['filename']."</a>
                        <img src='files.php?table=$table&column=$col_name&id=$id&format=thumb' />";
                    } else {
                        $blob_info = "Currently empty.";
                    }
                    $info_element = new HTML_QuickForm_static(null, null, $blob_info);
                } else {
                    $info_element = new HTML_QuickForm_static(null, null, '');
                }
                $form->addElement('group',null,$col_label,array($file_element,$info_element),null,false);
                
                // Default (text)
            } else {
                $form->addElement('text',$col_name, $col_label, array());
            }

        }

        $form->addElement('hidden','table',$table);
        if (isset($_REQUEST['return_to'])) {
            $form->addElement('hidden','return_to',$_REQUEST['return_to']);
        }
        $form->addElement('submit','save','Save');

        // Save submitted data to the DB.
        $saved = false;
        if ($form->validate() && isset($_POST['save'])) {
            $saved = $form->process(array($this->db, 'save'));
        }

        // Finish the form and display it.
        $form->setDefaults($data);

        if ($saved && isset($_REQUEST['return_to'])) {
            header("Location:{$_REQUEST['return_to']}");
        } else {
            $this->htmlPage->addBodyContent($form);
        }
        
        // Add related tables.
        if ($id) $this->htmlPage->addBodyContent($this->getRelatedTables());
        
        $this->htmlPage->display();

    } // __construct()
    
    function getRelatedTables() {
        $out = "";
        $tables_referring_here = $this->db->getTablesReferringTo($this->table_name);
        if (count($tables_referring_here)>0) {
            $out = "<h2>Related Tables</h2>";
            if (count($tables_referring_here)>1) {
                $out .= "<ul>";
                foreach ($tables_referring_here as $table) {
                    $out .= "<li><a href='#related-".$table['table']."-".$table['column']."'>";
                    $out .= titlecase($table['table'])." (as ".titlecase($table['column']).")";
                    $out .= "</a></li>";
                }
                $out .= "</ul>";
            }
            foreach ($tables_referring_here as $table) {
                $ftbl = $table['table'];
                $fcol = $table['column'];
                $out .= "<form action='form.php?table=$ftbl' method='post'>
                <input type='hidden' name='return_to' value='form.php?table=$this->table_name&id=$this->id#related-$ftbl-$fcol' />
                <h3><a name='related-$ftbl-$fcol'>
                ".titlecase($ftbl)." (as ".titlecase($fcol).")
                </a>
                <input name='$fcol' value='$this->id' type='image' src='images/new_row.png' alt='New row icon.' />
                </h3></form>";

                $where = $this->db->esc($table['column'])."=".$this->db->esc($this->id);
                $tbl = new HTML_DB_Table($this->db, $table['table'], $where, true, false, false);
                $out .= $tbl->toHtml();
            }
        }
        return $out;
    }

} // class

new CAWITM_Form();
