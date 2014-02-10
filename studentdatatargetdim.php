<?php

//////////////////////////////////////////////////
// load the mysql database config
//////////////////////////////////////////////////
include "config.php";

// create an array to hold the where statements
$whereFilter = array();
$orderSelect = "";
$orderOrder = "";

// set the dimension for orders
if (isset($_GET['dimension'])) {
    switch ($_GET['dimension']) {
        case "year":
            $orderSelect = ", submission_year as 'dimension' ";
            $orderOrder = " submission_year ";
            break;
        case "discipline":
            $orderSelect = ", main_discipline as 'dimension' ";
            $orderOrder = " main_discipline ";
            break;
        case "scheme":
            $orderSelect = ", scheme as 'dimension' ";
            $orderOrder = " scheme ";
            break;
        case "source":
            $orderSelect = ", source as 'dimension' ";
            $orderOrder = " source ";
            break;
    }
    
}


// build a list of where statements based on the page filters
if (isset($_GET['disc'])) {
    $discs = explode(",", $_GET['disc']);
    $discs = implode(',', $discs);
    array_push($whereFilter, "s.main_discipline_number in (" . $discs . ")");
}

if (isset($_GET['scheme'])) {
    $schemes = explode(",", $_GET['scheme']);
    $schemes = "'" . implode("','", $schemes) . "'";
    array_push($whereFilter, "s.scheme in (" . $schemes . ")");
}

if (isset($_GET['year'])) {
    $years = explode(",", $_GET['year']);
    $years = "'" . implode("','", $years) . "'";
    array_push($whereFilter, "s.submission_year in (" . $years . ")");
}


if (isset($_GET['country']) && $_GET['country'] != "") {
    $countries = explode(",", $_GET['country']);
    $countries = "'" . implode("','", $countries) . "'";
    array_push($whereFilter, "s.destination_country in (" . $countries . ")");
}

if (isset($_GET['region'])) {
    $regions = explode(",", $_GET['region']);
    $regions = implode(",", $regions);
    array_push($whereFilter, "r.id in (" . $regions . ")");
}

if (isset($_GET['target']) && $_GET['target'] != "") {
    $targets = explode(",", $_GET['target']);
    $targets = "'" . implode("','", $targets) . "'";
    array_push($whereFilter, "s.target in (" . $targets . ")");
}

if (isset($_GET['source']) && $_GET['source'] != "") {
    $sources = explode(",", $_GET['source']);
    $sources = "'" . implode("','", $sources) . "'";
    array_push($whereFilter, "s.source_id in (" . $sources . ")");
}


// set the output header to JSON
header('Content-type: application/json; charset=iso-8859-1');

// create the main DB connection
$myPDODB = new PDO_DB();
$sth = $myPDODB->prepare("SET NAMES utf8");
$sth->execute();

// read the list of where clauses to create a single "where" string
$whereClause = getWhereClause();

if ($orderOrder != "") {
    $orderMain = "group by " . $orderOrder;
} else {
    $orderMain = "";
}


// get the total number of students for the current filter
$sql = "SELECT COUNT( s.id ) AS  'number' $orderSelect  
FROM students AS s 
JOIN countries as c on s.destination_country = c.id 
JOIN regions AS r ON r.id = c.region_id 
$whereClause  
$orderMain";


$orderOrder = ", " . $orderOrder;

