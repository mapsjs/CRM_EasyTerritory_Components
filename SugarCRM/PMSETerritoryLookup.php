<?php
require_once 'modules/pmse_Inbox/engine/PMSEElements/PMSEScriptTask.php';
require_once 'include/SugarQuery/SugarQuery.php';
/**
 * Custom action that does a territory lookup and assigns the user to a record.  The purpose of this would be lead assignment.
 */
class PMSETerritoryLookup extends PMSEScriptTask
{
    /**
     * @inheritDoc
     */
    public function run($flowData, $bean = null, $externalAction = '', $arguments = array())
    {
        $this->eztTerritoryLookup($bean, $flowData);
        $flowAction = $externalAction === 'RESUME_EXECUTION' ? 'UPDATE' : 'CREATE';
        return $this->prepareResponse($flowData, 'ROUTE', $flowAction);
    }
    /**
     * Handles the action of doing a location lookup and assigning a user to that reecord. 
     *
     * Sequence flow includes geocoding records address field, querying map dot net endpoint to see what territory geocode point falls in, looking up that territory user, finally assigning that user to that record.
     *
     * @param SugarBean $bean
     */
    protected function eztTerritoryLookup(SugarBean $bean, $flowData)
    {
            
            try{ 
            	
            	$recordName = $bean->name;
              
              
	            $eztConfigurationSettings = $this->getEztSettings($bean, $flowData);

	            $recordAddress = $this->getRecordAddress($bean, $eztConfigurationSettings);

	            $token = $this->getEztToken($eztConfigurationSettings, $recordName);

	            $latAndLon = $this->geocodeRecord($token, $eztConfigurationSettings, $recordAddress, $recordName);

	            $eztTerritory = $this->mdnRecordLookup($token, $latAndLon, $eztConfigurationSettings, $recordName);

	            $user = $this->getTerritoryMapping($eztTerritory, $recordName);

	            $userDetails = $this->getUserDetails($user, $recordName, $bean);

                

	            if(empty($userDetails) || empty($userDetails['userFullName']) || empty($userDetails['userId'])) {

                    throw new Exception('Error EZT Territory Lookup. Exception thrown on line 49 of PMSETerritoryLookup.php- Cannot find $userDetails properties or record properties for territory assignment on record: ' . $recordName);
                } else {

                    //$GLOBALS['log']->fatal(print_r($bean, true));


                    $tagField = $bean->getTagField();
                    $tagFieldProperties = $bean->field_defs[$tagField];

                    $link = 'assigned_user_link';

                    if($bean->load_relationship($link)){

                        $bean->$link->add($userDetails['userId']);

                        //$GLOBALS['log']->fatal(print_r($bean->field_defs[$tagField], true));
                    }

                    //$rel_name = 'accounts_contacts';

                    //if($bean->load_relationship($rel_name)){
                     //   $GLOBALS['log']->fatal(print_r("HERE!" true));
                      //  $relatedBeans = $bean->$relname->getBeans();
                        //$GLOBALS['log']->fatal(print_r($relatedBeans, true));

                    //}


                    /*
	                $bean->assigned_user_name = $userDetails['userFullName'];
	                $bean->assigned_user_id = $userDetails['userId'];
	                $bean->assigned_user_link->full_name = $userDetails['userFullName'];
	                $bean->assigned_user_link->id = $userDetails['userId'];
                    
                    
                    $rel_name = 'accounts_assigned_user';


                    $bean->load_relationship($rel_name);
                    $bean->$rel_name->add($userDetails['userId']);
                      */
        

                    $latField = $eztConfigurationSettings['latitudeField'];
                    $lonField = $eztConfigurationSettings['longitudeField'];
                    $geoQualityField = $eztConfigurationSettings['geocodeQualityField'];

                    if(!empty($latField) || !empty($lonField)){
                        
                        $bean->$latField = $latAndLon['lat'];
                        $bean->$lonField = $latAndLon['lon'];

                        if(!empty($geoQualityField)){
                            
                            $bean->$geoQualityField = $latAndLon['geocodeQuality'];
                        }
                        

                    } else {

                        $GLOBALS['log']->fatal(print_r('EZT Location Lookup notice. No latitude or longitude fields or geocode quality fields present on record: ' . $recordName, true)); 
                    } 
	                
	                $bean->save(); 
	            } 
	        
	        
	        } catch(Exception $ex) {

	        	$GLOBALS['log']->fatal(print_r($ex->getMessage(), true));  	
	        	
	        }
        //return(true);
            
    }
	
