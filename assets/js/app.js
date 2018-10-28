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

require('./jquery.collection.js')

$(document).ready(function () {
  $('.form-collection').collection({
		position_field_selector: '.rank',
		allow_duplicate: true,
		allow_up: true,
 		allow_down: true,
 	}
  	);

	feather.replace();

	$('[data-toggle="popover"]').popover()

});



console.log('Hello Webpack Encore! Edit me in assets/js/app.js');
