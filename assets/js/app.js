/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you require will output into a single css file (app.css in this case)

require('../css/app.scss');
const $ = require('jquery');
global.$ = global.jQuery = $;
//require('jquery');
//global.jQuery = global.$ = require('jquery');
require('bootstrap');
const feather = require('feather-icons');

require('./jquery.collection.js');

require('chart.js');

require('@fortawesome/fontawesome-free/css/regular.min.css');
require('@fortawesome/fontawesome-free/js/regular.js');


const routes = require('../../public/js/fos_js_routes.json');
import Routing from '../../vendor/friendsofsymfony/jsrouting-bundle/Resources/public/js/router.min.js';

Routing.setRoutingData(routes);
// Routing.generate('rep_log_list');
console.log(Routing.generate('set-position'));
console.log(Routing.generate('set-cluster'));

$(document).ready(function () {

  $('.alert-fade').fadeOut(10000);
	
  $('.form-collection').collection({
		position_field_selector: '.rank',
		allow_duplicate: true,
		allow_up: true,
 		allow_down: true,
 	}
  	);

	feather.replace();

	$('[data-toggle="popover"]').popover();

	$('[data-toggle="tooltip"]').tooltip();

	$('.clk_increment').on('click', function() {
		console.log("click");
		var old = parseInt($("input", this).val());
		if(old < 10) {
			var next =  old + 1;
		} else {
			var next = 1;
		}
		console.log(old+" > "+next);
		$("input", this).val(next);
		$(".value", this).html(next);
	});

	$('#unlock').on('click', function() {
		console.log("unlock");
		if ($(this).hasClass("fa-lock-open")) {
            $(this).removeClass("fa-lock-open");
            $('.cluster').attr("disabled","true");
        } else {
        	$(this).addClass("fa-lock-open");            
            $('.cluster').removeAttr("disabled");
        }
	})

	// var d = document.getElementById('dataset');
	// console.log(typeof(d.dataset.values));
	// console.log(JSON.parse(d.dataset.values));

	$('.set-address').on('click', function(){
		var address = $(this).next().html();
		console.log(address.length);
		if(address.length == 0) {
			console.log("vide");
		}
	})

		$('.cluster-plus').on('click', function(){
			var label = $(this).next();
			var input = $('input[name=cluster]', this);
			var cluster = parseInt($('input[name=cluster]', this).val())+1;
			var luminaire = $('input[name=luminaire]', this).val();
			var d = {l: luminaire, c: cluster}
			$.ajax({
				type: 'post',
				url: Routing.generate('set-cluster'),
				data: {'data': d },
				beforeSend: function() {
					console.log('chargement !')
					console.log(d);
				},
				success: function(response) {
					console.log(response);
	 				label.text(response.c);
	 				input.val(response.c);
	 				if (response.cluster_added == 1) {
	 					location.reload();
	 				}
				}
			})

			console.log(cluster,luminaire);
		})

		$('.cluster-minus').on('click', function(){
			var label = $(this).prev();
			var input = $('input[name=cluster]', this);
			var cluster = parseInt($('input[name=cluster]', this).val())-1;
			var luminaire = $('input[name=luminaire]', this).val();
			var d = {l: luminaire, c: cluster}
			$.ajax({
				type: 'post',
				url: Routing.generate('set-cluster'),
				data: {'data': d },
				beforeSend: function() {
					console.log('chargement !')
					console.log(d);
				},
				success: function(response) {
					console.log(response);
	 				label.text(response.c);
	 				input.val(response.c);
	 				if (response.cluster_added == 1) {
	 					location.reload();
	 				}
	 				
				}
			})

			console.log(cluster,luminaire);
		})

	$('.set-position').on('click', function(){
		var id = $("input", this).val();
		var x_pos = $("#"+id+"_colonne").val();
		var y_pos = $("#"+id+"_ligne").val();
		var positions = {id: id, x: x_pos, y: y_pos};
  		$.ajax({
			type: 'post',
			url: Routing.generate('set-position'),
			data: {'data': positions },
			beforeSend: function() {
				console.log('chargement !')
				console.log(positions);
			},
			success: function(response) {
				console.log(response);
 				location.reload();
			}
		})
	})

	// var ctx = document.getElementById('myChart').getContext('2d');
	// var d = document.getElementById('dataset');
	// console.log(typeof(d.dataset.values));
	// console.log(JSON.parse(d.dataset.values));
	// var chart = new Chart(ctx, {
 //    // The type of chart we want to create
 //    type: 'line',

 //    // The data for our dataset
 //    data: JSON.parse(d.dataset.values),
 //    //{ labels: ["January", "February", "March", "April", "May", "June", "July"], datasets: [{ label: "My First dataset", backgroundColor: "rgb(255, 99, 132)", borderColor: "rgb(255, 99, 132)", data: [0, 10, 5, 2, 20, 30, 45], }] },

 //    // Configuration options go here
 //    options: {}
	// });

});



console.log('Hello Webpack Encore! Edit me in assets/js/app.js');
