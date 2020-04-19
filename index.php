<?php
// Start session
session_start();

// include lib to get data from COVID
include_once("libCovid.php");

// Check if we have to increase nb visits
if( !isset($_SESSION["incVisits"]) ){
    $_SESSION["incVisits"] = True;
}

// Update nb connections
$STATS = updateNbConnections($_SESSION["incVisits"]);
$_SESSION["incVisits"] = False;



// check if the most recent local file matches the current day
// if yes, ths function does nothing
// if no, this function gets data from the API and store a new local file
checkUpdateDataFile();

$RAW  = getWholeData();
$DATA = json_encode($RAW);


?>

<!doctype html>
<html>

<link rel="stylesheet" href="./w3.css">

<head>
	<title>Line Chart</title>
	<script src="./Chart.bundle.min.js"></script>
	<script src="./utils.js"></script>
	<style>
	canvas{
		-moz-user-select: none;
		-webkit-user-select: none;
		-ms-user-select: none;
	}
	</style>
</head>

<body>
	
    <div id="canvas1" class="canvas1" >
        <canvas id="canvasCases" ></canvas>
    </div>        
    
    <div id="canvas3" class="canvas3" >
        <canvas id="canvasPerc" ></canvas>
    </div>

    
    
    <div id="canvas2A" class="canvas2A" >
        <canvas id="canvasWorldA" ></canvas>
    </div>
    
    <div id="canvas2B" class="canvas2B" >
        <canvas id="canvasWorldB" ></canvas>
    </div>
    
    <div id="canvas2C" class="canvas2C" >
        <canvas id="canvasWorldC" ></canvas>
    </div>


    
    <div id="canvas9" class="canvas9" >
        <canvas id="canvasStats" ></canvas>
    </div>        

    
    <div class="list1">
        <img class="butts" src="./images/save.png"  width="48%" onClick="saveCountries()"  />
        <img class="butts" src="./images/clear.png" width="48%" onClick="clearAllCharts()" />


        <form autocomplete="off">
            <select class="scroller" name="countries" id="countrySelect"  onclick="changeCountry()" onkeypress="pressedKey(event)" size="33" style="max-width:100%;max-height:100%" >
            <?php            
            foreach($RAW as $k=>$v){
                $selected = "";
                if($k == WORLD){
                    $selected = "selected";
                }
                echo("<option value=" . $k . " $selected>" . ucfirst(str_replace("-"," ",$k)) . "</option>");
            }
            ?>    
            </select>
        </form>
    </div>
    
    
    <script>
        

        
        //-------------------------------------------------------------------------------------------
        // COLOR FUNCTIONS
        //-------------------------------------------------------------------------------------------
        function transparentize(color, opacity) {
			var alpha = opacity === undefined ? 0.5 : 1 - opacity;
			return Color(color).alpha(alpha).rgbString();
		}

        //-------------------------------------------------------------------------------------------
        // COOKIES FUNCTIONS
        //-------------------------------------------------------------------------------------------
        function saveCountries(){
            outList = [];
            // Get selected countries from the chart
            config1.data.datasets.forEach( (d) => {
                if( outList.indexOf(d.label) < 0 ){
                    outList.push(d.label)
                }
            });
            // convert this list into JSON
            var jsonTxt = JSON.stringify(outList);
            console.log("Saving display preferences into cookie");
            // Store this string into a cookie
            
            var date = new Date();
            date.setTime(date.getTime()+(1000*60*60*24*365));
            var expiry = date.toUTCString();
            document.cookie = "covid19Prefs="+ jsonTxt +"; expires="+ expiry + "; path=/";
        }
        function restoreCountries(){
            // Get cookie
            countries = '[]';
            var name = "covid19Prefs=";
            var decodedCookie = decodeURIComponent(document.cookie);
            var ca = decodedCookie.split(';');
            for(var i = 0; i <ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                    console.log("loading cookie for display preferences");
                    countries = c.substring(name.length, c.length);
                }
            }
            countries = JSON.parse(countries);
            if(countries.length <= 0){
                countries.push(WORLD);
            }
            
            countries.forEach( (c) => {
                console.log("restoring charts for "+c);
                addCountryCharts(c);
            });
        }
        
        

        //-------------------------------------------------------------------------------------------
        // CHART DATA AND CONFIGS
        //-------------------------------------------------------------------------------------------
        var DATA  = <?php echo($DATA); ?>;
        var WORLD = "<?php echo(WORLD); ?>";
        var RANK  = "<?php echo(RANK); ?>";
        var STATS = <?php echo($STATS); ?>;
        
        var selectedCountries = [];
    
		var config1 = {
			type: 'line',
			data: {
				datasets: []
			},
			options: {
				responsive: true,
				title: {
					display: true,
					text: 'COVID-19 : Number of cases and deaths'
				},
				tooltips: {
					mode: 'index',
					intersect: false,
				},
				hover: {
					mode: 'nearest',
					intersect: true
				},
                scales: {
					xAxes: [{
						type: 'time',
						display: true,
						scaleLabel: {
							display: true,
							labelString: 'Date'
						},
						ticks: {
							major: {
								fontStyle: 'bold',
								fontColor: '#FF0000'
							}
						}
					}],
					yAxes: [{
						display: true,
						scaleLabel: {
							display: true,
							labelString: ''
						},
                        position: 'left',
						id: 'y-axis-1'
					}]
				}
			}
		};

        config1.options.maintainAspectRatio = false;

        var config3 = JSON.parse(JSON.stringify(config1));
        config3.options.title.text = 'COVID-19 : percentage of death'

        
        
        var config9 = JSON.parse(JSON.stringify(config1));
        config9.options.title.text = 'Connection stats'
        config9.labels = [];
        config9.options.scales.xAxes[0].type = 'time';
        config9.options.scales.xAxes[0].time = {unit:'hour'};
        config9.options.scales.xAxes[0].scaleLabel.labelString = 'Hours';
        config9.options.maintainAspectRatio = false;
        config9.options.responsive = true;
        
        var config2A = JSON.parse(JSON.stringify(config1));
        config2A.options.title.text = 'COVID-19 : Delta of cases and deaths'
        config1.options.maintainAspectRatio = false;
        
        config2A.options.scales.yAxes = [{
						display: true,
						scaleLabel: {
							display: true,
							labelString: ''
						},
                        position: 'left',
						id: 'y-axis-1'
					},{
						display: true,
						scaleLabel: {
							display: true,
							labelString: ''
						},
                        position: 'right',
						id: 'y-axis-2'
					}];
		
        
        var config2B = {
            type:'radar',
            data:{
                labels:[],
                datasets:[{
                        backgroundColor: transparentize(window.chartColors.blue,0.75),
                        borderColor: transparentize(window.chartColors.blue,0.3),
                        data: [],
                        label: 'Nb cases'
                    }]
            }
        };

        var config2C = {
            type:'radar',
            data:{
                labels:[],
                datasets:[{
                        backgroundColor: transparentize(window.chartColors.red,0.75),
                        borderColor: transparentize(window.chartColors.red,0.3),
                        data: [],
                        label: 'Nb Deaths'
                    }]
            }
        };

        
        // Set config 1 to logarithmic Y
        //config1.options.scales.yAxes[0].type='logarithmic';
        // Set config 2A to logarithmic Y
        //config2A.options.scales.yAxes[0].type='logarithmic';

        
        
        
        //-------------------------------------------------------------------------------------------
        // CHART FUNCTIONS
        //-------------------------------------------------------------------------------------------       
        // a key has been pressed
        function pressedKey(event){
            if(event.key == "Enter"){
                console.log(" key "+event.key+" has been pressed.");
                changeCountry();
            }
        }
        
        // ADD a chart into the canvas
        function addLineChart(country ,color, chartNumber, labelled){
            axisID = 1;
            if(chartNumber == 4){
                axisID = 2;
            }
            var newDataset = {
				label: labelled,
				backgroundColor: 'rgb(255, 255, 255)',
				borderColor: color,
				data: [],
				fill: false,
                yAxisID: 'y-axis-'+axisID
			};            
            // Add Label + Value
            Object.keys(DATA[country]).forEach( (key) => {
                if (key != RANK){
                    var value = DATA[country][key][chartNumber];
                    newDataset.data.push( {x:key, y:value });  
                }
            });
            // Add DataSet
            switch(chartNumber){
                case 0:
                case 1:
                    console.log("add chart 0");
                    config1.data.datasets.push(newDataset);
                    window.myLine1.update();
                    break;
                case 2:
                    console.log("add chart 3");
                    config3.data.datasets.push(newDataset);
                    window.myLine3.update();
                    break;
                case 3:
                case 4:
                    console.log("add chart 3");
                    config2A.data.datasets.push(newDataset);
                    window.myLine2A.update();
                    break;
            }
        }

        // Remove all charts from country
        function removeCountryCharts(country, datasets){
            // Init locals
            var hasBeenFound   = true;
            var hasBeenRemoved = false;
            var idx            = 0;
            // check if the country is present in the datasets
            while(hasBeenFound == true){
                hasBeenFound = false;
                // Search for index
                datasets.forEach( (d) => {
                    if(d.label == country){
                        console.log("Remove charts for "+country);
                        idx = datasets.indexOf(d)
                        hasBeenFound = true;
                    }
                } );
                // Remove if found
                if(hasBeenFound){
                    datasets.splice(idx,1);
                }
                // update result
                hasBeenRemoved |= hasBeenFound;
            }
            // return result of process
            return hasBeenRemoved;
        }
        
        // Generate all charts for a country
        function addCountryCharts(country) {
            // first check if wez have to remove data, else just add data
            var remove1  = removeCountryCharts(country, config1.data.datasets);
            var remove3  = removeCountryCharts(country, config3.data.datasets);
            var remove2A = removeCountryCharts(country, config2A.data.datasets);
            
            if( remove1 == false && remove3 == false && remove2A == false){
                console.log("generating chart for "+country);
                // Get hash code from country string
                var hash = hashCode(country);
                // Get pseudo random color from hash
                var R0 = ((hash    )&0xFF);
                var G0 = ((hash>> 8)&0xFF);
                var B0 = ((hash>>16)&0xFF);
                var color = 'rgb('+R0+', '+G0+', '+B0+')';
                // add charts
                addLineChart(country, color, 0, country);
                addLineChart(country, color, 1, country);
                addLineChart(country, color, 2, country);
                addLineChart(country, color, 3, country);
                addLineChart(country, color, 4, country);

            }
            else{
                // updaye charts after removal
                window.myLine1.update();
                window.myLine3.update();
                window.myLine2A.update();
            }
        };

        
        // Clear all charts
        function clearAllCharts(){
            config1.data.datasets = [];
            config3.data.datasets = [];
            window.myLine1.update();
            window.myLine3.update();
        }
        
        // Clear all charts and generate charts for one country
        function displayOneCountry(country) {
            console.log("Clearing chart");
            clearAllCharts();
            addCountryCharts(country);
        };
        
        // Change country
        function changeCountry(){
            console.log("change!");
            country = document.getElementById("countrySelect").value;
            //displayOneCountry(country);
            addCountryCharts(country);
        }

        // Display radar charts
        function displayRadars(){
            Object.keys(DATA).forEach( (country) => {
                if(country != WORLD){                   
                    var rank = DATA[country][RANK];
                    if(rank < 9){
                        var size    = Object.keys(DATA[country]).length-2;
                        var dt      = Object.keys(DATA[country])[size];
                        var nbCases = DATA[country][dt][0];                                 
                        var nbDeaths = DATA[country][dt][1];
                        config2B.data.datasets[0].data.push( nbCases );
                        config2C.data.datasets[0].data.push( nbDeaths );
                        config2B.data.labels.push( country );
                        config2C.data.labels.push( country );
                    }
                }
            });    
            window.myLine2B.update();    
            window.myLine2C.update();    
        }

        function displayStats(){
            var axisID = 1;
            var newDataset = {
				label: "Connections/Hour",
				backgroundColor: 'rgb(255, 255, 255)',
				borderColor: 'rgb(0, 0, 255)',
				data: [],
				fill: false,
                yAxisID: 'y-axis-'+axisID
			};
            Object.keys(STATS).sort().forEach( (k) => {
                var nb = STATS[k];
                var dt = k * 1;
                if (dt == 0){
                    dt += 24;
                }
                dt -= 1;
                dt *= 3600*1000;
                newDataset.data.push({x:dt, y:nb});
                console.log(dt/(3600*1000) + " " + nb);
            });
            config9.data.datasets.push(newDataset);
            window.myLine9.update();
        }
            
        // ON LOAD : clear charts and display WORLD values
		window.onload = function(){
            var ctx1  = document.getElementById('canvasCases').getContext('2d');
            var ctx2A = document.getElementById('canvasWorldA').getContext('2d');
            var ctx2B = document.getElementById('canvasWorldB').getContext('2d');
            var ctx2C = document.getElementById('canvasWorldC').getContext('2d');
            var ctx3  = document.getElementById('canvasPerc').getContext('2d');
            var ctx9  = document.getElementById('canvasStats').getContext('2d');
            window.myLine1  = new Chart(ctx1 , config1 );
            window.myLine2A = new Chart(ctx2A, config2A);
            window.myLine2B = new Chart(ctx2B, config2B);
            window.myLine2C = new Chart(ctx2C, config2C);
            window.myLine3  = new Chart(ctx3 , config3 );
            window.myLine9  = new Chart(ctx9 , config9 );

            // Restore Cookie countries to be displayed (or else the WORLD)
            restoreCountries();
            
            // Display radars
            displayRadars();
            
            // display Stats
            displayStats();
        }

        //-------------------------------------------------------------------------------------------
        // END OF CHART SCRIPTS
        //-------------------------------------------------------------------------------------------
        


        </script>
        
        
        
</body>

</html>