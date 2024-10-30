<?php

include_once dirname(__FILE__) . '/lightningimport-apihelper.php';

//Add actions and filters for settings menus

add_action('admin_menu', 'lightningimport_lightningimportTopLevelMenuSetup');

add_action('admin_menu', 'lightningimport_lightningimportSettingsSetup');

add_filter('plugin_action_links', 'lightningimport_SettingsLink', 2, 2);

add_action('admin_init', 'lightningimport_SettingsInit');

class lightningimport_admin
{

    //Easy way to turn lightningimport_debugging on and off

    public static function lightningimport_debug()
    {

        return false;

    }

    //Initialze the settings menu

    public static function lightningimport_start()
    {

        add_action('admin_menu', 'lightningimport_admin::lightningimport_adminMenu');

    }

    //Setup the settings menu and restrict access to it

    public static function lightningimport_adminMenu()
    {

        $user = wp_get_current_user(); //role support

        $current_role = $user->roles;

        //Restrict menu based on user and role

        if (is_admin() && (count($current_role) > 0 || current_user_can('manage_options'))) {

            $page = add_menu_page('Lightning Import', 'Lightning Import', current($current_role), 'lightningimport', 'lightningimport_admin::lightningimport_mainPage', 'dashicons-randomize', '63.154');

        }

        //Initialize the scripts for settings menu

        add_action('admin_print_scripts-' . $page, 'lightningimport_admin::lightningimport_initJs'); //lightningimport_start javascripts function

        add_action('admin_print_styles-' . $page, 'lightningimport_admin::lightningimport_initCss'); //lightningimport_start css function

    }   

    //Removes the customized search file from the theme directory to allow the default woocommerce search to work correctly.

    public static function lightningimport_deactivateCustomSearch()
    {

        $customsearchformfile = $theme_dir = get_stylesheet_directory() . '/product-searchform.php';

        if (file_exists($customsearchformfile)) {

            unlink($customsearchformfile);

        }

    }

    //Function to initialize js scripts

    public static function lightningimport_initJs()
    {

        //Register the jquery scripts

        wp_enqueue_script('jquery');

        //Localize the scripts

        $strings = array(

            'error' => __('Could not establish connection with the server. Check permissions.'),

            'done' => __('Success'),

            'lightningimport_start' => __('lightningimport_start'),

        );

        wp_localize_script('lightningimport_script', 'strings', $strings);

        //Register the plugin scripts

        wp_enqueue_script('lightningimport_script', plugins_url('../js/lightningimport-js.js', __FILE__), array('jquery'), true);

    }

    //Function to initialize the CSS

    public static function lightningimport_initCss()
    {

        //Register the CSS styles

        wp_register_style('lightningimport-css', plugins_url('../css/lightningimport-css.css', __FILE__));

        wp_enqueue_style('lightningimport-css');

    }

    //Display the content for the settings page

    public static function lightningimport_mainPage()
    {

        echo '<div id="lightningImport" class="wrap">';

        echo '<div id="csv_warning" style="display:none" class="updated"></div>';

        self::lightningimport_mainPageContent();

        echo '</div>';

    }

    public static function lightningimport_mainPageContent()
    {

        ?>



	</div>

	<?php

    }

    public static function lightningimport_RunRequirementCheck()
    {

        $requirementCheck = lightningimport_CheckForRequiredComponents();

        //error_log('Ran req check: '.print_r($requirementCheck,true));

        if (!$requirementCheck['Success']) {

            if (!$requirementCheck['APIAccess']) {

                add_action('admin_notices', 'lightningimport_APIAccessError');

            } else {

                remove_action('admin_notices', 'lightningimport_APIAccessError');

            }

        } else {

            remove_action('admin_notices', 'lightningimport_APIAccessError');

        }

    }

}

function lightningimport_lightningimportTopLevelMenuSetup()
{

    // Add a new top-level menu (ill-advised):

    add_menu_page(__('Lightning Import', 'lightningimport-menu'), __('Lightning Import', 'lightningimport-menu'), 'manage_options', 'lightningimport', 'lightningimport_lightningimportTopLevelPage');

}

//Displays content for top level menu

function lightningimport_lightningimportTopLevelPage()
{

    echo "<h2>" . __('Lightning Import', 'lightningimport-menu') . "</h2>";

}

//Register the menu items