	/**
     * Gets the setting's values for EZT customer. 
     *
     * @return array
     */
    protected function getEztSettings($bean, $flowData) { 


            $advancedWorkflowModule = $flowData['cas_sugar_module'];

            $sugarQuery = new SugarQuery();

            $sugarQuery->from(BeanFactory::newBean('EztV1_EasyTerritory_Settings'));
 
            $sugarQuery->select(array('id', 'targetmodule_c'));
            $sugarQuery->where()->equals('targetmodule_c', $advancedWorkflowModule);

           
            $resultSet = $sugarQuery->execute();
            //$GLOBALS['log']->fatal(print_r($resultSet, true)); 
            $countResults = count($resultSet);

           if($countResults == 0){

           		throw new Exception("Error in Ezt_Settings module.  There are no records in Ezt_settings module.");
                
           } elseif ($countResults == 1){ 

                //$userModule = BeanFactory::getBean('Users', $result[0]['id']); 1a4e41e4-73eb-11e8-85cb-0800270fcb6c
                $eztSettingsModule = BeanFactory::getBean('EztV1_EasyTerritory_Settings', $resultSet[0]['id']); 


                $easyterritoryURL = $eztSettingsModule->easyterritoryurl;
                $mapdotneturl = $eztSettingsModule->mapdotneturl;
                $easyterritoryprojectid = $eztSettingsModule->easyterritoryprojectid;
                $easyterritoryusername = $eztSettingsModule->easyterritoryusername;
                $easyterritorypassword = $eztSettingsModule->easyterritorypassword;
                $locationlookupstreetfield = $eztSettingsModule->locationlookupstreetfield;
                $locationlookupcityfield = $eztSettingsModule->locationlookupcityfield;
                $locationlookupstatefield = $eztSettingsModule->locationlookupstatefield;
                $locationlookupzipfield =  $eztSettingsModule->locationlookupzipfield;
                $locationlookupcountryfield = $eztSettingsModule->countrycodefield_c;
                $bingkey = $eztSettingsModule->bingkey;
                $latitudeField = $eztSettingsModule->latitudefield_c;
                $longitudeField = $eztSettingsModule->longitudefield_c;
                $geocodeQualityField = $eztSettingsModule->geocodequality_c;
                $targetModule = $eztSettingsModule->targetmodule_c;
                $territorySqlColumnName = $eztSettingsModule->territorycolumnname_c;
                $whereClause = $eztSettingsModule->whereclause_c;
                $territoryId = $eztSettingsModule->territoryidcolumnname_c;

                if(empty($easyterritoryURL) || empty($mapdotneturl) || empty($easyterritoryprojectid) || empty($easyterritoryusername) || empty($easyterritorypassword) || empty($locationlookupstreetfield) || empty($locationlookupcityfield) || empty($locationlookupstatefield) || empty($locationlookupzipfield) || empty($bingkey)) {

                		throw new Exception("Error EZT Territory Lookup. One or more fields do not have values in Ezt_Settings module.");

                } else {
                

		                $eztSettingsData = array("easyterritoryUrl" => $easyterritoryURL, 
		                                        "mapDotNet" => $mapdotneturl,
		                                        "easyterritoryProjectId" => $easyterritoryprojectid,
		                                        "easyterritoryUserName" => $easyterritoryusername,
		                                        "easyterritoryPassword" => $easyterritorypassword,
		                                        "locationLookupStreet" =>  $locationlookupstreetfield,
		                                        "locationLookupCity" => $locationlookupcityfield,
		                                        "locationLookupState" => $locationlookupstatefield,
		                                        "locationLookupZipField" => $locationlookupzipfield,
                                                "locationLookupCountry" => $locationlookupcountryfield,
		                                        "bingKey" => $bingkey,
                                                "latitudeField" => $latitudeField,
                                                "longitudeField" => $longitudeField,
                                                "geocodeQualityField" => $geocodeQualityField,
                                                "targetModule" => $targetModule,
                                                "territorySqlColumnName" => $territorySqlColumnName,
                                                "whereClause" => $whereClause,
                                                "territoryId" => $territoryId,
		                                        ); 
		                         
		                return $eztSettingsData;
		        }

            } else {

            	throw new Exception("Error in Ezt_Settings module. More than of record in Ezt_settings module that is registered with module " . $advancedWorkflowModule);
                
            }

    }

