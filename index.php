<?php /* $Id: index.php 46 2009-06-11 06:46:34Z samwilson $ */
require_once 'common.php';

class CAWITM_Browser extends CAWITM {

    private $table_name;

    function __construct() {
        parent::__construct();
        if (isset($_GET['table'])) {
            $this->table_name = $_GET['table'];
            $this->htmlPage->addBodyContent("<h2>Browsing ".titlecase($this->table_name).
            "<a href='form.php?table=$this->table_name&return_to=".urlencode($_SERVER['REQUEST_URI'])."'
            title='Add new row&hellip;'> <img src='images/new_row.png' alt='New row icon.' />
            </a></h2>");
            $this->loadSearchForm();
            
            $where_clauses = "";
            if (isset($_SESSION['search_terms']) && !empty($_SESSION['search_terms'])) {
                $terms = array_unique(preg_grep("|\S+|", preg_split("|\r?\n|", trim($_SESSION['search_terms']))));
                if (count($terms)>0) {
                    foreach ($terms as $term) {
                        $term = trim($term);
                        foreach ($this->db->getColumnNames($this->table_name) as $col) {
                            $where_clauses .= "`$col` LIKE '%$term%' OR ";
                        }
                    }
                    $where_clauses = substr($where_clauses,0,-4); // remove trailing OR.
                }
            }
            $html_table = new HTML_DB_Table($this->db, $this->table_name, $where_clauses);
            $this->htmlPage->addBodyContent($html_table->toHtml());

        } else {
            $this->htmlPage->addBodyContent("<p class='good message'>Please select from the menu at right.</p>");
        }
        $this->htmlPage->display();
    }

    function loadSearchForm() {
        if (isset($_POST['search_terms'])) $_SESSION['search_terms'] = $_POST['search_terms'];
        $search_terms = (isset($_SESSION['search_terms'])) ? $_SESSION['search_terms'] : '';
        $this->htmlPage->addBodyContent("<form id='search-form' action='index.php?table=$this->table_name' method='post'>
        <fieldset><legend>Search <img src='images/search.png' alt='Magnifying glass icon' /></legend>
        <textarea name='search_terms'>$search_terms</textarea><br />
        <input type='submit' value='Search' />
        </fieldset></form>");
    }

} // class

new CAWITM_Browser();
