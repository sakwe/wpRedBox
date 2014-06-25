var canceled=false;

function redbox_action_ajax_callback(data){
	jQuery(document).ready(function($) {
		$.post(ajaxurl, data, function(response) {
			if(!canceled){
				if (response!='' 
				&& (data['redbox_ajax_action']=='redbox_proposition_approve' 
				||  data['redbox_ajax_action']=='redbox_proposition_disapprove' 
				||  data['redbox_ajax_action']=='redbox_proposition_delete' )) {
					$("#redbox_proposition_box_"+data['redbox_ajax_id']).html(response);
				}
				else if (data['redbox_ajax_action']=='redbox_working_action_mini') { 
					$("#"+ data['redbox_ajax_working_action'] + "_"+data['redbox_ajax_id']).html(response);
				}
				else{
					$("#redbox_status").html(response);
				}
			}
			else{
				response = null;
				$("#redbox_status").html('');
			}
		});
	});
}

function redbox_ajax_do(action,id,with_waiting,without_message){
	if (action){
		canceled=false;
		if (with_waiting){
			if (!without_message){
				var data = {
					action:'redbox_action_ajax',
					redbox_ajax_action: 'redbox_working_action',
					redbox_ajax_id:id,
					redbox_ajax_working_action: action
				};
			}
			else{
				var data = {
					action:'redbox_action_ajax',
					redbox_ajax_action: 'redbox_working_action_mini',
					redbox_ajax_id:id,
					redbox_ajax_working_action: action
				};
			}
		}
		else{
			var data = {
				action:'redbox_action_ajax',
				redbox_ajax_action: action,
				redbox_ajax_id:id
			};
		}
		redbox_action_ajax_callback(data);
	}
	else{
		canceled = true;
		document.getElementById("redbox_status").innerHTML='';
	}
}

function redbox_do_post(choice){
	switch (choice){
		case "Y" : 
			document.getElementById("redbox_action").value = "redbox_confirm_post";
			window.document.forms['redbox_form'].submit();
			break;
			
		default : 
			redbox_ajax_do('redbox_cancel_proposition');
			break;
	}
}

function redbox_go_to_post(choice,target){
	switch (choice){
		case "Y" : 
			window.location.href = target;
			break;
			
		default : 

			break;
	}
}


function redbox_do_proposition(choice){
	switch (choice){
		case "Y" : 
			document.getElementById("redbox_action").value = "redbox_confirm_proposition";
			window.document.forms['redbox_form'].submit();
			break;
			
		default : 
			redbox_ajax_do('redbox_cancel_proposition');
			break;
	}
}

function redbox_facebook_import(choice){
	switch (choice){
	
		case "Y" : 
			redbox_do_reported_action();
			break;
			
		default : 
			document.getElementById("redbox_report_action_status").value = "0";
			break;
	}
}


