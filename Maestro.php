<?php

include_once(dirname(__FILE__)."/../config/core.php");

/*
    Class:  Maestro
    Author: Phillihp Harmon
    Date:   2013.01.24
    
    Description:
        High level Maestro object for ORM build of class components
        
        This ORM class was used for the integration between a Maestro LMS system and needs to be generalized
        to adapt to other uses. For now, the raw code is availible and the techniques can be extracted out.
*/
class Maestro { 
    public $data;   // Data
    public $keys;   // Key Records
    public $db;     // Base MySQLi Instance
    public $t;      // Base Table Name
    public $pk;     // Primary Key
    public $fk;     // Foreign Key
    public $err;    // Error Detection
    public $m;      // Maestro Object
    public $pre;    // Database Prefix
    
    // Pagination
    public $last_count;
    public $pa_current;
    public $pa_limit;
    
    /*
        __construct()
    */
    function __construct() {
        $con = MaestroConfig::getConnection();
        
        // Database and Tables
        $this->db   = MaestroConfig::getDatabase();
        $this->pre  = $con['dbprefix'];
        $this->t    = $this->pre.(isset($this->t) ? $this->t : "");
        
        // Fields and Data
        $this->data = "";
        $this->keys = isset($this->keys) ? $this->keys : array();
        $this->pk   = isset($this->pk) ? $this->pk : "";
        $this->fk   = isset($this->fk) ? $this->fk : "";
        
        // Error Detection
        $this->err  = "";
        
        // Maestro Configuration
        $this->m    = MaestroConfig::getMaestro();
        
        // Ordering and Listing
        $this->last_count = 0;
        $this->pa_current = 1;
        $this->pa_limit = 20;
        
        // Argument Constructors
        $args = func_get_args();
        
        $this->data['id'] = "";
        foreach($this->keys as $key => $type) {
            $this->data[$key] = "";
        }
        
        // Read if entered an ID
        if(isset($args[0])) {
            $this->read($args[0]);
        }
    }
    
    /*
        function find()
    */
    public function find($inputArgs = Array()) {
        // Allowed Incoming Variables
        $vars = Array("select", "query", "page", "limit", "join", "order", "group");
        foreach($vars as $var) {
            $$var = isset($inputArgs[$var]) ? $inputArgs[$var] : "";
        }
        
        if($limit) {
            $page = (int)$page;     if($page < 1) $page = 1;
            $limit = (int)$limit;   if($limit < 1) $limit = 20;
            
            $limitSQL = "LIMIT ".(($page-1)*$limit).", $limit";
        } else {
            $page = 1;
            $limit = 20;
            $limitSQL = "";
        }
        
        if(!$select) $select = "*";
        else {
            $selobjs = $select;
            if(is_array($selobjs)) {
                $select = "";
                foreach($selobjs as $selobj) {
                    $select.=($select ? "," : "").$selobj;
                }
            }
        }
        
        if($order) $order = "ORDER BY ".$order;
        if($group) $group = "GROUP BY ".$group;
        
        if($join) {
            if(!is_array($join))
                $joinArray = Array($join);
            else
                $joinArray = $join;
                
            $joinSQL = $this->t;
            
            foreach($joinArray as $join) {
                $newJoin = new $join();
                $this->keys = array_merge($this->keys, $newJoin->keys);
                $joinSQL.=",".$newJoin->t;
                $query.=($query ? " AND " : "")."{$this->t}.{$this->fk[$join]}={$newJoin->t}.{$newJoin->pk}";
            }
        } else
            $joinSQL = $this->t;
        
        // Get the count of the results for Pagination Logging
        $query = ($query != "" ? "WHERE $query" : "");
        
        $result = $this->db->query("SELECT COUNT(*) as count FROM $joinSQL $query") or die($this->db->error);
        $resarr = $result->fetch_array();
        $this->last_count = $resarr['count'];
        
        // Get the actual Results
        $result = $this->db->query("SELECT $select FROM $joinSQL $query $order $limitSQL $group") or die($this->db->error);
        $this->pa_current = $page;
        $this->pa_limit = $limit;
        
        $dataPack = array();
        
        if($result->num_rows) {
            while($resarr = $result->fetch_array()) {
                array_push($dataPack, $this->cleanVars($resarr));
            }
        }
        return $dataPack;
    }
    
