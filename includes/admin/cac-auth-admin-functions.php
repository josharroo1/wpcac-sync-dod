<?php
/**
 * CAC Authentication Admin Functions
 */

// Add custom registration fields section
function cac_auth_custom_fields_section_callback() {
    echo '<p>Add custom fields to the CAC registration form.</p><p>For "select" field types, enter select options as a comma separated list OR upload a CSV file with "key" & "value" columns.</p><p><a href="#">Download Example CSV</a></p>';
}

// Render custom registration fields
function cac_auth_render_custom_fields() {
    $custom_fields = get_option('cac_auth_registration_fields', array());
    if (!is_array($custom_fields)) {
        $custom_fields = array();
    }

    ?>
    <div class="form-information">To display the CAC registration form on a page or post, use the following shortcode: <code>[cac_registration]</code></div>
    <div class="form-information">Users will fill out the form, including any custom fields you have defined below, and register using their CAC credentials.</div>
    <div class="form-information"><strong>An orginzation email is always required</strong></div>
    <div class="csv-information">For select fields, add options as a comma-separated list, or a CSV file upload with "key" & "value" columns.</div>
    <table class="cac-auth-custom-fields">
        <thead>
            <tr>
                <th>Field Label</th>
                <th>Field Type</th>
                <th>Options (for select field)</th>
                <th>CSV File (for select field)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($custom_fields as $field_id => $field_data) : ?>
                <?php
                if (!is_array($field_data)) {
                    $field_data = array(
                        'label' => '',
                        'type' => 'text',
                        'options' => '',
                    );
                }
                // Retrieve the CSV file information
                $csv_file = get_option('cac_auth_csv_file_' . $field_id, '');
                ?>
                <tr>
                    <td><input type="text" name="cac_auth_registration_fields[<?php echo esc_attr($field_id); ?>][label]" value="<?php echo esc_attr($field_data['label']); ?>"></td>
                    <td>
                        <select name="cac_auth_registration_fields[<?php echo esc_attr($field_id); ?>][type]">
                            <option value="text" <?php selected($field_data['type'], 'text'); ?>>Text</option>
                            <option value="number" <?php selected($field_data['type'], 'number'); ?>>Number</option>
                            <option value="select" <?php selected($field_data['type'], 'select'); ?>>Select</option>
                        </select>
                    </td>
                    <td><input type="text" name="cac_auth_registration_fields[<?php echo esc_attr($field_id); ?>][options]" value="<?php echo esc_attr($field_data['options']); ?>" placeholder="Options (comma-separated)" class="cac-auth-options-input <?php echo $field_data['type'] !== 'select' ? 'disabled' : ''; ?>"></td>
                    <td>
                        <input type="file" name="cac_auth_registration_fields[<?php echo esc_attr($field_id); ?>][csv_file]" accept=".csv" class="cac-auth-options-input <?php echo $field_data['type'] !== 'select' ? 'disabled' : ''; ?>">
                        <?php if (!empty($csv_file)) : ?>
                            <span class="small-desc">
                                Current file: <?php echo esc_html($csv_file); ?>
                                <button type="button" class="button button-small cac-auth-remove-csv" data-field-id="<?php echo esc_attr($field_id); ?>">&times;</button>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><button type="button" class="button button-secondary cac-auth-remove-field">Remove</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button type="button" class="button button-secondary cac-auth-add-field">Add Field</button>
    <?php
}

