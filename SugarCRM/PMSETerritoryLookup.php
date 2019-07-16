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

	            $eztConfigurationSettings = $this->getEztSettings($flowData['cas_sugar_module']);
	            
	            $recordAddress = $this->getRecordAddress($bean, $eztConfigurationSettings);

	            $token = $this->getEztToken($eztConfigurationSettings, $recordName);

	            $latAndLon = $this->geocodeRecord($token, $eztConfigurationSettings, $recordAddress, $recordName);

	            $eztTerritory = $this->mdnRecordLookup($token, $latAndLon, $eztConfigurationSettings, $recordName);

	            $user = $this->getTerritoryMapping($eztTerritory, $recordName);

	            $userDetails = $this->getUserDetails($user, $recordName);
                //$GLOBALS['log']->fatal(print_r($flowData, true));

	            if($userDetails) {
                    
                    $assigned = $eztConfigurationSettings['modRel'];
                    
                    if($bean->load_relationship($assigned)){
                         
                         $bean->$assigned->add($userDetails['userId']);   
                        
                    }else{

                        $GLOBALS['log']->fatal(print_r("Failed to load relationship link name '" . $assigned . "' for record: " . $recordName . ". Check to see if relationship link name is valid between modules: '". $flowData['cas_sugar_module'] ."' and 'Users'", true));
                    }

	            } else {
	            	$GLOBALS['log']->fatal(print_r("No user found in Ezt Territory Lookup.", true));
	            }
	        }
	        
	        catch(Exception $ex) {

	        	$GLOBALS['log']->fatal(print_r($ex->getMessage(), true));  	
	        	
	        }
            
    }

	
	/**
     * Gets the setting's values for EZT customer. 
     *
     * @return array
     */
    protected function getEztSettings($module) {

            $sugarQuery = new SugarQuery();

            $sugarQuery->from(BeanFactory::newBean('EztV1_EztSettings'));
 
            $sugarQuery->select(array('id','targetmodulename'));
           
            $sugarQuery->where()->equals('targetmodulename', $module);
           
            $resultSet = $sugarQuery->execute();
            
            $countResults = count($resultSet);

           if($countResults == 0){

           		throw new Exception("Error in querying Ezt_Settings module.  Please check to make sure settings are correct.");
                
           } elseif ($countResults == 1){ 

                //$userModule = BeanFactory::getBean('Users', $result[0]['id']); 1a4e41e4-73eb-11e8-85cb-0800270fcb6c
                $eztSettingsModule = BeanFactory::getBean('EztV1_EztSettings', $resultSet[0]['id']); 


                $easyterritoryURL = $eztSettingsModule->easyterritoryurl;
                $easyterritoryprojectid = $eztSettingsModule->easyterritoryprojectid;
                $easyterritoryusername = $eztSettingsModule->username;
                $easyterritorypassword = $eztSettingsModule->easyterritorypassword;
                $locationlookupstreetfield = $eztSettingsModule->locationlookupstreetfield;
                $locationlookupcityfield = $eztSettingsModule->locationlookupcityfield;
                $locationlookupstatefield = $eztSettingsModule->locationlookupstatefield;
                $locationlookupzipfield =  $eztSettingsModule->locationlookupzipfield;
                $bingkey = $eztSettingsModule->bingkey;
                $moduleRel = $eztSettingsModule->modulerelationshipname;

                if(empty($easyterritoryURL)  || empty($easyterritoryprojectid) || empty($easyterritoryusername) || empty($easyterritorypassword) || empty($bingkey))  {

                		throw new Exception("Error EZT Territory Lookup. One or more fields do not have values in Ezt_Settings module -> easyterritory url, easyterritory project Id, username, password, bingkey");

                } else {
                

		                $eztSettingsData = array("easyterritoryUrl" => $easyterritoryURL,
		                                        "easyterritoryProjectId" => $easyterritoryprojectid,
		                                        "easyterritoryUserName" => $easyterritoryusername,
		                                        "easyterritoryPassword" => $easyterritorypassword,
		                                        "locationLookupStreet" =>  $locationlookupstreetfield,
		                                        "locationLookupCity" => $locationlookupcityfield,
		                                        "locationLookupState" => $locationlookupstatefield,
		                                        "locationLookupZipField" => $locationlookupzipfield,
		                                        "bingKey" => $bingkey,
                                                "modRel" => $moduleRel,
		                                        ); 
		                         
		                return $eztSettingsData;
		        }

            } else {

            	throw new Exception("Error in Ezt_Settings module. More than of record in Ezt_settings module.");
   
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
            $adddressZip = $eztDataSettings['locationLookupZipField'];
               
	        $street = $bean->$addressStreet;
	        $city = $bean->$addressCity;
	        $state = $bean->$addressState;
	        $zip = $bean->$addressZip;
	            
	        if(empty($street) && empty($city) && empty($state) && empty($zip)){

            	throw new Exception("Error EZT Territory Lookup. Address fields not populated for record " . $bean->name);

            } else{
	            
	            $addressData = array(
	                                "street" => $street,
	                                "city" => $city,
	                                "state" => $state,
	                                "zip" => $zip,
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

            $c = base64_decode($eztDataSettings['easyterritoryPassword']);
            $key = "4e9eb050-7711-46e0-8e9f-1104c2f78d45";
            $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, $sha2len=32);
            $ciphertext_raw = substr($c, $ivlen+$sha2len);
            $pass = openssl_decrypt($ciphertext_raw, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
            
            if (hash_equals($hmac, $calcmac))//PHP 5.6+ timing attack safe comparison
            {
                $password = $pass;
            }
            //$GLOBALS['log']->fatal(print_r($password, true));
            //$GLOBALS['log']->fatal(print_r($eztDataSettings['easyterritoryUserName'], true));
            $data = array("username" => $eztDataSettings['easyterritoryUserName'], "password" => $password);                                                                    
            $data_string = json_encode($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $eztDataSettings['easyterritoryUrl'] . '/REST/Login/Login');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_FAILONERROR, true);        
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);  
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));                        
            
            $data = curl_exec($ch);

            if(!$data){

            	$curlError = curl_error($ch);
            }

            curl_close($ch);

            if(isset($curlError)){

            	throw new Exception("Error EZT Location Lookup. Retrieving Easyterritory OItoken failed with url: " . $eztDataSettings['easyterritoryUrl'] . "/REST/Login/Login" . ", for record: " . $recordName . ", Error Details: " . $curlError);
            	
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
            $data = array("keyCustomer" => "9c95ba67-2f48-48b9-845c-1d705229b77c", "keyBing" => $eztDataSettings['bingKey'], "addressList" => [$recordAddress['street'] . ',' . $recordAddress['city'] . ',' . $recordAddress['state'] . ',' . $recordAddress['zip']]); 
            
            $data_string = json_encode($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $eztDataSettings['easyterritoryUrl'] . '/REST/Geocode/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);  
            curl_setopt($ch, CURLOPT_FAILONERROR, true); 
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string); 
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
	            
	            $latAndLon = array("lat" => $decoded[results][0][location][lat] , "lon" => $decoded[results][0][location][lon]);

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
            curl_setopt($ch, CURLOPT_URL, $eztDataSettings['easyterritoryUrl'] . '/REST/ProjectMarkupPolygon/' . $eztDataSettings['easyterritoryProjectId'] . '/location?lat=' . $latLon['lat'] . '&lon=' . $latLon['lon'] .'&omitWkt=true&omitMetadata=true');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FAILONERROR, true); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Cookie: oitoken=' . $eztToken));


            $data = curl_exec($ch);

            if(!$data){
            	$curlError = curl_error($ch);
            }

            curl_close($ch);

            if(isset($curlError)){


            	throw new Exception("Error EZT Location Lookup. With url: ". $eztDataSettings['easyterritoryUrl'] . "/REST/ProjectMarkupPolygon/" . $eztDataSettings['easyterritoryProjectId'] . "/location?lat=" . $latLon['lat'] . "&lon=" . $latLon['lon'] .'&omitWkt=true&omitMetadata=true' ." Request error when getting territory name for record " . $recordName . ": " . $curlError);
            	
            }
            else {
        
	           
	            $decoded = json_decode($data, true);

	            if(empty($decoded[0][tag])){

	            	throw new Exception('Error EZT Location Lookup. Failed to retrieve territory for record '. $recordName .' from: ' . $eztDataSettings['easyterritoryUrl'] . '/REST/ProjectMarkupPolygon/' . $eztDataSettings['easyterritoryProjectId'] . '/location?lat=' . $latLon['lat'] . '&lon=' . $latLon['lon'] .'&omitWkt=true&omitMetadata=true,  json returned:' . strval($decoded) );
	            }
	            else {

	            	return $decoded[0][markupId];
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

            $sugarQuery = new SugarQuery();
            $sugarQuery->from(BeanFactory::newBean('EztV1_EztTerritories'));
 
            $sugarQuery->select(array('id','eztmarkupid','assigned_user_name'));
           
            $sugarQuery->Where()->equals('eztmarkupid', $territory);

            $result = $sugarQuery->execute();

            $count = count($result);

            if($count == 0){

                throw new Exception("Error EZT Location Lookup. Error in getTerritoryMapping() function.  Cannot map territory name to user for territory: " . $territory);
            } elseif ($count == 1){

                $EztMappingBean = BeanFactory::getBean('EztV1_EztTerritories', $result[0]['id']);             
                
                $user = $EztMappingBean->territoryowner;

                $assignedUser = $EztMappingBean->assigned_user_id;
             
                //return $user;
                return $assignedUser;

            } else {

            	throw new Exception("Error EZT Location Lookup. Error in getTerritoryMapping() function.  There are multiple mapping records for this territory: " . $territory);

            }

    } 

    /**
     * Gets the users name and id. 
     *
     * @param string $sugarUser
     * @return array $user Returns users id and full name.
     */
    protected function getUserDetails($sugarUser, $recordName){

            

            $sugarQuery = new SugarQuery();

            $sugarQuery->from(BeanFactory::newBean('Users'));
 
            $sugarQuery->select(array('id','first_name','last_name','user_name'));
           
            $sugarQuery->where()->equals('id', $sugarUser);
           

            $resultSet = $sugarQuery->execute();
            
            $countResults = count($resultSet);

           if($countResults == 0){

                throw new Exception("Error EZT Location Lookup. Error in getUserDetails() function when processing " . $recordName . ".  Cannot find user in users module: " . $sugarUser);
           } elseif ($countResults == 1){ 
 
                //$userModule = BeanFactory::getBean('Users', $result[0]['id']); 1a4e41e4-73eb-11e8-85cb-0800270fcb6c
                $userModule = BeanFactory::getBean('Users', $resultSet[0]['id']);
                
                $userId = $userModule->id;
                $userFullName = $userModule->name;
               
                $sugarUserDetails = array('userFullName' => $userName , 'userId' => $userId);

                return $sugarUserDetails;


            }
    }

    /**
     * Decrypts the users password. 
     *
     * @param string $ciphertext
     * @return plain text string of password.
     */
    protected function decryptPass($ciphertext){
        
        $c = base64_decode($ciphertext);
        $key = "4e9eb050-7711-46e0-8e9f-1104c2f78d45";
        $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len=32);
        $ciphertext_raw = substr($c, $ivlen+$sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options=OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary=true);
        //if (hash_equals($hmac, $calcmac))//PHP 5.6+ timing attack safe comparison
        //{
            return $original_plaintext;
        //}else{

            
        //}

    }   
}

