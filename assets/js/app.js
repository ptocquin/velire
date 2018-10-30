/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you require will output into a single css file (app.css in this case)
require('../css/app.scss');

// Need jQuery? Install it with "yarn add jquery", then uncomment to require it.
const $ = require('jquery');

require('bootstrap');
const feather = require('feather-icons');

require('./jquery.collection.js');

require('chart.js');

$(document).ready(function () {
  $('.form-collection').collection({
		position_field_selector: '.rank',
		allow_duplicate: true,
		allow_up: true,
 		allow_down: true,
 	}
  	);

	feather.replace();

	$('[data-toggle="popover"]').popover();

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
    options: {}
	});

});



console.log('Hello Webpack Encore! Edit me in assets/js/app.js');
