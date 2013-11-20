jQuery(document).ready(function($){
	$("#ajaxButton").click(function(event){
		event.preventDefault();
		$("#resyncLoaderStatus").html(" <img src='/wp-content/plugins/WP-Facebook-Importer/loading.gif' width='11' /> Resyncing <small> - Page can be reloaded or closed and process will run in background</small>");
		$.ajax({
			url: $(this).attr("href"),
		}).done(function(data) {
			if (data == "done") {
				$("#resyncLoaderStatus").html("Resync done, <a href='"+ window.location +"'>reload page</a> to see changes");
			}
		});
	})
	
	if ($("#resyncLoaderStatus").attr("rel") == "resync-fb-galleries" && $("#resyncLoaderStatus").html() != "") {
		setInterval("checkGalleryLoader()", 1500)
	}
});

function checkGalleryLoader() {
	console.log(true);
	jQuery.ajax({
		url: "/wp-admin/index.php?check=resync-fb-galleries",
	}).done(function(data) {
		if (data == "done") {
			jQuery("#resyncLoaderStatus").html("Resync done, <a href='"+ window.location +"'>reload page</a> to see changes");
		}
	})
}