function lightningimport_lightningimportSettingsSetup()
{

    //Add the menu page

    add_submenu_page(

        'lightningimportTopLevelHandle',

        'Lightning Import Plugin Settings',

        'Settings',

        'manage_options',

        'lightningimport',

        'lightningimport_lightningimportSettingsAdminPage'

    );

}

//Show the menu page

function lightningimport_lightningimportSettingsAdminPage()
{

    global $submenu;

    //Loop through the menu options and get the plugin specific options

    $page_data = array();

    foreach ($submenu['options-general.php'] as $i => $menu_item) {

        if ($submenu['options-general.php'][$i][2] == 'lightningimport') {
            $page_data = $submenu['options-general.php'][$i];
        }

    }

    ?>

<div id="lightningImport" class="wrap">

    <?php screen_icon();?>

	<h2><?php //echo$page_data[3];?></h2>

    <form id="lightningimport_lightningimportOptions" action="options.php" method="post">

        <?php

    settings_fields('lightningimport_lightningimportOptions');

    do_settings_sections('lightningimport');

    submit_button('Save options', 'primary', 'lightningimport_lightningimportOptionsSubmit');

    echo lightningimport_UploadNewFileLink();
    if (lightningimport_apihelper::lightningimport_debug()) {
        submit_button('Delete Log File', 'delete', 'lightningimport_OptionsDeleteLog');
        submit_button('Email Log File', 'email', 'lightningimport_OptionsEmailLog');
    }
    ?>

	</form>

</div>

<?php

}

//Returns an anchor tag for uploading a file to import

function lightningimport_UploadNewFileLink()
{

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_Username' => '', 'lightningimport_Password' => ''));

    if (lightningimport_admin::lightningimport_debug()) {

        //echo print_r($options,true);

        //error_log(print_r($options,true));

    }

    $apiUsername = '';

    $apiPassword = '';

    if (isset($options['lightningimport_Username'])) {

        $apiUsername = $options['lightningimport_Username'];

    }

    if (isset($options['lightningimport_Password'])) {

        $apiPassword = $options['lightningimport_Password'];

    }

    if (isset($options['lightningimport_APILoginSuccess'])) {

        if ($options['lightningimport_APILoginSuccess'] == true) {

            return '<br/><a class="button" target="_blank" href="' . lightningimport_apihelper::lightningimport_apiurl() . 'Account/PluginLogin/?username=' . $apiUsername . '&password=' . $apiPassword . '&returnUrl=~/Dashboard/">Click here to upload a data import zip file!</a>';

        }

    }

    //return '<br/><a class="button" target="_blank" href="' . lightningimport_apihelper::lightningimport_apiurl() . 'Account/Register/">Register to upload data files for import!</a>';

}

//Returns an anchor tag for upgrading or to go to registration if the username and password is not entered yet.

function lightningimport_RegisterLink()
{

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_Username' => ''));

    if (lightningimport_admin::lightningimport_debug()) {

        //echo print_r($options,true);

        //error_log(print_r($options,true));

    }

    $apiUsername = '';

    $apiPassword = '';

    if (isset($options['lightningimport_Username'])) {

        $apiUsername = $options['lightningimport_Username'];

    }

    if (isset($options['lightningimport_Password'])) {

        $apiPassword = $options['lightningimport_Password'];

    }

    if (isset($options['lightningimport_APILoginSuccess'])) {

        if ($options['lightningimport_APILoginSuccess'] == true) {

            //return '<br/><a target="_blank" href="'.lightningimport_apihelper::lightningimport_apiurl().'Account/Upgrade/?username='.$apiUsername.'&password='.$apiPassword.'">Upgrade to access custom import features!</a>';

        }

    }

    return '<br/><a class="button" target="_blank" href="' . lightningimport_apihelper::lightningimport_apiurl() . 'Account/Register/">Register to get started!</a>';

}

//Add settings button to plugin management screen

function lightningimport_SettingsLink($actions, $file)
{

    if (false !== strpos($file, 'lightningimport')) {
        $actions['settings'] = '<a href="options-general.php?page=lightningimport">Settings</a>';
    }

    return $actions;

}

//Register the settings sections and settings