// Save custom registration fields
function cac_auth_save_custom_fields($options) {
    error_log('Entering cac_auth_save_custom_fields function');
    error_log(print_r($options, true));
    error_log(print_r($_FILES, true));

    if (isset($_POST['cac_auth_registration_fields'])) {
        error_log('$_POST[cac_auth_registration_fields] is set');

        $custom_fields = array();
        foreach ($_POST['cac_auth_registration_fields'] as $field_id => $field_data) {
            error_log('Processing field ID: ' . $field_id);

            $field_label = sanitize_text_field($field_data['label']);
            $field_type = sanitize_text_field($field_data['type']);
            $field_options = sanitize_text_field($field_data['options']);

            // Check if a new CSV file is uploaded
            if ($field_type === 'select' && isset($_FILES['cac_auth_registration_fields']['name'][$field_id]['csv_file']) && !empty($_FILES['cac_auth_registration_fields']['name'][$field_id]['csv_file'])) {
                error_log('Processing CSV file for field ID: ' . $field_id);

                $csv_file_name = $_FILES['cac_auth_registration_fields']['name'][$field_id]['csv_file'];
                if (!empty($csv_file_name)) {
                    // Generate a unique file name to prevent collisions
                    $unique_file_name = $field_id . '_' . sanitize_file_name($csv_file_name);
                    $upload_dir = wp_upload_dir();
                    $target_dir = trailingslashit($upload_dir['basedir']) . 'cac-auth-csv-files/';
                    $target_file = $target_dir . $unique_file_name;

                    if (!file_exists($target_dir)) {
                        wp_mkdir_p($target_dir);
                    }

                    if (move_uploaded_file($_FILES['cac_auth_registration_fields']['tmp_name'][$field_id]['csv_file'], $target_file)) {
                        // Store the CSV file information separately
                        update_option('cac_auth_csv_file_' . $field_id, $unique_file_name);
                        error_log('File uploaded successfully: ' . $target_file);
                    } else {
                        error_log('Failed to move uploaded file: ' . $csv_file_name);
                    }
                }
            }

            $custom_fields[$field_id] = array(
                'label' => $field_label,
                'type' => $field_type,
                'options' => $field_options,
            );
        }

        $options = $custom_fields;
        error_log(print_r($options, true));
    } else {
        error_log('$_POST[cac_auth_registration_fields] is not set');
    }

    error_log('Leaving cac_auth_save_custom_fields function');
    return $options;
}

add_filter('cac_auth_settings_sanitize', 'cac_auth_save_custom_fields');

// Enqueue admin scripts
function cac_auth_admin_enqueue_scripts($hook) {
    if ('settings_page_cac-auth-settings' !== $hook) {
        return;
    }
    wp_enqueue_script('wp-color-picker');

    // Enqueue color picker styles
    wp_enqueue_style('wp-color-picker');

    // Initialize color picker
    $script = '
        jQuery(document).ready(function($) {
            $(".cac-color-picker").wpColorPicker();
        });
    ';
    wp_add_inline_script('wp-color-picker', $script);
    
    wp_enqueue_style('cac-auth-styles', CAC_AUTH_PLUGIN_URL . 'includes/assets/css/cac-admin-style.css', array(), CAC_AUTH_PLUGIN_VERSION);
    wp_enqueue_script('cac-auth-admin', CAC_AUTH_PLUGIN_URL . 'includes/assets/js/cac-auth-admin.js', array('jquery'), CAC_AUTH_PLUGIN_VERSION, true);
    
}
add_action('admin_enqueue_scripts', 'cac_auth_admin_enqueue_scripts');

// User Meta from CAC Registration
add_action('show_user_profile', 'cac_show_additional_user_meta');
add_action('edit_user_profile', 'cac_show_additional_user_meta');

function cac_show_additional_user_meta($user) {
    echo '<div class="cac-additional-info">';
    echo '<h3>Additional Information</h3>';

    $user_meta = get_user_meta($user->ID);
    foreach ($user_meta as $key => $values) {
        if (strpos($key, 'cac_field_') === 0) {
            // Extract the label part of the key and capitalize it
            $label = ucwords(str_replace('_', ' ', substr($key, 10))); // Remove 'cac_field_' prefix and replace underscores with spaces

            $value = maybe_unserialize($values[0]);

            echo '<div class="cac-field-row">';
            echo '<label for="' . esc_attr($key) . '">' . esc_html($label) . '</label>';
            echo '<input type="text" name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text">';
            echo '</div>';
        }
    }

    echo '</div>'; // Close .cac-additional-info
}



// Editable Meta Section
add_action('personal_options_update', 'cac_save_additional_user_meta');
add_action('edit_user_profile_update', 'cac_save_additional_user_meta');

function cac_save_additional_user_meta($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'cac_field_') === 0) {
            update_user_meta($user_id, sanitize_text_field($key), sanitize_text_field($value));
        }
    }
}

function cac_admin_styles() {
    echo '<style>
        .cac-additional-info {
            background-color: #ffffff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            margin-top: 20px;
            border-radius: 5px;
        }
        .cac-field-row {
            margin-bottom: 20px;
        }
        .cac-field-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .cac-input {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccd0d4;
            box-sizing: border-box;
        }

        .cac-additional-info h3 {
            text-transform: uppercase;
        }
    
    </style>';
}

add_action('admin_head', 'cac_admin_styles');
