function openEZT(projectId, selectedItem) {

		 var eztUrlOpen = '';
		 var collectionName = '';
		 var primaryIdAttribute = '';
		 var latitude = '';
		 var longitude = '';

		 if (typeof selectedItem !== 'undefined') {
	        var recordId = selectedItem[0].Id;
	        recordId = recordId.replace('{', '').replace('}', '');
	        var entity = selectedItem[0].TypeName;
            var needCrmApiCalls = true;

   		 }
                

   		 var clientURL = Xrm.Page.context.getClientUrl();

		function openEztApp(callback){
			
			
			window.open(eztUrlOpen + '/index.html?' + projectId + '&zoomToLatLon=' + latitude + ',' + longitude);
            
			callback();
		}

		function getRecordLatLonFromEntity(callback) {
		  
			if (needCrmApiCalls) {

                
                var restRecordURL = clientURL + '/api/data/v9.0/' + collectionName + '?$filter=' + primaryIdAttribute +' eq ' + recordId;
               

                var xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            var responseJson = JSON.parse(this.responseText);
                            latitude = responseJson.value[0].address1_latitude;
                            longitude = responseJson.value[0].address1_longitude;
                            
                            callback();
                        } else if (this.status == 404 || this.status == 400) {
                            alert('Call to ' + clientURL + '/api/data/v9.0/' + collectionName + '?$filter=' + primaryIdAttribute +' eq ' + recordId + ' failed.');
                        }
                    };
                xhttp.open('GET', restRecordURL, true);
                xhttp.setRequestHeader('Accept', 'application/json; charset=utf-8');
                xhttp.send();
            } else {
                latitude = Xrm.Page.getAttribute("address1_latitude").getValue();
                longitude = Xrm.Page.getAttribute("address1_longitude").getValue();
                
                callback();
            }

		  
		}

		function getRecordInfoFromApi(callback) {
		  if (needCrmApiCalls) { 
                var restEntityURL = clientURL + "/api/data/v9.0/EntityDefinitions?$select=PrimaryIdAttribute,LogicalName,LogicalCollectionName&$filter=LogicalName eq '" + entity + "'";
                
                var xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        var responseJson = JSON.parse(this.responseText);
                        collectionName = responseJson.value[0].LogicalCollectionName;
                        primaryIdAttribute = responseJson.value[0].PrimaryIdAttribute;
                        
                        callback();
                        
                    } else if (this.status == 404 || this.status == 400) {
                        alert('Call to ' + restQueryURL + ' failed.');
                    }
                };
                xhttp.open('GET', restEntityURL, true);

                xhttp.setRequestHeader('Accept', 'application/json; charset=utf-8');
                xhttp.send();
            } else{

                callback();
            }
		}

		function getEztInstaneUrl(callback) {
		  
		   var restQueryURL = clientURL + "/XRMServices/2011/OrganizationData.svc/ezt_easyterritorysettingsSet?$select=ezt_easyterritorysettingsId,ezt_Setting,ezt_SettingName&$filter=ezt_SettingName eq 'EZT Instance URL'";

	        var xhttp = new XMLHttpRequest();
	        xhttp.onreadystatechange = function() {
	            if (this.readyState == 4 && this.status == 200) {
	                var responseJson = JSON.parse(this.responseText);
	                
	                eztUrlOpen = responseJson.d.results[0].ezt_Setting;
	                callback();
	            } else if (this.status == 404 || this.status == 400) {
	                alert('Call to ' + restQueryURL + ' failed.');
	            }
	        };
	        xhttp.open('GET', restQueryURL, true);

	        xhttp.setRequestHeader('Accept', 'application/json; charset=utf-8');
	        xhttp.send();
			  
		}

		function runInOrder(callback) {
		    getEztInstaneUrl(function() {
		        getRecordInfoFromApi(function() {
		            getRecordLatLonFromEntity(function(){
		            	openEztApp(callback);
		            });
		        });
		    });
		}

		runInOrder();


}