function lightningimport_SettingsInit()
{

    //Initialize the css and js

    lightningimport_admin::lightningimport_initCss();

    lightningimport_admin::lightningimport_initJs();

    //Register the settings

    register_setting(

        'lightningimport_lightningimportOptions',

        'lightningimport_lightningimportOptions',

        'lightningimport_OptionsValidate'

    );

    //Add the section to the settings

    add_settings_section(

        'lightningimport_lightningimportOptionsSettings',

        'Lightning Import Plugin Options',

        'lightningimport_SettingsDescription',

        'lightningimport'

    );

        //Add the APILoginSuccess to the settings

        add_settings_section(

            'lightningimport_APILoginSuccess',
    
            '',
    
            'lightningimport_APILoginSuccess',
    
            'lightningimport',
    
            'lightningimport_lightningimportOptionsSettings'
    
        );
    
        // //Add the APILoginSuccess to the settings
    
        // add_settings_field(
    
        //     'lightningimport_APILoginSuccessMessage',
    
        //     '',
    
        //     'lightningimport_APILoginSuccessMessage',
    
        //     'lightningimport',
    
        //     'lightningimport_lightningimportOptionsSettings'
    
        // );
    
     //Add the Username to the settings

     add_settings_field(

        'lightningimport_Username',

        'Username',

        'lightningimport_Username',

        'lightningimport',

        'lightningimport_lightningimportOptionsSettings'

    );

    //Add the Password to the settings

    add_settings_field(

        'lightningimport_Password',

        'Password',

        'lightningimport_Password',

        'lightningimport',

        'lightningimport_lightningimportOptionsSettings'

    );


    //Add lightningimport_debugging to the settings

    add_settings_field(

        'lightningimport_debug',

        'Debugging Mode',

        'lightningimport_debug',

        'lightningimport',

        'lightningimport_lightningimportOptionsSettings'

    );

    //Add the toggle to the settings

    add_settings_field(

        'lightningimport_ScanToggle',

        'Toggle Product Import',

        'lightningimport_ScanToggleInput',

        'lightningimport',

        'lightningimport_lightningimportOptionsSettings'

    );

    //Add the toggle to the settings

    add_settings_field(

        'lightningimport_SearchWidgetToggle',

        'Toggle Custom Search Widget',

        'lightningimport_SearchWidgetToggle',

        'lightningimport',

        'lightningimport_lightningimportOptionsSettings'

    );


}

//Validate the input of each of the settings

function lightningimport_OptionsValidate($input)
{

    global $allowedposttags, $allowedrichhtml;

    if (array_key_exists('lightningimport_OptionsDeleteLog', $_POST) && $_POST['lightningimport_OptionsDeleteLog'] == 'Delete Log File') {

        lightningimport_apihelper::lightningimport_deleteLogFile();

    }

    if (array_key_exists('lightningimport_OptionsEmailLog', $_POST) && $_POST['lightningimport_OptionsEmailLog'] == 'Email Log File') {

        lightningimport_SendPluginLogToDeveloper();

    }

    if (isset($input['lightningimport_Username'])) {

        $input['lightningimport_Username'] = wp_kses_post($input['lightningimport_Username']);

    }

    if (isset($input['lightningimport_Password'])) {

        $input['lightningimport_Password'] = wp_kses_post($input['lightningimport_Password']);

    }

    //If both username and password are both set send to the api to confirm the credentials or register a new account

    if (isset($input['lightningimport_Username']) && isset($input['lightningimport_Password'])) {

        if (!ctype_space($input['lightningimport_Username']) && !$input['lightningimport_Username'] == '' && !$input['lightningimport_Username'] == false && !ctype_space($input['lightningimport_Password']) && !$input['lightningimport_Password'] == '' && !$input['lightningimport_Password'] == false) {
            $result = lightningimport_apihelper::lightningimport_SendCredentialstoAPI($input['lightningimport_Username'],$input['lightningimport_Password']);
            //error_log(print_r($result,true));

            $input['lightningimport_APILoginSuccess'] = $result['success'];

            $input['lightningimport_APILoginSuccessMessage'] = $result['message'];

        }

    }

    //May need to revise once api call to pinger is setup

    if (isset($input['lightningimport_ScanToggle'])) {

        //Set the value of this setting to "1"

        $input['lightningimport_ScanToggle'] = wp_kses_post($input['lightningimport_ScanToggle']);

    } else {

        //Set the value to "0"

        $input['lightningimport_ScanToggle'] = "0";

    }

    if (isset($input['lightningimport_SearchWidgetToggle'])) {

        //Set the value of this setting to "1"

        $input['lightningimport_SearchWidgetToggle'] = wp_kses_post($input['lightningimport_SearchWidgetToggle']);

    } else {

        //Set the value to "0"

        $input['lightningimport_SearchWidgetToggle'] = "0";

        lightningimport_admin::lightningimport_deactivateCustomSearch();

    }

    return $input;

}

