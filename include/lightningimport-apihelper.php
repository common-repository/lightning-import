<?php

set_time_limit(0);

class lightningimport_apihelper
{

    //Returns the url for the api

    public static function lightningimport_apiurl()
    {

        return 'https://lightningimport.com/';

        //return 'http://localhost:58970/';
    }

    public static function lightningimport_GetCurrentAPIToken()
    {
        $token = get_option('lightningimport_Token', "");
        //If the token exists check to make sure it works
        if (isset($token) && strlen($token) > 0) {
            $data = self::lightningimport_executeHttp(self::lightningimport_apiurl() . 'api/productdata/TokenCheck/', 'GET', $token);
            //IF it worked return the valid token
            if (isset($data) && isset($data['body']) && $data['body'] == true) {
                return $token;
            }
        }
        //By default try to get a new one
        return self::lightningimport_GetNewToken();
    }

    public static function lightningimport_GetNewToken($username = null, $password = null)
    {
        $newtoken = "";
        if (is_null($username) || is_null($password)) {
            $options = self::lightningimport_lightningimportOptions();
            $username = $options['lightningimport_Username'];
            $password = $options['lightningimport_Password'];
        }
        if (isset($username) && isset($password) && count($username) > 0 && count($password) > 0) {
            $response = self::lightningimport_executeHttp(self::lightningimport_apiurl() . 'api/account/login', 'POST', null, array('Email' => $username, 'Password' => $password, 'RememberMe' => false), array(), 'application/json');
            if (isset($response['body']) && strlen($response['body']) > 0) {
                try {
                    $loginResponse = json_decode($response['body']);
                    if (isset($loginResponse->token) && strlen($loginResponse->token) > 0) {
                        $newtoken = $loginResponse->token;
                        update_option('lightningimport_Token', $newtoken);
                    }
                } catch (Exception $ex) {
                    self::lightningimport_writeToLog('Error in lightningimport_GetNewToken: ' . print_r($ex, true));
                }
            }
        }
        return $newtoken;
    }

    public static function lightningimport_executeHttp($url, $method, $token = null, $fields = null, $headers = array(), $contenttype = null)
    {
        $httpResponse = self::lightningimport_executewp_remote($url, $method, $token, $fields, $headers, $contenttype);
        return $httpResponse;
    }

