<?php

require '/opt/bitnami/wordpress/wp-load.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    header('Content-Type: application/json');

    if (isset($_POST['action']) && $_POST['action'] == 'update_gpldl_plugin_now') {

        if ( ! wp_next_scheduled( 'gpldl_plugin_update_hook' ) ) {
            wp_schedule_single_event( time(), 'gpldl_plugin_update_hook' );
        }
        //gpldl_plugins_update_exec();
        echo json_encode(['status' => 'OK']);
        return;
    }

    if (isset($_POST['is_auto_update_on'])) {
        $is_auto_update_on = $_POST['is_auto_update_on'] == 'false' ? 0 : 1;

        if (get_option('is_auto_update_on') !== null) {
            update_option('is_auto_update_on', $is_auto_update_on);
        }else{
            add_option( 'is_auto_update_on',$is_auto_update_on, '', $autoload = 'yes' );
        }
    }

    echo json_encode(['status' => 'OK']);
}
echo '<pre>'; print_r( _get_cron_array() ); echo '</pre>';