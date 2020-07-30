$(document).ready(function () {
	console.log("test.js")
	$('#test_recipe').on('click', function(e){
		labels = [];
		intensities = [];
		pwm_start = [];
		pwm_stop = [];
		commands = [];
		$('tbody tr').each(function(i, tr){
			labels.push($(tr).children('th').text());
		});
		$('tbody tr td input ').each(function(i, input){
			if($(input).attr('id').match(/level/)){
				intensities.push($(input).val());
			}
		});
		$('tbody tr td input ').each(function(i, input){
			if($(input).attr('id').match(/pwm_start/)){
				pwm_start.push($(input).val());
			}
		});
		$('tbody tr td input ').each(function(i, input){
			if($(input).attr('id').match(/pwm_stop/)){
				pwm_stop.push($(input).val());
			}
		});

		for (var i = labels.length - 1; i >= 0; i--) {
			commands.push(labels[i]+" "+intensities[i]+" "+pwm_start[i]+" "+pwm_stop[i]);
		}

		console.log(commands);
				   // if( $(this).attr('id').match(/ingredients/) ) {
		   // 		labels.push($("label[for='" + $(this).attr('id') + "']").text());
		   //      intensities.push($(this).val());
		   //      commands.push($("label[for='" + $(this).attr('id') + "']").text()+" "+$(this).val());
		   // }

		d = {labels: labels, intensities: intensities, commands: commands}
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
