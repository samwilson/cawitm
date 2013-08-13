<?php
/* $Id: admin.php 45 2009-06-10 02:15:14Z samwilson $ */
require_once 'common.php';

class CAWITM_Admin extends CAWITM {

    function __construct() {
        parent::__construct();
        if ($this->auth && $this->auth->getUsername()!='admin') {
            $this->htmlPage->addBodyContent("<p>
                Only administrators are allowed to access this page, sorry.
                </p>");
            $this->htmlPage->display();
            die();
        }
        
        $this->htmlPage->addBodyContent("<ul>
            <li><a href='?action=edit_views'>Edit views.</a></li>
        </ul>");

        if (isset($_GET['action']) && function_exists($_GET['action']) ) {
            $_GET['action']();
        }        
        
        $this->htmlPage->display();
    } // __construct
    
    private function edit_views() {
     /*
        $report_names = preg_grep('|^report_|i', $this->db->getTableNames());
        foreach ($report_names as $report_name) {
            $report_title = titlecase($report_name);
            if (isset($_GET['report']) && $_GET['report']==$report_name) {
                $this->htmlPage->addBodyContent("<li><strong>$report_title</strong></li>");
            } else {                
                $this->htmlPage->addBodyContent("<li><a href='?view=$report_name'>$report_title</a></li>");            
            }
        }
        $this->htmlPage->addBodyContent("</ul>");
        if (isset($_GET['report'])&&in_array($_GET['report'],$report_names)) {
            $this->htmlPage->addBodyContent("<h2>".titlecase($_GET['view'])."</h2>".$this->getHtmlTable($_GET['view']));
        }*/
    }
    
} // class

new CAWITM_Admin();