function lightningimport_CheckForRequiredComponents()
{
    

    $requiredComponents['APIAccess'] = lightningimport_apihelper::lightningimport_APIAccessCheck();

    $requiredComponents['Success'] = false;

    if ($requiredComponents['APIAccess'] == true) {

        $requiredComponents['Success'] = true;

    }

    return $requiredComponents;

}

function lightningimport_APIAccessError()
{

    $class = 'notice notice-error is-dismissible';

    $message = __('<h4>Lightning Import</h4>It appears that your server, network, or hosting provider configuration does not allow you to access our data processing server.', 'APIAccessDomain');

    printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);

}

//Display the section for the scan toggle

function lightningimport_ScanToggleInput()
{

    $upload_dir = wp_upload_dir();

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_ScanToggle' => "1"));

    $lightningimport_ScanToggle = (isset($options['lightningimport_ScanToggle'])) ? $options['lightningimport_ScanToggle'] : "1";

    //var_dump($options);

    ?>

<!-- <p>Click to toggle import on or off. (You must click save after setting the toggle.)</p>

<br /> -->

<div class="onoffswitch" id="ScanToggleDiv">

	<input type="checkbox" name="lightningimport_lightningimportOptions[lightningimport_ScanToggle]" class="onoffswitch-checkbox" id="lightningimport_ScanToggle" <?php if ($lightningimport_ScanToggle == "1") {echo "checked";}?> value="<?php echo $lightningimport_ScanToggle

    ?>">

	<label class="onoffswitch-label" for="myonoffswitch">

		<span class="onoffswitch-inner"></span>

		<span class="onoffswitch-switch"></span>

	</label>

</div>

<?php

}

//Display the section for the scan toggle

function lightningimport_SearchWidgetToggle()
{

    $upload_dir = wp_upload_dir();

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_SearchWidgetToggle' => "0"));

    $lightningimport_SearchWidgetToggle = (isset($options['lightningimport_SearchWidgetToggle'])) ? $options['lightningimport_SearchWidgetToggle'] : "0";

    //var_dump($options);

    ?>

<!-- <p>Click to toggle the custom search widget functionality. (You must click save after setting the toggle. When activated this will override the default Woocommerce Search Widget.)</p>

<br /> -->

<div class="onoffswitch" id="SearchToggleDiv">

	<input type="checkbox" name="lightningimport_lightningimportOptions[lightningimport_SearchWidgetToggle]" class="onoffswitch-checkbox" id="lightningimport_SearchWidgetToggle" <?php if ($lightningimport_SearchWidgetToggle == "1") {echo "checked";}?> value="<?php echo $lightningimport_SearchWidgetToggle

    ?>">

	<label class="onoffswitch-label" for="myonoffswitch">

		<span class="onoffswitch-inner"></span>

		<span class="onoffswitch-switch"></span>

	</label>

</div>

<p> Note: When activated this will override the default Woocommerce Search Widget.</p>

<?php

}

//Display the section for the scan toggle

function lightningimport_debug()
{

    $upload_dir = wp_upload_dir();

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_debug' => "0"));

    $lightningimport_debug = (isset($options['lightningimport_debug'])) ? $options['lightningimport_debug'] : "0";

    $lightningimport_Username = esc_textarea($lightningimport_Username); //sanitise output
    //var_dump($options);

    ?>
<!-- 
<p>Click to toggle debugging on or off. (You must click save after setting the toggle.)</p>

<br /> -->

<div class="onoffswitch" id="lightningimport_debugToggleDiv">

	<input type="checkbox" name="lightningimport_lightningimportOptions[lightningimport_debug]" class="onoffswitch-checkbox" id="lightningimport_debug" <?php if ($lightningimport_debug == "1") {echo "checked";}?> value="<?php echo $lightningimport_debug

    ?>">

	<label class="onoffswitch-label" for="myonoffswitch">

		<span class="onoffswitch-inner"></span>

		<span class="onoffswitch-switch"></span>

	</label>

</div>

<?php

}