    public static function lightningimport_executewp_remote($url, $method, $token = null, $fields = null, $headers = array(), $contenttype = null)
    {
        self::lightningimport_writeToLog($method . ' Request to URL:' . $url);

        //If content type is set add it to the headers array
        if (isset($contenttype) && strlen($contenttype) > 0) {
            $headers['Content-Type'] = $contenttype;
            //Also encode the fields so they get sent as JSON object
            if (isset($fields) && strpos($contenttype, 'json')) {
                $fields = json_encode($fields);
            }
        }
        //If token is set add it to the headers array
        if (isset($token) && strlen($token) > 0) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        if ($method == 'POST') {

            $response = wp_remote_post($url, array(
                'method' => $method,
                'timeout' => 90,
                'redirection' => 5,
                'httpversion' => '1.0',
                'headers' => $headers,
                'body' => $fields,
            )
            );
        } else if ($method == 'GET') {
            $response = wp_remote_get($url, array(
                'method' => $method,
                'timeout' => 90,
                'redirection' => 5,
                'httpversion' => '1.0',
                'headers' => $headers,
            )
            );
        }

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            echo "Something went wrong: $error_message";

        }
        //self::lightningimport_writeToLog('Reponse WP_REMOTE_'.$method.':'.print_r($response,true));
        self::lightningimport_writeToLog('Completed Request to URL:' . $url);
        return $response;
    }

    public static function lightningimport_debug()
    {

        //Get the options

        $lightningimport_debugging = self::lightningimport_lightningimportOptions();

        //If its is set use it otherwise set to false

        if (isset($lightningimport_debugging['lightningimport_debug'])) {

            return $lightningimport_debugging['lightningimport_debug'];

        }

        return false;

    }

    public static function lightningimport_writeToLog($message)
    {

        if (self::lightningimport_debug()) {
            $log = self::lightningimport_logFilePath();
            $dateNow = new DateTime();
            $max_size = 204800;
            $x_amount_of_lines = 1000;

            if (filesize($log) >= $max_size) {
                $file = file($log);
                $line = $file[0];
                $file = array_slice($file, $x_amount_of_lines * -1);
                $logFile = fopen($log, "w");
                array_push($file, $dateNow->format('Y-m-d H:i:s') . ' ' . $message . PHP_EOL);
                file_put_contents($log, $file);
            } else {
                $logFile = fopen($log, "a");

                fwrite($logFile, $dateNow->format('Y-m-d H:i:s') . ' ' . $message . PHP_EOL);

                fclose($logFile);
            }

        }

    }
    public static function lightningimport_deleteLogFile()
    {
        unlink(self::lightningimport_logFilePath());
    }

    //Returns the options for the plugin

    public static function lightningimport_lightningimportOptions()
    {

        return $options = get_option('lightningimport_lightningimportOptions');

    }

    //Returns the tmp lock file for this process

    public static function lightningimport_tmpFileName()
    {

        return "productImportLock.tmp";

    }

    //Returns the log file for this process

    public static function lightningimport_logFilePath()
    {

        $directory = dirname(__FILE__);
        $lightningimport_logFilePath = $directory . '/lightningimportLogFile.txt';

        if (!file_exists($lightningimport_logFilePath)) {

            $logFile = fopen($lightningimport_logFilePath, "w");

            fwrite($logFile, 'Created log file');

            fclose($logFile);

        }

        return $lightningimport_logFilePath;

    }

    //Performs check to confirm the user and password combination is authorized with our data service.

    public static function lightningimport_APIAccessCheck()
    {
        //return true;
        $success = false;

        try {

            $data = self::lightningimport_executeHttp(self::lightningimport_apiurl() . 'api/productdata/AccessCheck/', 'GET');
            // self::lightningimport_writeToLog('Access Check result '.print_r($data,true));

            // if (curl_errno($ch)) {

            // self::lightningimport_writeToLog('Curl error: ' . curl_error($ch));

            // }
            //self::lightningimport_writeToLog('Access Check result '.print_r($data,true));

            $success = $data['body'];

            // ...process $content now

        } catch (Exception $e) {

            self::lightningimport_writeToLog('lightningimport_APIAccessCheck Error: HTTP failed with error ' . $e->getCode() . ' | ' . $e->getMessage());

        }

        return $success;
    }

    //Sends credentials to API. Creates account if it does not exist.
    public static function lightningimport_SendCredentialstoAPI($username = null, $password = null)
    {
        $return['success'] = false;

        $return['message'] = 'Oops. Looks like something went wrong.';
        try {

//                $url = self::lightningimport_apiurl().'api/Account/TokenCheck/';
            //$data = self::lightningimport_executeHttp($url,'GET',self::lightningimport_GetCurrentAPIToken());
            $data = self::lightningimport_GetNewToken($username, $password);
            if (false === $data) {

                //throw new Exception(curl_error($ch), curl_errno($ch));

                throw new Exception('Curl error occured');

            }

            self::lightningimport_writeToLog('Register or confirm returned: ' . print_r($data, true));
            //$result = json_decode($data['body']);
            // if(isset($result->success)&&isset($result->message)){
            //     $return['success'] = $result->success;
            //     $return['message'] = $result->message;

            //     //If we successfully setup the account. Send the plugins url to the api
            //     if($return['success']){
            //         self::lightningimport_SendPluginURLtoAPI(get_site_url());
            //     }

            // }
            if (isset($data) && strlen($data) > 0) {
                self::lightningimport_SendPluginURLtoAPI(get_site_url());
                $return['success'] = true;
                $return['message'] = 'Registered successfully';
            } else {

                $return['success'] = false;

                $return['message'] = 'An error occured while setting up your credentials. Please try again.';

            }

            // ...process $content now

        } catch (Exception $e) {

            self::lightningimport_writeToLog('lightningimport_SendCredentialstoAPI Error: ' . 'Curl failed with error #%d: %s',

                $e->getCode(), $e->getMessage());

            $return['success'] = false;

            $return['message'] = $e->getMessage();

        }

        return $return;

    }

    //Sends credentials to API. Creates account if it does not exist.
    public static function lightningimport_SendPluginURLtoAPI($siteURL)
    {
        $return['success'] = false;

        $return['message'] = 'Oops. Looks like something went wrong.';

        try {

            $fields = array(
                'siteURL' => $siteURL,
            );

            $url = self::lightningimport_apiurl() . 'api/Account/SetUserSiteURL/';

            $data = self::lightningimport_executeHttp($url, 'POST', self::lightningimport_GetCurrentAPIToken(), $fields);

            self::lightningimport_writeToLog('Set Site URL returned: ' . print_r($data, true));
            $result = json_decode($data['body']);
            if (isset($result->success) && isset($result->message)) {

                $return['success'] = $result->success;

                $return['message'] = $result->message;

            } else {

                $return['success'] = false;

                $return['message'] = 'An error occured while setting the site url with the API. Please try again.';

            }

            // ...process $content now

        } catch (Exception $e) {

            self::lightningimport_writeToLog('lightningimport_SendPluginURLtoAPI Error: ' . 'Curl failed with error #%d: %s',

                $e->getCode(), $e->getMessage());

            $return['success'] = false;

            $return['message'] = $e->getMessage();

        }

        return $return;

    }

    //Product Processing

    //Import product data from api

    public static function lightningimport_ImportProductData()
    {

        $options = self::lightningimport_lightningimportOptions();

        if (isset($options['lightningimport_ScanToggle'])) {

            if ($options['lightningimport_ScanToggle'] == "1") {

                self::lightningimport_writeToLog('Started Product Import');

                $lightningimport_tmpFileName = self::lightningimport_tmpFileName();

                $directory = dirname(__FILE__);

                $tmpFilePath = $directory . '/' . $lightningimport_tmpFileName;

                if (!self::lightningimport_CheckForImportProcess()) {

                    $dataSubscriptionList = self::lightningimport_GetProductDataSubscriptionIDList();

                    if (count($dataSubscriptionList) > 0) {

                        //self::lightningimport_GetProductImportList($dataSubscriptionList[0]);

                        //Loop through the list and process every data subscription received

                        // $i=0;

                        foreach ($dataSubscriptionList as $dataSubscription) {

                            //self::lightningimport_GetProductImportList($dataSubscription);

                            //if($i==0){

                            $lightningimport_tmpFileName = self::lightningimport_tmpFileName();

                            $directory = dirname(__FILE__);

                            $tmpFilePath = $directory . '/' . $lightningimport_tmpFileName;

                            if (file_exists($tmpFilePath)) {

                                unlink($tmpFilePath);

                            }

                            $fh = fopen($tmpFilePath, 'w') or die("Can't open file $name for writing temporary stuff.");

                            fwrite($fh, 'Products In Process');

                            fclose($fh);

                            self::lightningimport_GetProductImportFile($dataSubscription);

                            self::lightningimport_DeleteDirectory($upload_dir . '/' . $dataSubscription);

                            self::lightningimport_DeleteDirectory($upload_dir . '/lightningimports/' . $dataSubscription);

                            //}

                            //$i=1;

                        }

                        // self::lightningimport_GetProductImportFile('ECCC7AE5-F104-47D0-9931-291AA9FD92FF');

                    }

                    $quantityDSList = lightningimport_imagehelper::lightningimport_GetImageDataSubscriptionIDList();

                    self::lightningimport_writeToLog('Quantity ds list:' . print_r($quantityDSList, true));

                    foreach ($quantityDSList as $qtdataSubscription) {

                        self::lightningimport_GetProductQuantityFile($qtdataSubscription);

                    }

                    if (file_exists($tmpFilePath)) {

                        self::lightningimport_writeToLog('Try to delete productImportLock.tmp');

                        unlink($tmpFilePath);

                    }

                    self::lightningimport_writeToLog('Finished Product Import');

                }

            } else {

                self::lightningimport_writeToLog('Product Data Import toggle is off');

            }

        } else {

            self::lightningimport_writeToLog('Product Data Import toggle is not set');

        }

    }

    public static function lightningimport_CheckForImportProcess()
    {

        $inProcess = false;

        $lightningimport_tmpFileName = self::lightningimport_tmpFileName();

        $directory = dirname(__FILE__);

        $tmpFilePath = $directory . '/' . $lightningimport_tmpFileName;

        if (file_exists($tmpFilePath)) {

            //If we get a hard failure in this script it could leave a phantom lock file

            //Code to remove lock file if it is present after 30M

            $dateNow = new DateTime();

            $dateNow->sub(new DateInterval('PT10M'));

            $fileModified = new DateTime();

            $fileModified->setTimestamp(filemtime($tmpFilePath));

            self::lightningimport_writeToLog('File modified: ' . $fileModified->format('Y-m-d H:i:s') . ' Date: ' . $dateNow->format('Y-m-d H:i:s'));

            if ($fileModified < $dateNow) {

                unlink($tmpFilePath);

                self::lightningimport_writeToLog('Deleted product lock file due to time');

            } else {

                $inProcess = true;

            }

        } else {

            $fh = fopen($tmpFilePath, 'w') or die("Can't open file $name for writing temporary stuff.");

            fwrite($fh, 'Images In Process');

            fclose($fh);

        }

        self::lightningimport_writeToLog('Product Process running: ' . print_r($inProcess, true));

        return $inProcess;

    }

    //Performs check to confirm the user and password combination is authorized with our data service.

    public static function lightningimport_GetProductDataSubscriptionIDList()
    {

        //Perform user check to api here. Confirm user api login succeeds and they're subscription is valid

        $options = self::lightningimport_lightningimportOptions();

        $apiUsername = $options['lightningimport_Username'];

        $apiPassword = $options['lightningimport_Password'];

        //Get product sku list from api here
        $data = self::lightningimport_executeHttp(self::lightningimport_apiurl() . 'api/productdata/GetProductDataSubscriptionIDListByAccountName/', 'GET', self::lightningimport_GetCurrentAPIToken());

        //Parse received data from api and set list
        if (isset($data['body'])) {
            $dataSubscriptionList = json_decode($data['body']);
        }

        self::lightningimport_writeToLog('List of users product data subscriptions: ' . print_r($dataSubscriptionList, true));

        return $dataSubscriptionList;

    }

    //Gets the product object list from the api for import.

    public static function lightningimport_GetProductImportList($dataSubscriptionID)
    {

        self::lightningimport_writeToLog('Got to Get ImportList URL for subscription id: ' . $dataSubscriptionID);

        $options = self::lightningimport_lightningimportOptions();

        $apiUsername = $options['lightningimport_Username'];

        $apiPassword = $options['lightningimport_Password'];

        //Get product sku list from api here

        $data = self::lightningimport_executeHttp(self::lightningimport_apiurl() . 'api/productdata/', 'GET', self::lightningimport_GetCurrentAPIToken());

        //Uncomment this to log the product data. This will greatly increase log file size.

        //$logFile = fopen(self::lightningimport_logFilePath(),"a");

        //fwrite($logFile,'Data for DataSubscriptionID: '.$dataSubscriptionID.' with data: '.$data.PHP_EOL);

        //fclose($logFile);

        self::lightningimport_writeToLog('Performed request for product data for subscription id: ' . $dataSubscriptionID);

        //Parse received data from api and set list
        if (isset($data['body'])) {
            $productAndAttributeMap = json_decode($data['body']);
        }
        if (isset($productAndAttributeMap->attributeMap) && self::lightningimport_AreCustomTablesPresent()) {

            $attributeMap = $productAndAttributeMap->attributeMap;

            self::lightningimport_SetAttributeMap($attributeMap);

        }

        if (isset($productAndAttributeMap->Products)) {

            $productList = $productAndAttributeMap->Products;

            //$productList = $productAndAttributeMap;

            if (count($productList) > 0) {

                //Run the function that gets the product details for each product in the list

                self::InsertUpdateProducts($dataSubscriptionID, $productList);

                if (isset($productList[0]->RequestBatchId)) {

                    self::lightningimport_MarkProductsReceived($productList[0]->RequestBatchId);

                }

            }

        }

        //Category processing

        if (isset($productAndAttributeMap->categories)) {

            self::lightningimport_writeToLog('Started Categories Import');

            $categories = $productAndAttributeMap->categories;

            if (isset($categories)) {

                self::lightningimport_ProcessCategories($categories);

            } else {

                self::lightningimport_writeToLog('Categories was null');

            }

            self::lightningimport_writeToLog('Finished Categories Import');

        }

    }

    public static function lightningimport_ProcessCategories($categories)
    {

        self::lightningimport_writeToLog('There are the categories' . print_r($categories, true));

        global $wpdb;

        $term_id = false;

        $parent_term_id = false;

        $productArray = array();

        $categoryArray = array();
        $customTablesExists = self::lightningimport_AreCustomTablesPresent();
        //self::lightningimport_writeToLog('There are '.count($categories).' categories');

        if (count($categories) > 0) {

            self::lightningimport_writeToLog('Started processing foreach loops for categories object');

            foreach ($categories as $categoryKey) {

                self::lightningimport_writeToLog('Started process parent category: ' . print_r($categoryKey, true));

                if (isset($categoryKey->key)) {

                    $categoryParentName = $categoryKey->key;

                    $parentCategory = get_term_by('name', $categoryParentName, 'product_cat');

                    //If the category does not have a term_id create it

                    if ($parentCategory == false) {

                        //Create the new category

                        $newParentCategory = wp_insert_term(

                            $categoryParentName,

                            'product_cat'

                        );

                        if (!is_wp_error($newParentCategory)) {

                            $parent_term_id = $newParentCategory['term_id'];

                            add_term_meta($parent_term_id, 'order', '0', true);

                        } else {

                            if (isset($newParentCategory->error_data)) {

                                $parent_term_id = $newParentCategory->get_error_data('term_exists');

                                if (!is_numeric($parent_term_id)) {

                                    $parent_term_id = false;

                                }

                            }

                            self::lightningimport_writeToLog('Category error: ' . print_r($newParentCategory, true));

                        }

                    } else {

                        $parent_term_id = $parentCategory->term_id;

                    }

                    foreach ($categoryKey->values as $categoryValue) {

                        if (isset($categoryValue)) {

                            $categoryName = $categoryValue->value;

                            self::lightningimport_writeToLog('Started processing child or top level category: ' . $categoryName);

                            if (isset($categoryName)) {

                                // Get term by name $categoryName in product_cat taxonomy.

                                $category = get_term_by('name', $categoryName, 'product_cat');

                                //If the category does not have a term_id create it

                                if ($category == false) {

                                    //Create the new category

                                    $newCategory = wp_insert_term(

                                        $categoryName,

                                        'product_cat',

                                        array(

                                            'parent' => $parent_term_id,

                                        )

                                    );

                                    if (!is_wp_error($newCategory)) {

                                        $term_id = $newCategory['term_id'];

                                        add_term_meta($term_id, 'order', '0', true);

                                    } else {

                                        if (isset($newCategory->error_data)) {

                                            $term_id = $newCategory->get_error_data('term_exists');

                                            if (!is_numeric($term_id)) {

                                                $term_id = false;

                                            }

                                        }

                                        self::lightningimport_writeToLog('Category error: ' . print_r($newCategory, true));

                                    }

                                } else {

                                    $term_id = $category->term_id;

                                }

                            }

                            if (isset($categoryValue->skus) && count($categoryValue->skus) > 0) {

                                self::lightningimport_writeToLog('Started processing for for category skus for : ' . $categoryValue->value);

                                //self::lightningimport_writeToLog('Sku list for cagegory: '.$categoryName.'is: '.print_r($categoryValue->skus,true));

                                foreach ($categoryValue->skus as $categorySku) {

                                    // $args = array(

                                    // 'meta_key' => '_sku',

                                    // 'meta_value' => $categorySku,

                                    // 'post_type' => 'product',

                                    // 'post_status' => 'any',

                                    // 'posts_per_page' => -1

                                    // );
                                    $getPostIDQuery = $wpdb->prepare("select post_id from lightningimport_sku where sku = %s;", $categorySku);
                                    if(!$customTablesExists){
                                        $getPostIDQuery = $wpdb->prepare("select post_id from $wpdb->postmeta where meta_key = 'li_sku' and meta_value = %s;", $categorySku);                                    
                                    }

                                    $product_id = $wpdb->get_var($getPostIDQuery);

                                    //Check if the product exists

                                    // $posts = get_posts($args);

                                    if (!is_null($product_id) && is_numeric($product_id)) {

                                        //May need to deal with duplication of sku entries here.

                                        //For now just get the first entry

                                        //$product_id = $posts[0]->ID;

                                        //self::lightningimport_writeToLog('Found post_id: '.$product_id.' for sku: '.$categorySku);

                                        $productArray[] .= $product_id;

                                        if (isset($categoryName)) {

                                            //self::lightningimport_writeToLog('Attempting to use term_id: '.$term_id);

                                            if (is_numeric($product_id) && is_numeric($term_id)) {

                                                $categoryArray[] .= "($product_id,$term_id,0)";

                                            }

                                        } else {

                                            //self::lightningimport_writeToLog('Attempting to use parent_term_id: '.$parent_term_id);

                                            if (is_numeric($product_id) && is_numeric($parent_term_id)) {

                                                $categoryArray[] .= "($product_id,$parent_term_id,0)";

                                            }

                                        }

                                        //$termRelationshipQuery.="($product_id,$term_id,0),";

                                    } else {

                                        //Do nothing

                                    }

                                }

                            }

                        }

                    }

                }

            }

            $productArray = array_unique($productArray);

            $categoryArray = array_unique($categoryArray);

            //$deleteTermRelationshipQuery = "delete from ".$wpdb->prefix."term_relationships where object_id in(".implode(', ', $productArray).");";

            $termRelationshipQuery = "insert " . $wpdb->prefix . "term_relationships(object_id,term_taxonomy_id,term_order) values " . implode(', ', $categoryArray) . ";";

            self::lightningimport_writeToLog('Trying to run query:' . $termRelationshipQuery);

            //$wpdb->query($deleteTermRelationshipQuery);

            //self::lightningimport_writeToLog('Trying to run query:'.$termRelationshipQuery);

            if (count($categoryArray) > 0) {

                $wpdb->query($termRelationshipQuery);

            }

        }

        self::lightningimport_writeToLog('Ran the category insert query');

    }

    //Confirms a DataSubscriptionID was processed

    public static function lightningimport_MarkProductsReceived($requestBatchId)
    {

        $options = self::lightningimport_lightningimportOptions();

        $apiUsername = $options['lightningimport_Username'];

        $apiPassword = $options['lightningimport_Password'];

        self::lightningimport_writeToLog('Api URL: ' . self::lightningimport_apiurl() . 'api/MarkProductsReceived/');

        self::lightningimport_writeToLog('Making request to: ' . self::lightningimport_apiurl() . 'api/productdata/MarkProductsReceived/' . trim($requestBatchId));

        //Get product sku list from api here

        $data = self::lightningimport_executeHttp('https://lightningimport.com/api/productdata/MarkProductsReceived/' . trim($requestBatchId), 'GET', self::lightningimport_GetCurrentAPIToken());

        self::lightningimport_writeToLog("lightningimport_MarkProductsReceived data after executecurl:" . print_r($data, true));

        return $data['body'];

    }

    //Verifies that a category exists and if not creates one. Returns category term_id

    public static function lightningimport_VerifyCategory($categoryObject)
    {

        $term_id = false;

        $parent_term_id = false;

        $categoryParentName = $categoryObject->Key;

        $parentCategory = get_term_by('name', $categoryParentName, 'product_cat');

        ////self::lightningimport_writeToLog(print_r($category,true));

        //If the category does not have a term_id create it

        if ($parentCategory == false) {

            //Create the new category

            $newParentCategory = wp_insert_term(

                $categoryParentName,

                'product_cat'

            );

            if (!is_wp_error($newParentCategory)) {

                $parent_term_id = $newParentCategory['term_id'];

            } else {

                if (isset($newParentCategory->error_data)) {

                    $parent_term_id = $newParentCategory->get_error_data('term_exists');

                    if (!is_numeric($term_id)) {

                        $parent_term_id = false;

                    }

                }

                ////self::lightningimport_writeToLog('Category error: '.print_r($newCategory,true));

            }

        } else {

            $parent_term_id = $parentCategory->term_id;

        }

        $categoryName = $categoryObject->Value;

        ////self::lightningimport_writeToLog('Category name: '.$categoryName);

        // Get term by name $categoryName in product_cat taxonomy.

        $category = get_term_by('name', $categoryName, 'product_cat');

        ////self::lightningimport_writeToLog(print_r($category,true));

        //If the category does not have a term_id create it

        if ($category == false) {

            //Create the new category

            $newCategory = wp_insert_term(

                $categoryName,

                'product_cat',

                array(

                    'parent' => $parent_term_id,

                )

            );

            if (!is_wp_error($newCategory)) {

                $term_id = $newCategory['term_id'];

            } else {

                if (isset($newCategory->error_data)) {

                    $term_id = $newCategory->get_error_data('term_exists');

                    if (!is_numeric($term_id)) {

                        $term_id = false;

                    }

                }

                ////self::lightningimport_writeToLog('Category error: '.print_r($newCategory,true));

            }

        } else {

            $term_id = $category->term_id;

        }

        ////self::lightningimport_writeToLog(var_dump($category));

        //Return the existing or newly created category

        return $term_id;

    }

    //End Product Processing

    //Attribute Processing

    //Performs check to confirm the user and password combination is authorized with our data service.

    public static function lightningimport_GetAttributesFile($requestBatchID)
    {

        $requestBatchID = trim($requestBatchID);

        self::lightningimport_writeToLog('Start Attribute File Import');

        //Perform user check to api here. Confirm user api login succeeds and they're subscription is valid

        $upload_dir = wp_upload_dir()['basedir'];

        //$plugin_dir = plugin_dir_path( __FILE__ );

        $date = new DateTime();

        if (!file_exists($upload_dir . '/AttributesImport/' . $requestBatchID)) {

            self::lightningimport_writeToLog("Create directory at:" . $upload_dir . '/AttributesImport/' . $requestBatchID);

            mkdir($upload_dir . '/AttributesImport/' . $requestBatchID, 0777, true);

        }

        $filePath = $upload_dir . '/AttributesImport/' . $requestBatchID . '/Attributes_' . $date->format('Y_m_d') . '.csv';

        $options = self::lightningimport_lightningimportOptions();

        $apiUsername = $options['lightningimport_Username'];

        $apiPassword = $options['lightningimport_Password'];

        self::lightningimport_writeToLog("Url is: " . self::lightningimport_apiurl() . 'api/productdata/AttributesFile/' . $requestBatchID);

        //Get product sku list from api here

        $data = self::lightningimport_executeHttp(self::lightningimport_apiurl() . 'api/productdata/AttributesFile/' . $requestBatchID, 'GET', self::lightningimport_GetCurrentAPIToken());

        //Get the content type for mime settings
        if (isset($data['headers'])) {
            $type = $data['headers']['content-type'];
        }

        if (!isset($type)) {

            $type = 'unknown';

        }

        self::lightningimport_writeToLog("Received data from api with type of: " . $type);

        if ($type == 'text/csv') {

            $file = fopen($filePath, "w+");

            fputs($file, $data['body']);

            fclose($file);

            if (file_exists($filePath)) {

                self::lightningimport_writeToLog("Attempting to process file at: " . $filePath . " with type of: " . $type);

                //Run the attribute import

                //if(file_exists($filePath)){

                $result = self::lightningimport_ProcessAttributesFile($filePath);

                self::lightningimport_writeToLog("Processing attribute file at:" . $filePath . " with success: " . $result);

                if ($result) {

                    global $wpdb;

                    self::lightningimport_writeToLog('Drop temp table: ' . $result);

                    $dropTempTable = "drop table $result;";

                    $wpdb->query($dropTempTable);

                    //If the attribute file was successfully processed tell the api to remove the attribute file

                    //self::lightningimport_ConfirmAttributeFileRemoval($dataSubscriptionID);

                }

            }

        }

        //self::lightningimport_DeleteDirectory($upload_dir.'/AttributesImport/'.$dataSubscriptionID);

        self::lightningimport_writeToLog('Finished Attribute File Import');

    }

    public static function lightningimport_DeleteDirectory($dir)
    {

        if (!file_exists($dir)) {

            return true;

        }

        if (!is_dir($dir)) {

            return unlink($dir);

        }

        foreach (scandir($dir) as $item) {

            if ($item == '.' || $item == '..') {

                continue;

            }

            if (!self::lightningimport_DeleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {

                return false;

            }

        }

        return rmdir($dir);

    }

    public static function lightningimport_ProcessAttributesFile($filePath)
    {

        // $plugin_dir = plugin_dir_path( __FILE__ );

        // $filePath = $plugin_dir.'/files/70059F82-A0D9-4A44-AF70-1809D486BF30.csv';

        $result = false;

        try {

            //Setup the load file import from the attribute file

            global $wpdb;

            $tempTableName = self::lightningimport_CreateAttributeTemporaryTable();

            self::lightningimport_writeToLog('LOAD attributes into tempTable: ' . $tempTableName);

            $query = $wpdb->prepare('

				LOAD DATA LOCAL INFILE %s IGNORE INTO TABLE ' . $tempTableName . '

				FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\' LINES TERMINATED BY \'\\n\'

				IGNORE 1 LINES

				(sku,f1,f2,f3,f4,f5,f6,f7,f8);', $filePath);

            self::lightningimport_writeToLog('Insert attributes query:' . $query);

            //Run the import

            $qresult = $wpdb->query($query);

            self::lightningimport_writeToLog('Result of load data:' . $qresult);

            self::lightningimport_writeToLog('Result of load data last error:' . $wpdb->print_error());

            //Delete the file

            unlink($filePath);

            self::lightningimport_writeToLog('Insert attributes from temp table into final table');

            self::lightningimport_InsertIntoProductAttributesTable($tempTableName);

            $result = $tempTableName;

        } catch (Exception $e) {

            self::lightningimport_writeToLog('Error while processing attributes file:' . print_r($e, true));

        }

        return $result;

    }

    public static function lightningimport_InsertIntoProductAttributesTable($tempTable)
    {

        global $wpdb;

        $updateProductyAttributeQuery = "insert ignore into lightningimport_product_attributes

			(post_id,sku,f1,f2,f3,f4,f5,f6,f7,f8)

			select sku.post_id,sku.sku,tmp.f1,tmp.f2,tmp.f3,tmp.f4,tmp.f5,tmp.f6,tmp.f7,tmp.f8 from lightningimport_sku sku

			join $tempTable tmp on sku.sku = tmp.sku";

        $wpdb->query($updateProductyAttributeQuery);

    }

    public static function lightningimport_ConfirmAttributeFileRemoval($dataSubscriptionID)
    {

        //Perform user check to api here. Confirm user api login succeeds and they're subscription is valid

        $options = self::lightningimport_lightningimportOptions();

        $apiUsername = $options['lightningimport_Username'];

        $apiPassword = $options['lightningimport_Password'];

        //HIt api endpoint to let the api know its good to remove the product image

        $data = self::lightningimport_executeHttp(lightningimport_apihelper::lightningimport_apiurl() . "api/productdata/deleteattributesfile/" . $dataSubscriptionID, 'GET', self::lightningimport_GetCurrentAPIToken());

    }

    //Sets the attribute mapping for the product attributes

    public static function lightningimport_SetAttributeMap($attributeMap)
    {

        global $wpdb;

        self::lightningimport_writeToLog('Started AttributeMap Import');

        //Setup the sql statement
        $selectMappingSql = 'select count(1) from lightningimport_product_attribute_mapping where columnname = %s;';
        $updateMappingSql = 'update lightningimport_product_attribute_mapping set attributename = %s where columnname = %s;';
        $insertMappingSql = 'insert lightningimport_product_attribute_mapping (columnname,columnorder,attributename) values(%s,%d,%s);';

        //Loop through each attribute mapping entry as keys and values
        $i = 0;
        foreach ($attributeMap as $key => $value) {

            self::lightningimport_writeToLog($wpdb->prepare($updateMappingSql, $value, $key));

            //Set the entry in the attribute mapping table

            //Check to see if the attribute mapping exists based on the key
            $keyCount = $wpdb->get_var($wpdb->prepare($selectMappingSql, $key));

            //If it exists run an update otherwise run an insert
            if (isset($keyCount) && $keyCount > 0) {
                $wpdb->query($wpdb->prepare($updateMappingSql, $value, $key));
            } else {
                $wpdb->query($wpdb->prepare($insertMappingSql, $key, $i, $value));
            }

            if (isset($value) && $value != '') {

                try {

                    //Don't use prepare for this as we do not want single quotes around the key

                    $createIndexScript = 'CREATE INDEX ' . $key . ' ON lightningimport_product_attributes (' . $key . ');';

                    $wpdb->query($createIndexScript);

                } catch (Exception $e) {

                    //Fail silently. This should only hit if the index already exists.

                }

            }
            $i++;
        }

        self::lightningimport_writeToLog('Finished AttributeMap Import');

    }

    public static function lightningimport_UpdateProductAttributesPostID($product)
    {

        global $wpdb;

        if ($product->PostMeta_Sku && $product->product_id) {

            self::lightningimport_writeToLog('lightningimport_UpdateProductAttributesPostID updating attributes for product' . $product->PostMeta_Sku);

            $updateQuery = $wpdb->prepare("update lightningimport_product_attributes set post_id = %s where sku = %s", $product->product_id, $product->PostMeta_Sku);

            $wpdb->query($updateQuery);

            self::lightningimport_writeToLog('lightningimport_UpdateProductAttributesPostID completed updating attributes for product: ' . $product->PostMeta_Sku);

        } else {

            self::lightningimport_writeToLog('lightningimport_UpdateProductAttributesPostID skipped product due to missing required sku or post_id: ' . print_r($product, true));

        }

    }

    //New Import Process

    public static function lightningimport_CreateTemporaryTable()
    {

        global $wpdb;

        $getcollationsql = "show table status like '$wpdb->posts'";
        $results = $wpdb->get_row($getcollationsql);
        //self::lightningimport_writeToLog('Collation query check results: ' . print_r($results, true));
        $collationtype = "";
        if (isset($results) && count($results) > 0) {
            $collationtype = "COLLATE " . $results->Collation;
        }
        $wpdb->query($createQuery);
        $currentDate = new DateTime();

        $currentDate = $currentDate->format('Y_m_d_H_i_s');

        $tempTableName = 'import' . self::lightningimport_generateRandomString() . $currentDate;

        $createQuery = "create table if not exists `$tempTableName` (

			`import_id` bigint not null auto_increment

			,`PostMeta_Sku` varchar(255) not null $collationtype

			,`PostTitle` text null $collationtype

			,`PostContent` longtext null $collationtype

			,`PostExcerpt` text null $collationtype

			,`PostContentFiltered` longtext null $collationtype

			,`PostMeta_RegularPrice` varchar(255) null $collationtype

			,`PostMeta_SalePrice` varchar(255)  null $collationtype

			,`PostMeta_Weight` varchar(255)  null $collationtype

			,`PostMeta_Length` varchar(255)  null $collationtype

			,`PostMeta_Width` varchar(255)  null $collationtype

			,`PostMeta_Height` varchar(255)  null $collationtype

			,`PostMeta_Price` varchar(255)  null $collationtype

			,`PostMeta_Stock` varchar(255)  null $collationtype

			,`PostMeta_Image` varchar(255)  null $collationtype

			,`RequestBatchId` varchar(255)  null $collationtype

			,`Wholesale` varchar(255) null $collationtype

			,`MAP` varchar(255) null $collationtype

			,`post_id` bigint null $collationtype

			,primary key(`import_id`)

			)ENGINE = INNODB;";

        //self::lightningimport_writeToLog('Temp table create query is: ' . $createQuery);

        $wpdb->query($createQuery);

        return $tempTableName;

    }

    public static function lightningimport_CreateAttributeTemporaryTable()
    {

        global $wpdb;

        $currentDate = new DateTime();

        $currentDate = $currentDate->format('Y_m_d_H_i_s');

        $tempTableName = 'import' . self::lightningimport_generateRandomString() . $currentDate;

        $createQuery = "create table if not exists `$tempTableName` (

			`post_id` bigint null

			,`sku` varchar(100) not null

			,`f1` varchar(100) null

			,`f2` varchar(100) null

			,`f3` varchar(100) null

			,`f4` varchar(100) null

			,`f5` varchar(100) null

			,`f6` varchar(100) null

			,`f7` varchar(100) null

			,`f8` varchar(100) null

			)ENGINE = INNODB;";

        $wpdb->query($createQuery);

        return $tempTableName;

    }

    public static function lightningimport_DoesTableExist($tableName)
    {

        $exists = false;

        global $wpdb;

        $query = $wpdb->prepare('SELECT count(table_name)

			FROM information_schema.tables

			WHERE table_name = \'%s\';', $tableName);

        self::lightningimport_writeToLog('Table check query is: ' . $query);

        //Run the import

        $qresult = $wpdb->get_var($query);

        //self::lightningimport_writeToLog('Result of Table check :'.$qresult);

        //self::lightningimport_writeToLog('Result of Table check last error:'.$wpdb->print_error());

        if (isset($qresult) && $qresult > 0) {

            self::lightningimport_writeToLog('Result of Table check :' . $qresult);

            $exists = true;

        }

        return $exists;

    }    

    public static function lightningimport_AreCustomTablesPresent()
    {

        $exists = false;
        try{
            $exists = self::lightningimport_DoesTableExist('lightningimport_sku');
            self::lightningimport_writeToLog('Result of  lightningimport_sku table check :' . $exists);        
        }
        catch(Exception $ex){
            self::lightningimport_writeToLog('Error checking for lightningimport_sku table: ' . $ex); 
        }
        return $exists;

    } 

    public static function lightningimport_LoadFileProductBatch($filePath)
    {

        // $plugin_dir = plugin_dir_path( __FILE__ );

        // $filePath = $plugin_dir.'/files/70059F82-A0D9-4A44-AF70-1809D486BF30.csv';

        $result = false;

        try {

            $tempTableName = self::lightningimport_CreateTemporaryTable();

            //Setup the load file import from the attribute file

            global $wpdb;

            $query = $wpdb->prepare('

				LOAD DATA LOCAL INFILE \'%s\' IGNORE INTO TABLE ' . $tempTableName . '

				FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\' LINES TERMINATED BY \'\\n\'

				IGNORE 1 LINES

				(PostTitle,PostContent,PostExcerpt,PostContentFiltered,

				PostMeta_Sku,PostMeta_RegularPrice,PostMeta_SalePrice,PostMeta_Weight,PostMeta_Length,PostMeta_Width,

				PostMeta_Height,PostMeta_Price,PostMeta_Stock,PostMeta_Image,RequestBatchId,Wholesale,MAP);', $filePath);

            self::lightningimport_writeToLog('LOAD DATA query is: ' . $query);

            //Run the import

            $qresult = $wpdb->query($query);

            self::lightningimport_writeToLog('Result of load data:' . $qresult);

            self::lightningimport_writeToLog('Result of load data last error:' . $wpdb->print_error());

            //Delete the file

            //unlink($filePath);

            //Check at this point if the temp table has been created. If not attempt the alternate function

            if (!self::lightningimport_DoesTableExist($tempTableName)) {

                self::lightningimport_LoadFileProductBatchAlt($filePath, $tempTableName);

            }

            $result = $tempTableName;

        } catch (Exception $e) {

            self::lightningimport_writeToLog('Error while processing attributes file:' . print_r($e, true));

        }

        return $result;

    }

    public static function lightningimport_LoadFileProductBatchAlt($filePath, $tempTableName = null)
    {

        // $plugin_dir = plugin_dir_path( __FILE__ );

        // $filePath = $plugin_dir.'/files/70059F82-A0D9-4A44-AF70-1809D486BF30.csv';

        $result = false;

        try {

            //get the csv file

            $handle = fopen($filePath, "r");

            if (!isset($tempTableName)) {

                $tempTableName = self::lightningimport_CreateTemporaryTable();

            }

            self::lightningimport_writeToLog("Started alternate process into temp table $tempTableName");

            global $wpdb;

            //loop through the csv file and insert into database

            do {

                if ($data[0]) {

                    $query = $wpdb->prepare("INSERT INTO $tempTableName (PostTitle,PostContent,PostExcerpt,PostContentFiltered,

							PostMeta_Sku,PostMeta_RegularPrice,PostMeta_SalePrice,PostMeta_Weight,PostMeta_Length,PostMeta_Width,

							PostMeta_Height,PostMeta_Price,PostMeta_Stock,PostMeta_Image,RequestBatchId,Wholesale,MAP) VALUES

								(

									'" . addslashes($data[0]) . "',

									'" . addslashes($data[1]) . "',

									'" . addslashes($data[2]) . "',

									'" . addslashes($data[3]) . "',

									'" . addslashes($data[4]) . "',

									'" . addslashes($data[5]) . "',

									'" . addslashes($data[6]) . "',

									'" . addslashes($data[7]) . "',

									'" . addslashes($data[8]) . "',

									'" . addslashes($data[9]) . "',

									'" . addslashes($data[10]) . "',

									'" . addslashes($data[11]) . "',

									'" . addslashes($data[12]) . "',

									'" . addslashes($data[13]) . "',

									'" . addslashes($data[14]) . "',

									'" . addslashes($data[15]) . "',

									'" . addslashes($data[16]) . "'

								)

							");

                    $wpdb->query($query);

                }

            } while ($data = fgetcsv($handle, 9999, ","));

            $query = $wpdb->prepare("delete from $tempTableName where PostTitle = 'PostTitle';");

            $wpdb->query($query);

            self::lightningimport_writeToLog("Processed with $i rows");

            $result = $tempTableName;

        } catch (Exception $e) {

            self::lightningimport_writeToLog('Error while processing attributes file:' . print_r($e, true));

        }

        return $result;

    }

    public static function lightningimport_LoadFileProductBatchNoDB($filePath)
    {
        $i = 0;
        // $plugin_dir = plugin_dir_path( __FILE__ );

        // $filePath = $plugin_dir.'/files/70059F82-A0D9-4A44-AF70-1809D486BF30.csv';

        $result = false;

        try {

            //get the csv file

            $handle = fopen($filePath, "r");

            self::lightningimport_writeToLog("Started no db process");

            global $wpdb;

            //loop through the csv file and insert into database

            do {                
                try {
                    //Make sure there is a first entry and a 4th entry
                    if ($i > 0 && $data[0] && $data[4] && $data[4] != 'PostMeta_Sku') {
                        $updateProductQuery = $wpdb->prepare("update $wpdb->posts p

                    join $wpdb->postmeta sku on p.id = sku.post_id and sku.meta_key = 'li_sku'

                    set p.post_content = %s

                    ,p.post_content_filtered = %s

                    ,p.post_title = %s

                    ,p.post_excerpt = %s

                    ,p.post_status = CASE WHEN IFNULL(%d,0)=0 THEN 'draft' ELSE 'publish' END

                    where sku.meta_value = %s;",$data[1],$data[3],$data[0],$data[2],$data[5],$data[4]);

                    self::lightningimport_writeToLog('Update post field query is: ' . $updateProductQuery);

                        $wpdb->query($updateProductQuery);

                        $updatePostMetaQuery = $wpdb->prepare("

                delete pm

                from $wpdb->postmeta pm

                where pm.meta_key in ('_stock_status','_regular_price','map_price','wholesale','_sale_price','_weight','_length','_width','_height','_sku','_price','_stock','_visibility',

                '_purchase_note',

                '_featured',

                'total_sales',

                '_downloadable',

                '_virtual',

                '_sale_price_dates_from',

                '_sale_price_dates_to')
                and exists (select 1 from $wpdb->postmeta pm2 where pm.post_id = pm2.post_id and pm2.meta_key = 'li_sku' and pm2.meta_value = %s);",$data[4]);

                self::lightningimport_writeToLog('Update post meta field query is: ' . $updatePostMetaQuery);

                        $wpdb->query($updatePostMetaQuery);

                        $insertProductQuery = $wpdb->prepare("INSERT INTO $wpdb->posts(post_author, post_date, post_date_gmt, post_content, post_content_filtered,

			post_title, post_excerpt, post_status, post_type, comment_status, ping_status, post_password,

			post_name, to_ping, pinged, post_modified, post_modified_gmt, post_parent, menu_order, post_mime_type, guid)

			select 9999,now(),now(),%s,%s,%s,%s,CASE WHEN IFNULL(%d,0)=0 THEN 'draft' ELSE 'publish' END, 'product','open', 'closed',

			%s,lower(replace(%s,' ','-')),'','',now(),now(),0,0,'',%s

            from DUAL

			where not exists (select 1 from  $wpdb->posts p

            join $wpdb->postmeta sku on p.id = sku.post_id and sku.meta_key = 'li_sku' and sku.meta_value = %s);",$data[1],$data[3],$data[0],$data[2],$data[5],$data[13],$data[4],$data[4],$data[4]);

            self::lightningimport_writeToLog('Insert post field query is: ' . $insertProductQuery);

            $wpdb->query($insertProductQuery);

            $last_post_id = $wpdb->insert_id;

            $insertPostMetaQuery = $wpdb->prepare("

			insert $wpdb->postmeta

			(

			post_id

			,meta_key

			,meta_value

			)

			select $last_post_id as post_id,'_regular_price',%s
			

			union all

			select $last_post_id as post_id,'map_price',%s

			

			union all

			select $last_post_id as post_id,'wholesale',%s

			

			union all

			select $last_post_id as post_id,'_sale_price',%s

			

			union all

			select $last_post_id as post_id,'_weight',%s

			

			union all

			select $last_post_id as post_id,'_length',%s

			

			union all

			select $last_post_id as post_id,'_width',%s

			

			union all

			select $last_post_id as post_id,'_height',%s

			

			union all

			select $last_post_id as post_id,'_sku',%s

			

            union all
            
            select $last_post_id as post_id,'li_sku',%s

			

			union all

			select $last_post_id as post_id,'_price',%s

			

			union all

			select $last_post_id as post_id,'_stock',%s

			

			union all

			select $last_post_id as post_id,'_stock_status',CASE WHEN %d > 0 THEN 'instock' ELSE 'outofstock' END

			

			union all

			select $last_post_id as post_id,'_visibility','visible'

			

			union all

			select $last_post_id as post_id,'_purchase_note',''

			

			union all

			select $last_post_id as post_id,'_featured','no'

			

			union all

			select $last_post_id as post_id,'total_sales','0'

			

			union all

			select $last_post_id as post_id,	'_downloadable','no'

			

			union all

			select $last_post_id as post_id,'_virtual','no'

			

			union all

			select $last_post_id as post_id,'_sale_price_dates_from',''

			

			union all

			select $last_post_id as post_id,'_sale_price_dates_to',''

			;",$data[5],$data[16],$data[15],$data[6],$data[7],$data[8],$data[9],$data[10],$data[4],$data[4],$data[5],$data[12],$data[12]);

            self::lightningimport_writeToLog('Insert post_meta field query is: ' . $insertPostMetaQuery);

        $wpdb->query($insertPostMetaQuery);

        //Set the request batch
        $requestBatchID = $data[14];                
                    }
                } catch (Exception $e) {
                    self::lightningimport_writeToLog('Error while importing product row:' . print_r($e, true));
                }
                $i++;
            } while ($data = fgetcsv($handle, 9999, ","));

            $insertSkuQuery = "INSERT IGNORE INTO $wpdb->postmeta(post_id,meta_key,meta_value)

			select p.id,'li_image',p.post_password

			from $wpdb->posts p

            join $wpdb->postmeta sku on p.id = sku.post_id and sku.meta_key = 'li_sku'

			where 1=1

			and p.post_author = 9999;";

            self::lightningimport_writeToLog('Insert image field query is: ' . $insertSkuQuery);

            $wpdb->query($insertSkuQuery);

            $updatePostAuthor = "update $wpdb->posts

            set post_author =0,
            post_password = ''                    
			where post_author = 9999;";

            $wpdb->query($updatePostAuthor);
            
            self::lightningimport_writeToLog("Processed with $i rows");

            self::lightningimport_writeToLog("Request batch:" . $requestBatchID);

            if ($requestBatchID) {
    
                $result = $requestBatchID;
    
            }            

        } catch (Exception $e) {

            self::lightningimport_writeToLog('Error while processing attributes file:' . print_r($e, true));

        }

        return $result;

    }

    //Gets product csv to import into database

    public static function lightningimport_GetProductImportFile($dataSubscriptionID)
    {

        try {

            //Perform user check to api here. Confirm user api login succeeds and they're subscription is valid

            $upload_dir = wp_upload_dir()['basedir'];

            //$plugin_dir = plugin_dir_path( __FILE__ );

            $date = new DateTime();

            if (!file_exists($upload_dir . '/lightningimports/' . $dataSubscriptionID)) {

                mkdir($upload_dir . '/lightningimports/' . $dataSubscriptionID, 0777, true);

            }

            $filePath = $upload_dir . '/lightningimports/' . $dataSubscriptionID . '/productfile' . $date->format('Y_m_d') . '.csv';

            $options = self::lightningimport_lightningimportOptions();

            $apiUsername = $options['lightningimport_Username'];

            $apiPassword = $options['lightningimport_Password'];

            //Check if the custom tables exists
            $customTablesExists = self::lightningimport_AreCustomTablesPresent();

            self::lightningimport_writeToLog("Request product file from:" . self::lightningimport_apiurl() . 'api/productdata/' . $dataSubscriptionID . '?username=' . $apiUsername . '&password=' . $apiPassword);

            //Get product sku list from api here
            $data = self::lightningimport_executeHttp(self::lightningimport_apiurl() . 'api/productdata/' . $dataSubscriptionID, 'GET', self::lightningimport_GetCurrentAPIToken());

            //Get the content type for mime settings
            if (isset($data['headers'])) {
                $type = $data['headers']['content-type'];
            }

            if (!isset($type)) {

                $type = 'unknown';

            }

            if ($type == 'text/csv') {

                $file = fopen($filePath, "w+");

                fputs($file, $data['body']);

                fclose($file);

                if (file_exists($filePath)) {

                    ////self::lightningimport_writeToLog($filePath);

                    //Run the attribute import

                    self::lightningimport_writeToLog("Attempting to process file at: " . $filePath . " with type of: " . $type);


                    if(!$customTablesExists){
                        self::lightningimport_writeToLog("Processing using no db on file at: " . $filePath . " with type of: " . $type);
                        $requestBatchId = self::lightningimport_LoadFileProductBatchNoDB($filePath);
                    }
                    else{
                        self::lightningimport_writeToLog("Attempting to process file at: " . $filePath . " with type of: " . $type);

                        $tempTable = self::lightningimport_LoadFileProductBatch($filePath);

                        self::lightningimport_writeToLog('Temp table is: ' . $tempTable);

                        if ($tempTable) {

                            $requestBatchId = self::lightningimport_InsertProductsFromImportTable($tempTable);

                            self::lightningimport_writeToLog("Processing product file at:" . $filePath . " with requestBatchId returned: " . $requestBatchId);
                        }
                    }

                    self::lightningimport_writeToLog("Processing product file at:" . $filePath . " with requestBatchId returned: " . $requestBatchId);

                    if ($requestBatchId) {

                        //If the attribute file was successfully processed tell the api to remove the attribute file

                        $data = self::lightningimport_MarkProductsReceived($requestBatchId);                        

                        if($customTablesExists){
                            self::lightningimport_GetAttributesFile($requestBatchId);
                        }
                        $result = json_decode($data);

                        //self::lightningimport_writeToLog("lightningimport_MarkProductsReceived returned:". print_r($result,true));

                        self::lightningimport_ProcessCategories($result->categories);

                        if($customTablesExists){
                            self::lightningimport_SetAttributeMap($result->attributeMap);
                        }

                    }

                    

                }

            }

        } catch (Exception $e) {

            self::lightningimport_writeToLog('Error while trying to get product file and write to disk:' . print_r($e, true));

        }

    }

    public static function lightningimport_InsertProductsFromImportTable($importTableName = 'lightningimport_temp')
    {

        global $wpdb;

        $result = false;

        //If we previously had entries in the postmeta for skus make sure they also have an entry in our custom tables
        $insertExistingPostMetaSkuEntries = "
        insert ignore into lightningimport_sku(image,post_id,sku)
        select pmimage.meta_value,p.id,pm.meta_value
        from wp_posts p
        join wp_postmeta pm on p.id = pm.post_id and  pm.meta_key = 'li_sku'
        left join wp_postmeta pmimage on p.id = pm.post_id and  pm.meta_key = 'li_image'
        where not exists (select 1 from lightningimport_sku lisku where lisku.sku = pm.meta_value)";

        $wpdb->query($insertExistingPostMetaSkuEntries);

        $deleteTermRelationshipQuery = "delete tr from " . $wpdb->prefix . "term_relationships tr

			join lightningimport_sku sku on tr.object_id = sku.post_id

			join $importTableName tmp on tmp.PostMeta_sku = sku.sku;";

        $wpdb->query($deleteTermRelationshipQuery);

        //$lastPostID = $wpdb->get_var("select max(id) from $wpdb->posts;");

        //self::lightningimport_writeToLog('last product id is: '.$lastPostID);

        $updateProductQuery = "update $wpdb->posts p

			join lightningimport_sku sku on p.id = sku.post_id

			join $importTableName tmp on tmp.PostMeta_sku = sku.sku

			set p.post_content = tmp.PostContent

			,p.post_content_filtered = tmp.PostContentFiltered

			,p.post_title = tmp.PostTitle

			,p.post_excerpt = tmp.PostExcerpt

			,p.post_status = CASE WHEN IFNULL(tmp.PostMeta_RegularPrice,0)=0 THEN 'draft' ELSE 'publish' END;";

        $wpdb->query($updateProductQuery);

        $updatePostMetaQuery = "

			delete pm

			from $wpdb->postmeta pm

			join lightningimport_sku sku on pm.post_id = sku.post_id

			join $importTableName tmp on tmp.PostMeta_sku = sku.sku

			and pm.meta_key in ('_stock_status','_regular_price','map_price','wholesale','_sale_price','_weight','_length','_width','_height','_sku','_price','_stock','_visibility',

			'_purchase_note',

			'_featured',

			'total_sales',

			'_downloadable',

			'_virtual',

			'_sale_price_dates_from',

            '_sale_price_dates_to',

            'li_sku',

            'li_image');";

        $wpdb->query($updatePostMetaQuery);

        $insertProductQuery = "INSERT INTO $wpdb->posts(post_author, post_date, post_date_gmt, post_content, post_content_filtered,

			post_title, post_excerpt, post_status, post_type, comment_status, ping_status, post_password,

			post_name, to_ping, pinged, post_modified, post_modified_gmt, post_parent, menu_order, post_mime_type, guid)

			select 9999,now(),now(),PostContent,PostContentFiltered,PostTitle,PostExcerpt,CASE WHEN IFNULL(PostMeta_RegularPrice,0)=0 THEN 'draft' ELSE 'publish' END, 'product','open', 'closed',

			'',lower(replace(PostMeta_Sku,' ','-')),'','',now(),now(),0,0,'',PostMeta_Sku

			from $importTableName

			where not exists (select 1 from lightningimport_sku where sku = PostMeta_Sku);";

        $wpdb->query($insertProductQuery);

        $insertSkuQuery = "INSERT IGNORE INTO lightningimport_sku(sku,post_id,image)

			select p.guid,p.id,tmp.PostMeta_Image

			from $wpdb->posts p

			join $importTableName tmp on p.guid = tmp.PostMeta_sku

			where 1=1

			and p.post_author = 9999;";

        self::lightningimport_writeToLog('Insert Sku query is: ' . $insertSkuQuery);

        $wpdb->query($insertSkuQuery);       

        $updatePostAuthor = "update $wpdb->posts

			set post_author =0

			where post_author = 9999;";

        $wpdb->query($updatePostAuthor);

        $updateTmpTablePostID = "update $importTableName tmp

			join lightningimport_sku sku on tmp.PostMeta_sku = sku.sku

			set tmp.post_id = sku.post_id;";

        $wpdb->query($updateTmpTablePostID);

        $insertPostMetaQuery = "

			insert $wpdb->postmeta

			(

			post_id

			,meta_key

			,meta_value

			)

			select tmp.post_id as post_id,'_regular_price',tmp.PostMeta_RegularPrice

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'map_price',tmp.MAP

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'wholesale',tmp.Wholesale

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_sale_price',tmp.PostMeta_SalePrice

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_weight',tmp.PostMeta_Weight

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_length',tmp.PostMeta_Length

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_width',tmp.PostMeta_Width

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_height',tmp.PostMeta_Height

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_sku',tmp.PostMeta_Sku

			from $importTableName tmp

            union all
            
            select tmp.post_id as post_id,'li_sku',tmp.PostMeta_Sku

			from $importTableName tmp

            union all
            
            select tmp.post_id as post_id,'li_image',tmp.PostMeta_Image

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_price',tmp.PostMeta_RegularPrice

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_stock',tmp.PostMeta_Stock

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_stock_status',CASE WHEN tmp.PostMeta_Stock > 0 THEN 'instock' ELSE 'outofstock' END

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_visibility','visible'

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_purchase_note',''

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_featured','no'

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'total_sales','0'

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,	'_downloadable','no'

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_virtual','no'

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_sale_price_dates_from',''

			from $importTableName tmp

			union all

			select tmp.post_id as post_id,'_sale_price_dates_to',''

			from $importTableName tmp;";

        $wpdb->query($insertPostMetaQuery);

        $updateSkuQuery = "update lightningimport_sku sku

			join $importTableName tmp on sku.sku = tmp.PostMeta_Sku

			set sku.image = tmp.PostMeta_Image;";

        //self::lightningimport_writeToLog('Insert Sku query is: '.$insertSkuQuery);

        $wpdb->query($updateSkuQuery);

        $requestBatchID = $wpdb->get_var("select RequestBatchId from $importTableName LIMIT 1;");

        $removeExistingRows = "

			delete tmp from $importTableName tmp

			join lightningimport_sku sku on tmp.PostMeta_Sku = sku.sku;";

        $dropTempTable = "drop table $importTableName;";

        $wpdb->query($dropTempTable);

        self::lightningimport_writeToLog("Request batch:" . $requestBatchID);

        if ($requestBatchID) {

            $result = $requestBatchID;

        }

        return $result;

    }

    public static function lightningimport_generateRandomString($length = 10)
    {

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $charactersLength = strlen($characters);

        $randomString = '';

        for ($i = 0; $i < $length; $i++) {

            $randomString .= $characters[rand(0, $charactersLength - 1)];

        }

        return $randomString;

    }

    //End Attribute Processing

    //Quantity Processing

    public static function lightningimport_CreateQuantityTemporaryTable()
    {

        global $wpdb;

        $currentDate = new DateTime();

        $currentDate = $currentDate->format('Y_m_d_H_i_s');

        $tempTableName = 'quantity' . self::lightningimport_generateRandomString() . $currentDate;

        $createQuery = "create table if not exists `$tempTableName` (

			`sku` varchar(100) not null

			,`quantity` int null

			)ENGINE = INNODB;";

        $wpdb->query($createQuery);

        return $tempTableName;

    }

    public static function lightningimport_LoadFileProductQuantityBatch($filePath)
    {

        // $plugin_dir = plugin_dir_path( __FILE__ );

        // $filePath = $plugin_dir.'/files/70059F82-A0D9-4A44-AF70-1809D486BF30.csv';

        $result = false;

        try {

            $tempTableName = self::lightningimport_CreateQuantityTemporaryTable();

            //Setup the load file import from the attribute file

            global $wpdb;

            $query = $wpdb->prepare('

				LOAD DATA LOCAL INFILE \'%s\' IGNORE INTO TABLE ' . $tempTableName . '

				FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\' LINES TERMINATED BY \'\\n\'

				(sku,quantity);', $filePath);

            //self::lightningimport_writeToLog('LOAD DATA query is: '.$query);

            //Run the import

            $wpdb->query($query);

            //Delete the file

            //unlink($filePath);

            //Check if the temp table exists if not run the alternate function

            if (!self::lightningimport_DoesTableExist($tempTableName)) {

                self::lightningimport_LoadFileProductQuantityBatchAlt($filePath, $tempTableName);

            }

            $result = $tempTableName;

        } catch (Exception $e) {

            self::lightningimport_writeToLog('Error while processing quantities file:' . print_r($e, true));

        }

        return $result;

    }

    public static function lightningimport_LoadFileProductQuantityBatchAlt($filePath, $tempTableName = null)
    {

        // $plugin_dir = plugin_dir_path( __FILE__ );

        // $filePath = $plugin_dir.'/files/70059F82-A0D9-4A44-AF70-1809D486BF30.csv';

        $result = false;

        try {

            if (!isset($tempTableName)) {

                $tempTableName = self::lightningimport_CreateTemporaryTable();

            }

            $handle = fopen($filePath, "r");

            self::lightningimport_writeToLog("Started alternate quantity process into temp table $tempTable");

            $i = 0;

            global $wpdb;

            //loop through the csv file and insert into database

            do {

                if ($data[0] && $i != 0) {

                    $query = $wpdb->prepare("INSERT INTO $tempTableName (sku,quantity) VALUES

                            (

                                '" . addslashes($data[0]) . "',

                                '" . addslashes($data[1]) . "',

                                '" . addslashes($data[2]) . "',

                                '" . addslashes($data[3]) . "',

                                '" . addslashes($data[4]) . "',

                                '" . addslashes($data[5]) . "',

                                '" . addslashes($data[6]) . "',

                                '" . addslashes($data[7]) . "',

                                '" . addslashes($data[8]) . "',

                                '" . addslashes($data[9]) . "',

                                '" . addslashes($data[10]) . "',

                                '" . addslashes($data[11]) . "',

                                '" . addslashes($data[12]) . "',

                                '" . addslashes($data[13]) . "',

                                '" . addslashes($data[14]) . "',

                                '" . addslashes($data[15]) . "',

                                '" . addslashes($data[16]) . "'

                            )

                        ");

                    $wpdb->query($query);

                }

                $i++;

            } while ($data = fgetcsv($handle, 9999, ",", "'"));

            self::lightningimport_writeToLog("Processed quantity with $i rows");

            //Delete the file

            //unlink($filePath);

            $result = $tempTableName;

        } catch (Exception $e) {

            self::lightningimport_writeToLog('Error while processing quantities file:' . print_r($e, true));

        }

        //Try and close the file if its open

        if ($handle) {

            fclose($file);

        }

        return $result;

    }

    //Gets product csv to import into database

    public static function lightningimport_GetProductQuantityFile($dataSubscriptionID)
    {

        //Perform user check to api here. Confirm user api login succeeds and they're subscription is valid

        $upload_dir = wp_upload_dir()['basedir'];

        //$plugin_dir = plugin_dir_path( __FILE__ );

        $date = new DateTime();

        if (!file_exists($upload_dir . '/lightningimports/' . $dataSubscriptionID)) {

            mkdir($upload_dir . '/lightningimports/' . $dataSubscriptionID, 0777, true);

        }

        $filePath = $upload_dir . '/lightningimports/' . $dataSubscriptionID . '/productquantity' . $date->format('Y_m_d') . '.csv';

        $options = self::lightningimport_lightningimportOptions();

        $apiUsername = $options['lightningimport_Username'];

        $apiPassword = $options['lightningimport_Password'];

        self::lightningimport_writeToLog("Request product quantity file from:" . self::lightningimport_apiurl() . 'api/productdata/quantity/' . $dataSubscriptionID . '?username=' . $apiUsername . '&password=' . $apiPassword);

        //Get product sku list from api here

        $data = self::lightningimport_executeHttp(self::lightningimport_apiurl() . 'api/productdata/quantity/' . $dataSubscriptionID, 'GET', self::lightningimport_GetCurrentAPIToken());

        //Get the content type for mime settings
        if (isset($data['headers'])) {
            $type = $data['headers']['content-type'];
        }

        if (!isset($type)) {

            $type = 'unknown';

        }

        if ($type == 'text/csv') {

            $file = fopen($filePath, "w+");

            fputs($file, $data['body']);

            fclose($file);

            if (file_exists($filePath)) {

                ////self::lightningimport_writeToLog($filePath);

                //Run the attribute import

                self::lightningimport_writeToLog("Attempting to process file at: " . $filePath . " with type of: " . $type);

                $tempTable = self::lightningimport_LoadFileProductQuantityBatch($filePath);

                self::lightningimport_writeToLog('Temp table is: ' . $tempTable);

                if ($tempTable) {

                    $result = self::lightningimport_UpdateQuantityFromImportTable($tempTable);

                    self::lightningimport_writeToLog("Processing product file at:" . $filePath . " with requestBatchId returned: " . $result);

                    if ($result) {

                        //sleep(10);

                        //If the quantity file was successfully processed tell the api to remove the attribute file

                        self::lightningimport_MarkProductQuantitiesReceived($dataSubscriptionID);

                    }

                }

            }

        }

    }

    public static function lightningimport_UpdateQuantityFromImportTable($quantityTableName = 'lightningimport_temp')
    {

        global $wpdb;

        $result = false;

        try {

            $updateProductQuery = "update $wpdb->postmeta pm

				join lightningimport_sku sku on pm.post_id = sku.post_id

				join $importTableName tmp on tmp.sku = sku.sku

				set pm.meta_value = tmp.quantity

				where pm.meta_key = '_stock';";

            $wpdb->query($updateProductQuery);

            $updateStatusProductQuery = "update $wpdb->postmeta pm

				join lightningimport_sku sku on pm.post_id = sku.post_id

				join $importTableName tmp on tmp.sku = sku.sku

				set pm.meta_value = CASE WHEN tmp.quantity >0 THEN 'instock' ELSE 'outofstock' END

				where pm.meta_key = '_stock_status';";

            $wpdb->query($updateStatusProductQuery);

            $dropTempTable = "drop table $importTableName;";

            $wpdb->query($dropTempTable);

            $result = true;

        } catch (Exception $ex) {

            self::lightningimport_writeToLog('Error while processing quantities file:' . print_r($e, true));

        }

        return $result;

    }

    //Confirms a DataSubscriptionID was processed

    public static function lightningimport_MarkProductQuantitiesReceived($dataSubscriptionID)
    {

        $options = self::lightningimport_lightningimportOptions();

        $apiUsername = $options['lightningimport_Username'];

        $apiPassword = $options['lightningimport_Password'];

        self::lightningimport_writeToLog('Api URL: ' . self::lightningimport_apiurl() . 'api/MarkProductsReceived/');

        self::lightningimport_writeToLog('Making request to: ' . self::lightningimport_apiurl() . 'api/productdata/deletequantity/' . trim($dataSubscriptionID));

        //Get product sku list from api here

        $result = self::lightningimport_executeHttp('https://lightningimport.com/api/productdata/deletequantity/' . trim($dataSubscriptionID), 'GET', self::lightningimport_GetCurrentAPIToken());

        self::lightningimport_writeToLog("MarkProductQuantityReceived data after executecurl:" . print_r($result, true));

    }

    //End Processing

}
