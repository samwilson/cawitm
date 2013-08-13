<?php /* $Id: readme.php 27 2009-05-14 01:08:23Z samwilson $ */

require_once 'common.php';

class CAWITM_Readme extends CAWITM {

    function __construct() {
        parent::__construct();

        $this->htmlPage->addBodyContent("

<h2>About the constraint-aware web interface to MySQL</h2>

<p>In no particular order, here are some notes about this software:</p>
<ul>
    <li>Foreign keys are displayed as text from the table to which they refer.</li>
    <li>If a foreign key refers to a table, the first non-<abbr title='Primary Key'>PK</abbr> field of which is <em>also</em> a FK, then the displayed text is taken from the second (or third, or forth, etc.) foreign table.</li>
    <li>Every table must have an integer primary key called <code>id</code>.</li>
    <li>If a reporting view definition contains user-defined variables then when that report is produced, the user will be asked to provide values for those variables.</li>
    <li>A reporting view is just a view&mdash;of whatever form you like&mdash;whose name begins with <code>report_</code>.  Contrary to MySQL's usual behaviour, these reports can contain user-defined variables.</li>
</ul>

<!--h2>Filesystem Arrangement</h2>

<pre>
.htaccess
common.php
index.php
import_csv.php
form.php
update.php
webservice.php
readme.php
images/
  ...                   &larr; Site images, not user-uploaded.
library_classes/
  database.php
  html.php
temporary_files/
  ...
</pre-->

");
        $this->htmlPage->display();

    }
}

new CAWITM_Readme();