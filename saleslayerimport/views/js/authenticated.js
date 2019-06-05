$(document).ready(function(){
	$('.form-update').submit(function(){
	  var empty_shops = $('#'+$(this).attr('id')+' [type="checkbox"]').not(':checked');
	  var all_shops = $('#'+$(this).attr('id')+' [type="checkbox"]');
	  
	  if (empty_shops.length == all_shops.length){
	  	jConfirm('All shops will be synchronized, are you sure??',  '', function(r){
	  	    if (r != true){                    
	  	    	return false;
	  	    }
	  	});
	  }
	});
});