    /*
        function paginate()
        
        Description:
            Builds the pagination based on the last time the find() function was run.
    */
    public function paginate($link) {
        $output = "";
        $sep = (strpos($link, "?") === "false" ? "?" : "&");
        $lastPage = ceil($this->last_count / $this->pa_limit);
        
        $output.=($this->pa_current == 1 ? "" : "<a href='".$link.$sep."page=1&limit={$this->pa_limit}'>&lt;&lt; First</a> <a href='".$link.$sep."page=".($this->pa_current-1)."&limit={$this->pa_limit}'>&lt; Prev</a>");
        for($i = 1; $i <= $lastPage; $i++) {
            $output.=($output != "" ? " - " : "");
            if($i == $this->pa_current) $output.="<strong>$i</strong>";
            else $output.="<a href='".$link.$sep."page=$i&limit={$this->pa_limit}'>$i</a>";
        }
        $output.= ($this->pa_current == $lastPage || $lastPage == 0 ? "" : " - 
            <a href='".$link.$sep."page=".($this->pa_current+1)."&limit={$this->pa_limit}'>Next &gt;</a>
            <a href='".$link.$sep."page=$lastPage&limit={$this->pa_limit}'>Last &gt;&gt;</a>");
        return $output;
    }
    
    /*
        function read()
    */
    public function read($pkid = "") {
        $pkid = $this->db->escape_string($pkid);
        $result = $this->db->query("SELECT * FROM {$this->t} WHERE `{$this->pk}`='$pkid' LIMIT 1");
        $resarr = $result->fetch_array();
        
        $this->last_count = $result->num_rows;
        
        $this->data = $this->cleanVars($resarr);
    }
    
    /*
        function delete()
    */
    public function delete($pkid = "") {
        $pkid = $this->db->escape_string($pkid);
        $pkid = $pkid ? $pkid : $this->data[$this->pk];
        $result = $this->db->query("DELETE FROM {$this->t} WHERE `{$this->pk}`='$pkid'");
        
        // Should add some return results on success
    }
    
    /*
        function cleanVars()
    */
    public function cleanVars($data) {
        $dataSet = array();
        $dataSet['id'] =  isset($data['id']) ? $data['id'] : "";
        foreach($this->keys as $key => $type) {
            if(isset($data[$key])) {
                switch($type) {
                    case "JSON":
                        $dataSet[$key] = json_decode($data[$key]);
                    	break;
                    default:
                        $dataSet[$key] = self::convertText($data[$key]);
                        break;
                }
            }
        }
        return $dataSet;
    }
    
    /*
        function save()
    */
    public function save($inData = "") {
        $query = "";
        $dataObject = ($inData == "" ? $this->data : $inData);
        
        $update = $dataObject['id'] ? true : false;
        
        if($update) {
            $query = "UPDATE {$this->t} SET ";
        } else {
            $query = "INSERT INTO {$this->t} ";
        }
        
        $keys = "";
        $vals = "";
        foreach($this->keys as $key => $type) {
            switch($type) {
                case "BOOLEAN":
                    $dataObject[$key] = str_ireplace("yes", "1", $dataObject[$key]);
                    $dataObject[$key] = str_ireplace("no", "0", $dataObject[$key]);
                    $dataObject[$key] = str_ireplace("on", "1", $dataObject[$key]);
                    $dataObject[$key] = str_ireplace("off", "0", $dataObject[$key]);
                    $dataObject[$key] = str_ireplace("true", "1", $dataObject[$key]);
                    $dataObject[$key] = str_ireplace("false", "0", $dataObject[$key]);
                    break;
                case "INT":
                    $dataObject[$key] = (float)$dataObject[$key];
                    break;
                case "DECIMAL":
                    $dataObject[$key] = (float)$dataObject[$key];
                    break;
                case "TEXT":
                    break;
                case "TIME":
                    break;
                case "DATE":
                    $dataObject[$key] = date("Y-m-d", strtotime($dataObject[$key]));
                    break;
                case "DATETIME":
                    $dataObject[$key] = str_replace("T", " ", $dataObject[$key]);
                    $dataObject[$key] = str_replace("Z", "", $dataObject[$key]);
                    break;
                case "JSON":
                    $dataObject[$key] = json_encode($dataObject[$key]);
                    break;
                default:
                    break;
            }
            $dataObject[$key] = self::convertText($dataObject[$key]);
            $dataObject[$key] = $this->db->escape_string($dataObject[$key]);
        
            if($update) {
                $vals.=(strlen($vals) ? "," : "")."`$key`='{$dataObject[$key]}'";
            } else {
                $keys.=(strlen($keys) ? "," : "")."`$key`";
                $vals.=(strlen($vals) ? "," : "")."'{$dataObject[$key]}'";
            }
        }
        
        if($update) {
            $query.="$vals,`modified`=NOW() WHERE `id`='{$dataObject['id']}';";
        } else {
            $query.="($keys,`created`,`modified`) VALUES ($vals,NOW(),NOW());";
        }
        
        $this->db->query($query);
        unset($dataObject);
    }
    
    /*
        function getLastUpdate()
    */
    public function getLastUpdate() {
        $result = $this->db->query("SELECT `modified` FROM `{$this->t}` ORDER BY modified DESC LIMIT 1");
        if($result->num_rows > 0) {
            $resarr = $result->fetch_array();
            return convertDate($resarr['modified']);
        } else {
            return "0000-00-00 00:00:00";
        }
    }
    
    /*
        function exists()
    */
    public function exists($pkid = "") {
        $pkid = $this->db->escape_string($pkid);
        $result = $this->db->query("SELECT `id` FROM {$this->t} WHERE `{$this->pk}`='$pkid' LIMIT 1");
        $resarr = $result->fetch_array();
        return ($result->num_rows == 1 ? $resarr['id'] : 0);
    }
    
    /*
        function convertText()
        
        Description:
            Resolve the bad character conversions and convert them to HTMLSpecialCharacters
    */
    public static function convertText($text) {
        $text = str_replace("’", "'", $text);
        $text = str_replace("“", "&quot;", $text);
        $text = str_replace("•", "&bull;", $text);
        $text = str_replace("™", "&trade;", $text);
        $text = str_replace("–", "-", $text);
        $text = str_replace("�", "&quot;", $text);
        $text = str_replace("®", "&reg;", $text);
        $text = str_replace("�", "", $text);
        
        $text = preg_replace('/[^(\x20-\x7F)]*/','', $text);
        //$text = utf8_encode($text);
        //$text = str_replace("&bull;", $text);
        
        return $text;
    }
    
    /*
        function xmlConvert()
    */
    public static function xmlConvert($data) {
        $data = str_replace("geo:", "", $data);
        return $data;
    }
    
    /*
        function downloadExtraction()
        
        Description:
            Retrieves the CSV download and extracts the data since the last updated date.
    */
    public static function getExtraction($type, $date) {
        $postFields = "header_required=1&".($date ? "since=$date" : "");
        $maestro = MaestroConfig::getMaestro();
        
        $url = "{$maestro['webservice']}/{$maestro['environment']}dataextraction/{$maestro['domain']}/$type";
        $dataElements = array();
        
        // Request and build a new Export to pull from
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERPWD, "{$maestro['username']}:{$maestro['password']}");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($curl, CURLOPT_HEADER, 1); 
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/x-www-form-urlencoded"));
        $header = curl_exec($curl);
        
        curl_close($curl);
        
        // Sift through the header information to find the location
        $lines = explode("\n", $header);
        $key = "";
        $loc = "";
        foreach($lines as $line) {
            $pos = strpos($line, ":");
            if($pos == -1) {
                $key = $line;
                $loc = "";
            } else {
                $key = substr($line, 0, $pos);
                $loc = trim(substr($line, $pos+1));
            }
            
            if($key == "Location")
                break;
        }
        
        // Now that we have the link to the CSV file, lets download it
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $loc);
        curl_setopt($curl, CURLOPT_USERPWD, "{$maestro['username']}:{$maestro['password']}");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: text/csv"));
        
