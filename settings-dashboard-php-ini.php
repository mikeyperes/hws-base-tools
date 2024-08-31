<? function hws_ct_display_settings_php_ini() {
    ?>
    <style>
        .panel-settings-php-ini {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #f7f7f7;
            padding: 10px 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            font-size: 14px;
        }

        .panel-settings-php-ini .panel-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .panel-settings-php-ini .panel-content {
            padding: 10px 0;
        }

        .panel-settings-php-ini ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .panel-settings-php-ini li {
            padding: 1px 0;
            font-size: 12px;
            color: #888;
        }

        .panel-settings-php-ini label {
            font-size: 13px;
            color: #555;
        }

        .panel-settings-php-ini small {
            display: block;
            margin-top: 3px;
            color: #777;
            font-size: 12px;
        }

        .php-ini-item {
            margin-bottom: 12px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #dcdcdc;
            background-color: #fff;
        }
    </style>
    <!-- PHP INI Status Panel -->
    <div class="panel panel-settings-php-ini">
        <h2 class="panel-title">PHP INI Information</h2>
        <div class="panel-content">
            <button id="php-ini-toggle" style="background-color: #007cba; color: white; padding: 10px; border: none; border-radius: 5px;">Show PHP INI Details</button>
            <div id="php-ini-details" style="display:none; margin-top: 15px;">
                <?php
                // Get all PHP INI settings
                $ini_settings = ini_get_all();

                // Display each setting
                foreach ($ini_settings as $key => $value) {
                    echo "<p><strong>{$key}:</strong> " . $value['local_value'] . "</p>";
                }
                ?>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#php-ini-toggle').on('click', function() {
                $('#php-ini-details').slideToggle();
            });
        });
    </script>
    <?php
}