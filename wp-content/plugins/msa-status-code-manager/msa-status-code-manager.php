<?php

/*
Plugin Name: Status Code Manger
Description: WordPress plugin for returning a custom status code
Author: msa-works
Author URI: mailto:msa-works@mailbox.org
Version: 1.0.0
Requires at least: 6.8
Requires PHP: 8.1
*/

add_action('admin_menu', function () {
    add_management_page(
        page_title: 'Status Code Manager',
        menu_title: 'Status Code Manager',
        capability: 'manage_options',
        menu_slug: 'msa-status-code-manager',
        callback: function () {
            if (isset($_POST['delete_rule']) && isset($_POST['rule_index'])) {
                check_admin_referer('msa_status_code_manager_delete_rule');
                $rules = get_option('msa_status_code_manager_rules', []);
                $index = intval($_POST['rule_index']);
                if (isset($rules[$index])) {
                    unset($rules[$index]);
                    $rules = array_values($rules);
                    update_option('msa_status_code_manager_rules', $rules);
                    echo '<div class="updated"><p>Rule deleted</p></div>';
                }
            }

            ?>
            <div class="wrap">
                <h1>Status Code Manager</h1>
                <p>Define URL patterns (Regex) and the desired action here.</p>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('msa_status_code_manager');
                    $rules = get_option('msa_status_code_manager_rules', []);
                    ?>

                    <table class="form-table widefat striped">
                        <thead>
                        <tr>
                            <th style="padding: 15px 10px;">Pattern</th>
                            <th style="padding: 15px 10px;">Target (Slug oder URL, empty for 404 Page)</th>
                            <th style="padding: 15px 10px;">Status Code</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($rules)) : ?>
                            <?php foreach ($rules as $i => $rule) : ?>
                                <tr>
                                    <td>
                                        <input type="text" name="msa_status_code_manager_rules[<?php echo $i; ?>][pattern]"
                                               value="<?php echo esc_attr($rule['pattern']); ?>" class="regular-text"/>
                                    </td>
                                    <td>
                                        <input type="text" name="msa_status_code_manager_rules[<?php echo $i; ?>][target]" value="<?php echo esc_attr($rule['target']); ?>"
                                               class="regular-text"/>
                                    </td>
                                    <td>
                                        <select name="msa_status_code_manager_rules[<?php echo $i; ?>][type]">
                                            <option value="301" <?php selected($rule['type'], '301'); ?>>301 Moved Permanently</option>
                                            <option value="302" <?php selected($rule['type'], '302'); ?>>302 Found</option>
                                            <option value="307" <?php selected($rule['type'], '302'); ?>>307 Temporary Redirect</option>
                                            <option value="308" <?php selected($rule['type'], '302'); ?>>308 Permanent Redirect</option>
                                            <option value="403" <?php selected($rule['type'], '403'); ?>>403 Forbidden</option>
                                            <option value="404" <?php selected($rule['type'], '404'); ?>>404 Not Found</option>
                                            <option value="410" <?php selected($rule['type'], '410'); ?>>410 Gone</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small" onclick="msaStatusCodeManagerDeleteRule(<?php echo esc_js($i); ?>)">✖</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr>
                            <td>
                                <input type="text" name="msa_status_code_manager_rules[new][pattern]" placeholder="example/.*" class="regular-text"/>
                            </td>
                            <td>
                                <input type="text" name="msa_status_code_manager_rules[new][target]" placeholder="slug oder https://target.com" class="regular-text"/>
                            </td>
                            <td>
                                <select name="msa_status_code_manager_rules[new][type]">
                                    <option value="301">301 Moved Permanently</option>
                                    <option value="302">302 Found</option>
                                    <option value="307">307 Temporary Redirect</option>
                                    <option value="308">308 Permanent Redirect</option>
                                    <option value="403">403 Forbidden</option>
                                    <option value="404">404 Not Found</option>
                                    <option value="410" selected>410 Gone</option>
                                </select>
                            </td>
                            <td></td>
                        </tr>
                        </tbody>
                    </table>
                    <p>
                        <button type="submit" class="button-primary">Save changes</button>
                    </p>
                </form>

                <form id="msa-status-code-manager-delete-form" method="post" style="display:none;">
                    <?php wp_nonce_field('msa_status_code_manager_delete_rule'); ?>
                    <input type="hidden" name="rule_index" id="msa-status-code-manager-delete-index">
                    <input type="hidden" name="delete_rule" value="1">
                </form>

                <script>
                    function msaStatusCodeManagerDeleteRule(index) {
                        if (confirm('Are you sure you want to delete this rule?')) {
                            document.getElementById('msa-status-code-manager-delete-index').value = index;
                            document.getElementById('msa-status-code-manager-delete-form').submit();
                        }
                    }
                </script>
            </div>
            <?php
        },
    );
});

add_action('admin_notices', function () {
    if (!isset($_GET['page']) || $_GET['page'] !== 'msa-status-code-manager') {
        return;
    }

    settings_errors('msa_status_code_manager');

    // Optionale eigene Meldung nach erfolgreichem Speichern
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
        echo '<div class="updated"><p>Rules saved</p></div>';
    }
});

add_action('admin_init', function () {
    register_setting('msa_status_code_manager', 'msa_status_code_manager_rules');

    add_filter('pre_update_option_msa_status_code_manager_rules', function ($new_value) {
        $cleaned = [];
        foreach ($new_value as $rule) {
            if (empty($rule['pattern'])) {
                continue;
            }

            $cleaned[] = [
                'pattern' => sanitize_text_field($rule['pattern']),
                'target' => sanitize_text_field($rule['target']),
                'type' => sanitize_text_field($rule['type']),
            ];
        }

        array_multisort(array_column($cleaned, 'pattern'), SORT_ASC, $cleaned);

        return array_values($cleaned);
    }, 10, 2);
});

add_action('template_redirect', function () {
    if (empty($rules = get_option('msa_status_code_manager_rules', []))) {
        return;
    }

    foreach ($rules as $rule) {
        $request_path ??= trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $pattern = $rule['pattern'] ?? '';
        $target = $rule['target'] ?? '';
        $type = $rule['type'] ?? '410';

        if (empty($pattern)) {
            continue;
        }

        if (!preg_match('/^'.$pattern.'$/i', $request_path)) {
            continue;
        }

        if (in_array($type, ['301', '302', '307', '308'], true)) {
            if (filter_var($target, FILTER_VALIDATE_URL)) {
                wp_redirect($target, (int)$type);
                exit;
            }

            $url = home_url('/'.ltrim($target, '/').'/');
            wp_redirect($url, (int)$type);
            exit;
        }

        if (in_array($type, ['403', '404', '410'], true)) {
            status_header($type);
            nocache_headers();

            if ($target) {
                $page = get_page_by_path($target);
                if ($page) {
                    query_posts(['page_id' => $page->ID]);
                    include get_page_template();
                    exit;
                }
            }

            include get_query_template('404');
            exit;
        }
    }
});
