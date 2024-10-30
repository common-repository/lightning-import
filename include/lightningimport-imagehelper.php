<?php
set_time_limit(0);
//include dirname( __FILE__ ) . '/lightningimport-apihelper.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
class lightningimport_imagehelper
{
    //Image processing

    //Returns the url for the api
    public static function lightningimport_tmpFileName()
    {
        return "imageImportLock.tmp";
    }

    //Import Image Data from api
    public static function lightningimport_ImportImageData()
    {
        $options = lightningimport_apihelper::lightningimport_lightningimportOptions();
        if (isset($options['lightningimport_ScanToggle'])) {
            if ($options['lightningimport_ScanToggle'] == "1") {
                lightningimport_apihelper::lightningimport_writeToLog('Started Image Import');
                $lightningimport_tmpFileName = self::lightningimport_tmpFileName();
                $directory = dirname(__FILE__);
                $tmpFilePath = $directory . '/' . $lightningimport_tmpFileName;
                if (!self::lightningimport_CheckForImportProcess()) {
                    $dataSubscriptionList = self::lightningimport_GetImageDataSubscriptionIDList();
                    if (count($dataSubscriptionList) > 0) {
                        //Loop through the list and process every data subscription received
                        //self::lightningimport_GetImageList($dataSubscriptionList[0]);
                        //Link existing images to products if necessary
                        self::lightningimport_LinkProductToExistingImage();
                        foreach ($dataSubscriptionList as $dataSubscription) {

                            //$productlightningimport_tmpFileName = lightningimport_apihelper::lightningimport_tmpFileName();
                            //$directory = dirname( __FILE__ );
                            //$producttmpFilePath = $directory.'/'.$productlightningimport_tmpFileName;
                            //if(file_exists($producttmpFilePath)){
                            //lightningimport_apihelper::lightningimport_writeToLog('Product Import running. Cancel Image Import');
                            //unlink($tmpFilePath);
                            //exit();
                            //}
                            self::lightningimport_GetImageList($dataSubscription);
                        }
                    }
                    if (file_exists($tmpFilePath)) {
                        unlink($tmpFilePath);
                    }
                    lightningimport_apihelper::lightningimport_writeToLog('Finished Image Import');
                    exit();
                }
            } else {
                lightningimport_apihelper::lightningimport_writeToLog('Image Import toggle is off');
            }
        } else {
            lightningimport_apihelper::lightningimport_writeToLog('Image Import toggle is not set');
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
            $dateNow->sub(new DateInterval('PT30M'));
            $fileModified = new DateTime();
            $fileModified->setTimestamp(filemtime($tmpFilePath));
            lightningimport_apihelper::lightningimport_writeToLog('File modified: ' . $fileModified->format('Y-m-d H:i:s') . ' Date: ' . $dateNow->format('Y-m-d H:i:s'));
            //lightningimport_apihelper::lightningimport_writeToLog(print_r(strtotime($posts[0]->post_modified)>$date,true));
            if ($fileModified < $dateNow) {
                unlink($tmpFilePath);
                lightningimport_apihelper::lightningimport_writeToLog('Deleted lock file due to time');
            } else {
                $inProcess = true;
            }
        } else {
            $fh = fopen($tmpFilePath, 'w') or die("Can't open file $name for writing temporary stuff.");
            fwrite($fh, 'Images In Process');
            fclose($fh);
        }
        lightningimport_apihelper::lightningimport_writeToLog('Image Process running: ' . print_r($inProcess, true));
        return $inProcess;
    }

    //Performs check to confirm the user and password combination is authorized with our data service.
    public static function lightningimport_GetImageDataSubscriptionIDList()
    {

        //Get product sku list from api here
        $data = lightningimport_apihelper::lightningimport_executeHttp(lightningimport_apihelper::lightningimport_apiurl() . 'api/productdata/GetProductDataSubscriptionIDListByAccountName/?fetchAll=True', 'GET', lightningimport_apihelper::lightningimport_GetCurrentAPIToken());
        $dataSubscriptionList = array();
        if (isset($data) && isset($data['body']) && $data['body'] == true) {
            //Parse received data from api and set list
            $dataSubscriptionList = json_decode($data['body']);
        }
        //lightningimport_apihelper::lightningimport_writeToLog(print_r($dataSubscriptionList,true));
        return $dataSubscriptionList;
    }