//Display the section for the Username

function lightningimport_Username()
{

    $upload_dir = wp_upload_dir();

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_Username' => ''));

    $lightningimport_Username = (isset($options['lightningimport_Username'])) ? $options['lightningimport_Username'] : '';

    $lightningimport_Username = esc_textarea($lightningimport_Username); //sanitise output

    ?>

<!-- <p>Enter your Username for the Lightning Import Data Process Service.</p>

<br /> -->

<input type="text" id="lightningimport_Username" name="lightningimport_lightningimportOptions[lightningimport_Username]" placeholder="Enter your Username Here" cols="50" rows="5" class="large-text code" value="<?php echo $lightningimport_Username; ?>">

</input>

<?php

}

//Display the section for the Password

function lightningimport_Password()
{

    $upload_dir = wp_upload_dir();

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_Password' => ''));

    $lightningimport_Password = (isset($options['lightningimport_Password'])) ? $options['lightningimport_Password'] : '';

    $lightningimport_Password = esc_textarea($lightningimport_Password); //sanitise output

    ?>
<!-- 
<p>Enter your Password for the Lightning Import Data Process Service.</p>

<br /> -->

<input type="password" id="lightningimport_Password" name="lightningimport_lightningimportOptions[lightningimport_Password]" placeholder="Enter your Password Here" cols="50" rows="5" class="large-text code" value="<?php echo $lightningimport_Password; ?>">

</input>

<br/><a target="_blank" href="<?php echo lightningimport_apihelper::lightningimport_apiurl() ?>Account/ForgotPassword/">Forgot Password ?</a>
<br/>
<?php    
        if (!isset($options['lightningimport_APILoginSuccessMessage']) || is_null($options['lightningimport_APILoginSuccessMessage']) || $options['lightningimport_APILoginSuccessMessage'] == false) {
            echo lightningimport_RegisterLink();
        }    
}

function lightningimport_APILoginSuccess()
{

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_APILoginSuccess' => true));

    //echo "The login success was: ".$options['lightningimport_APILoginSuccess'];

    //If there was an issue with the credentials display this error message.

    if (isset($options['lightningimport_APILoginSuccess'])) {

        if ($options['lightningimport_APILoginSuccess'] == false) {

            $errorMessage = '<p style="color:red;"><strong>There was an issue with your username or password. Please reenter your credentials to try again.</strong><p>';

            $customErrorMessage = lightningimport_APILoginSuccessMessage();

            if ($customErrorMessage != '') {

                $errorMessage = '<p style="color:red;"><strong>' . $customErrorMessage . '</strong></p>';

            }

            echo $errorMessage;

        } else if ($options['lightningimport_APILoginSuccess'] == true) {

            $customMessage = lightningimport_APILoginSuccessMessage();

            if ($customMessage != '') {

                echo '<p style="color:green;"><strong> ' . $customMessage . '</strong></p>';

            }

            //$options['lightningimport_APILoginSuccessMessage']='';

            //echo print_r($options,true);

            //update_option('lightningimport_lightningimportOptions',$options);

        }

    }

}

function lightningimport_APILoginSuccessMessage()
{

    $options = get_option('lightningimport_lightningimportOptions', array('lightningimport_APILoginSuccessMessage' => ''));

    if (isset($options['lightningimport_APILoginSuccessMessage'])) {

        if (!$options['lightningimport_APILoginSuccessMessage'] == false) {

            return $options['lightningimport_APILoginSuccessMessage'];

        }

    }

    return '';

}

//Display the summary for the settings page

function lightningimport_SettingsDescription()
{

    echo "<p>Enter your API username and password. You may toggle scheduled product sync on or off here.</p>";

}

//Send log file to developer
function lightningimport_SendPluginLogToDeveloper()
{

    $to = 'support@lightningimport.com';
    $subject = date("D M d, Y G:i").' Plugin Log from: '.get_site_url();
    $body = date("D M d, Y G:i").' Plugin Log from: '.get_site_url();
    $headers = array('Content-Type: text/html; charset=UTF-8');    

    // For attachment 
    $attachments = array(lightningimport_apihelper::lightningimport_logFilePath());    

    wp_mail( $to, $subject, $body, $headers, $attachments );

    lightningimport_apihelper::lightningimport_writeToLog('Tried to send email log');

}

?>