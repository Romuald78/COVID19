<?php

//-------------------------------------------
// DEFINITIONS
//-------------------------------------------
define("FILE_PREFIX"   , "covid19_data_");
define("FILE_SUFFIX"   , ".json"        );
define("DATA_DIRECTORY", "covid19"      );
define("STAT_DIRECTORY", "stats"        );
define("WORLD"         , "(_world_)"    );
define("RANK"          , "(_rank_)"     );
define("CASES"         , 0              );
define("DEATHS"        , 1              );
define("RATIO"         , 2              );
define("DELTA_CASES"   , 3              );
define("DELTA_DEATHS"  , 4              );
define("MAXCASES"      , "maxCases"     );



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
        $tab[$time][CASES]        = $cases;
        $tab[$time][DEATHS]       = $deaths;
        $tab[$time][RATIO]        = $ratio;
        $tab[$time][DELTA_CASES]  = 0;
        $tab[$time][DELTA_DEATHS] = 0;
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

function computeDelta(&$out){

    // For each country/world
    foreach($out as $country => $data){
        // Convert special characters for country variable
        $country = removeAccents($country);
        
        // init previous data
        $prevTime   = -1;
        $prevCases  = -1;
        $prevDeaths = -1;

        // for each time
        foreach($data as $time=>$v){
            // get details
            $cases   = $v[CASES];
            $deaths  = $v[DEATHS];
            
            // Process if not the first loop
            if( $prevTime != -1 ){                
                // Update country/World deltas 
                $out[$country][$prevTime][DELTA_CASES]  = $prevCases  - $cases;
                $out[$country][$prevTime][DELTA_DEATHS] = $prevDeaths - $deaths;    
            }
            
            // update previous values for next time
            $prevTime   = $time;
            $prevCases  = $cases;
            $prevDeaths = $deaths;            
        }
    }
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

function getCurrentTime(){
    // Get current date (formatted)
    $today = date("Ymd_H");
    return $today;
}

function checkUpdateDataFile(){
    // Get filename
    $fileName = FILE_PREFIX . getCurrentTime() . FILE_SUFFIX;
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
        // Compute delta cases and delta deaths
        computeDelta($data);
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
    $rank = [];
    foreach($out as $country=>$v){
        // Store ranking except for world
        if($country != WORLD){   
            $maxCases  = array_values($out[$country])[0][CASES];
            $rank[$country] = $maxCases;
        }
        // reverse data to have oldest date first and recent dates at the ned of the curve
        $out[$country] = array_reverse($out[$country]);
    }
    // Sort countries by alphabetical order
    ksort($out);
    // sort ranking by number of cases (greatest first)
    arsort($rank);
    // Add ranking in out array
    $i = 1;
    foreach($rank as $country=>$v){
        $out[$country][RANK] = $i;
        $i++;
    }
    
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


function updateNbConnections($flag){
    // Get today
    $today = getCurrentTime();
    $hour  = substr($today,-2);
    if($hour=="00"){
        $hour = "24";
    }
    // Get filename
    $fileName = "stats.json";
    $filePath = './' . STAT_DIRECTORY . '/' . $fileName;
    // Open json file
    $json = "";

    // If the file exists, we open it and LOCK it
    if(file_exists($filePath) ){
        $fp = fopen($filePath, "r+");
        if( flock($fp, LOCK_EX) ){

            // READ all FILE
            $json = fread($fp, 1024*64);
            // decode to json
            $cnx = json_decode($json,True);
            
            // Create entry if not existing
            if( !isset($cnx[$hour]) ){
                $cnx[$hour] = 0;
            }
            
            // If we need to increase
            if($flag){
                // Increase entry
                $cnx[$hour] += 1;
                // Store json
                $json = json_encode($cnx);
                // Set write pointer to the beginning
                fseek($fp, 0);
                // Write all data inside and flush
                fwrite($fp, $json);
                fflush($fp);
            }
            else{
                $json = json_encode($cnx);
            }
            // Remove lock
            flock($fp, LOCK_UN);
            // Close file
            fclose($fp);        
        }
    }
        
    // return the updated connections
    return $json;
}


/*

// check if the most recent local file matches the current day
// if yes, ths function does nothing
// if no, this function gets data from the API and store a new local file
checkUpdateDataFile();

$DATA = getWholeData();
print_r($DATA);

//*/

?>