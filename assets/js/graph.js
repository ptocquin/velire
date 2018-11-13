$(document).ready(function () {

	var ctx = document.getElementById('myChart').getContext('2d');
	var d = document.getElementById('dataset');
	console.log(typeof(d.dataset.values));
	console.log(JSON.parse(d.dataset.values));
	var chart = new Chart(ctx, {
    // The type of chart we want to create
    type: 'line',

    // The data for our dataset
    data: JSON.parse(d.dataset.values),
    //{ labels: ["January", "February", "March", "April", "May", "June", "July"], datasets: [{ label: "My First dataset", backgroundColor: "rgb(255, 99, 132)", borderColor: "rgb(255, 99, 132)", data: [0, 10, 5, 2, 20, 30, 45], }] },

    // Configuration options go here
    // options: {}
    options: {
        maintainAspectRatio: false,
        scales: {
            xAxes: [{
              type: 'time',
              time: {
                    unit: 'minute',
                    displayFormats: {
                        minute: 'h:mm'
                    },
                    parser: 'YY-MM-DD HH:mm:ss',
                    stepSize: 15
                }
            }]
        }
    }
	});

});