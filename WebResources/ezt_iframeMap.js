function iframeMap(){
 
    //var addressLat = Xrm.Page.getAttribute("address1_latitude").getValue();

    //var addressLon = Xrm.Page.getAttribute("address1_longitude").getValue();

 var IFrame = Xrm.Page.ui.controls.get("IFRAME_EasyTerritory");

  var Url = IFrame.getSrc();

  var newUrl = Url.split("?");

  var path = newUrl[0];

  var queryString = newUrl[1];

   if (queryString.indexOf("zoom") == -1){
       var queryStringArray = queryString.split('&');

       var i;
    for( i= 0; i < queryStringArray.length; i++) {
          if(queryStringArray[i].includes("Lat")) {
            var addressLat = queryStringArray[i];
            addressLat = addressLat.replace("Lat=","");
          } else if (queryStringArray[i].includes("Lon")) {
            
            var addressLon = queryStringArray[i];
            addressLon = addressLon.replace("Lon=","");
          } else if (queryStringArray[i].includes("projectId")) {
            var projectId = queryStringArray[i];
            
          }    
    }
       
      addressLat = Xrm.Page.getAttribute(addressLat).getValue();
      addressLon = Xrm.Page.getAttribute(addressLon).getValue();


      var param1 = ("&zoomToLatLon="+addressLat+","+addressLon);

      var newTarget ='';

     newTarget = (path + '?' +projectId + param1);

     IFrame.setSrc(newTarget);

      
     } 

}