	/**
     * Gets the records address fields. 
     *
     * @param SugarBean $bean 
     * @param array $eztDataSettings Setting's values from Ezt_settings module
     * @return array Returns moudle's address fields
     */
    protected function getRecordAddress($bean, $eztDataSettings){

            
            $addressStreet = $eztDataSettings['locationLookupStreet'];
            $addressCity = $eztDataSettings['locationLookupCity'];
            $addressState = $eztDataSettings['locationLookupState'];
            $adddressZip = $eztDataSettings['locationLookupZip'];
            $addressCountry = $eztDataSettings['locationLookupCountry'];


               
	        $street = $bean->$addressStreet;
	        $city = $bean->$addressCity;
	        $state = $bean->$addressState;
	        $zip = $bean->$addressZip;
            $country = $bean->$addressCountry;
            


	            
	        if(empty($street) && empty($city) && empty($state) && empty($zip) && empty($targetSugarModule) && empty($sqlTerritoryColumn)){

            	throw new Exception("Error EZT Territory Lookup. Fields not populated for record " . $bean->name);

            } else{
	            
	            $addressData = array(
	                                "street" => $street,
	                                "city" => $city,
	                                "state" => $state,
	                                "zip" => $zip,
                                    "country" => $country,

	            );
	            
	            
	            return $addressData;
	        }

    }

