jQuery(document).ready(function($) {
	 $(function(){
	  
	  var $container = $('#redbox_container');

	  //$container.isotope({
	//	itemSelector : '.redbox_item'
	//  });
	  
	  
	  var $optionSets = $('#options .option-set'),
		  $optionLinks = $optionSets.find('a');

	  $optionLinks.click(function(){
	  
		var $this = $(this);
		// don't proceed if already selected
		if ( $this.hasClass('current') ) {
		  return false;
		}
		var $optionSet = $this.parents('.option-set');
		$optionSet.find('.current').removeClass('current');
		$this.addClass('current');
  
		// make option object dynamically, i.e. { filter: '.my-filter-class' }
		var options = {},
			key = $optionSet.attr('data-option-key'),
			value = $this.attr('data-option-value');
		// parse 'false' as false boolean
		value = value === 'false' ? false : value;
		options[ key ] = value;
		if ( key === 'layoutMode' && typeof changeLayoutMode === 'function' ) {
		  // changes in layout modes need extra logic
		  changeLayoutMode( $this, options )
		} else {
		  // otherwise, apply new options
		  $container.isotope( options );
		}
	
		return false;
	  });
	});
});