        ob_start();
        if(curl_exec($curl) === false) {
            echo 'Curl error: ' . curl_error($curl);
        }
        $v = ob_get_contents();
        ob_end_clean();
        
        curl_close($curl);
        $v = str_replace("\"last_modified\"", "\"last_modifiedZ\"", $v); 
        
        // Iterate through the records and begin building MaestroCourse objects to be saved to the database
        $lines = explode("Z\"\n\"", $v."\n\"");
        $count = 0;
        $index = array();
        foreach($lines as $line) {
            if(substr($line, strlen($line)-2) != "Z\"" && $line != "") $line.="Z\"";
            if(substr($line, 0, 1) != "\"") $line="\"".$line;
            if($count + 1 == count($lines)) $line = str_replace("\n\n\"Z\"", "", $line);    // Last Line Modify
            if(!$count) $line = str_replace("\"last_modifiedZ\"", "\"last_modified\"", $line); // First Line Modify
            
            // Split the data values out
            $vars = str_getcsv($line);
            
            // First Line will be the headers that we use as keys
            if(!$count) {
                $i = 0;
                // Build the Key Reference Table
                foreach($vars as $var) {
                    $index[$i] = $var;
                    $index[$var] = $i++;
                }
                $dataElements[$count] = $index;
            } else {
                $dataElements[$count] = $vars;
            }
            
            $count++;
        }
        return $dataElements;
    }
    
    /*
        getCatalogDetail()
    */
    public static function getCatalogDetail($location) {
        $maestro = MaestroConfig::getMaestro();
        
        // Load the additional catalog details individually
        $v = "";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $location);
        curl_setopt($curl, CURLOPT_USERPWD, "{$maestro['username']}:{$maestro['password']}");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: text/xml"));
        
        ob_start();
        if(curl_exec($curl) === false) {
            echo 'Curl error: ' . curl_error($curl);
        }
        $v = ob_get_contents();
        ob_end_clean();
        
        $xmlV = simplexml_load_string(self::xmlConvert($v));
        
        return $xmlV;
    }
}

?>
