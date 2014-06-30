<?php
/*
    Class:  MaestroCourse
    Author: Phillihp Harmon
    Date:   2013.01.24
    
    Description:
        Course object ORM
*/

include_once "Maestro.php";

class MaestroCourse extends Maestro {
    
    function __construct() {
        $this->keys = array(
            "course_id"=>"INT",
            "course_name"=>"",
            "course_code"=>"",
            "course_type"=>"",
            "course_status"=>"",
            "course_description"=>"TEXT",
            "course_summary"=>"TEXT",
            "course_ceu"=>"DECIMAL",
            "course_duration"=>"INT",
            "course_cost"=>"DECIMAL",
            "course_url"=>"",
            "primaryCatalogType"=>"",
            "secondaryCatalogType"=>"",
            "category"=>"JSON",
            "external_url"=>"",
            "vendor_name"=>"",
            "vendor_id"=>"INT",
            "test_required"=>"BOOLEAN",
            "certificate_available"=>"BOOLEAN",
            "featured_course"=>"BOOLEAN",
            "ApprovalForm"=>"",
            "Format"=>"",
            "PrimaryDeliveryMethod"=>"",
            "ProductCode"=>"",
            "TargetAudience"=>"TEXT",
            "last_modified"=>"DATETIME"
        );
        
        $this->t = "maestrocourses";
        $this->pk = "course_id";
        
        // We need to pass the parent constructor the arguments that come in through the child constructor
        call_user_func_array(array($this, 'parent::__construct'), func_get_args());
    }
    
    /*
        function getCategoris
        
        $option = "all" - 
        $option = "type" - Split by Types
        $option = "delivery" - Split by Delivery
    */
    public static function getCategories($option = "all") {
        $categoryList = array();
        $mc = new MaestroCourse();
        $me = new MaestroException();
        
        $filter = self::getCategoryFilter($option);
        
        $data = $me->find(array(
            'query'=>"field='category'"
        ));
        $exclude = Array();
        foreach($data as $elem) {
            array_push($exclude, $elem['value']);
        }
        
        $result = $mc->db->query("
            SELECT
                DISTINCT(`category`), {$filter['groupBy']}
            FROM `{$mc->t}`
            WHERE `course_status`='Active'
            ORDER BY {$filter['groupBy']}, category ASC");
        
        if($result->num_rows) {
            $catType = "";
            while($resarr = $result->fetch_array()) {
                $objects = json_decode($resarr['category']);
                $catType = $filter['option'] == "all" ? "All" : $resarr[$filter['groupBy']];
                $catType = $catType == "" ? "Newly Added Courses" : $catType;
                if(!isset($categoryList[$catType])) $categoryList[$catType] = Array();
                foreach($objects as $objName => $obj) {
                    if(!in_array($objName, $categoryList[$catType]) && !in_array($objName, $exclude)) {
                        array_push($categoryList[$catType], $objName);
                    }
                }
            }
            foreach($categoryList as $key => $cat)
                asort($categoryList[$key]);
        }
        return $categoryList;
    }
    
    public static function getCategoryFilter($option) {
        $filter = array();
        $filter['option'] = $option;
        switch($option) {
            case "catalog":
                $filter['groupBy'] = "secondaryCatalogType";
                $filter['name'] = "Subject Area";
                break;
            case "delivery":
                $filter['groupBy'] = "PrimaryDeliveryMethod";
                $filter['name'] = "Delivery Method";
                break;
            default:
                $filter['groupBy'] = "secondaryCatalogType";
                $filter['name'] = "Subject Area";
                $filter['option'] = "all";
                break;
        }
        return $filter;
    }
    
    public static function dataExtraction($date = "") {
        $dataElements = parent::getExtraction("courses", $date);
        
        $index = array();
        $count = 0;
        foreach($dataElements as $dataElement) {
            if(!$count) {
                $index = $dataElement;
            } else {
                $mc = new MaestroCourse($dataElement[$index['course_id']]);
                
                foreach($mc->keys as $key => $type)
                    $mc->data[$key] = isset($index[$key]) ? $dataElement[$index[$key]] : "";
                
                $loc = $mc->m['webservice']."/".$mc->m['environment']."api/".$mc->m['domain']."/catalog/Course/".$dataElement[$index['course_id']];
                
                $xmlV = parent::getCatalogDetail($loc);
                
                $mc->data['course_summary'] = isset($xmlV->entry->summary) ? (string)$xmlV->entry->summary : "";
                $mc->data['primaryCatalogType'] = isset($xmlV->entry->PrimaryCatalogType) ? (string)$xmlV->entry->PrimaryCatalogType : "";
                $mc->data['secondaryCatalogType'] = isset($xmlV->entry->SecondaryCatalogType) ? (string)$xmlV->entry->SecondaryCatalogType : "";
                
                // JSON Encode the Categories as these can be dynamic
                $categories = Array();
                foreach($xmlV->entry->Category as $cat) {
                    $categories[(string)$cat] = Array(
                        'IsCompetency' => isset($cat->attributes()->IsCompetency) ? (string)$cat->attributes()->IsCompetency : "",
                        'Code' => isset($cat->attributes()->Code) ? (string)$cat->attributes()->Code : ""
                    );
                }
                $mc->data['category'] = $categories;
                $mc->data['external_url'] = isset($xmlV->entry->external_url) ? (string)$xmlV->entry->external_url : "";
                
                $mc->save();
                echo "($count) Course_{$dataElement[$index['course_id']]} Saved...\n";
            }
            $count++;
        }
    }
    
    public static function customExtraction($date = "") {
        $dataElements = parent::getExtraction("custom_course_fields", $date);
        
        $index = array();
        $count = 0;
        foreach($dataElements as $dataElement) {
            if(!$count) {
                $index = $dataElement;
            } else {
                $save = true;
                $mc = new MaestroCourse($dataElement[$index['course_id']]);
                
                $field_name = $dataElement[$index['course_field_name']];
                $field_data = $dataElement[$index['course_field_value']];
                
                switch($field_name) {
                    case "Approval Form":
                        $mc->data['ApprovalForm'] = $field_data;
                        break;
                    case "Format":
                        $mc->data['Format'] = $field_data;
                        break;
                    case "Primary Delivery Method":
                        $mc->data['PrimaryDeliveryMethod'] = $field_data;
                        break;
                    case "Product Code":
                        $mc->data['ProductCode'] = $field_data;
                        break;
                    case "Target Audience":
                        $mc->data['TargetAudience'] = $field_data;
                        break;
                    default:
                        $save = false;
                        break;
                }
                if($mc->data['id'] > 0 AND $save) {
                    $mc->save();
                    echo "($count) Course_{$dataElement[$index['course_id']]} (Custom Field: $field_name) Saved...\n";
                }
            }
            $count++;
        }
    }
}

?>