$sth = $myPDODB->prepare($sql);
$result = $sth->execute();

    $output = array();

    if ($result) {
        $students = $sth->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $values = array();
    $cumTotal = 0;
    foreach ($students as $student) {
            $cumTotal += $student["number"];
            array_push($values,array("name" => $student["dimension"],"value"=>$student['number'],"cumValue"=> $cumTotal));

    }
    // get the regions
    $regions = getRegions();
    $output = array("id" => 1000000, "name" => "Switzerland", "type" => "world", "x" => 480, "y" => 250, "fixed" => true, "values" => $values, "number" => $cumTotal, "children" => $regions);

// output the data 
echo json_encode($output);


///////////////////////////////////////////////////////////////
// get the regions
///////////////////////////////////////////////////////////////
function getRegions() {
    global $myPDODB, $whereFilter, $orderSelect, $orderOrder;
    $output = array();

    $whereLocal = array();
    $whereClause = getWhereClause($whereLocal);

    $sql = "SELECT r.name AS  'name', r.id AS  'id', COUNT( s.id ) AS  'number',  'region' AS  'type' $orderSelect 
FROM regions AS r
JOIN countries AS c ON r.id = c.region_id
JOIN students AS s ON c.id = s.destination_country 
$whereClause
GROUP BY r.name $orderOrder
ORDER BY r.name;";

    $sth = $myPDODB->prepare($sql);
    $result = $sth->execute();

    $curRegionId = -1;
    $oldRegion;
    
    if ($result) {
        $regions = $sth->fetchAll(PDO::FETCH_ASSOC);

        foreach ($regions as $region) {
            // get the country details for each region
//            $countries = getCountries($region['id']);
            if ($region['id'] != $curRegionId) {
                
                if ($curRegionId != -1) {
                    $countries = getCountries($oldRegion['id']);
                    array_push($output, array("item_id" => $oldRegion['id'] * 100000, "fixed" => true, "name" => $oldRegion['name'], "number" => $cumTotal,"values"=>$values, "type" => $oldRegion['type'], "children" => $countries));
                }
                $values = array();
                $cumTotal = 0;
                $curRegionId = $region['id'] ;
                $oldRegion = $region;

            } 
            $cumTotal += $region["number"];
            array_push($values,array("name" => $region["dimension"],"value"=>$region['number'] ,"cumValue"=> $cumTotal));

            
        }
        $countries = getCountries($oldRegion['id']);
        array_push($output, array("item_id" => $oldRegion['id'] * 100000, "fixed" => true, "name" => $oldRegion['name'], "number" => $cumTotal,"values"=>$values, "type" => $oldRegion['type'], "children" => $countries));
        
    }

    // add the regions to the output array
    return $output;
}


///////////////////////////////////////////////////////////////
// get the countries for the current region
///////////////////////////////////////////////////////////////
function getCountries($region_id) {
    global $myPDODB, $whereFilter, $orderSelect, $orderOrder;


    $whereLocal = array();
    array_push($whereLocal, "c.region_id = $region_id");

    $whereClause = getWhereClause($whereLocal);

    $sql = "SELECT c.name AS  'name', c.id  AS  'id', c.map_id as 'map_id',COUNT( s.id ) AS  'number',  'country' AS  'type' $orderSelect 
FROM countries AS c
JOIN students AS s ON c.id = s.destination_country 
join regions as r on c.region_id = r.id 
$whereClause
GROUP BY c.name $orderOrder
ORDER BY c.name";

    $sth = $myPDODB->prepare($sql);
    $result = $sth->execute();

    $output = array();

    $curCountryId = "";
    $oldCountry;

    
    if ($result) {
        $countries = $sth->fetchAll(PDO::FETCH_ASSOC);

        foreach ($countries as $country) {
            if ($country['id'] != $curCountryId) {
                
                if ($curCountryId != "") {
                    // get the unis for each country
                    $unis = getUnis($oldCountry['id']);
                    array_push($output, array("item_id" => ($oldCountry['map_id'] * 10) + ($region_id * 100000), "name" => $oldCountry['name'], "values"=>$values, "number" => $cumTotal, "type" => $oldCountry['type'], "children" => $unis));
                }
                $values = array();
                $cumTotal = 0;
                $curCountryId = $country['id'] ;
                $oldCountry = $country;

            } 
            $cumTotal += $country["number"];
            array_push($values,array("name" => $country["dimension"],"value"=>$country["number"] ,"cumValue"=> $cumTotal));
            
        }
        $unis = getUnis($oldCountry['id']);
        array_push($output, array("item_id" => ($oldCountry['map_id'] * 10) + ($region_id * 100000), "name" => $oldCountry['name'], "values"=>$values, "number" => $cumTotal, "type" => $oldCountry['type'], "children" => $unis));
    }

    // add the country details to the output array
    return $output;
}

///////////////////////////////////////////////////////////////
// get the unis for the current country
///////////////////////////////////////////////////////////////
function getUnis($country_id) {
    global $myPDODB, $whereFilter, $orderSelect, $orderOrder;

    $whereLocal = array();
    array_push($whereLocal, "s.destination_country = '$country_id'");
    $whereClause = getWhereClause($whereLocal);

    
$sql = "SELECT s.destination_city AS  'name', s.destination_city as 'uni_code', c.map_id as 'country_id', r.id as 'region_id', s.destination_city AS  'id', COUNT( s.id ) AS  'number',  'uni' AS  'type' $orderSelect 
FROM students AS s 
JOIN countries as c on s.destination_country = c.id 
JOIN regions AS r ON r.id = c.region_id 
$whereClause
GROUP BY s.destination_city $orderOrder
ORDER BY s.destination_city";
    

    $sth = $myPDODB->prepare($sql);
    $result = $sth->execute();

    $output = array();
    $curUniId = "-1";
    $oldUni;
    $cumTotal = 0;

    if ($result) {
        $unis = $sth->fetchAll(PDO::FETCH_ASSOC);
        $i = 1;
        foreach ($unis as $uni) {
            // get details of each uni 
            if ($uni['id'] != $curUniId) {
                if ($curUniId != "-1") {
                    array_push($output, array("item_id" => ($i + ($oldUni['country_id'] * 10) + ($oldUni['region_id']*100000)), "name" => $oldUni['name'],"uni_code" => $oldUni['uni_code'], "values" => $values,"number" => $cumTotal, "type" => $oldUni['type']));
                    $i++;
                }
                $values = array();
                $cumTotal = 0;
                $curUniId = $uni['id'] ;
                $oldUni = $uni;

            } 
            $cumTotal += $uni["number"];
            array_push($values,array("name" => $uni["dimension"],"value"=>$uni["number"] ,"cumValue"=> $cumTotal));
            
        }
        array_push($output, array("item_id" => ($i + ($oldUni['country_id'] * 10) + ($oldUni['region_id']*100000)), "name" => $oldUni['name'], "uni_code" => $oldUni['uni_code'], "values" => $values,"number" => $cumTotal, "type" => $oldUni['type']));
    }

    return $output;
}


///////////////////////////////////////////////////////////////
// build the where string from the array of clauses
///////////////////////////////////////////////////////////////
function getWhereClause($local = array()) {
    global $whereFilter;

    $where = array_merge($local, $whereFilter);
    if (count($where) > 0) {
        $output = " where " . implode(" and ", $where) . " ";
        return $output;
    } else {
        return "";
    }
}

?>