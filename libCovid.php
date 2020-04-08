<?php

//-------------------------------------------
// DEFINITIONS
//-------------------------------------------
define("FILE_PREFIX"   , "covid19_data_");
define("FILE_SUFFIX"   , ".json"        );
define("DATA_DIRECTORY", "covid19"      );
define("WORLD"         , "(_world_)"    );
define("CASES"         , 0              );
define("DEATHS"        , 1              );
define("RATIO"         , 2              );



//-------------------------------------------
// FUNCTIONS
//-------------------------------------------
function removeAccents($s){
    $unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
    $s = strtr( $s, $unwanted_array );
    $s = str_replace(" ","-",$s);
    $s = strtolower($s);
    return $s;
}

function addEntryOut(&$tab, $time, $cases, $deaths){
    // Compute ratio
    $ratio   = 0;
    if($cases != 0){
        $ratio   = (int)(1000*$deaths/$cases)/10;
    }
    // Reshape time etiquette
    $time = explode("T",$time)[0];
    // Create entry of date and add details
    if(!isset($tab[$time])){
        $tab[$time] = [];
        $tab[$time][CASES]  = $cases;
        $tab[$time][DEATHS] = $deaths;
        $tab[$time][RATIO]  = $ratio;
    }
    else{
        // display an error if date is already present
        echo("[ERROR] input already exists !<br>");
    }
}

function processData(&$dataIn, $category, &$dataOut ){
    // If the JSON is malformed, just do nothing
    if(isset($dataIn[$category])){
        foreach($dataIn[$category] as $k=>$v){
            // Get Country/world
            if($category == "GlobalData"){
                $country = WORLD;
            }
            else{
                $country = $v["Pays"];
            }
            // Convert special characters for country variable
            $country = removeAccents($country);
            // get details
            $time    = $v["Date"];
            $cases   = $v["Infection"];
            $deaths  = $v["Deces"];
            // check if country/world entry exists
            if(!isset($dataOut[$country])){
                $dataOut[$country] = [];
            }
            // Update country/World with date and details 
            addEntryOut($dataOut[$country], $time, $cases, $deaths);
        }
    }    
}

function reshapeData($data){
    // Reshape RAW data coming from the API into the local format we wanna use
    $out        = [];
    // First take whole WORLD data
    processData($data, "GlobalData", $out);
    // Then take each country data
    processData($data, "PaysData", $out);    
    // Return the reshaped data
    return $out;
}

function getTimeFromFilename($fileName){
    $A = str_replace(FILE_PREFIX, "", $fileName);
    $A = str_replace(FILE_SUFFIX, "", $A);
    $A = str_replace("_", "", $A);
    return $A;
}

function getMostRecentDataFile(){
    // Init result
    $recentFileName = null;
    // Get data directory path
    $dataDir = './' . DATA_DIRECTORY . '/';
    // Get all directory files and check date
    $maxDate = "";
    if ($handle = opendir($dataDir)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                // Get time from filename
                $time = getTimeFromFilename($entry);
                // Keep the most recent file in memory
                if($time > $maxDate){
                    $maxDate = $time;
                    $recentFileName = $entry;
                }
            }
        }
        closedir($handle);
    }
    else {
        // Create folder if it does not exist
        mkdir($dataDir);
    }    
    // return the most recent file
    return $recentFileName;
}

function checkUpdateDataFile(){
    // Get current date (formatted)
    $today = date("Ymd_H");
    // Get filename
    $fileName = FILE_PREFIX . $today . FILE_SUFFIX;
    // Get full file path
    $filePath = './' . DATA_DIRECTORY . '/' . $fileName;
    // Get most recent data file
    $recentFile = getMostRecentDataFile();
    // Filter filenames
    $A = getTimeFromFilename($recentFile);
    $B = getTimeFromFilename($fileName);
    // Compare current file and most recent file
    if($A != $B){
        // Update needed : get data from API
        $json = file_get_contents("https://www.data.gouv.fr/fr/datasets/r/a7596877-d7c3-4da6-99c1-2f52d418e881");
        // Transform JSON to PHP array
        $data = json_decode($json,True);
        // Reshape data as needed
        $data = reshapeData($data);
        // Transform reshaped data into JSON
        $json = json_encode($data);
        // Store into file
        if($fp = fopen($filePath,'w')){
            // Write all data inside and close file
            fwrite($fp, $json);
            fclose($fp);
        }
    }
}

function getWholeData(){
    // prepare output data
    $out =[];
    
    // Open the most recent file
    $fileName = getMostRecentDataFile();
    if($fileName != null){
        // read all data from file
        $filePath = './' . DATA_DIRECTORY . '/' . $fileName;
        $json     = file_get_contents($filePath);
        $out      = json_decode($json,True);
    }
    
    // invert all dates from oldest to newest
    foreach($out as $k=>$v){
        $out[$k] = array_reverse($out[$k]);
    }
    
    ksort($out);
    
    // Return whole data
    return $out;    
}

function getCountryData($data, $country){
    // prepare output data
    $out =[];
    
    // check if the country is present
    if( isset($data[$country]) ){
        $out = $data[$country];
    }
    
    // Return country data
    return $out;
}


/*

// check if the most recent local file matches the current day
// if yes, ths function does nothing
// if no, this function gets data from the API and store a new local file
checkUpdateDataFile();

$DATA = getWholeData();
$world = getCountryData($DATA,"France");
print_r($world);

*/

?>