$(document).ready(function () {
	console.log("test.js")
	$('#test_recipe').on('click', function(e){
		labels = [];
		intensities = [];
		$('input').each(function(){
		   if( $(this).attr('id').match(/ingredients/) ) {
		   		labels.push($("label[for='" + $(this).attr('id') + "']").text());
		        intensities.push($(this).val());
		   }
		});

		d = {labels: labels, intensities: intensities}
		$.ajax({
			type: 'post',
			url: Routing.generate('test-recipe'),
			data: {'data': d },
			beforeSend: function() {
				console.log('chargement !')
				console.log(d);
			},
			success: function(response) {
				console.log(response);
 				// label.text(response.c);
 				// input.val(response.c);
 				// if (response.cluster_added == 1) {
 				// 	location.reload();
 				// }
			}
		})
	});
});
