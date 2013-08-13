<?php /* $Id$ */

function titlecase($out) {
    include 'config.php';
    if (is_array($out)) {
        return array_map('titlecase',$out);
    } else {
        $out = ucwords(preg_replace('|_|',' ', $out));
        if (isset($config['label_replacements']) && is_array($config['label_replacements'])) {
            foreach ($config['label_replacements'] as $search=>$replacement) {
                $out = preg_replace("|\b$search\b|i", $replacement, $out);
            }
        }
        return $out;
    }
}



function strto1or0($str) {
	if ( $str==''
         || $str==null 
         || empty($str) 
         || !isset($str)
	     || strcasecmp($str,'false')===0 
         || strcasecmp($str,'off')===0
	     || strcasecmp($str,'no')===0
	   ) {
		return 0;
	} else {
		return 1;
	}
}

function numtoyesno($num) {
    if ($num>0) return 'Yes';
    else return 'No';
}

// Get a properly-formatted (ISO) date from a string or QuickForm-produced array.
function isodate($d) {
    if (is_array($d)) {
        $year = (isset($d['Y'])) ? $d['Y'] : '0000';
        $month = (isset($d['M'])) ? $d['M'] : '00';
        $day = (isset($d['d'])) ? $d['d'] : '00';
          $d = "$year-$month-$day";
      } else {
          $d = (strtotime($d))
              ? date('Y-m-d',strtotime($d))
              : '0000-00-00';
      }
      return $d;
}

?>