    /**
     * Gets Ezt Token for subsequent calls to EasyTerritory geocoding service. 
     *
     * @param array $eztDataSettings Setting's values from Ezt_settings module
     * @return Returns Ezt Token object 
     */
    protected function getEztToken($eztDataSettings, $recordName) {

            $data = array("username" => $eztDataSettings['easyterritoryUserName'], "password" => $eztDataSettings['easyterritoryPassword']);                                                                    
            $data_string = json_encode($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $eztDataSettings['easyterritoryUrl'] . '/REST/Login/Login');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);        
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);  
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));                        
            
            $data = curl_exec($ch);

            if(!$data){

            	$curlError = curl_error($ch);
            }

            curl_close($ch);

            if(isset($curlError)){

            	throw new Exception("Error EZT Location Lookup. Retrieving Easyterritory OItoken failed for record " . $recordName . ": " . $curlError);
            	
            } else{ 

            	$decoded = json_decode($data);
            
            	return $decoded->token;
            }
    }
    

    /**
     * Geocodes adress using EasyTerritory geocoding service. 
     *
     * @param object $eztToken 
     * @param array $eztDataSettings
     * @param array $recordAddress
     * @return array $latAndLon Returns latitude and longitude coordinates. 
     */      
    protected function geocodeRecord($eztToken, $eztDataSettings, $recordAddress, $recordName){


            //$data = array("keyCustomer" => "9c95ba67-2f48-48b9-845c-1d705229b77c", "keyBing" => "","addressList" => [""]);   
            $data = array("keyCustomer" => "9c95ba67-2f48-48b9-845c-1d705229b77c", "keyBing" => $eztDataSettings['bingKey'], "addressList" => [$recordAddress['street'] . ',' . $recordAddress['city'] . ',' . $recordAddress['state'] . ',' . $recordAddress['zip'] . ',' . $recordAddress['country']]); 
            
            $data_string = json_encode($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $eztDataSettings['easyterritoryUrl'] . '/REST/Geocode/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_FAILONERROR, true); 
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string); 
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));     
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Cookie: oitoken= ' . $eztToken));
 

            $data = curl_exec($ch);

            if(!$data){
            	$curlError = curl_error($ch);
            }

            curl_close($ch);

            if(isset($curlError)){


            	throw new Exception("Error EZT Location Lookup. Request error when Geocoding record " . $recordName . ": " . $curlError);
            	
            }
            else{

	            $decoded = json_decode($data, true);
	            
	            $latAndLon = array("lat" => $decoded[results][0][location][lat] , "lon" => $decoded[results][0][location][lon], "geocodeQuality" => $decoded[results][0][qualityString]);

	            if(empty($latAndLon['lat']) && empty($latAndLon['lon'])){

	            	throw new Exception("Error EZT Location Lookup. Failed to retrieve Lat and Lon for record " . $recordName . " from EZT URL: " . $eztDataSettings['easyterritoryUrl'] . "/REST/Geocode/ ");
	            } else{
	            
	            	return $latAndLon; 
	            }
	        }
    } 

	/**
     * Gets Territory that geocoded point falls in. 
     *
     * @param object $eztToken 
     * @param array $LatLon 
     * @param array $eztDataSettings
     * @return Returns Ezt Token object
     */
    protected function mdnRecordLookup($eztToken, $latLon, $eztDataSettings, $recordName) {

            $ch = curl_init();

            if(empty($eztDataSettings['whereClause'])){

                $restMDNUrl = $eztDataSettings['mapDotNet'] . '/rest/9.0/Map/EasyTerritory/Features/' . $eztDataSettings['easyterritoryProjectId'] . '/WKT/POINT(' . $latLon['lon'] . '%20' . $latLon['lat'] .')?Format=JSON&ReturnShapes=0&ReturnTypes=0&Fields='. $eztDataSettings['territorySqlColumnName'] . ',' . $eztDataSettings['territoryId'] . '&EPSG=4326';

                //$GLOBALS['log']->fatal(print_r($restMDNUrl, true));

            } else {


                $urlEncodeWhere = urlencode($eztDataSettings['whereClause']);

                $restMDNUrl = $eztDataSettings['mapDotNet'] . '/rest/9.0/Map/EasyTerritory/Features/' . $eztDataSettings['easyterritoryProjectId'] . '/WKT/POINT(' . $latLon['lon'] . '%20' . $latLon['lat'] .')?Format=JSON&ReturnShapes=0&ReturnTypes=0&Fields='. $eztDataSettings['territorySqlColumnName'] . ',' . $eztDataSettings['territoryId'] . '&EPSG=4326&where=' . $urlEncodeWhere;                        

            }

            curl_setopt($ch, CURLOPT_URL, $restMDNUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FAILONERROR, true); 
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Cookie: oitoken=' . $eztToken));


            $data = curl_exec($ch);

            if(!$data){
            	$curlError = curl_error($ch);
            }

            curl_close($ch);

            if(isset($curlError)){


            	throw new Exception("Error EZT Location Lookup. Request error when getting territory name for record " . $recordName . ": " . $curlError);
            	
            }
            else {
        
	           
	            $decoded = json_decode($data, true);

	            if(empty($decoded[Values][0][0])){

	            	throw new Exception('Error EZT Location Lookup. Failed to retrieve territory name for record '. $recordName .' from ' . $restMDNUrl);
	            }
	            else {

	            	
                    $eztTerritoryArray = array('name' => $decoded[Values][0][0], 
                                          'id' =>   $decoded[Values][0][1],
                            ); 
                    //$GLOBALS['log']->fatal(print_r($eztTerritoryArray, true));
                    return $eztTerritoryArray; 

	        	}
        	}
    }

 
    /**
     * Gets the user that is assigned to given territory mapped in EztV1_EZT_Territory_Mapping. 
     *
     * @param string $territory
     * @return string $user
     */
    protected function getTerritoryMapping($territory, $recordName){

            //require_once('include/SugarQuery/SugarQuery.php');

            $eztTerritoryId = $territory['id'];

            $sugarQuery = new SugarQuery();
            $sugarQuery->from(BeanFactory::newBean('EztV1_EZT_Territory_Mapping'));
 
            $sugarQuery->select(array('id','name','territoryid_c'));
           
            $sugarQuery->Where()->equals('territoryid_c', $eztTerritoryId);


            $result = $sugarQuery->execute();
            
            $count = count($result);

            if($count == 0){

                throw new Exception("Error EZT Location Lookup. Error in getTerritoryMapping() function.  Cannot map territory name to user for territory: " . $territory['name'] . " with ID " . $territory['id']);
            } elseif ($count == 1){

                $EztMappingBean = BeanFactory::getBean('EztV1_EZT_Territory_Mapping', $result[0]['id']);

                $user = $EztMappingBean->territoryowner;

                return $user;


            } else {

            	throw new Exception("Error EZT Location Lookup. Error in getTerritoryMapping() function.  There are multiple mapping records for this territory: " . $territory['name'] . " with ID: " . $territory['id']);

            }

    } 

    /**
     * Gets the users name and id. 
     *
     * @param string $sugarUser
     * @return array $user Returns users id and full name.
     */
    protected function getUserDetails($sugarUser, $recordName, $bean){

            $sugarQuery = new SugarQuery();

            $sugarQuery->from(BeanFactory::newBean('Users'));
 
            $sugarQuery->select(array('id','first_name','last_name','user_name'));
           
            $sugarQuery->where()->equals('user_name', $sugarUser);
           

            $resultSet = $sugarQuery->execute();
            
            $countResults = count($resultSet);

           if($countResults == 0){

                throw new Exception("Error EZT Location Lookup. Error in getUserDetails() function when processing " . $recordName . ".  Cannot find user in users module: " . $sugarUser);
           } elseif ($countResults == 1){ 

                //$userModule = BeanFactory::getBean('Users', $result[0]['id']); 1a4e41e4-73eb-11e8-85cb-0800270fcb6c
                $userModule = BeanFactory::getBean('Users', $resultSet[0]['id']); 
                $userId = $userModule->id;
                $userFullName = $userModule->name;
               
                $sugarUserDetails = array('userFullName' => $userFullName , 'userId' => $userId);

                
                //$rel_name = 'accounts';


                //$userModule->load_relationship('accounts_assigned_user'));
                //{
                  //      $relatedBeans = $bean->accounts->getBeans();
                  //  $test = $userModule->load_relationship('accounts_assigned_user');
                        //$GLOBALS['log']->fatal(print_r($relatedBeans, true));

                //}


                //$userModule->$rel_name->add($bean->id);
                //$userModule->save();
                
                return $sugarUserDetails;
                

            }
    } 
    
}

