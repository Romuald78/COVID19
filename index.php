<?php
// include lib to get data from COVID
include_once("libCovid.php");

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
            
        <div id="canvas3" class="canvas3" >
            <canvas id="canvasPerc" ></canvas>
        </div>
    </div>
	
    
    <div class="list1">
        <input type="button" value="CLEAR" style="width=20%" onClick="clearAllCharts()">
        <form autocomplete="off">
            <select name="countries" id="countrySelect"  onclick="changeCountry()" onkeypress="pressedKey(event)" style="width:70%"size=40 >
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
        // CHART DATA AND CONFIGS
        //-------------------------------------------------------------------------------------------
        var DATA  = <?php echo($DATA); ?>;
        var WORLD = "<?php echo(WORLD); ?>";
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
							labelString: 'value/percentage'
						},
                        position: 'left',
						id: 'y-axis-1'
					}]
				}
			}
		};
        var config3 = JSON.parse(JSON.stringify(config1));
        config3.options.title.text = 'COVID-19 : percentage of death'

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
                newDataset.data.push( {x:key, y:DATA[country][key][chartNumber] });  
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
            var remove1 = removeCountryCharts(country, config1.data.datasets);
            var remove3 = removeCountryCharts(country, config3.data.datasets);
            
            if( remove1 == false && remove3 == false ){
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
            }
            else{
                // updaye charts after removal
                window.myLine1.update();
                window.myLine3.update();
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

        // ON LOAD : clear charts and display WORLD values
		window.onload = function(){
            var ctx1 = document.getElementById('canvasCases').getContext('2d');
            var ctx3 = document.getElementById('canvasPerc').getContext('2d');
            window.myLine1 = new Chart(ctx1, config1);
            window.myLine3 = new Chart(ctx3, config3);

            displayOneCountry(WORLD);
        }

        //-------------------------------------------------------------------------------------------
        // END OF CHART SCRIPTS
        //-------------------------------------------------------------------------------------------
        


        </script>
        
        
        
</body>

</html>