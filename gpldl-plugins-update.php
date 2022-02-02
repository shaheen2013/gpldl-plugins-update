<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.linkedin.com/in/saidul-alam-6697591b5/
 * @since             1.0.0
 * @package           Gpldl_Plugins_Update
 *
 * @wordpress-plugin
 * Plugin Name:       GPLDL Plugins Update
 * Plugin URI:
 * Description:       Codibu.com - Auto plugin update / Only works with plugins in Codibu's free plugin list.
 * Version:           1.0.0
 * Author:            mediusware
 * Author URI:        https://mediusware.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gpldl-plugins-update
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('GPLDL_PLUGINS_UPDATE_VERSION', '1.0.0');

//this action callback is triggered when wordpress is ready to add new items to menu.
add_action("admin_menu", "add_new_menu_items");

function add_new_menu_items()
{
    //add a new menu item. This is a top level menu item i.e., this menu item can have sub menus
    add_menu_page(
        "Update Plugins", //Required. Text in browser title bar when the page associated with this menu item is displayed.
        "Update Plugins", //Required. Text to be displayed in the menu.
        "manage_options", //Required. The required capability of users to access this menu item.
        "update-plugins", //Required. A unique identifier to identify this menu item.
        "theme_options_page", //Optional. This callback outputs the content of the page associated with this menu item.
        "", //Optional. The URL to the menu item icon.
        100 //Optional. Position of the menu item in the menu.
    );

}

function theme_options_page()
{
    $is_auto_update_on = get_option('is_auto_update_on');
    ?>

    <div class=wrap>
        <h1>Update Gpldl Plugins</h1>
        <p>Codibu.com - Auto plugin update / Only works with plugins in Codibu's free plugin list</p>
        <form action="<?php echo plugin_dir_url(__FILE__) . 'plugin-update.php' ?>" method="POST" class="ajax">
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row">Auto update</th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span>Auto update</span></legend>
                            <label for="gpldl_auto_update">
                                <input name="gpldl_auto_update" type="checkbox" id="gpldl_auto_update" <?php echo  $is_auto_update_on ? 'checked' : ''; ?>>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                </tbody>
            </table>
            <span class="spinner" style="float: none"></span>
            <?php
            if (! $is_auto_update_on) {
                submit_button('Update Now', 'primary', 'submit','submit', true, $is_auto_update_on? 'disabled' : '');
            }
            ?>
        </form>
    </div>

    <script>
        jQuery(document).ready(function ($) {
            const gpldlPluginUpdateEndpoint = "<?php echo plugin_dir_url(__FILE__) . 'plugin-update.php' ?>";

            $('#gpldl_auto_update').change(function (e) {
                const isAutoUpdateOn = $('#gpldl_auto_update').is(':checked');
                // TODO: Save this settings to database and use it on gpldl scheduler
                $.ajax({
                    url: gpldlPluginUpdateEndpoint,
                    type: "POST",
                    dataType: 'JSON',
                    data: {
                        is_auto_update_on: isAutoUpdateOn
                    },
                    success: (response) => {
                        location.reload();
                    },
                    error: (data) => {
                        $(".error_msg").css("display", "block");
                    }
                });
            })

            $('form.ajax').on('submit', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(".spinner").addClass("is-active");
                $('#submit, #gpldl_auto_update').attr('disabled','disabled');
                $.ajax({
                    url: gpldlPluginUpdateEndpoint,
                    type: "POST",
                    dataType: "JSON",
                    data: {
                        action: 'update_gpldl_plugin_now',
                    },
                    success: function (response) {
                        $(".spinner").removeClass("is-active");
                        $('#submit, #gpldl_auto_update').removeAttr('disabled');
                        $(".success_msg").css("display", "block");
                    },
                    error: function (data) {
                        $(".spinner").removeClass("is-active");
                        $('#submit, #gpldl_auto_update').removeAttr('disabled');
                        $(".error_msg").css("display", "block");
                    }
                });
                $('.ajax')[0].reset();
            });

        });
    </script>
    <?php
}

if( !function_exists('get_plugin_data') ){
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

if ( ! wp_next_scheduled( 'gpldl_plugin_update_hook' ) && $is_auto_update_on) {
    wp_schedule_event( time(), 'weekly', 'gpldl_plugin_update_hook' );
}

add_action('gpldl_plugin_update_hook', 'gpldl_plugins_update_exec');
/**
 * update all plugins from api list.
 */
function gpldl_plugins_update_exec() {
    $results     = fetch_api();
    if (count($results) > 0) {
        foreach ($results as $index => $item) {
            $gpldl_plugin_path = gpldl_plugin($item->name);

            if ($gpldl_plugin_path){
                deactivate_old_version($gpldl_plugin_path);
            }

            $pluginZipDownloadDestination = "/bitnami/wordpress/wp-content/plugins/" . basename($item->download_url);
            $pluginExtractBaseDestination = "/bitnami/wordpress/wp-content/plugins/";
            $downloadUrl = $item->download_url;

            exec("curl --create-dirs --output {$pluginZipDownloadDestination} {$downloadUrl}");
            exec("unzip -o {$pluginZipDownloadDestination} -d {$pluginExtractBaseDestination}");
            exec("rm -rf {$pluginZipDownloadDestination}");

            if ($gpldl_plugin_path){
                activate_new_version($gpldl_plugin_path);
            }
        }
    }
}

function fetch_api() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://codibu.com/api/download?client_root=be.codibu.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $result = json_decode($response);
    curl_close($ch);

    return $result;
}

/**
 * @param $name
 * @return mixed
 */
function gpldl_plugin($name){
    foreach (get_option('active_plugins') as $item) {
        $plugin_details = get_plugin_data(plugin_dir_url( '/'). $item);
        if ($name == $plugin_details['Name']){
            return $item;
        }
    }
    return false;
}

if ( ! wp_next_scheduled( 'gpldl_plugin_update_hook' ) ) {
    wp_schedule_single_event( time(), 'gpldl_plugin_update_hook' );
}
/**
 * @param $gpldl_plugin_path
 */
function deactivate_old_version($gpldl_plugin_path) {
    if ( is_plugin_active($gpldl_plugin_path) ) {
        deactivate_plugins($gpldl_plugin_path);
    }
}

/**
 * @param $gpldl_plugin_path
 */
function activate_new_version($gpldl_plugin_path) {
    if ( !is_plugin_active($gpldl_plugin_path) ) {
        activate_plugin($gpldl_plugin_path);
    }
}


