<?php /* $Id: files.php 45 2009-06-10 02:15:14Z samwilson $ */
require_once 'common.php';

class CAWITM_Files extends CAWITM {

    function __construct() {
        parent::__construct();
        $ds = DIRECTORY_SEPARATOR;
        if (isset($_GET['table'])
            && isset($_GET['column'])
            && isset($_GET['id'])
            && file_exists(DATA_DIR.$ds.$_GET['table'].$ds.$_GET['id'].$ds.$_GET['column'])
            && isset($_GET['format'])) {
            if ($_GET['format']=='thumb') {
                $this->outputThumb();
            } elseif ($_GET['format']=='full') {
                $this->outputFull();
            }

        } else {
            header("HTTP/1.0 404 Not Found");
            die("ERROR 404 Not Found");
        }
    }

    private function outputThumb() {
    
        $ds = DIRECTORY_SEPARATOR;
        //preg_match("|(.*?)\\$ds([0-9]*?)\\$ds(.*?)|", $file, $filename);
        //print_r($filename);
        $table = $_GET['table'];
        $id = $_GET['id'];
        $column = $_GET['column'];
        
        $file_name = unserialize($this->db->fetchOne("SELECT $column FROM $table WHERE id=$id"));
        $file_name = $file_name['filename'];
        $file_path = DATA_DIR.$ds.$table.$ds.$id.$ds.$column;

        require_once 'MIME/Type.php';
        //preg_match('|.([a-zA-Z0-9]+)$|',$file_name,$matches);
        //$file_extension = $matches[1];
        //$mt = new MIME_Type_Extension();
        //$mimetype = $mt->extensionToType[$file_extension];
        //$finfo = finfo_open(FILEINFO_MIME);
        //$mimetype = finfo_file($finfo, $filename);
        //finfo_close($finfo);
        $is_temp = false;
        $mimetype = MIME_Type::autoDetect($file_path);
        switch ($mimetype) {
            case 'image/jpeg':
                $thumb = exif_thumbnail($file_path);
                if ($thumb) {
                    $file_path = TEMP_DIR.$ds.md5().'.jpg';
                    $is_temp = true;
                    file_put_contents($file_path, $thumb);
                } else {
                    $mimetype = "image/png";
                    $file_path = dirname(__FILE__)."/images/famfamfam_silk_icons/icons/picture.png";
                }
                break;
            case 'text/plain':
                $mimetype = "image/png";
                $file_path = dirname(__FILE__)."/images/famfamfam_silk_icons/icons/page_white_text.png";
                break;
            case 'application/pdf':
                $mimetype = "image/png";
                $file_path = dirname(__FILE__)."/images/famfamfam_silk_icons/icons/page_white_acrobat.png";
                break;
            default:
                $mimetype = "image/png";
                $file_path = dirname(__FILE__)."/images/famfamfam_silk_icons/icons/page_white.png";
                break;
        }
        $file_size = filesize($file_path);
        
        // Getting headers sent by the client.
        $headers = apache_request_headers();

        // Checking if the client is validating his cache and if it is current.
        if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file_path))) {
            // Client's cache IS current, so we just respond '304 Not Modified'.
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 304);
        } else {
            // Image not cached or cache outdated, we respond '200 OK' and output the image.
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 200);
            header("Content-Type: $mimetype");
            header("Content-Disposition: inline; filename=".$file_name."; size=$file_size;");
            header("Content-Length: $file_size");
            readfile($file_path);
        }        
        if ($is_temp) {
            unlink($file_path);
        }
    }

    private function outputFull() {
    
        $ds = DIRECTORY_SEPARATOR;
        $table = $_GET['table'];
        $id = $_GET['id'];
        $column = $_GET['column'];
        
        $file_name = unserialize($this->db->fetchOne("SELECT $column FROM $table WHERE id=$id"));
        $file_name = $file_name['filename'];
        $file_path = DATA_DIR.$ds.$table.$ds.$id.$ds.$column;

        require_once 'MIME/Type.php';
        $mimetype = MIME_Type::autoDetect($file_path);
        $file_size = filesize($file_path);
        
        $headers = apache_request_headers();
        // Checking if the client is validating his cache and if it is current.
        if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($file_path))) {
            // Client's cache IS current, so we just respond '304 Not Modified'.
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 304);
        } else {
            // File not cached or cache outdated, we respond '200 OK' and output the image.
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($file_path)).' GMT', true, 200);
            header("Content-Type: $mimetype");
            header("Content-Disposition: inline; filename=".$file_name."; size=$file_size;");
            header("Content-Length: $file_size");
            readfile($file_path);
        }
    }

}

new CAWITM_Files();