    //Gets a list of image sku's to request
    public static function lightningimport_GetImageList($dataSubscriptionID)
    {
        //Get product sku list from api here
        $data = lightningimport_apihelper::lightningimport_executeHttp(lightningimport_apihelper::lightningimport_apiurl() . 'api/productdata/GetImageList/' . $dataSubscriptionID, 'GET', lightningimport_apihelper::lightningimport_GetCurrentAPIToken());
        $imageList = array();
        if (isset($data) && isset($data['body']) && $data['body'] == true) {
            //Parse received data from api and set list
            $imageList = json_decode($data['body']);
        }
        //lightningimport_apihelper::lightningimport_writeToLog('Image list: '.print_r($imageList,true));
        if (count($imageList) > 0) {
            //Loop through the list and process every data subscription received
            $successArray = [];
            for ($i = 0; $i < count($imageList); $i++) {
                $success = self::lightningimport_SetProductImage($dataSubscriptionID, $imageList[$i]);
                $successArray[$imageList[$i]] = $success;
            }
            //lightningimport_apihelper::lightningimport_writeToLog(print_r($successArray,true));
            //Loop through the list and process every data subscription received
            foreach ($successArray as $sku => $success) {
                lightningimport_apihelper::lightningimport_writeToLog('Sku: ' . $sku . ' and Success: ' . $success);
                if ($success == true) {
                    //If the image was processed successfully notify the api to remove the images
                    self::lightningimport_ConfirmApiImageRemoval($dataSubscriptionID, $sku);
                }
            }
            lightningimport_apihelper::lightningimport_writeToLog('Finished batch of images');
        }
    }

