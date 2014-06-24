<?php /* $Id: common.php 46 2009-06-11 06:46:34Z samwilson $ */

class CAWITM {

    protected $db;
    protected $auth;
    protected $htmlPage;
    protected $config;

    function __construct() {

        if (!file_exists('config.php')) die('Configuration File Not Found');
        require_once 'config.php';
        $this->config = $config;

        define('DATA_DIR',$this->config['data_dir']);
        if (!file_exists(DATA_DIR)) {
            mkdir(DATA_DIR, 0700, true);
        }
        if (!is_writable(DATA_DIR)) {
            die(DATA_DIR." is not writable.");
        }
        define('TEMP_DIR',$this->config['data_dir'].DIRECTORY_SEPARATOR.'temporary_files');
        if (!file_exists(TEMP_DIR)) {
            mkdir(TEMP_DIR, 0700, true);
        }
        require_once 'vendor/autoload.php';
        require_once 'library_functions.php';
        require_once 'library_classes/database.php';
        require_once 'library_classes/html.php';

        // Set up database.
        $dsn = 'mysql://'.$config['db_user'].':'.$config['db_pass'].'@'.$config['db_host'].'/'.$config['db_name'];
        $this->db = new Database($dsn);

        // Set up the HTML Page object.
        $this->setHtmlPage();

        // Authentication
        $this->auth = $this->authenticateUser($dsn);

        $this->htmlPage->addBodyContent("<div id='body'>");
    }

    /**
     * @return void
     */
    private function setHtmlPage() {
        require_once 'HTML/Page2.php';
        $this->htmlPage = new HTML_Page2();
        $this->htmlPage->addStylesheet('screen.css');
        $this->htmlPage->setTitle($this->config['site_title']);
        $this->htmlPage->addBodyContent("<h1><a href='index.php'>".$this->config['site_title']."</a></h1>");
        $this->htmlPage->addBodyContent("<ul id='nav' class='fixed'>");
        $this->htmlPage->addBodyContent("<li class='tables'>Tables<ul>");
        $table_info = $this->db->getTables();
        $all_tables = array_keys($table_info);
        $tables  = preg_grep('|^report_|i', $all_tables, PREG_GREP_INVERT);
        $reports = preg_grep('|^report_|i', $all_tables);
        foreach ($tables as $table) {
            if ($table_info[$table]=='BASE TABLE') {
                $class = 'base-table';
            } elseif ($table_info[$table]=='VIEW') {
                $class = 'view';
            }
            $this->htmlPage->addBodyContent("<li class='$class'><a href='index.php?table=$table'>".
            "".titlecase($table)."</a></li>");
        }
        $this->htmlPage->addBodyContent("</ul></li><li>Reports<ul>");
        foreach ($reports as $report) {
            $report_title = titlecase(substr($report,7));
            $this->htmlPage->addBodyContent("<li><a href='reports.php?report=$report'>$report_title</a></li>");
        }
        $this->htmlPage->addBodyContent("</ul></li><li>Ancillary<ul>
            <li><a href='import_csv.php'>Import CSV</a></li>
            <li><a href='readme.php'>readme</a></li>
            </ul></li></ul>");

    }
    
    
    private function checkSession() {
        $timeout = 60 * 30; // In seconds, i.e. 30 minutes.
        $fingerprint = md5($_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']);
        session_start();
        if (    (isset($_SESSION['last_active']) && $_SESSION['last_active']<(time()-$timeout))
             || (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint']!=$fingerprint)
             || isset($_GET['logout'])
            ) {
            setcookie(session_name(), '', time()-3600, '/');
            session_destroy();
        }
        session_regenerate_id(); 
        $_SESSION['last_active'] = time();
        $_SESSION['fingerprint'] = $fingerprint;
        // User authenticated at this point (i.e. $_SESSION['email_address'] can be trusted).
    }

    /**
     *
     * @param string $dsn
     * @return Auth
     */
    private function authenticateUser($dsn) {
        if ($this->db->tableExists('users')) {

            require_once 'Auth.php';
            $auth_options = array(
            'dsn'=>$dsn,
            'table'=>'users',
            //'regenerateSessionId'=>true
            );
            $auth = new Auth('MDB2', $auth_options, array(&$this, 'doLoginForm'));
            $auth->start();
            $auth->setExpire(8*60*60); // 8 hours
            $auth->setIdle(45*60); // 45 minutes
            if (isset($_GET['action']) && $_GET['action']=='logout' && $auth->checkAuth()) {
                $auth->logout();
                $auth->start();
            }
            if ($auth->checkAuth()) {
                $this->htmlPage->addBodyContent("<p id='login-details'>
                You are logged in as <em class='sc'>".$auth->getUsername()."</em>.
                <a href='?action=logout' title='Click here to log out.'><br />
                <img src='images/logoff.png' alt='Exit icon.' /></a></p>");
            } else {
                die();
            }
            return $auth;
        } else {
            return false;
        }
    }

    /**
     * This function is called statically by Auth.
     */
    public function doLoginForm($username=null, $status=null, $auth=null) {
        require_once 'HTML/QuickForm.php';
        $page = new HTML_Page2();
        $page->setTitle("Please Login");
        $page->addStyleDeclaration('table {margin:auto} body {margin:2em}');
        include_once 'Auth/Frontend/Html.php';
        ob_start();
        Auth_Frontend_Html::render($auth, $username);
        $auth_form = ob_get_contents();
        ob_end_clean();
        $page->addBodyContent($auth_form);
        $page->display();
        die();
    }

    /**
     *
     * @param string $msg
     * @param string $type
     * @return string HTML for message, to be added to page.
     */
    protected function getMessage($msg, $type) {
        return "<div class='$type message'>$msg</div>";
    }

}
