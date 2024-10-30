//spa_array s/b defined in the php file in a different script tag
//spa_count s/b defined in the php file in a different script tag

jQuery(document).ready(function(){		
	try{	
		//replace all the subcategories extra text
		jQuery('li.cat-item a').each(function (i){ 
			var str   = jQuery(this).html();
			var regex = /(\s[\(]).+([\)])/;
			str = str.replace(regex, "");
			jQuery(this).html(str);
			//console.log(jQuery(this).html()); 
		});	
		
		var ResetDropDownLists = function(){
			for (var i = 1; i <= spa_count; i++) {
				var filteredDropDown = jQuery("#f"+i);
				filteredDropDown.val("--Select--");
				if(i!=1){
					filteredDropDown   
					.find('option')
					.remove()
					.end()
					.append(jQuery("<option></option>")
					.text("--Select--"));  
					
					if(jQuery("#f"+i+"_chosen")){
						filteredDropDown.trigger("chosen:updated");
					}
					
					filteredDropDown.prop('disabled',true);
				}	
				
				if(jQuery("#f"+i+"_chosen")){
					filteredDropDown.trigger("chosen:updated");
				}
				
				
			}			
		}
		
		var GetNextDropDownList = function(index,selectedValue){
			var nextIndex = index+1;
			var filters = [];
			for(var i=1;i<=index;i++){
				var dropDownSelection = jQuery("#f"+i).val();
				if(dropDownSelection){
					filters.push(dropDownSelection);
				}
			}
			//var currentDropDownSelected = jQuery("#f"+index).val();
			var nextDropDown = jQuery("#f"+nextIndex);		
			jQuery.ajax({
				//url : "/index.php?action=GetDropDownList&index="+nextIndex+"&filter="+currentDropDownSelected,
				url : "/index.php?action=lightningimport_GetDropDownList&index="+nextIndex+"&filter="+filters,
				type : "get",
				async: false,
				success : function(data) {
					nextDropDown
				.find('option')
				.remove()
				.end()
				.append(jQuery("<option></option>")
				.text("--Select--")); 
				
				var values = data;
				
				for(var ii = 0;ii<values.length;ii++){				
				//add the options
				nextDropDown   
				.find('option')
				.end()
				.append(jQuery("<option></option>")
				.attr("value",values[ii])
				.text(values[ii])); 				
				}
				nextDropDown.prop('disabled',false);
				
				if(jQuery("#f"+nextIndex+"_chosen")){
				nextDropDown.trigger("chosen:updated");
				}
				
				if(selectedValue){
				nextDropDown.val(selectedValue);
				}
				},
				error: function() {
				connectionError();
				}
				});	
				
				
				for (var i = index+2; i <= spa_count; i++) {
				var filteredDropDown = jQuery("#f"+i);
				filteredDropDown.val("--Select--");			
				filteredDropDown   
				.find('option')
				.remove()
				.end()
				.append(jQuery("<option></option>")
				.text("--Select--"));  
				
				filteredDropDown.prop('disabled',true);
				}
				}
				
				var urlParams;
				(window.onpopstate = function () {
				var match,
				pl     = /\+/g,  // Regex for replacing addition symbol with a space
				search = /([^&=]+)=?([^&]*)/g,
				decode = function (s) { return decodeURIComponent(s.replace(pl, " ")); },
				query  = window.location.search.substring(1);
				
				urlParams = {};
				while (match = search.exec(query))
				urlParams[decode(match[1])] = decode(match[2]);
				})();
				
				//Populate drop down lists with default data
				ResetDropDownLists();
				
				
				var spa_count = jQuery(".spa_count").data("val");
				if (typeof spa_count  == 'undefined'){ spa_count = 0; }
				
				for (var i = 1; i <= spa_count; i++) {
				jQuery("#f"+i).change(function(){
				//FilterDropDownLists();
				GetNextDropDownList(jQuery(this).data("index"));
				});
				
				//If a value is set in the query string update the value of the select input
				var fQueryString = urlParams["f"+i];
				if(fQueryString != null)
				{	
				if(i==1){
				jQuery("#f"+i).val(fQueryString);
				if(jQuery("#f"+i+"_chosen")){
				jQuery("#f"+i).trigger("chosen:updated");
				}
				}
				else{
				// jQuery("#f"+i).prop('disabled',false);
				// jQuery("#f"+i).append(jQuery('<option value="'+fQueryString+'"></option>')
				// .text(fQueryString));				
				GetNextDropDownList(i-1,fQueryString);				
				}
				}
				}
				
				jQuery("#clearDropDowns").click(function(e){
				e.preventDefault();
				// for (var i = 1; i <= spa_count; i++) {
				// jQuery("#f"+i).val("--Select--");
				// }
				// FilterDropDownLists();
				ResetDropDownLists();
				});	
				}
				catch(err){
				//An error occured. Fail silently so we dont interfere with other plugins js
				alert(err);
				}
				
				});					