    //Gets the product image from the api and sets the thumbnail for the product
    public static function lightningimport_SetProductImage($dataSubscriptionID, $sku)
    {
        global $wpdb;
        //Setup the default success value
        $imageSuccess = false;
        $customTablesExists = lightningimport_apihelper::lightningimport_AreCustomTablesPresent();
        // //Setup the check for a post with the product sku
        // $args = array(
        // 'meta_key' => '_sku',
        // 'meta_value' => $sku,
        // 'post_type' => 'product',
        // 'post_status' => 'any',
        // 'posts_per_page' => -1
        // );

        // //Check if the product exists
        // $posts = get_posts($args);

        $getPostIDQuery = $wpdb->prepare("select post_id from lightningimport_sku where sku = %s;", $sku);
        if(!$customTablesExists){
            $getPostIDQuery = $wpdb->prepare("select post_id from $wpdb->postmeta where meta_key = 'li_sku' and meta_value = %s;", $sku);
        }
        $product_id = $wpdb->get_var($getPostIDQuery);

        //lightningimport_apihelper::lightningimport_writeToLog('Found post for sku?'.print_r($posts,true));
        if (!is_null($product_id) && is_numeric($product_id)) {
            //May need to deal with duplication of sku entries here.
            //For now just get the first entry
            //$product_id = $posts[0]->ID;

            try {

                $db_prefix = $wpdb->prefix;

                //Get the image name from the sku
                // $getImageName = "select pmi.meta_value as Image from ".$db_prefix."posts p join ".$db_prefix."postmeta pmi on p.Id = pmi.post_id join ".$db_prefix."postmeta pms on p.Id = pms.post_id
                // where p.post_type='product' and pmi.meta_key = 'Image' and pms.meta_key = '_sku' and pms.meta_value = '".$sku."' LIMIT 1;";

                $getImageName = $wpdb->prepare("select image from lightningimport_sku where sku = %s;", $sku);
                if(!$customTablesExists){
                    $getImageName = $wpdb->prepare("select image.meta_value from $wpdb->postmeta sku 
                    join $wpdb->postmeta image on sku.post_id = image.post_id and image.meta_key = 'li_image' 
                    where sku.meta_key = 'li_sku' and sku.meta_value = %s;", $sku);
                }
                $imageName = $wpdb->get_var($getImageName);
                $imageFileName = str_replace(' ', '-', trim($imageName));

                lightningimport_apihelper::lightningimport_writeToLog('Check image name: ' . $imageFileName);
                $dirs = self::lightningimport_glob_directory_recursive(wp_upload_dir()['basedir'] . '/*');
                //lightningimport_apihelper::lightningimport_writeToLog('lightningimport_SetProductImage look in dirs: '.print_r($dirs,true));
                $fileexists = false;
                foreach ($dirs as $dir) {
                    //lightningimport_apihelper::lightningimport_writeToLog('lightningimport_SetProductImage looking for file at: '.$dir.'/'.$imageFileName);
                    if (file_exists($dir . '/' . $imageFileName)) {
                        lightningimport_apihelper::lightningimport_writeToLog('lightningimport_SetProductImage found the file at: ' . $dir . '/' . $imageFileName);
                        $fileexists = true;
                        $image_src = $dir . '/' . $imageFileName;
                        break;
                    }
                }

                $query = "SELECT id FROM {$wpdb->posts} WHERE post_title = '" . $imageName . "' and post_type='attachment' LIMIT 1;";
                $attach_id = $wpdb->get_var($query);

                lightningimport_apihelper::lightningimport_writeToLog('lightningimport_SetProductImage attach_id query result: ' . $attach_id);

                //If the attach_id doesnt exist get the image from the api
                if ($fileexists && (!isset($attach_id) || !is_numeric($attach_id))) {

                    $type = lightningimport_mime_content_type($image_src);
                    lightningimport_apihelper::lightningimport_writeToLog("File type: " . $type);
                    if ($type) {
                        //Setup the attachement details before insert
                        $attachment = array(
                            'post_title' => $imageFileName,
                            'post_mime_type' => $type,
                        );

                        //Insert the attachment post
                        $attach_id = wp_insert_attachment($attachment, $image_src, $product_id);

                        $attach_data = wp_generate_attachment_metadata($attach_id, $image_src);

                        //Update the attachment with metadata
                        wp_update_attachment_metadata($attach_id, $attach_data);
                        lightningimport_apihelper::lightningimport_writeToLog('lightningimport_SetProductImage created new attach_id: ' . $attach_id);
                    }
                }

                //Try to get the attachment id before downloading a new copy
                //$image_src = wp_upload_dir()['baseurl'] . '/' . _wp_relative_upload_path( $imageName );
                //lightningimport_apihelper::lightningimport_writeToLog('lightningimport_SetProductImage relative upload path: '.$image_src);

                //If the attach_id doesnt exist get the image from the api
                if (!$fileexists) {

                    //Setup the url to request the image
                    $image = lightningimport_apihelper::lightningimport_apiurl() . "api/productdata/image/" . $dataSubscriptionID . "?sku=" . urlencode($sku);

                    lightningimport_apihelper::lightningimport_writeToLog($image);

                    //Perform the image http request
                    $get = lightningimport_apihelper::lightningimport_executeHttp($image, 'GET', lightningimport_apihelper::lightningimport_GetCurrentAPIToken());

                    if (isset($get) && is_wp_error($get)) {
                        lightningimport_apihelper::lightningimport_writeToLog('Image request resulted in error: ' . print_r($get, true));
                        return $imageSuccess;
                    }
                    //Get the content type for mime settings
                    $type = wp_remote_retrieve_header($get, 'content-type');

                    //Get the disposition for the filename
                    $disposition = wp_remote_retrieve_header($get, 'content-disposition');

                    //Return false if the image type is unknown
                    if (!$type || !$disposition) {
                        return $imageSuccess;
                    }

                    //lightningimport_debugging code
                    //lightningimport_apihelper::lightningimport_writeToLog(print_r($get,true));
                    //lightningimport_apihelper::lightningimport_writeToLog($disposition);
                    //lightningimport_apihelper::lightningimport_writeToLog(self::lightningimport_GetHeaderFilename($disposition));

                    //Get the filename from the disposition
                    $filename = self::lightningimport_GetHeaderFilename($disposition);

                    //Save the http body as a file
                    $mirror = wp_upload_bits($filename, '', wp_remote_retrieve_body($get));

                    //lightningimport_debugging code
                    //lightningimport_apihelper::lightningimport_writeToLog("Mirror: ".print_r($mirror,true));

                    //Setup the attachement details before insert
                    $attachment = array(
                        'post_title' => $filename,
                        'post_mime_type' => $type,
                    );

                    //Remove any previous attachments associated with this product before inserting the new one
                    //self::lightningimport_DeleteProductAttachments($product_id,$filename);
                    if (file_exists($mirror['file'])) {
                        if (filesize($mirror['file']) > 8000000) {
                            lightningimport_apihelper::lightningimport_writeToLog('File too big resizing');
                            $directory = wp_upload_dir()['path'];
                            $compressedFile = $directory . '/' . $filename . 'reduced.jpg';
                            //lightningimport_apihelper::lightningimport_writeToLog('Destination file path: '.$compressedFile);
                            self::lightningimport_compress_image($mirror['file'], $compressedFile, 90);
                            unlink($mirror['file']);
                            //Insert the attachment post
                            $attach_id = wp_insert_attachment($attachment, $compressedFile, $product_id);
                        } else {
                            //Insert the attachment post
                            $attach_id = wp_insert_attachment($attachment, $mirror['file'], $product_id);
                        }
                    }

                    //lightningimport_apihelper::lightningimport_writeToLog('Got past insert attachment'.lightningimport_apihelper::lightningimport_writeToLog(print_r($attach_id,true)));

                    //Generate metadata for the attachment
                    //lightningimport_apihelper::lightningimport_writeToLog('Starting try catch');

                    if ($attach_data = wp_generate_attachment_metadata($attach_id, $mirror['file']));

                    //lightningimport_apihelper::lightningimport_writeToLog('Got attach meta: '.print_r($attach_data,true));
                    //Update the attachment with metadata
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    //lightningimport_apihelper::lightningimport_writeToLog('Got past insert attachment meta');
                    //Set the attachement as the thumbnail for the product
                }
                if (isset($attach_id) && is_numeric($attach_id)) {
                    //Set the existing or created image to the thumbnail for the product.
                    set_post_thumbnail($product_id, $attach_id);
                    //lightningimport_apihelper::lightningimport_writeToLog('Got past settting the thumbnail');
                    //Set the return value
                    $imageSuccess = true;
                }
            } catch (Exception $e) {

                //Log any error that occurs
                lightningimport_apihelper::lightningimport_writeToLog($e);
                return $imageSuccess;
            }
        }
        //lightningimport_apihelper::lightningimport_writeToLog('lightningimport_SetProductImage for dsid'.$dataSubscriptionID.' : '.print_r($imageSuccess,true));
        return $imageSuccess;
    }

    public static function lightningimport_LinkProductToExistingImage()
    {
        global $wpdb;

        $db_prefix = $wpdb->prefix;
        $customTablesExists = lightningimport_apihelper::lightningimport_AreCustomTablesPresent();

        $productImageQuery = "select p.Id,s.image as Image from " . $db_prefix . "posts p join lightningimport_sku s on p.Id = s.post_id
			where p.post_type='product' and not exists (select 1 from " . $db_prefix . "postmeta where meta_key = '_thumbnail_id' and post_id = p.Id)  and not exists (select 1 from " . $db_prefix . "postmeta where meta_key = 'ImageMissing' and meta_value='1' and post_id = p.Id)
            LIMIT 10000;";
            
        if(!$customTablesExists){
            $productImageQuery = "select p.Id,s.meta_value as Image from " . $db_prefix . "posts p join $wpdb->postmeta s on p.Id = s.post_id and s.meta_key = 'li_image'
			where p.post_type='product' and not exists (select 1 from " . $db_prefix . "postmeta where meta_key = '_thumbnail_id' and post_id = p.Id)  and not exists (select 1 from " . $db_prefix . "postmeta where meta_key = 'ImageMissing' and meta_value='1' and post_id = p.Id)
			LIMIT 10000;";
        }
        lightningimport_apihelper::lightningimport_writeToLog('Running product image query: ' . $productImageQuery);
        $productImages = $wpdb->get_results($productImageQuery, OBJECT);

        //lightningimport_apihelper::lightningimport_writeToLog('Found post for sku?'.print_r($posts,true));
        if (isset($productImages) && count($productImages) > 0) {
            $directory = wp_upload_dir()['path'];
            foreach ($productImages as $productImage) {
                if (isset($productImage->Image) && strlen($productImage->Image) > 4) {
                    $dirs = self::lightningimport_glob_directory_recursive(wp_upload_dir()['basedir'] . '/*');
                    //lightningimport_apihelper::lightningimport_writeToLog('lightningimport_SetProductImage look in dirs: '.print_r($dirs,true));
                    $fileexists = false;

                    $imageFileName = str_replace(' ', '-', $productImage->Image);
                    foreach ($dirs as $dir) {
                        $imageLocation = $dir . '/' . $imageFileName;
                        //lightningimport_apihelper::lightningimport_writeToLog('lightningimport_LinkProductToExistingImage looking for file at: '.$imageLocation);
                        if (file_exists($imageLocation)) {
                            lightningimport_apihelper::lightningimport_writeToLog('lightningimport_LinkProductToExistingImage found the file at: ' . $imageLocation);
                            $fileexists = true;
                            $imageFilePath = $imageLocation;
                            break;
                        }
                    }

                    if ($fileexists) {

                        //Try to get the attachment id before downloading a new copy

                        $query = "SELECT id FROM {$wpdb->posts} WHERE post_title='$productImage->Image' and post_type='attachment' LIMIT 1;";
                        $attach_id = $wpdb->get_var($query);

                        //If the attach_id doesnt exist get the image from the api
                        if (!isset($attach_id) || !is_numeric($attach_id)) {

                            $type = lightningimport_mime_content_type($imageFilePath);
                            lightningimport_apihelper::lightningimport_writeToLog("File type: " . $type);
                            if ($type) {
                                //Setup the attachement details before insert
                                $attachment = array(
                                    'post_title' => $productImage->Image,
                                    'post_mime_type' => $type,
                                );

                                //Insert the attachment post
                                $attach_id = wp_insert_attachment($attachment, $imageFilePath, $productImage->Id);

                                $attach_data = wp_generate_attachment_metadata($attach_id, $imageFilePath);

                                //Update the attachment with metadata
                                wp_update_attachment_metadata($attach_id, $attach_data);
                            }
                        }

                        //Set the attachement as the thumbnail for the product
                        set_post_thumbnail($productImage->Id, $attach_id);
                        update_post_meta($productImage->Id, 'ImageMissing', '0');
                    } else {
                        lightningimport_apihelper::lightningimport_writeToLog("File not found at path");
                        update_post_meta($productImage->Id, 'ImageMissing', '1');
                    }
                } else {
                    lightningimport_apihelper::lightningimport_writeToLog("Image isnt set or is too short");
                    update_post_meta($productImage->Id, 'ImageMissing', '1');
                }
            }
        }
    }

    public static function lightningimport_compress_image($source_url, $destination_url, $quality)
    {
        $info = getimagesize($source_url);

        if ($info['mime'] == 'image/jpeg') {
            $image = imagecreatefromjpeg($source_url);
        } elseif ($info['mime'] == 'image/gif') {
            $image = imagecreatefromgif($source_url);
        } elseif ($info['mime'] == 'image/png') {
            $image = imagecreatefrompng($source_url);
        }

        //save file
        imagejpeg($image, $destination_url, $quality);

        //return destination file
        return $destination_url;
    }

    public static function lightningimport_DeleteProductAttachments($product_id, $imageFilename)
    {
        try {
            //Check if product id is set because this will wipe out all images if it is not
            if (isset($product_id)) {
                //Get all attachments associated with the product and image filename
                $attachments = get_posts(array(
                    'post_type' => 'attachment',
                    'posts_per_page' => -1,
                    'post_status' => 'any',
                    'post_parent' => $product_id,
                    'post_title' => $imageFilename,
                ));

                //Loop through each attachment found and remove them
                foreach ($attachments as $attachment) {
                    if (false === wp_delete_attachment($attachment->ID)) {
                        //Log delete failures
                    }
                }
            }
        } catch (Exception $e) {
            lightningimport_apihelper::lightningimport_writeToLog($e);
        }
        //lightningimport_apihelper::lightningimport_writeToLog('Done completing the delte product attachmetns');
    }

    //Pulls the filename from a disposition header
    public static function lightningimport_GetHeaderFilename($header)
    {
        $tmp_name = explode('=', $header);
        if ($tmp_name[1]) {
            return trim($tmp_name[1], '";\'');
        }

    }

    //Let's the api know it can remove an image from its folder structure
    public static function lightningimport_ConfirmApiImageRemoval($dataDubscriptionID, $sku)
    {
        global $wpdb;

        $db_prefix = $wpdb->prefix;
        $customTablesExists = lightningimport_apihelper::lightningimport_AreCustomTablesPresent();
        //Get the image name from the sku
        // $getImageName = "select pmi.meta_value as Image from ".$db_prefix."posts p join ".$db_prefix."postmeta pmi on p.Id = pmi.post_id join ".$db_prefix."postmeta pms on p.Id = pms.post_id
        // where p.post_type='product' and pmi.meta_key = 'Image' and pms.meta_key = '_sku' and pms.meta_value = '".$sku."' LIMIT 1;";

        $getImageName = $wpdb->prepare("select image from lightningimport_sku where sku = %s;", $sku);
        if(!$customTablesExists){
            $getImageName = $wpdb->prepare("select image.meta_value from $wpdb->postmeta sku 
            join $wpdb->postmeta image on sku.post_id = image.post_id and image.meta_key = 'li_image' 
            where sku.meta_key = 'li_sku' and sku.meta_value = %s;", $sku);
        }
        $imageResult = $wpdb->get_var($getImageName);

        $imageName = str_replace(' ', '-', trim($imageResult));

        //Check if there are any other existing products that use this image but do not have it set
        // $otherProductsQuery = "select count(1) as otherProductCount from ".$db_prefix."posts p join ".$db_prefix."postmeta pm on p.Id = pm.post_id
        // where p.post_type='product' and pm.meta_key = 'Image' and not exists (select 1 from ".$db_prefix."postmeta where meta_key = '_thumbnail_id' and post_id = p.Id)
        // and pm.meta_value ='".$imageName."'
        // LIMIT 2;";

        // $otherProducts = $wpdb->get_var($otherProductsQuery);
        // lightningimport_apihelper::lightningimport_writeToLog("Other products found: ".print_r($otherProducts,true));
        //Only run the confirm removal if there are not other products we still need to get
        //if($otherProducts<1){

        lightningimport_apihelper::lightningimport_writeToLog("Mark image received: " . lightningimport_apihelper::lightningimport_apiurl() . "api/productdata/deleteimage/" . $dataDubscriptionID . "?sku=" . $sku);
        //HIt api endpoint to let the api know its good to remove the product image
        $data = lightningimport_apihelper::lightningimport_executeHttp(lightningimport_apihelper::lightningimport_apiurl() . "api/productdata/deleteimage/" . $dataDubscriptionID . "?sku=" . $sku, 'GET', lightningimport_apihelper::lightningimport_GetCurrentAPIToken());
        //}
    }

    public static function lightningimport_glob_directory_recursive($pattern, $flags = 0)
    {
        $dirs = glob($pattern, GLOB_ONLYDIR | GLOB_NOSORT);
        //lightningimport_apihelper::lightningimport_writeToLog("glob pattern: ".$pattern." returned: ".print_r($dirs,true));

        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {

            $dirs = array_merge($dirs, self::lightningimport_glob_directory_recursive($dir . '/' . basename($pattern), GLOB_ONLYDIR | GLOB_NOSORT));

        }

        return $dirs;
    }
    //End Image processing
}

if (!function_exists('lightningimport_mime_content_type')) {

    function lightningimport_mime_content_type($filename)
    {

        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $filenamearray = explode('.', $filename);

        $ext = strtolower(array_pop($filenamearray));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        } else {
            return 'application/octet-stream';
        }
    }
}